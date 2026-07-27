<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional re-use of documented WooCommerce core email hooks.
defined('ABSPATH') || die('Restricted Access');

/**
 * Action on the tracking email's header
 *
 * @since 1.6
 */
do_action('woocommerce_email_header', $email_heading, $email); ?>
	<?php // translators: %s customer billing first name ?>
	<p><?php echo esc_html(sprintf(__('Hi %s,', 'colissimo-shipping-methods-for-woocommerce'), $order->get_billing_first_name())); ?></p>
	<?php // translators: %s order number ?>
	<p><?php echo esc_html(sprintf(__('Your order #%s is being prepared and will soon be taken care of for shipping.', 'colissimo-shipping-methods-for-woocommerce'), $order->get_order_number())); ?></p>
	<p>
        <?php esc_html_e('You can follow up your order', 'colissimo-shipping-methods-for-woocommerce'); ?> <a href="<?php echo esc_url($tracking_link); ?>" target="_blank">
            <?php esc_html_e('here', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</a>
	</p>
	<p></p>
	<p>
        <?php
        echo sprintf(
            // translators: %s tracking number link of the outward parcel
            esc_html__('Tracking number: %s', 'colissimo-shipping-methods-for-woocommerce'),
            '<a target="_blank" href="' . esc_url($tracking_link) . '">' . esc_html($order->get_meta('lpc_outward_parcel_number')) . '</a>'
        );
        ?>
	</p>

	<p><?php echo esc_html__('Shipping address:', 'colissimo-shipping-methods-for-woocommerce') . '<br>' . esc_html($order->get_formatted_shipping_address()); ?></p>
	<p>
        <?php
        if (!empty($additional_content)) {
            echo wp_kses_post(wpautop(wptexturize($additional_content)));
        }
        ?>
	</p>
<?php
/**
 * Action on the tracking email's footer
 *
 * @since 1.6
 */
do_action('woocommerce_email_footer', $email);
