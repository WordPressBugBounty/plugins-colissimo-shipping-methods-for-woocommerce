<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
?>
</tbody>
</table>
<div class="forminp-lpc_hazmat_description">
	<p>
        <?php
        esc_html_e(
            'The hazmat option lets you specify which parcel contains hazardous materials, ensuring that your goods are protected and safe during all the shipment.',
            'colissimo-shipping-methods-for-woocommerce'
        );
        ?>
	</p>
	<p>
        <?php
        esc_html_e(
            'There are a total of 5 categories of hazardous materials that can be specified on your products and/or product categories. The categories are as follows:',
            'colissimo-shipping-methods-for-woocommerce'
        );
        ?>
	</p>
	<table>
		<thead>
			<tr>
				<th><?php esc_html_e('Category', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
				<th><?php esc_html_e('Limited hazardous materials quantity', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
				<th><?php esc_html_e('Extra cost', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
				<th><?php esc_html_e('Activated', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
			</tr>
		</thead>
		<tbody>
            <?php foreach ($args['hazmatCategories'] as $category) { ?>
				<tr>
					<td>
                        <?php
                        // Cannot call __() in class constants
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                        esc_html_e($category['label'], 'colissimo-shipping-methods-for-woocommerce');
                        ?>
					</td>
					<td>
                        <?php
                        // Cannot call __() in class constants
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                        esc_html_e($category['max_weight_text'], 'colissimo-shipping-methods-for-woocommerce');
                        ?>
					</td>
					<td>0,20€</td>
					<td>
                        <?php
                        if ($category['active']) {
                            esc_html_e('Yes', 'colissimo-shipping-methods-for-woocommerce');
                        } else {
                            esc_html_e('No', 'colissimo-shipping-methods-for-woocommerce');
                        }
                        ?>
					</td>
				</tr>
            <?php } ?>
		</tbody>
	</table>
</div>
<table class="form-table">
	<tbody>
