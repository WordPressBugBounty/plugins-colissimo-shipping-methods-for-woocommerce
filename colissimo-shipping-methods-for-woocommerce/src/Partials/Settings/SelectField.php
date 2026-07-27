<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

$id_and_name     = $args['id_and_name'];
$label           = $args['label'];
$multiple        = empty($args['multiple']) ? '' : 'multiple';
$selected_values = $args['selected_values'] ?: [];
$values          = $args['values'];
$tips            = empty($args['tips']) ? '' : $args['tips'];
$rowClass        = $args['row_class'] ?? '';
?>
<tr class="<?php echo esc_attr($rowClass); ?>">
	<th scope="row">
		<label for="<?php echo esc_attr($id_and_name); ?>">
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($label, 'colissimo-shipping-methods-for-woocommerce');
            if (!empty($tips)) {
                echo wp_kses(wc_help_tip($tips), Helper::KSES_WC_TOOLTIP);
            } ?>
		</label>
	</th>
	<td>
		<select <?php echo esc_attr($multiple); ?>
				name="<?php echo esc_attr($id_and_name . ('multiple' === $multiple ? '[]' : '')); ?>"
				id="<?php echo esc_attr($id_and_name); ?>"
				style="height:100%;">
            <?php
            if (!empty($args['optgroup'])) {
                foreach ($values as $oneGroup) { ?>
					<optgroup label="<?php echo esc_attr($oneGroup['label']); ?>">
                        <?php foreach ($oneGroup['values'] as $name => $label) { ?>
							<option value="<?php echo esc_attr($name); ?>"
                                <?php selected(('multiple' === $multiple && in_array($name, $selected_values))
                                               || (('' === $multiple && $name === $selected_values))); ?>>
                                <?php echo esc_attr($label) . ' (' . esc_attr($name) . ')'; ?>
							</option>
                        <?php } ?>
					</optgroup>
                <?php }
            } else {
                foreach ($values as $name => $label) { ?>
					<option value="<?php echo esc_attr($name); ?>"
                        <?php selected(('multiple' === $multiple && in_array($name, $selected_values))
                                       || (('' === $multiple && $name === $selected_values))); ?>><?php echo esc_attr($label); ?>
					</option>
                <?php }
            } ?>
		</select>
	</td>
</tr>
