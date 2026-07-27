<?php

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<div class="lpc_balreturn">
	<h2 class="entry-title margin-top-0"><?php esc_html_e('MailBox picking return', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>

	<div class="lpc_balreturn_shipping lpc_balreturn_withseparator">
		<div>
            <?php esc_html_e('Address from which the return will be from:', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</div>
		<div class="lpc_balreturn_shipping_address">
            <?php echo wp_kses_post(WC()->countries->get_formatted_address($args['addressDisplay'])); ?>
		</div>
	</div>
	<div>
        <?php if ($args['pickupConfirmation']) { ?>
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
			<script src="<?php echo esc_url(Helper::getJsUrl('orders/return_bal.js')); ?>"></script>
		<input type="hidden" id="lpc_download_url" value="<?php echo esc_url($args['labelDownloadUrl'], null, 'javascript'); ?>" />

			<div>
				<b><?php esc_html_e('Your PickUp has been confirmed.', 'colissimo-shipping-methods-for-woocommerce'); ?></b>
			</div>
			<div>
                <?php esc_html_e('Your return tracking number is:', 'colissimo-shipping-methods-for-woocommerce'); ?>
                <?php echo esc_html($args['returnTrackingNumber']); ?>
			</div>

			<div id="lpc_return_instructions_container">
				<p id="instructions_title"><?php esc_html_e('How to return your parcel?', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
				<ol>
					<li><?php esc_html_e('Pack your products.', 'colissimo-shipping-methods-for-woocommerce'); ?></li>
					<li><?php esc_html_e('Print your label and stick it on your parcel.', 'colissimo-shipping-methods-for-woocommerce'); ?></li>
					<li><?php esc_html_e('Put your parcel in your mailbox before the appointment time.', 'colissimo-shipping-methods-for-woocommerce'); ?></li>
					<li>
                        <?php
                        wp_kses(
                            printf(
                                // translators: %s tracking link HTML anchor tag
                                esc_html__('Track your parcel shipment on %s', 'colissimo-shipping-methods-for-woocommerce'),
                                '<a target="_blank" href="https://laposte.fr/outils/suivre-vos-envois?code=' . esc_attr($args['returnTrackingNumber']) . '">https://laposte.fr/suivi</a>'
                            ),
                            [
                                'a' => [
                                    'href' => [],
                                    'target' => [],
                                ],
                            ]
                        );
                        ?>
					</li>
				</ol>
			</div>
        <?php } else { ?>
			<p class="lpc_balreturn_error"><b><?php esc_html_e('An error occured while confirming the mailBox pick-up.', 'colissimo-shipping-methods-for-woocommerce'); ?></b></p>
        <?php } ?>
	</div>
</div>
