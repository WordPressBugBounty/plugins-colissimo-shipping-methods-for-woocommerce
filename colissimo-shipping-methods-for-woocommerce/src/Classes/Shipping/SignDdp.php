<?php

namespace Colissimo\Classes\Shipping;

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class SignDdp extends ShippingMethod {
    const ID = 'lpc_sign_ddp';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo with signature - DDP option', 'colissimo-shipping-methods-for-woocommerce');
        $this->method_description = __('A signature will be necessary on delivery', 'colissimo-shipping-methods-for-woocommerce');

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return Helper::get_option('lpc_domicileas_FreeFromOrderValue', null);
    }
}
