<?php

namespace Colissimo\Classes\Pickup;

use Colissimo\Helpers\Helper;
use Colissimo\Classes\Shipping\CapabilitiesPerCountry;
use Colissimo\Core\Modal;
use Colissimo\Core\Register;
use Colissimo\Classes\Shipping\Relay;
use Colissimo\Api\PickupWidgetApi;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

defined('ABSPATH') || die('Restricted Access');

class PickupWidget extends Pickup {
    const BASE_URL = 'https://ws.colissimo.fr';
    const MAP_JS_URL = 'https://api.mapbox.com/mapbox-gl-js/v2.6.1/mapbox-gl.js';
    const MAP_CSS_URL = 'https://api.mapbox.com/mapbox-gl-js/v2.6.1/mapbox-gl.css';
    const WEB_JS_URL = self::BASE_URL . '/widget-colissimo/js/jquery.plugin.colissimo.js';

    protected Modal $modal;
    protected $pickUpWidgetApi;
    protected $lpcPickUpSelection;
    protected $lpcCapabilitiesPerCountry;
    protected $lpcPickupWebService;

    public function __construct() {
        $this->pickUpWidgetApi           = Register::get('pickupWidgetApi');
        $this->lpcPickUpSelection        = Register::get('pickupSelection');
        $this->lpcCapabilitiesPerCountry = Register::get('capabilitiesPerCountry');
        $this->lpcPickupWebService       = Register::get('pickupWebService');
    }

    public function addWidgetOnCart() {
        add_action(
            'wp_enqueue_scripts',
            function () {
                if (is_checkout() || has_block('woocommerce/checkout')) {
                    wp_register_script('lpc_mapbox', self::MAP_JS_URL, ['jquery'], '0.1', true);

                    wp_register_script('lpc_widgets_web_js_url', self::WEB_JS_URL, ['lpc_mapbox'], '0.1', ['in_footer' => true]);

                    $args = [
                        'baseAjaxUrl'           => admin_url('admin-ajax.php'),
                        'messagePhoneRequired'  => __('Please set a valid phone number', 'colissimo-shipping-methods-for-woocommerce'),
                        'messagePickupRequired' => __('Please set a pick up point', 'colissimo-shipping-methods-for-woocommerce'),
                        'pickUpSelectionUrl'    => $this->lpcPickUpSelection->getAjaxUrl(),
                        'errorSavingRelay'      => __(
                            'An error occurred when trying to save the selected relay, please select it again.',
                            'colissimo-shipping-methods-for-woocommerce'
                        ),
                    ];
                    wp_localize_script('lpc_widgets_web_js_url', 'lpcPickUpSelection', $args);

                    // This js file opens the modal and loads the widget when the user clicks on the "Select/change relay point" button
                    wp_register_script(
                        'lpc_widget',
                        Helper::getJsUrl('pickup/widget.js'),
                        ['jquery-ui-autocomplete', 'lpc_widgets_web_js_url'],
                        LPC_VERSION,
                        true
                    );
                    wp_enqueue_script('lpc_widget');

                    $customerAddress = $this->getCurrentCustomerAddress();
                    $widgetInfo      = $this->getWidgetInfo($customerAddress);

                    wp_add_inline_script('lpc_widget', 'window.lpc_widget_info = ' . $widgetInfo, 'before');

                    wp_register_style('lpc_pickup_widget', Helper::getCssUrl('pickup/widget.css'), [], LPC_VERSION);
                    wp_enqueue_style('lpc_pickup_widget');

                    wp_register_style('lpc_pickup', Helper::getCssUrl('pickup/pickup.css'), [], LPC_VERSION);
                    wp_enqueue_style('lpc_pickup');

                    wp_register_style('lpc_mapbox', self::MAP_CSS_URL, [], LPC_VERSION);
                    wp_enqueue_style('lpc_mapbox');

                    $this->getModal()->loadScripts();
                }
            }
        );

        add_action('woocommerce_after_shipping_rate', [$this, 'showWidgetInHooks']);
    }

    public function showWidgetInHooks($method, $index = 0) {
        if ($this->getMode($method->get_method_id(), $method->get_id()) !== self::WIDGET) {
            return;
        }

        $this->displayWidgetModal();
    }

