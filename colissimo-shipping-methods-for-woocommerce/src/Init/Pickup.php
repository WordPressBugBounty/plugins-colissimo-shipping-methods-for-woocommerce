<?php

namespace Colissimo\Init;

defined('ABSPATH') || die('Restricted Access');

use Colissimo\Classes\Pickup\PickupSelection;
use Colissimo\Classes\Pickup\PickupWidget;
use Colissimo\Classes\Shipping\Relay;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\OrderQueries;

class Pickup {
    const APPLIED_CLOSEST_RELAY_META_KEY = 'lpc_applied_closest_relay';

    private PickupWidget $widget;

    public function __construct() {
        if ('widget' === Helper::get_option('lpc_pickup_map_type', 'widget')) {
            $this->widget = new PickupWidget();
            $this->widget->addWidgetOnCart();
        }

        add_action('wp_ajax_lpc_pickup_ajax_content', [$this, 'initPickupContent']);
        add_action('wp_ajax_nopriv_lpc_pickup_ajax_content', [$this, 'initPickupContent']);
        add_filter('wcpay_express_checkout_js_params', [$this, 'forcePhoneForExpressPayments'], 11, 1);
        add_action('woocommerce_before_order_object_save', [$this, 'applyClosestRelayForExpressPayments'], 10, 1);
    }

    public function initPickupContent() {
        ob_start();

        if ('widget' === Helper::get_option('lpc_pickup_map_type', 'widget')) {
            $this->widget->displayWidgetModal(true, true);
        } else {
            $webService = Register::get('pickupWebService');
            $webService->displayWebserviceModal(true);
        }

        $content = ob_get_clean();

        Helper::endAjax(true, ['content' => $content]);
    }

    public function forcePhoneForExpressPayments(array $params): array {
        // We need it for relay shipments and don't know which shipping method will be used
        $params['checkout']['needs_payer_phone'] = true;

        return $params;
    }

    public function applyClosestRelayForExpressPayments(object $order): void {
        if (!$order->get_id() || 'woocommerce_payments' !== $order->get_payment_method()) {
            return;
        }

        // Only for express payments with Apple Pay or Google Pay
        $methodTitle = $order->get_payment_method_title();
        if (strpos($methodTitle, 'Google Pay') === false && strpos($methodTitle, 'Apple Pay') === false) {
            return;
        }

        // Only for relay shipments
        if (!OrderQueries::hasShippingMethod($order, Relay::ID)) {
            return;
        }

        $applied = $order->get_meta(self::APPLIED_CLOSEST_RELAY_META_KEY);
        if (!empty($applied)) {
            return;
        }

        $order->update_meta_data(self::APPLIED_CLOSEST_RELAY_META_KEY, 'yes');

        $pickupWebService = Register::get('pickupWebService');
        $closestRelay     = $pickupWebService->getDefaultPickupLocationInfoWS(
            [
                'address'     => $order->get_shipping_address_1(),
                'zipCode'     => $order->get_shipping_postcode(),
                'city'        => $order->get_shipping_city(),
                'countryCode' => $order->get_shipping_country(),
            ]
        );

        if (!empty($closestRelay)) {
            Logger::debug(
                'Applying closest relay data to order for express payment',
                [
                    'orderID'      => $order->get_id(),
                    'closestRelay' => $closestRelay,
                ]
            );

            $order->set_shipping_address_1($closestRelay['adresse1']);
            $order->set_shipping_address_2($closestRelay['adresse2'] ?? '');
            $order->set_shipping_postcode($closestRelay['codePostal']);
            $order->set_shipping_city($closestRelay['localite']);
            $order->set_shipping_country($closestRelay['codePays']);
            $order->set_shipping_company($closestRelay['nom'] ?? '');

            $order->update_meta_data(PickupSelection::PICKUP_LOCATION_ID_META_KEY, $closestRelay['identifiant']);
            $order->update_meta_data(PickupSelection::PICKUP_LOCATION_LABEL_META_KEY, $closestRelay['nom']);
            $order->update_meta_data(PickupSelection::PICKUP_PRODUCT_CODE_META_KEY, $closestRelay['typeDePoint']);
            $order->update_meta_data(PickupSelection::PICKUP_LOCATION_DATA_META_KEY, wp_json_encode($closestRelay));
        } else {
            Logger::error(
                'No relay found near the shipping address with express payment',
                [
                    'orderID' => $order->get_id(),
                ]
            );
        }
    }
}
