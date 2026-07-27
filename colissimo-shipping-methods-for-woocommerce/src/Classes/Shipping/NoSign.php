<?php

namespace Colissimo\Classes\Shipping;

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class NoSign extends ShippingMethod {
    const ID = 'lpc_nosign';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo without signature', 'colissimo-shipping-methods-for-woocommerce');
        $this->method_description = __('A signature won\'t be necessary on delivery', 'colissimo-shipping-methods-for-woocommerce');

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return Helper::get_option('lpc_domiciless_FreeFromOrderValue', null);
    }
}
