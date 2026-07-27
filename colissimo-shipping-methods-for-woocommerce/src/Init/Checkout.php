<?php

namespace Colissimo\Init;

use Colissimo\Classes\Label\LabelGenerationPayload;
use Colissimo\Classes\Shipping\ExpertDdp;
use Colissimo\Classes\Shipping\SignDdp;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class Checkout {
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'includeScripts']);
        add_action('wp_ajax_lpc_checkout_delivery_date', [$this, 'getDeliveryDate']);
        add_action('wp_ajax_nopriv_lpc_checkout_delivery_date', [$this, 'getDeliveryDate']);
        add_action('woocommerce_after_shipping_rate', [$this, 'addDdpDescription']);
        add_action('woocommerce_checkout_process', [$this, 'preventCheckoutProcess']);
    }

    public function includeScripts() {
        if (is_checkout() || has_block('woocommerce/checkout')) {
            wp_register_script('lpc_checkout', null, [], LPC_VERSION, ['in_footer' => false]);
            wp_enqueue_script('lpc_checkout');
            wp_add_inline_script('lpc_checkout', 'window.lpc_baseAjaxUrl = "' . admin_url('admin-ajax.php') . '"', 'before');
        }
    }

    public function getDeliveryDate() {
        if ('yes' !== Helper::get_option('lpc_display_shipping_date')) {
            Helper::endAjax(false);
        }

        $postCode = Helper::getVar('postcode');
        if (empty($postCode) || strlen($postCode) < 5) {
            Helper::endAjax(false);
        }

        $checkoutApi  = Register::get('checkoutApi');
        $deliveryDate = $checkoutApi->getDeliveryDate($postCode);
        if (empty($deliveryDate)) {
            Helper::endAjax(false);
        }

        Helper::endAjax(true, ['deliveryDateText' => $deliveryDate]);
    }

    public function addDdpDescription($method, $index = 0) {
        // To get the currently selected shipping method: Helper::getWooSession()->get('chosen_shipping_methods', [])
        if (false === strpos($method->get_method_id(), '_ddp')) {
            return;
        }

        $description = Helper::get_option('lpc_extracost_msg');
        if (!empty($description)) {
            Helper::renderPartial('Checkout/DdpDescription.php', ['description' => $description]);
        }
    }

    public function preventCheckoutProcess() {
        $wcSession      = Helper::getWooSession();
        $shippingMethod = $wcSession->get('chosen_shipping_methods');
        $needShipping   = WC()->cart->needs_shipping();

        if (!$needShipping || empty($shippingMethod)) {
            return;
        }

        $ddp = false;
        foreach ($shippingMethod as $oneMethod) {
            if (strpos($oneMethod, ExpertDdp::ID) !== false || strpos($oneMethod, SignDdp::ID) !== false) {
                $ddp = true;
            }
        }

        if (!$ddp) {
            return;
        }

        $this->checkPhone();
        $this->checkState();
    }

    private function checkPhone() {
        $nonce = isset($_REQUEST['woocommerce-process-checkout-nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['woocommerce-process-checkout-nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'woocommerce-process_checkout')) {
            return;
        }

        $customerPhoneNumber = isset($_REQUEST['billing_phone']) ? sanitize_text_field(wp_unslash($_REQUEST['billing_phone'])) : '';

        // Even if we don't have a shipping phone natively in WooCommerce, we can check if a shipping phone exist if the billing one is empty
        // because a plugin or a theme can add it
        if (empty($customerPhoneNumber) && isset($_REQUEST['shipping_phone']) && !empty($_REQUEST['shipping_phone'])) {
            $customerPhoneNumber = sanitize_text_field(wp_unslash($_REQUEST['shipping_phone']));
        }

        $customerPhoneNumber = str_replace(' ', '', $customerPhoneNumber);

        if (empty($customerPhoneNumber)) {
            throw new Exception(esc_html__('Please define a mobile phone number for SMS notification tracking', 'colissimo-shipping-methods-for-woocommerce'));
        }
    }

    private function checkState() {
        $nonce = isset($_REQUEST['woocommerce-process-checkout-nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['woocommerce-process-checkout-nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'woocommerce-process_checkout')) {
            return;
        }

        $country = isset($_REQUEST['shipping_country']) ? sanitize_text_field(wp_unslash($_REQUEST['shipping_country'])) : '';
        $state   = isset($_REQUEST['shipping_state']) ? sanitize_text_field(wp_unslash($_REQUEST['shipping_state'])) : '';

        if (in_array($country, LabelGenerationPayload::COUNTRIES_NEEDING_STATE) && empty($state)) {
            throw new Exception(esc_html__('Please define a state / province', 'colissimo-shipping-methods-for-woocommerce'));
        }
    }
}
