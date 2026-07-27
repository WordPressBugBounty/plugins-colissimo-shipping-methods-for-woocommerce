<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
$id_and_name     = $args['id_and_name'];
$label           = $args['label'];
$selected_values = $args['selected_values'] ?: [];
$values          = $args['values'];
$description     = empty($args['description']) ? '' : $args['description'];
?>
<tr valign="top">
	<th scope="row">
		<label for="<?php echo esc_attr($id_and_name); ?>">
            <?php
            // Dynamic field
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($label, 'colissimo-shipping-methods-for-woocommerce');
            echo wp_kses(
            // Dynamic field
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                Helper::tooltip(__($description, 'colissimo-shipping-methods-for-woocommerce')),
                Helper::KSES_TOOLTIP
            );
            ?>
		</label>
	</th>
	<td>
		<fieldset>
			<select style="width: auto; max-width: 10rem"
                <?php echo empty($args['multiple']) ? '' : 'multiple="multiple"'; ?>
				    id="<?php echo esc_attr($id_and_name); ?>"
				    class="lpc__shipping_rates__multiselect lpc__shipping_rates__shipping_class__select select2-hidden-accessible"
				    name="<?php echo esc_attr($id_and_name); ?>">
                <?php
                foreach ($args['values'] as $oneClass) {
                    echo '<option value="' . esc_attr($oneClass->term_id) . '" ' . selected(
                            isset($selected_values) && in_array(
                                $oneClass->term_id,
                                $selected_values
                            ),
                            true,
                            false
                        )
                         . '>' . esc_html($oneClass->name) . '</option>';
                }
                ?>
			</select>
		</fieldset>
	</td>
</tr>