    public function displayWidgetModal(bool $forceCheckout = false, bool $gutenberg = false) {
        $customerAddress = $this->getCurrentCustomerAddress();

        $widgetInfo   = $this->getWidgetInfo($customerAddress);
        $currentRelay = $this->lpcPickUpSelection->getCurrentPickUpLocationInfo();

        $address = [
            'address'     => $customerAddress['address'] ?? '',
            'zipCode'     => $customerAddress['zipCode'] ?? '',
            'city'        => $customerAddress['city'] ?? '',
            'countryCode' => $customerAddress['countryCode'] ?? '',
        ];

        if ('yes' === Helper::get_option('lpc_select_default_pr', 'no')
            && empty($currentRelay)
            && count($address) === count(array_filter($address))) {
            $currentRelay = $this->lpcPickupWebService->getDefaultPickupLocationInfoWS($address);
        }

        $args = [
            'widgetInfo'   => $widgetInfo,
            'modal'        => $this->getModal(),
            'currentRelay' => $currentRelay,
            'showButton'   => is_checkout() || $forceCheckout,
            'showInfo'     => true,
            'type'         => 'button',
            'gutenberg'    => $gutenberg,
        ];

        Helper::renderPartial('Pickup/Widget.php', $args);
    }

    private function getWidgetInfo(array $customerAddress) {
        $availableCountries = $this->getWidgetListCountry();
        if (empty($availableCountries)) {
            $availableCountries = ['FR'];
        }

        $relayTypes = Helper::get_option('lpc_relay_types');
        if (empty($relayTypes)) {
            $relayTypes = '1';
        } elseif ('-1' === $relayTypes) {
            $relayTypes = '0';
        }

        $cartWeight = (int) wc_get_weight(WC()->cart->get_cart_contents_weight(), 'g');
        $widgetInfo = [
            'URLColissimo'      => self::BASE_URL,
            'ceCountry'         => $customerAddress['countryCode'] ?? 'FR',
            'token'             => $this->pickUpWidgetApi->authenticate(),
            'ceCountryList'     => implode(',', $availableCountries),
            'dyPreparationTime' => Helper::get_option('lpc_preparation_time', 1),
            'origin'            => 'CMS',
            'filterRelay'       => $relayTypes,
        ];

        if (!empty($cartWeight)) {
            $widgetInfo['dyWeight'] = $cartWeight;
        }

        $address = str_replace('’', "'", $customerAddress['address'] ?? '');
        if (!empty($address)) {
            $widgetInfo['ceAddress'] = $address;
        }

        if (!empty($customerAddress['zipCode'])) {
            $widgetInfo['ceZipCode'] = preg_replace('#[^0-9a-zA-Z]#', '', $customerAddress['zipCode']);
        }

        $city = str_replace('’', "'", $customerAddress['city'] ?? '');
        if (!empty($city)) {
            $widgetInfo['ceTown'] = $city;
        }

        if (Helper::get_option('lpc_prCustomizeWidget', 'no') === 'yes') {
            $lpcAddressTextColor = Helper::get_option('lpc_prAddressTextColor', null);
            if (!empty($lpcAddressTextColor)) {
                $widgetInfo['couleur1'] = $lpcAddressTextColor;
            }
            $lpcListTextColor = Helper::get_option('lpc_prListTextColor', null);
            if (!empty($lpcListTextColor)) {
                $widgetInfo['couleur2'] = $lpcListTextColor;
            }

            $font = Helper::getFont('lpc_prDisplayFont');
            if (!empty($font)) {
                $widgetInfo['font'] = $font;
            }
        }

        return wp_json_encode($widgetInfo);
    }

    /**
     * Get list of enabled countries for relay method
     *
     * @return array
     */
    private function getWidgetListCountry() {
        // Get theoric countries available for relay method
        $countriesOfMethod = $this->lpcCapabilitiesPerCountry->getCountriesForMethod(Relay::ID);

        // Get zones where relay method is enabled in configuration
        $allZones               = WC_Shipping_Zones::get_zones();
        $zonesWithMethodEnabled = [];
        foreach ($allZones as $oneZone) {
            foreach ($oneZone['shipping_methods'] as $oneMethod) {
                if (Relay::ID === $oneMethod->id && 'yes' === $oneMethod->enabled) {
                    $zonesWithMethodEnabled[$oneZone['id']] = 1;
                    break;
                }
            }
        }
        $zoneIds = array_keys($zonesWithMethodEnabled);

        // Get country codes from both
        $countries = [];
        foreach ($zoneIds as $oneZone) {
            $currentZone = new WC_Shipping_Zone($oneZone);
            $zoneLoc     = $currentZone->get_zone_locations();
            foreach ($zoneLoc as $oneLoc) {
                if ('country' === $oneLoc->type && in_array($oneLoc->code, $countriesOfMethod)) {
                    $countries[] = $oneLoc->code;
                }
            }
        }

        return $countries;
    }

    private function getModal(): Modal {
        if (empty($this->modal)) {
            $lpcImageUrl  = Helper::getImageUrl('colissimo_cropped.png');
            $imageHtmlTag = '<img src="' . esc_url($lpcImageUrl) . '" style="max-width: 90px; display:inline; vertical-align: middle;">';

            $modalContent = '<div id="lpc_widget_container" class="widget_colissimo"></div>';
            $this->modal  = new Modal($modalContent, $imageHtmlTag, 'lpc_pick_up_widget_container');
        }

        return $this->modal;
    }
}
