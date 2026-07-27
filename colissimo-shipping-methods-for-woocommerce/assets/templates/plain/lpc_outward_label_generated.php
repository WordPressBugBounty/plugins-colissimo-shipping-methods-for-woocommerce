<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional re-use of a documented WooCommerce core email hook.
defined('ABSPATH') || die('Restricted Access');

$shipping_address2 = '';
if (!empty($order->get_shipping_address_2())) {
    $shipping_address2 = $order->get_shipping_address_2() . "\n";
}
echo '= ' . esc_html($email_heading) . " =\n\n";
// translators: %s customer billing first name
echo esc_html(sprintf(__('Hi %s,', 'colissimo-shipping-methods-for-woocommerce'), esc_html($order->get_billing_first_name()))) . "\n\n";
// translators: %s order number
echo esc_html(sprintf(__('Your order #%s is being prepared and will soon be taken care of for shipping.', 'colissimo-shipping-methods-for-woocommerce'), $order->get_order_number())) . "\n\n";
echo esc_html(sprintf(__('You can follow up your order here:', 'colissimo-shipping-methods-for-woocommerce'))) . ' ' . esc_url($tracking_link) . "\n\n";
// translators: %s tracking number of the outward parcel
echo esc_html(sprintf(__('Tracking number: %s', 'colissimo-shipping-methods-for-woocommerce'), $order->get_meta('lpc_outward_parcel_number'))) . "\n\n";
echo esc_html__('Shipping address:', 'colissimo-shipping-methods-for-woocommerce') . "\n" . esc_html($order->get_shipping_first_name()) . ' ' . esc_html($order->get_shipping_last_name()) .
     "\n" . esc_html($order->get_shipping_address_1()) . "\n" . esc_html($shipping_address2 . $order->get_shipping_postcode()) . ' ' . esc_html($order->get_shipping_city())
     . "\n" . esc_html(WC()->countries->countries[$order->get_shipping_country()]) . "\n\n";

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
