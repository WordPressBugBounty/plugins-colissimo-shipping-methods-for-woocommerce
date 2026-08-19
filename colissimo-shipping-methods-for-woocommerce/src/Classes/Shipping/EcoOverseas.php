<?php

namespace Colissimo\Classes\Shipping;

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class EcoOverseas extends ShippingMethod {
    const ID = 'lpc_ecoom';

    public function __construct($instance_id = 0) {
        $this->id                 = self::ID;
        $this->method_title       = __('Colissimo ECO Overseas', 'colissimo-shipping-methods-for-woocommerce');
        $this->method_description = __(
            'This offer is only available for shipments to Guadeloupe, French Guiana, Reunion Island, Martinique and Mayotte, shipped from mainland France. Make sure this offer is enabled on your Colissimo account before activating it. Indicative delivery times: D+21 for Guadeloupe and Martinique, D+29 for French Guiana, D+31 for Reunion Island, D+48 for Mayotte.',
            'colissimo-shipping-methods-for-woocommerce'
        );

        parent::__construct($instance_id);
    }

    public function freeFromOrderValue() {
        return Helper::get_option('lpc_ecoom_FreeFromOrderValue', null);
    }
}
