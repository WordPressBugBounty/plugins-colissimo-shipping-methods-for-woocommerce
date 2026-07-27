<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional re-use of a documented WooCommerce core email hook.
defined('ABSPATH') || die('Restricted Access');

echo '= ' . esc_html($email_heading) . " =\n\n";
// translators: %s customer billing first name
echo esc_html(sprintf(__('Hi %s,', 'colissimo-shipping-methods-for-woocommerce'), $order->get_billing_first_name())) . "\n\n";
// translators: %s order number
echo esc_html(sprintf(__('The inward label for order #%s has been generated.', 'colissimo-shipping-methods-for-woocommerce'), $order->get_order_number())) . "\n\n";

if (!empty($additional_content)) {
    echo "\n\n";
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n";
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * Action on the tracking email's footer (text version)
 *
 * @since 1.6
 */
echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
