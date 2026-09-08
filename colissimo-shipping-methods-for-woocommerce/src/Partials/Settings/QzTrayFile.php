<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr($args['id']); ?>_file"><?php echo esc_html($args['title']); ?></label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr($args['type']); ?>">
		<input
				type="file"
				class="lpc_qz_file"
				id="<?php echo esc_attr($args['id']); ?>_file"
				accept="<?php echo esc_attr($args['accept']); ?>"
				data-target="<?php echo esc_attr($args['id']); ?>">
		<!-- The file is read by the browser, its content is sent with the form -->
		<input type="hidden" name="<?php echo esc_attr($args['id']); ?>" id="<?php echo esc_attr($args['id']); ?>" value="">
		<p class="description" id="<?php echo esc_attr($args['id']); ?>_status">
            <?php
            if (!empty($args['isFilled'])) {
                esc_html_e('A file is already saved, choose a new one only to replace it.', 'colissimo-shipping-methods-for-woocommerce');
            } else {
                esc_html_e('No file saved yet.', 'colissimo-shipping-methods-for-woocommerce');
            }
            ?>
		</p>
        <?php if (!empty($args['desc'])) : ?>
			<p class="description"><?php echo esc_html($args['desc']); ?></p>
        <?php endif; ?>
	</td>
</tr>
