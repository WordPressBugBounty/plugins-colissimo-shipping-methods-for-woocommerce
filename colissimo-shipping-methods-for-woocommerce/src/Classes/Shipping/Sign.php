<?php

namespace Colissimo\Classes\Shipping;

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class Sign extends ShippingMethod {
    const ID = 'lpc_sign';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo with signature', 'colissimo-shipping-methods-for-woocommerce');
        $this->method_description = __('A signature will be necessary on delivery', 'colissimo-shipping-methods-for-woocommerce');

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return Helper::get_option('lpc_domicileas_FreeFromOrderValue', null);
    }
}
