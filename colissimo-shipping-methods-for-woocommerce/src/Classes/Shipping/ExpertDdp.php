<?php

namespace Colissimo\Classes\Shipping;

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class ExpertDdp extends ShippingMethod {
    const ID = 'lpc_expert_ddp';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo International - DDP option', 'colissimo-shipping-methods-for-woocommerce');
        $this->method_description = __('For international delivery only', 'colissimo-shipping-methods-for-woocommerce');

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return Helper::get_option('lpc_expert_FreeFromOrderValue', null);
    }
}
