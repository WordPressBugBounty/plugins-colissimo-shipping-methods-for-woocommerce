<?php

namespace Colissimo\Classes\Order;

use Colissimo\Classes\Pickup\AdminWidget;
use Colissimo\Classes\Pickup\AdminWebService;
use Colissimo\Helpers\Helper;
use Colissimo\Classes\Shipping\CapabilitiesPerCountry;
use Colissimo\Classes\Pickup\PickupSelection;
use Colissimo\Core\Register;
use Colissimo\Classes\Shipping\Relay;
use Colissimo\Classes\Shipping\ShippingMethods;
use WC_Order;
use WC_Order_Item_Shipping;

defined('ABSPATH') || die('Restricted Access');

class AffectMethod {
    const NONCE_SWITCH_METHOD = '_lpc_switch_method';
    const NONCE_NAME_SWITCH_METHOD = 'colissimo_switch_method';

    protected $lpcShippingMethods;
    protected $lpcCapabilitiesByCountry;
    protected $lpcAdminPickupWebService;
    protected $lpcAdminPickupWidget;

    public function __construct(
        ?ShippingMethods $shippingMethods = null,
        ?CapabilitiesPerCountry $capabilitiesPerCountry = null,
        ?AdminWebService $lpcAdminPickupWebService = null,
        ?AdminWidget $lpcAdminPickupWidget = null
    ) {
        $this->lpcShippingMethods       = Register::get('shippingMethods');
        $this->lpcCapabilitiesByCountry = Register::get('capabilitiesPerCountry');
        if ('widget' === Helper::get_option('lpc_pickup_map_type', 'widget')) {
            $this->lpcAdminPickupWidget = Register::get('adminPickupWidget');
        } else {
            $this->lpcAdminPickupWebService = Register::get('adminPickupWebService');
        }
    }

