<?php

namespace Colissimo\Classes\Pickup;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Modal;
use Colissimo\Core\Register;
use Colissimo\Api\RelaysApi;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class PickupWebService extends Pickup {
    const GOOGLE_MAPS_JS_URL = 'https://maps.googleapis.com/maps/api/js?loading=async&libraries=marker&key=';

    protected $modal;
    protected $ajaxDispatcher;
    protected $lpcPickUpSelection;

    public function __construct() {
        $this->ajaxDispatcher     = Register::get('ajaxDispatcher');
        $this->lpcPickUpSelection = Register::get('pickupSelection');
    }

    public function init() {
        if ('widget' === Helper::get_option('lpc_pickup_map_type', 'widget')) {
            return;
        }

        $lpcImageUrl  = Helper::getImageUrl('colissimo_cropped.png');
        $imageHtmlTag = '<img src="' . esc_url($lpcImageUrl) . '" style="max-width: 90px; display:inline; vertical-align: middle;">';

        $this->modal = new Modal(null, $imageHtmlTag, 'lpc_pick_up_web_service');

        $this->ajaxDispatcher->register('pickupWS', [$this, 'pickupWS'], false);

        add_action(
            'wp_enqueue_scripts',
            function () {
                if (is_checkout() || has_block('woocommerce/checkout')) {
                    wp_register_script('lpc_pick_up_ws', Helper::getJsUrl('pickup/webservice.js'), ['jquery'], LPC_VERSION, true);

                    $args = [
                        'baseAjaxUrl'           => admin_url('admin-ajax.php'),
                        'messagePhoneRequired'  => __('Please set a valid phone number', 'colissimo-shipping-methods-for-woocommerce'),
                        'messagePickupRequired' => __('Please set a pick up point', 'colissimo-shipping-methods-for-woocommerce'),
                        'ajaxURL'               => $this->ajaxDispatcher->getUrlForTask('pickupWS'),
                        'pickUpSelectionUrl'    => $this->lpcPickUpSelection->getAjaxUrl(),
                        'mapType'               => Helper::get_option('lpc_pickup_map_type', 'widget'),
                        'mapMarker'             => Helper::getImageUrl('map_marker.png'),
                    ];

                    wp_localize_script('lpc_pick_up_ws', 'lpcPickUpSelection', $args);

                    wp_register_style('lpc_pick_up_ws', Helper::getCssUrl('pickup/webservice.css'), [], LPC_VERSION);
                    wp_register_style('lpc_pick_up', Helper::getCssUrl('pickup/pickup.css'), [], LPC_VERSION);

                    wp_enqueue_script('lpc_pick_up_ws');
                    wp_enqueue_style('lpc_pick_up_ws');
                    wp_enqueue_style('lpc_pick_up');
                    $googleApiKey = Helper::get_option('lpc_gmap_key', '');
                    $mapType      = Helper::get_option('lpc_pickup_map_type', 'leaflet');
                    if ('leaflet' !== $mapType && !empty($googleApiKey)) {
                        wp_register_script('lpc_google_maps', self::GOOGLE_MAPS_JS_URL . $googleApiKey, [], LPC_VERSION, ['in_footer' => true]);
                        wp_enqueue_script('lpc_google_maps');
                    } else {
                        wp_register_script('lpc_leaflet_js', Helper::getJsUrl('pickup/leaflet.js'), [], LPC_VERSION, ['in_footer' => true]);
                        wp_register_style('lpc_leaflet_css', Helper::getCssUrl('pickup/leaflet.css'), [], LPC_VERSION);
                        wp_enqueue_script('lpc_leaflet_js');
                        wp_enqueue_style('lpc_leaflet_css');
                    }
                    $this->modal->loadScripts();
                }
            }
        );

        add_action('woocommerce_after_shipping_rate', [$this, 'addWebserviceMap']);
    }

    /**
     * Uses a WC hook to add a "Select pick up location" button on the checkout page
     *
     * @param object $method
     * @param int    $index
     */
    public function addWebserviceMap($method, $index = 0) {
        if ($this->getMode($method->get_method_id(), $method->get_id()) !== self::WEB_SERVICE) {
            return;
        }

        $this->displayWebserviceModal();
    }

    public function displayWebserviceModal(bool $forceCheckout = false) {
        $customerAddress = $this->getCurrentCustomerAddress();

        $street   = $customerAddress['address'] ?? '';
        $postcode = $customerAddress['zipCode'] ?? '';
        $city     = $customerAddress['city'] ?? '';
        $country  = $customerAddress['countryCode'] ?? 'FR';

        ob_start();
        Helper::renderPartial(
            'Pickup/WebserviceMap.php',
            [
                'ceAddress'     => str_replace('’', "'", $street),
                'ceZipCode'     => preg_replace('#[^0-9a-zA-Z]#', '', $postcode),
                'ceTown'        => str_replace('’', "'", $city),
                'ceCountryId'   => $country,
                'maxRelayPoint' => Helper::get_option('lpc_max_relay_point', 20),
            ]
        );
        $map = ob_get_clean();
        $this->modal->setContent($map);
        $currentRelay = $this->lpcPickUpSelection->getCurrentPickUpLocationInfo();

        $address = [
            'address'     => $street,
            'zipCode'     => $postcode,
            'city'        => $city,
            'countryCode' => $country,
        ];

        if ('yes' === Helper::get_option('lpc_select_default_pr', 'no')
            && empty($currentRelay)
            && count($address) === count(array_filter($address))) {
            $currentRelay = $this->getDefaultPickupLocationInfoWS($address);
        }

        $args = [
            'modal'        => $this->modal,
            'apiKey'       => Helper::get_option('lpc_gmap_key', ''),
            'currentRelay' => $currentRelay,
            'type'         => 'button',
            'showButton'   => is_checkout() || $forceCheckout,
            'showInfo'     => is_checkout() || $forceCheckout,
            'mapType'      => Helper::get_option('lpc_pickup_map_type', 'leaflet'),
        ];

        Helper::renderPartial('Pickup/Webservice.php', $args);
    }

    public function pickupWS() {
        $address  = [
            'address'     => Helper::getVar('address'),
            'zipCode'     => Helper::getVar('zipCode'),
            'city'        => Helper::getVar('city'),
            'countryCode' => Helper::getVar('countryId'),
        ];
        $loadMore = Helper::getVar('loadMore', 0, 'int') === 1;

        $resultWs = $this->getPickupWS($address);

        if (empty($resultWs)) {
            return $this->ajaxDispatcher->makeError(['message' => __('No relay available', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        if (!isset($resultWs['errorCode'])) {
            $message = $resultWs['message'] ?? __('Error', 'colissimo-shipping-methods-for-woocommerce');

            return $this->ajaxDispatcher->makeError(['message' => $message]);
        } elseif (0 == $resultWs['errorCode']) {
            if (empty($resultWs['listePointRetraitAcheminement'])) {
                Logger::warn(__('The web service returned 0 relay', 'colissimo-shipping-methods-for-woocommerce'));

                return $this->ajaxDispatcher->makeError(['message' => __('No relay available', 'colissimo-shipping-methods-for-woocommerce')]);
            }

            $listRelaysWS = $resultWs['listePointRetraitAcheminement'];
            $html         = '';

            // Force Post office type if cart weight > 20kg
            $cartWeight = wc_get_weight(WC()->cart->get_cart_contents_weight(), 'kg');
            if ($cartWeight > 20) {
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
        } elseif (in_array($resultWs['errorCode'], [301, 300, 203])) {
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

    public function getPickupWS($address) {
        try {
            $generateRelaysPayload = new GetRelaysPayload();
            $relaysApi             = new RelaysApi();

            $generateRelaysPayload
                ->withCredentials()
                ->withAddress($address)
                ->withShippingDate()
                ->withOptionInter()
                ->withRelayTypeFilter()
                ->checkConsistency();

            $relaysPayload = $generateRelaysPayload->assemble();

            return $relaysApi->getRelays($relaysPayload);
        } catch (Exception $exception) {
            return $this->ajaxDispatcher->makeAndLogError(['message' => $exception->getMessage()]);
        }
    }

    public function getDefaultPickupLocationInfoWS($address) {
        $resultWs = $this->getPickupWS($address);
        if (isset($resultWs['errorCode']) && '0' == $resultWs['errorCode']) {
            $relays = $resultWs['listePointRetraitAcheminement'];
            if (count($relays) >= 1) {
                $defaultRelay = array_shift($relays);
                $this->lpcPickUpSelection->setCurrentPickUpLocationInfo($defaultRelay);

                return $defaultRelay;
            }
        }

        return null;
    }
}
