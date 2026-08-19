<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
$value = Helper::get_option($args['field_name'] ?? $args['id'], $args['default'] ?? '');
?>
<tr class="<?php echo esc_attr($args['row_class'] ?? ''); ?>">
	<th scope="row">
		<label for="<?php echo esc_attr($args['id']); ?>">
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($args['title'], 'colissimo-shipping-methods-for-woocommerce');

            if (!empty($args['desc']) && !empty($args['desc_tip'])) {
                // Dynamic option
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                echo wp_kses(wc_help_tip(__($args['desc'], 'colissimo-shipping-methods-for-woocommerce')), Helper::KSES_WC_TOOLTIP);
            }
            ?>
		</label>
	</th>
	<td>
        <?php
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        $placeHolder = empty($args['placeholder']) ? '' : __($args['placeholder'], 'colissimo-shipping-methods-for-woocommerce');
        ?>
		<input
				type="text"
				placeholder="<?php echo esc_attr($placeHolder); ?>"
				name="<?php echo esc_attr($args['id']); ?>"
				id="<?php echo esc_attr($args['id']); ?>"
				value="<?php echo esc_attr($value); ?>"
				class="<?php echo esc_attr($args['class'] ?? ''); ?>"
		/>
        <?php
        if (!empty($args['desc']) && empty($args['desc_tip'])) {
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            echo '<p class="description">' . esc_html__($args['desc'], 'colissimo-shipping-methods-for-woocommerce') . '</p>';
        }
        ?>
	</td>
</tr>
