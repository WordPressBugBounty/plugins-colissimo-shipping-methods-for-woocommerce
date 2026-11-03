<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label>
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($args['title'], 'colissimo-shipping-methods-for-woocommerce');

            if (!empty($args['desc'])) {
                // Dynamic option
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                echo wp_kses(wc_help_tip(__($args['desc'], 'colissimo-shipping-methods-for-woocommerce')), Helper::KSES_WC_TOOLTIP);
            }
            ?>
		</label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr($args['type']); ?>">
		<button type="button" class="button" id="lpc_check_db_button">
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($args['text'], 'colissimo-shipping-methods-for-woocommerce');
            ?>
		</button>
		<span class="spinner" id="lpc_check_db_spinner" style="float: none"></span>
		<p id="lpc_check_db_result"></p>
	</td>
</tr>
