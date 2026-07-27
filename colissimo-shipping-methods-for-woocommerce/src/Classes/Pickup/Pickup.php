<?php

namespace Colissimo\Classes\Pickup;

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

abstract class Pickup {
    const WEB_SERVICE = 'web_service';
    const WIDGET = 'widget';

    protected function getMode($methodId, $instanceId) {
        if ('lpc_relay' !== $methodId) {
            return '';
        }

        // Add the pickup selection button only when this shipping method is selected
        $selected       = false;
        $wcSession      = Helper::getWooSession();
        $shippingMethod = $wcSession->get('chosen_shipping_methods');
        foreach ($shippingMethod as $oneMethod) {
            if ($oneMethod === $instanceId) {
                $selected = true;
            }
        }

        if (!$selected) {
            return '';
        }

        if ('widget' === Helper::get_option('lpc_pickup_map_type', 'widget')) {
            return self::WIDGET;
        } else {
            return self::WEB_SERVICE;
        }
    }

    protected function getCurrentCustomerAddress(): array {
        $address = [
            'countryCode' => '',
            'zipCode'     => '',
            'city'        => '',
            'address'     => '',
        ];

        if (WC()->customer) {
            $customer = WC()->customer;
            $address  = [
                'countryCode' => $customer->get_shipping_country(),
                'zipCode'     => $customer->get_shipping_postcode(),
                'city'        => $customer->get_shipping_city(),
                'address'     => $customer->get_shipping_address_1(),
            ];
        }

        return $address;
    }
}
