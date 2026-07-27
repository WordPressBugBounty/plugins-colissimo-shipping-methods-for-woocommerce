<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
?>
<input type="hidden"
       id="lpc_select_products"
       value="<?php esc_attr_e('You need to select at least one item to generate a label', 'colissimo-shipping-methods-for-woocommerce'); ?>" />
<input type="hidden" id="lpc_generate_url" value="<?php echo esc_url($args['generateUrlBase'], null, 'javascript'); ?>" />
<input type="hidden" id="lpc_download_url" value="<?php echo esc_url($args['downloadUrlBase'], null, 'javascript'); ?>" />

<h2 class="woocommerce-return-details__title margin-top-0"><?php esc_html_e('Return details', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>

<div id="lpc_return_options">
	<p><?php esc_html_e('Select the products you would like to return:', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
	<table id="lpc_return_table" class="shop_table">
		<thead>
			<tr>
				<th><input type="checkbox" id="lpc_selectall" /></th>
				<th><?php esc_html_e('Product', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
				<th><?php esc_html_e('Quantity', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
			</tr>
		</thead>
		<tbody>
            <?php
            foreach ($args['order']->get_items() as $item) {
                $itemId = $item->get_id();
                ?>
				<tr>
					<td><input type="checkbox" class="lpc_return_checkbox" id="lpc_return_<?php echo esc_attr($itemId); ?>" /></td>
					<td><label for="lpc_return_<?php echo esc_attr($itemId); ?>"><?php echo esc_html($item->get_name()); ?></label></td>
					<td>
						<select data-lpc-product="<?php echo esc_attr($itemId); ?>">
                            <?php for ($i = 1; $i <= $item->get_quantity(); $i ++) { ?>
								<option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                            <?php } ?>
						</select>
					</td>
				</tr>
            <?php } ?>
		</tbody>
	</table>

	<button type="button" class="button wp-element-button" id="lpc_download_return_label">
        <?php
        if ($args['securedReturn']) {
            esc_html_e('Return from a post office', 'colissimo-shipping-methods-for-woocommerce');
        } else {
            esc_html_e('Generate inward label', 'colissimo-shipping-methods-for-woocommerce');
        }
        ?>
	</button>

    <?php if ($args['balReturn']) { ?>
		<input type="hidden" id="lpc_bal_url" value="<?php echo esc_url($args['balReturnUrl'], null, 'javascript'); ?>" />
		<button type="button" class="button wp-element-button" id="lpc_return_bal_button">
            <?php esc_html_e('MailBox picking return', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</button>
    <?php } ?>
</div>
<div id="lpc_return_label_confirmation">
    <?php
    if ($args['securedReturn']) {
        // translators: %s return label tracking number
        $message = __('Your secured code for the return label %s has been generated.', 'colissimo-shipping-methods-for-woocommerce');
    } else {
        // translators: %s return label tracking number
        $message = __('Your label %s has been generated', 'colissimo-shipping-methods-for-woocommerce');
    }

    printf(esc_html($message), '<span id="lpc_return_label_confirmation_tracking_number"></span>');
    ?>
</div>
<div id="lpc_return_instructions_container">
	<p id="instructions_title"><?php esc_html_e('How to return your parcel?', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
	<ol>
		<li><?php esc_html_e('Pack your products.', 'colissimo-shipping-methods-for-woocommerce'); ?></li>
        <?php if ($args['securedReturn']) { ?>
			<li>
                <?php esc_html_e('Go to the post office of your choice:', 'colissimo-shipping-methods-for-woocommerce'); ?>
				<a target="_blank"
				   href="https://localiser.laposte.fr/?jesuis=particulier&contact=vente&qp=<?php echo esc_attr($args['order']->get_shipping_postcode()); ?>">https://localiser.laposte.fr</a>
			</li>
			<li>
                <?php
                esc_html_e(
                    'At the drop-off point, present your barcode to your contact or to the machine (or your 9-character code) to have your label printed and make your deposit.',
                    'colissimo-shipping-methods-for-woocommerce'
                );
                ?>
			</li>
        <?php } else { ?>
			<li><?php esc_html_e('Print your label and stick it on your parcel.', 'colissimo-shipping-methods-for-woocommerce'); ?></li>
			<li>
                <?php esc_html_e('Drop off your parcel at the post office of your choice:', 'colissimo-shipping-methods-for-woocommerce'); ?>
				<a target="_blank"
				   href="https://localiser.laposte.fr/?jesuis=particulier&contact=vente&qp=<?php echo esc_attr($args['order']->get_shipping_postcode()); ?>">https://localiser.laposte.fr</a>
			</li>
        <?php } ?>
		<li>
            <?php
            wp_kses(
                printf(
                    // translators: %s tracking link HTML anchor tag
                    esc_html__('Track your parcel shipment on %s', 'colissimo-shipping-methods-for-woocommerce'),
                    '<a target="_blank" href="https://laposte.fr/outils/suivre-vos-envois">https://laposte.fr/suivi</a>'
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
