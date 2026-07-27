<?php

namespace Colissimo\Classes\Pickup;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Modal;
use Colissimo\Core\Register;
use Colissimo\Api\RelaysApi;
use Exception;
use WC_Order;

defined('ABSPATH') || die('Restricted Access');

class AdminWebService {
    protected $ajaxDispatcher;

    public function __construct(
        ?Ajax $ajaxDispatcher = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
    }

    public function init() {
        $this->ajaxDispatcher->register('adminPickupWS', [$this, 'adminPickupWS']);

        add_action(
            'current_screen',
            function ($currentScreen) {
                if ('woocommerce_page_wc-orders' === $currentScreen->base || ('post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type)) {
                    $args = [
                        'ajaxURL'   => $this->ajaxDispatcher->getUrlForTask('adminPickupWS'),
                        'mapType'   => Helper::get_option('lpc_pickup_map_type', 'widget'),
                        'mapMarker' => Helper::getImageUrl('map_marker.png'),
                    ];

                    Helper::enqueueScript(
                        'lpc_admin_pick_up_ws',
                        Helper::getJsUrl('pickup/webservice.js'),
                        ['jquery'],
                        'lpcPickUpSelection',
                        $args
                    );

                    Helper::enqueueStyle(
                        'lpc_admin_pick_up_ws',
                        Helper::getCssUrl('pickup/webservice.css')
                    );
                    Helper::enqueueStyle(
                        'lpc_admin_pick_up',
                        Helper::getCssUrl('pickup/pickup.css')
                    );
                }
            }
        );
    }

    public function addWebserviceMap(WC_Order $order) {
        $lpcImageUrl  = Helper::getImageUrl('colissimo_cropped.png');
        $imageHtmlTag = '<img src="' . esc_url($lpcImageUrl) . '" style="max-width: 90px; display:inline; vertical-align: middle;">';
        $modal        = new Modal(null, $imageHtmlTag, 'lpc_pick_up_web_service');

        ob_start();
        Helper::renderPartial(
            'Pickup/WebserviceMap.php',
            [
                'ceAddress'     => !empty($order->get_shipping_address_1()) ? str_replace('’', "'", $order->get_shipping_address_1()) : '',
                'ceZipCode'     => !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : '',
                'ceTown'        => !empty($order->get_shipping_city()) ? str_replace('’', "'", $order->get_shipping_city()) : '',
                'ceCountryId'   => !empty($order->get_shipping_country()) ? $order->get_shipping_country() : '',
                'maxRelayPoint' => Helper::get_option('lpc_max_relay_point', 20),
                'orderId'       => $order->get_id(),
            ]
        );
        $map = ob_get_clean();

        $modal->setContent($map);

        $args = [
            'modal'      => $modal,
            'apiKey'     => Helper::get_option('lpc_gmap_key', ''),
            'type'       => 'link',
            'showButton' => true,
            'showInfo'   => false,
            'mapType'    => Helper::get_option('lpc_pickup_map_type', 'leaflet'),
        ];

        Helper::renderPartial('Pickup/Webservice.php', $args);
    }

    public function adminPickupWS() {
        $address = [
            'address'     => Helper::getVar('address'),
            'zipCode'     => Helper::getVar('zipCode'),
            'city'        => Helper::getVar('city'),
            'countryCode' => Helper::getVar('countryId'),
        ];

        $loadMore = (int) Helper::getVar('loadMore', 0) === 1;

        $generateRelaysPayload = new GetRelaysPayload();

        try {
            $weight  = 0;
            $orderId = (int) Helper::getVar('orderId', 0);
            if ($orderId > 0) {
                $order = wc_get_order($orderId);
                $items = $order->get_items();
                foreach ($items as $item) {
                    if (!empty($item['product_id'])) {
                        $product = $item->get_product();
                        if (!$product->is_virtual()) {
                            $weight += wc_get_weight($product->get_weight(), 'kg') * $item['quantity'];
                        }
                    }
                }
            }

            $generateRelaysPayload
                ->withCredentials()
                ->withAddress($address)
                ->withShippingDate()
                ->withOptionInter()
                ->withRelayTypeFilter($weight)
                ->checkConsistency();

            $relaysPayload = $generateRelaysPayload->assemble();

            $relaysApi = new RelaysApi();
            $resultWs  = $relaysApi->getRelays($relaysPayload);
        } catch (Exception $exception) {
            Logger::error($exception->getMessage());

            return $this->ajaxDispatcher->makeError(['message' => $exception->getMessage()]);
        }

        if (0 == $resultWs['errorCode']) {
            if (empty($resultWs['listePointRetraitAcheminement'])) {
                Logger::warn(__('The web service returned 0 relay', 'colissimo-shipping-methods-for-woocommerce'));

                return $this->ajaxDispatcher->makeError(['message' => __('No relay available', 'colissimo-shipping-methods-for-woocommerce')]);
            }

            $listRelaysWS = $resultWs['listePointRetraitAcheminement'];
            $html         = '';

            // Force Post office type if cart weight > 20kg
            if ($weight > 20) {
                $overWarning = __('Only post offices are available for this order', 'colissimo-shipping-methods-for-woocommerce');
                $html        .= '<div class="lpc_layer_relay_warning_relay_type">' . $overWarning . '</div>';
            }

            // Limit number of displayed relays
            $maxRelayPoint = $loadMore ? 20 : Helper::get_option('lpc_max_relay_point', 20);
            $listRelaysWS  = array_slice($listRelaysWS, 0, $maxRelayPoint);

            $i           = 0;
            $partialArgs = [
                'relaysNb'    => count($listRelaysWS),
                'openingDays' => [
                    'Monday'    => 'horairesOuvertureLundi',
                    'Tuesday'   => 'horairesOuvertureMardi',
                    'Wednesday' => 'horairesOuvertureMercredi',
                    'Thursday'  => 'horairesOuvertureJeudi',
                    'Friday'    => 'horairesOuvertureVendredi',
                    'Saturday'  => 'horairesOuvertureSamedi',
                    'Sunday'    => 'horairesOuvertureDimanche',
                ],
            ];

            foreach ($listRelaysWS as $oneRelay) {
                if (empty($oneRelay['identifiant']) || empty($oneRelay['typeDePoint'])) {
                    continue;
                }

                $partialArgs['oneRelay'] = $oneRelay;
                $partialArgs['i']        = $i ++;

                ob_start();
                Helper::renderPartial('Pickup/PickupFullDetails.php', $partialArgs);
                $html .= ob_get_clean();
            }

            return $this->ajaxDispatcher->makeSuccess(
                [
                    'html'            => $html,
                    'chooseRelayText' => __('Choose this relay', 'colissimo-shipping-methods-for-woocommerce'),
                    'loadMore'        => $loadMore ? 1 : 0,
                ]
            );
        } else {
            if (in_array($resultWs['errorCode'], [301, 300, 203])) {
                Logger::warn($resultWs['errorCode'] . ' : ' . $resultWs['errorMessage']);

                return $this->ajaxDispatcher->makeError(['message' => __('No relay available', 'colissimo-shipping-methods-for-woocommerce')]);
            } else {
                // Error codes we want to display the related messages to the client, we'll only display a generic message for the other error codes
                $errorCodesWSClientSide = [
                    '104',
                    '105',
                    '117',
                    '125',
                    '129',
                    '143',
                    '144',
                    '145',
                    '146',
                ];

                if (in_array($resultWs['errorCode'], $errorCodesWSClientSide)) {
                    return $this->ajaxDispatcher->makeAndLogError(['message' => $resultWs['errorCode'] . ' : ' . $resultWs['errorMessage']]);
                } else {
                    Logger::error($resultWs['errorCode'] . ' : ' . $resultWs['errorMessage']);

                    return $this->ajaxDispatcher->makeError(['message' => __('Error', 'colissimo-shipping-methods-for-woocommerce')]);
                }
            }
        }
    }
}
