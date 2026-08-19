<?php

namespace Colissimo\Classes\Pickup;

use Colissimo\Helpers\Helper;
use Colissimo\Classes\Shipping\CapabilitiesPerCountry;
use Colissimo\Core\Modal;
use Colissimo\Core\Register;
use Colissimo\Classes\Shipping\Relay;
use Colissimo\Api\PickupWidgetApi;
use WC_Order;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

defined('ABSPATH') || die('Restricted Access');

class AdminWidget {
    const BASE_URL = 'https://ws.colissimo.fr';
    const MAP_JS_URL = 'https://api.mapbox.com/mapbox-gl-js/v2.6.1/mapbox-gl.js';
    const MAP_CSS_URL = 'https://api.mapbox.com/mapbox-gl-js/v2.6.1/mapbox-gl.css';
    const WEB_JS_URL = self::BASE_URL . '/widget-colissimo/js/jquery.plugin.colissimo.js';

    protected $pickUpWidgetApi;
    protected $lpcCapabilitiesPerCountry;

    public function __construct(
        ?PickupWidgetApi $pickUpWidgetApi = null,
        ?CapabilitiesPerCountry $lpcCapabilitiesPerCountry = null
    ) {
        $this->pickUpWidgetApi           = Register::get('pickupWidgetApi');
        $this->lpcCapabilitiesPerCountry = Register::get('capabilitiesPerCountry');
    }

    public function init() {
        add_action('current_screen',
            function ($currentScreen) {
                // Add scripts and styles only on the WC order in edition mode
                if ('woocommerce_page_wc-orders' === $currentScreen->base || ('post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type)) {
                    // Mapbox scripts to display the map, needed by the Colissimo widget
                    Helper::enqueueScript('lpc_mapbox', self::MAP_JS_URL, ['jquery']);
                    Helper::enqueueStyle('lpc_mapbox', self::MAP_CSS_URL);

                    wp_register_script('lpc_widgets_web_js_url', self::WEB_JS_URL, ['lpc_mapbox'], '0.1', true);

                    // This js file opens the modal and loads the widget when the user clicks on the "Choose PickUp point" link
                    Helper::enqueueScript(
                        'lpc_widget',
                        Helper::getJsUrl('pickup/widget.js'),
                        ['jquery-ui-autocomplete', 'lpc_widgets_web_js_url']
                    );

                    Helper::enqueueStyle('lpc_pickup_widget', Helper::getCssUrl('pickup/widget.css'));
                    Helper::enqueueStyle('lpc_pickup', Helper::getCssUrl('pickup/pickup.css'));
                }
            }
        );
    }

    public function addWidget(WC_Order $order) {
        $availableCountries = $this->getWidgetListCountry();
        if (empty($availableCountries)) {
            $availableCountries = ['FR'];
        }

        $args = [];

        $relayTypes = Helper::get_option('lpc_relay_types', '');
        if (empty($relayTypes)) {
            $relayTypes = '1';
        } elseif ('-1' === $relayTypes) {
            $relayTypes = '0';
        }

        $weight = 0;
        $items  = $order->get_items();
        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                $product = $item->get_product();
                if (!empty($product) && !$product->is_virtual()) {
                    $weight += wc_get_weight($product->get_weight(), 'g') * $item['quantity'];
                }
            }
        }

        $args['widgetInfo'] = [
            'ceCountryList'     => implode(',', $availableCountries),
            'ceLang'            => defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'FR',
            'ceAddress'         => !empty($order->get_shipping_address_1()) ? str_replace('’', "'", $order->get_shipping_address_1()) : '',
            'ceZipCode'         => !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : '',
            'ceTown'            => !empty($order->get_shipping_city()) ? str_replace('’', "'", $order->get_shipping_city()) : '',
            'ceCountry'         => !empty($order->get_shipping_country()) ? $order->get_shipping_country() : '',
            'URLColissimo'      => self::BASE_URL,
            'token'             => $this->pickUpWidgetApi->authenticate(),
            'dyPreparationTime' => Helper::get_option('lpc_preparation_time', 1),
            'origin'            => 'CMS',
            'filterRelay'       => $relayTypes,
        ];

        if (!empty($weight)) {
            $args['widgetInfo']['dyWeight'] = $weight;
        }

        if (Helper::get_option('lpc_prCustomizeWidget', 'no') === 'yes') {
            $args['lpcAddressTextColor'] = Helper::get_option('lpc_prAddressTextColor', null);
            if (!empty($args['lpcAddressTextColor'])) {
                $args['widgetInfo']['couleur1'] = $args['lpcAddressTextColor'];
            }
            $args['lpcListTextColor'] = Helper::get_option('lpc_prListTextColor', null);
            if (!empty($args['lpcListTextColor'])) {
                $args['widgetInfo']['couleur2'] = $args['lpcListTextColor'];
            }

            $font = Helper::getFont('lpc_prDisplayFont');
            if (!empty($font)) {
                $args['widgetInfo']['font'] = $font;
            }
        }

        $args['widgetInfo'] = wp_json_encode($args['widgetInfo']);

        $lpcImageUrl  = Helper::getImageUrl('colissimo_cropped.png');
        $imageHtmlTag = '<img src="' . esc_url($lpcImageUrl) . '" style="max-width: 90px; display:inline; vertical-align: middle;">';

        $args['modal'] = new Modal(
            '<div id="lpc_widget_container" class="widget_colissimo"></div>', $imageHtmlTag,
            'lpc_pick_up_widget_container'
        );

        $args['showButton'] = true;
        $args['showInfo']   = false;
        $args['type']       = 'link';

        Helper::renderPartial('Pickup/WidgetInit.php', $args);
        Helper::renderPartial('Pickup/Widget.php', $args);
    }

    /**
     * Get list of enabled countries for relay method
     *
     * @return array
     */
    public function getWidgetListCountry() {
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
}
