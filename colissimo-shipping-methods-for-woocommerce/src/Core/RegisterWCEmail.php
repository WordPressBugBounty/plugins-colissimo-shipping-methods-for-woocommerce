<?php

namespace Colissimo\Core;

use Colissimo\Classes\Email\InwardLabelEmailManager;
use Colissimo\Classes\Email\OutwardLabelEmailManager;

defined('ABSPATH') || die('Restricted Access');

class RegisterWCEmail {
    public function init() {
        add_action('woocommerce_email_classes', [$this, 'generate_inward_label_woocommerce_email']);
        add_action('woocommerce_email_classes', [$this, 'generate_outward_label_woocommerce_email']);
    }

    public function generate_inward_label_woocommerce_email($emails) {
        $emailManager = new InwardLabelEmailManager();

        return $emailManager->generate_inward_label_woocommerce_email($emails);
    }

    public function generate_outward_label_woocommerce_email($emails) {
        $emailManager = new OutwardLabelEmailManager();

        return $emailManager->generate_outward_label_woocommerce_email($emails);
    }
}
