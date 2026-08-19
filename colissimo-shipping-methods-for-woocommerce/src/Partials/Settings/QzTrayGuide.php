<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');

$downloadLink    = '<a href="' . esc_url($args['downloadUrl']) . '" target="_blank" rel="noopener noreferrer">qz.io/download</a>';
$certificateLink = '<a href="' . esc_url($args['certificateUrl']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($args['certificateUrl']) . '</a>';

$allowedTags = [
    'a' => [
        'href'   => [],
        'target' => [],
        'rel'    => [],
    ],
];
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
		<p><?php esc_html_e('Follow these steps once, on the computer connected to the thermal printer:', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
		<ol class="lpc_qz_guide">
			<li>
                <?php
                // translators: %s is a link to the QZ Tray download page.
                echo wp_kses(sprintf(__('Download and install the latest version of QZ Tray from %s.', 'colissimo-shipping-methods-for-woocommerce'), $downloadLink), $allowedTags);
                ?>
			</li>
			<li><?php esc_html_e('Launch QZ Tray on the computer connected to the thermal printer, and keep it running.', 'colissimo-shipping-methods-for-woocommerce'); ?></li>
			<li><?php esc_html_e('Reload this settings page: with an up-to-date QZ Tray, the connection is usually trusted automatically and your printers appear below.', 'colissimo-shipping-methods-for-woocommerce'); ?></li>
			<li>
                <?php
                // translators: %s is a link to the local QZ Tray address.
                echo wp_kses(sprintf(__('Only if the connection fails or a security warning appears: open %s, accept the warning to trust the QZ Tray certificate (Advanced, then Continue to localhost), then reload this page. Using Chrome or Safari avoids this step in most cases.', 'colissimo-shipping-methods-for-woocommerce'), $certificateLink), $allowedTags);
                ?>
			</li>
			<li><?php esc_html_e('Select your thermal printer in the field below and save.', 'colissimo-shipping-methods-for-woocommerce'); ?></li>
		</ol>
	</td>
</tr>
