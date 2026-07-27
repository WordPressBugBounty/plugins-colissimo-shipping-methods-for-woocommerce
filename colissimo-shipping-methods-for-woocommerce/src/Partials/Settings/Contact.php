<?php
defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label>
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($args['title'], 'colissimo-shipping-methods-for-woocommerce');
            ?>
		</label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr($args['type']); ?>">
		<a class="button" href="mailto:<?php echo esc_attr($args['email']); ?>">
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($args['text'], 'colissimo-shipping-methods-for-woocommerce');
            ?>
		</a>
	</td>
</tr>

