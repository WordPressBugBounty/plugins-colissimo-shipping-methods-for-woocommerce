<?php

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row">
		<label for="<?php echo esc_attr($args['id_and_name']); ?>">
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($args['label'], 'colissimo-shipping-methods-for-woocommerce');
            ?>
		</label>
	</th>
	<td>
		<input
				name="<?php echo esc_attr($args['id_and_name']); ?>"
				id="<?php echo esc_attr($args['id_and_name']); ?>"
				type="text"
				value="<?php echo esc_attr(!empty($args['value']) ? $args['value'] : ''); ?>" />
		<p class="description">
            <?php
            echo wp_kses(
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                __($args['desc'], 'colissimo-shipping-methods-for-woocommerce'),
                Helper::KSES_LINK
            );
            ?>
		</p>
	</td>
</tr>

