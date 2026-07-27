<?php

namespace Colissimo\Classes\Email;

defined('ABSPATH') || die('Restricted Access');

class OutwardLabelEmailManager {
    const EMAIL_OUTWARD_TRACKING_OPTION = 'lpc_email_outward_tracking';
    const ON_OUTWARD_LABEL_GENERATION_OPTION = 'on_outward_label_generation';
    const ON_BORDEREAU_GENERATION_OPTION = 'on_bordereau_generation';

    public function init() {
        add_action('lpc_outward_label_generated_to_email', [$this, 'send_email']);
    }

    public function generate_outward_label_woocommerce_email($emails) {
        $emails['LpcOutwardLabelGenerationEmail'] = new OutwardLabelGenerationEmail();

        return $emails;
    }

    public function send_email($order_data) {
        WC()->mailer();
        $lpcOutwardLabelGenerationEmail = new OutwardLabelGenerationEmail();
        $lpcOutwardLabelGenerationEmail->trigger($order_data['order']);
    }
}
