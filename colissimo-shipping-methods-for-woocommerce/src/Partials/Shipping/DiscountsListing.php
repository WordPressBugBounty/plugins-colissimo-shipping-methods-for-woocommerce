<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
$shippingMethod  = $args['shippingMethod'];
$currentDiscount = $shippingMethod->get_option('shipping_discount', []);
?>
<tr valign="top">
	<th scope="row" class="titledesc"><label><?php esc_html_e('Shipping discount', 'colissimo-shipping-methods-for-woocommerce'); ?></label></th>
	<td class="forminp" id="<?php echo esc_attr($shippingMethod->id); ?>_shipping_discounts" style="overflow: auto;">
		<fieldset>
			<table class="shippingrows widefat" cellspacing="0">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox"></td>
						<th>
							<p><?php esc_html_e('Number of products', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
						</th>
						<th>
							<p><?php esc_html_e('Discount in percentage', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
						</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th colspan="7">
							<button type="button"
									class="add button"
									id="lpc_shipping_discount_add"
									style="margin-left: 24px">
                                <?php esc_html_e('Add discount', 'colissimo-shipping-methods-for-woocommerce'); ?>
							</button>
							<button type="button"
									class="remove button"
									id="lpc_shipping_discount_remove">
                                <?php esc_html_e('Delete selected', 'colissimo-shipping-methods-for-woocommerce'); ?>
							</button>
						</th>
					</tr>
				</tfoot>
				<tbody class="table_discount">
                    <?php
                    $counter = 0;
                    foreach ($currentDiscount as $i => $discount) {
                        ?>
						<tr>
							<td class="check-column"><input type="checkbox" /></td>
							<td style="text-align: center">
								<input type="number"
									   class="input-number regular-input"
									   step="1"
									   min="0"
									   required
									   value="<?php echo isset($discount['nb_product']) ? esc_attr($discount['nb_product']) : ''; ?>"
									   name="shipping_discount[<?php echo esc_attr($i); ?>][nb_product]" />
							</td>
							<td style="text-align: center">
								<input type="number"
									   class="input-number regular-input"
									   step="any"
									   min="0"
									   max="100"
									   required
									   value="<?php echo isset($discount['percentage']) ? esc_attr($discount['percentage']) : ''; ?>"
									   name="shipping_discount[<?php echo esc_attr($i); ?>][percentage]" />
							</td>
						</tr>
                        <?php
                        $counter ++;
                    }
                    ?>
				</tbody>
			</table>
		</fieldset>

		<template id="lpc_shipping_discount_row_template">
			<tr>
				<td class="check-column">
					<input type="checkbox" />
				</td>
				<td style="text-align: center">
					<input type="number"
						   class="input-number regular-input"
						   step="any"
						   min="0"
						   value="0"
						   required
						   name="shipping_discount[__row_id__][nb_product]" />
				</td>
				<td style="text-align: center">
					<input max="100"
						   type="number"
						   class="input-number regular-input"
						   step="any"
						   min="0"
						   value="0"
						   required
						   name="shipping_discount[__row_id__][percentage]" />
				</td>
			</tr>
		</template>
	</td>
</tr>
