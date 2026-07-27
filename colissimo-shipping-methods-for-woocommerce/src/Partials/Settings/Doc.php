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
		<button id="lpc_doc_download" class="button">
            <?php esc_html_e('Download FR', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</button>
		<button id="lpc_doc_EN_download" class="button">
            <?php esc_html_e('Download EN', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</button>
		<input type="hidden" id="lpc_doc_url" value="<?php echo esc_url($args['downloadUrl']); ?>" />
		<input type="hidden" id="lpc_doc_EN_url" value="<?php echo esc_url($args['downloadUrlEN']); ?>" />
	</td>
</tr>
