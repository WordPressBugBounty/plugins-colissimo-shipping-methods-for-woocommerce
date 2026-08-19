<?php

namespace Colissimo\Classes\Shipping;

use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

defined('ABSPATH') || die('Restricted Access');

class ShippingZones {
    const UNKNOWN_WC_COUNTRIES = ['AN', 'IC', 'XZ'];
    const DEFAULT_PRICES_PER_ZONE_JSON_FILE = LPC_RESOURCE_FOLDER . 'defaultPrices.json';

    private $addCustomZonesDone = false;
    protected $lpcCapabilitiesPerCountry;

    public function __construct(?CapabilitiesPerCountry $lpcCapabilitiesPerCountry = null) {
        $this->lpcCapabilitiesPerCountry = Register::get('capabilitiesPerCountry');
    }

    public function init() {
        // only at plugin installation
        register_activation_hook(
            LPC_MAIN_FILE,
            function () {
                $this->addCustomZonesOrUpdateOne();
            }
        );
    }

    public function addCustomZonesOrUpdateOne($zoneName = '') {
        if ($this->addCustomZonesDone) {
            return;
        }

        $currentZones = [];
        foreach (WC_Shipping_Zones::get_zones() as $zone) {
            $currentZones[$zone['zone_name']] = $zone;
        }

        $defaultPrices = json_decode(
            file_get_contents(self::DEFAULT_PRICES_PER_ZONE_JSON_FILE),
            true
        );

        foreach ($this->lpcCapabilitiesPerCountry->getCapabilitiesPerCountry() as $zoneCode => $zoneDefinition) {
            if (!empty($zoneName) && $zoneDefinition['name'] !== $zoneName) {
                continue;
            }

            $countries       = [];
            $shippingMethods = [];
            foreach ($zoneDefinition['countries'] as $countryCode => $countryDefinition) {
                $countries[] = $countryCode;

                if (!empty($countryDefinition['domiciless'])) {
                    $shippingMethods['lpc_nosign'] = true;
                }
                if (!empty($countryDefinition['domicileas'])) {
                    $shippingMethods['lpc_sign'] = true;
                }
                if (!empty($countryDefinition['domicileasddp'])) {
                    $shippingMethods['lpc_sign_ddp'] = true;
                }
                if (!empty($countryDefinition['pr'])) {
                    $shippingMethods['lpc_relay'] = true;
                }
                if (!empty($countryDefinition['ecoom'])) {
                    $shippingMethods['lpc_ecoom'] = true;
                }
            }

            $this->addCustomZone(
                $zoneDefinition['name'],
                $countries,
                array_keys($shippingMethods),
                $currentZones,
                empty($defaultPrices[$zoneCode]) ? [] : $defaultPrices[$zoneCode]
            );
        }

        if (empty($zoneName)) {
            $this->addCustomZonesDone = true;
        }
    }

    protected function addCustomZone($zoneName, array $countries, array $shippingMethods, array $currentZones, array $defaultPrices) {
        global $wpdb;

        $newZone = null;
        if (!empty($currentZones[$zoneName])) {
            $newZone = $currentZones[$zoneName];
        }
        if (empty($newZone['id'])) {
            $newZone = new WC_Shipping_Zone();
        } else {
            $newZone = WC_Shipping_Zones::get_zone($newZone['id']);
        }

        $newZone->set_zone_name($zoneName);

        $existingZoneLocations = array_map(
            fn($v) => $v->code,
            array_filter(
                $newZone->get_zone_locations(),
                fn($v) => 'country' === $v->type
            )
        );
        foreach ($countries as $country) {
            if (!in_array($country, self::UNKNOWN_WC_COUNTRIES)) {
                if (!in_array($country, $existingZoneLocations)) {
                    $newZone->add_location($country, 'country');
                }
            }
        }

        $existingShippingMethods = array_map(
            fn($v) => $v->id,
            $newZone->get_shipping_methods()
        );

        $weightUnit = Helper::get_option('woocommerce_weight_unit', 'kg');
        foreach ($shippingMethods as $shippingMethod) {
            if (in_array($shippingMethod, $existingShippingMethods)) {
                continue;
            }

            $shippingMethodInstanceId = $newZone->add_shipping_method($shippingMethod);

            // No WooCommerce API exists to set is_enabled on zone methods.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                "{$wpdb->prefix}woocommerce_shipping_zone_methods",
                [
                    'is_enabled' => false,
                ],
                [
                    'instance_id' => $shippingMethodInstanceId,
                ]
            );

            // Add default prices to the shipping method we just added
            if (empty($defaultPrices[$shippingMethod])) {
                continue;
            }

            $optionValues = ['shipping_rates' => []];
            foreach ($defaultPrices[$shippingMethod] as $onePrice) {
                $optionValues['shipping_rates'][] = [
                    'min_weight'       => wc_get_weight($onePrice['weight_min'], $weightUnit, 'g'),
                    'max_weight'       => wc_get_weight($onePrice['weight_max'], $weightUnit, 'g'),
                    'min_price'        => 0,
                    'shipping_class'   => [0 => 'all'],
                    'product_category' => [0 => 'all'],
                    'price'            => $onePrice['price'],
                ];
            }

            update_option('woocommerce_' . $shippingMethod . '_' . $shippingMethodInstanceId . '_settings', $optionValues);
        }

        $newZone->save();
    }
}
