<?php

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row">
		<label for="<?php echo esc_attr($args['id_and_name']); ?>">
            <?php
            echo esc_html(
                sprintf(
                // Dynamic option
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    __($args['label'], 'colissimo-shipping-methods-for-woocommerce'),
                    Helper::get_option('woocommerce_weight_unit', ''))
            );
            echo wp_kses(wc_help_tip($args['desc']), Helper::KSES_WC_TOOLTIP);
            ?>
		</label>
	</th>
	<td>
		<input
				name="<?php echo esc_attr($args['id_and_name']); ?>"
				id="<?php echo esc_attr($args['id_and_name']); ?>"
				type="number" step="0.01" min="0" style="height:100%;"
				value="<?php echo esc_attr(!empty($args['value']) ? $args['value'] : '0'); ?>" />
	</td>
</tr>

