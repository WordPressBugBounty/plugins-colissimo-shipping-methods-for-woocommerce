<?php
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

	<div class="lpc_balreturn_address woocommerce-address-fields__field-wrapper">
		<form method="POST" action="<?php echo esc_url($args['balReturnUrl']); ?>">
			<input type="hidden" name="lpc_label_products" value="<?php echo esc_attr($args['products']); ?>" />

            <?php if ($args['listMailBoxPickingDatesResponse'] && !empty($args['mailBoxPickingDate'])) { ?>
				<input type="hidden" id="lpc_bal_companyName" name="address[companyName]" value="<?php echo esc_attr($args['address']['companyName']); ?>" />
				<input type="hidden" id="lpc_bal_street" name="address[street]" value="<?php echo esc_attr($args['address']['street']); ?>" />
				<input type="hidden" id="lpc_bal_zipCode" name="address[zipCode]" value="<?php echo esc_attr($args['address']['zipCode']); ?>" />
				<input type="hidden" id="lpc_bal_city" name="address[city]" value="<?php echo esc_attr($args['address']['city']); ?>" />
				<input type="hidden"
				       id="lpc_bal_pickingDate"
				       name="pickingDate"
				       value="<?php echo esc_attr($args['listMailBoxPickingDatesResponse']['mailBoxPickingDates'][0]); ?>" />
				<p>
                    <?php
                    echo sprintf(
                        // translators: %1$s validity time today, %2$s mailbox picking date, %3$s maximum hour
                        esc_html__('Please confirm before today %1$s that you will put the parcel in the MailBox described previously, before the %2$s at %3$s.', 'colissimo-shipping-methods-for-woocommerce'),
                        esc_html($args['listMailBoxPickingDatesResponse']['validityTime']),
                        esc_html($args['mailBoxPickingDate']),
                        esc_html($args['listMailBoxPickingDatesResponse']['mailBoxPickingDateMaxHour'])
                    );
                    ?>
				</p>
				<div>
					<button type="submit" class="button wp-element-button lpc_balreturn_btn">
                        <?php esc_html_e('Confirm pick-up', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</button>
				</div>
            <?php } else { ?>
				<p class="lpc_balreturn_error"><b><?php esc_html_e('This address is not eligible for MailBox pick-up.', 'colissimo-shipping-methods-for-woocommerce'); ?></b></p>
            <?php } ?>
		</form>
	</div>
</div>