    public function init() {
        add_action('woocommerce_after_order_itemmeta', [$this, 'addAffectLink'], 10, 2);
        add_action('current_screen',
            function ($currentScreen) {
                if ('woocommerce_page_wc-orders' === $currentScreen->base || ('post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type)) {
                    Helper::enqueueScript(
                        'lpc_order_affect',
                        Helper::getJsUrl('orders/affect_methods.js'),
                        ['jquery-core']
                    );

                    Helper::enqueueStyle(
                        'lpc_order_affect_methods',
                        Helper::getCssUrl('orders/affect_methods.css')
                    );
                }
            }
        );

        add_action('wp_ajax_lpc_order_affect', [$this, 'updateShippingMethod']);
    }

    public function addAffectLink($itemId, $item) {
        if (empty($item) || $item->get_type() !== 'shipping') {
            return;
        }

        $order = $item->get_order();

        if (!empty($this->lpcShippingMethods->getColissimoShippingMethodOfOrder($order)) || !$order->is_editable()) {
            return;
        }

        $methods = $this->getColissimoShippingMethodsAvailable($order);

        $methods = array_map(
            fn($value) => $value->get_method_title(),
            $methods
        );

        $args = [
            'lpc_shipping_methods'  => $methods,
            'map_type'              => Helper::get_option('lpc_pickup_map_type', 'widget'),
            'order'                 => $order,
            'adminPickupWidget'     => $this->lpcAdminPickupWidget,
            'adminPickupWebService' => $this->lpcAdminPickupWebService,
            'lpc_partial_name'      => 'lpc_order_affect_methods_woocommerce',
        ];

        Helper::renderPartial('Order/SwitchMethod.php', $args);
    }

    public function updateShippingMethod() {
        if (!current_user_can('lpc_colissimo_bandeau') || 1 !== (int) check_ajax_referer(self::NONCE_NAME_SWITCH_METHOD, self::NONCE_SWITCH_METHOD, false)) {
            Helper::endAjax(false, ['message' => 'Unauthorized access']);
        }

        $orderId = Helper::getVar('order_id', 0, 'int');
        if (empty($orderId)) {
            Helper::endAjax(false, ['message' => 'Order not found']);
        }

        $order = wc_get_order($orderId);
        if (empty($order) || 'shop_order' !== $order->get_type()) {
            Helper::endAjax(false, ['message' => 'This post is not an order']);
        }

        $lpcNewShippingMethodId = Helper::getVar('new_shipping_method');
        if (empty($lpcNewShippingMethodId)) {
            Helper::endAjax(false, ['message' => __('Please select a shipping method', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        $lpcMethods           = $this->getColissimoShippingMethodsAvailable($order);
        $lpcNewShippingMethod = $lpcMethods[$lpcNewShippingMethodId] ?? null;

        if (empty($lpcNewShippingMethod)) {
            Helper::endAjax(false, ['message' => __('Please select a shipping method', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        if (Relay::ID === $lpcNewShippingMethod->id) {
            $relayInformation = Helper::getVar('relay_information');
            if (empty($relayInformation)) {
                Helper::endAjax(false, ['message' => __('Please select a pick-up point', 'colissimo-shipping-methods-for-woocommerce')]);
            }

            $relayInformationData = json_decode(stripslashes($relayInformation));

            $order->update_meta_data(PickupSelection::PICKUP_LOCATION_ID_META_KEY, $relayInformationData->identifiant);
            $order->update_meta_data(PickupSelection::PICKUP_LOCATION_LABEL_META_KEY, $relayInformationData->nom);
            $order->update_meta_data(PickupSelection::PICKUP_PRODUCT_CODE_META_KEY, $relayInformationData->typeDePoint);
            $order->update_meta_data(
                PickupSelection::PICKUP_LOCATION_DATA_META_KEY,
                json_encode(
                    [
                        'adresse1'   => $relayInformationData->adresse1,
                        'adresse2'   => $relayInformationData->adresse2 ?? '',
                        'codePostal' => $relayInformationData->codePostal,
                        'localite'   => $relayInformationData->localite,
                        'codePays'   => $relayInformationData->codePays,
                        'nom'        => $relayInformationData->nom,
                    ]
                )
            );

            $order->set_shipping_address_1($relayInformationData->adresse1);
            $order->set_shipping_postcode($relayInformationData->codePostal);
            $order->set_shipping_city($relayInformationData->localite);
            $order->set_shipping_country($relayInformationData->codePays);
            $order->set_shipping_company($relayInformationData->nom);

            $order->save();
        }

        $orderShippingItemId = Helper::getVar('shipping_item_id', 0, 'int');
        if (empty($orderShippingItemId)) {
            $shippingItem = new WC_Order_Item_Shipping();
            $shippingItem->set_props(
                [
                    'method_id'    => $lpcNewShippingMethod->id,
                    'method_title' => $lpcNewShippingMethod->get_method_title(),
                ]
            );

            $order->add_item($shippingItem);
            $order->save();
        } else {
            $shippingItem = $order->get_item($orderShippingItemId);
            if (empty($shippingItem)) {
                Helper::endAjax(false, ['message' => 'Shipping item not found']);
            }
            $shippingItem->set_props(
                [
                    'method_id'    => $lpcNewShippingMethod->id,
                    'method_title' => $lpcNewShippingMethod->get_method_title(),
                ]
            );
            $shippingItem->save();
        }

        Helper::endAjax();
    }

    /**
     * Retrieve Colissimo shipping methods available for an order by country
     *
     * @param WC_Order $order
     *
     * @return array
     */
    public function getColissimoShippingMethodsAvailable(WC_Order $order) {
        $allShippingMethods                 = WC()->shipping() ? WC()->shipping()->load_shipping_methods() : [];
        $colissimoShippingMethodsPerCountry = $this->lpcCapabilitiesByCountry->getCapabilitiesForCountry($order->get_shipping_country());
        $methods                            = [];

        foreach ($allShippingMethods as $oneMethod) {
            $method = $this->lpcCapabilitiesByCountry->getCapabilitiesFileMethod($oneMethod->id);
            if (!empty($colissimoShippingMethodsPerCountry[$method])) {
                $methods[$oneMethod->id] = $oneMethod;
            }
        }

        return $methods;
    }
}
