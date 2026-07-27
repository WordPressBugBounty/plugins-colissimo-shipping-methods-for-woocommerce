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
	<p><?php echo esc_html(sprintf(__('The inward label for order #%s has been generated.', 'colissimo-shipping-methods-for-woocommerce'), $order->get_order_number())); ?></p>
<?php if (!empty($additional_content)) { ?>
	<p></p>
	<p>
        <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
	</p>
<?php } ?>

<?php
/**
 * Action on the tracking email's footer
 *
 * @since 1.6
 */
do_action('woocommerce_email_footer', $email);
