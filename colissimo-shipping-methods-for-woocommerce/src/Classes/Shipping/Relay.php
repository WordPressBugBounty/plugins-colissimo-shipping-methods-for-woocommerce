<?php

namespace Colissimo\Classes\Shipping;

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class Relay extends ShippingMethod {
    const ID = 'lpc_relay';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo relay', 'colissimo-shipping-methods-for-woocommerce');
        $this->method_description = __('Delivery in a relay', 'colissimo-shipping-methods-for-woocommerce');

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return Helper::get_option('lpc_relay_FreeFromOrderValue', null);
    }
}
