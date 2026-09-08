<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr($args['id']); ?>"><?php echo esc_html($args['title']); ?></label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr($args['type']); ?>">
		<textarea
				name="<?php echo esc_attr($args['id']); ?>"
				id="<?php echo esc_attr($args['id']); ?>"
				style="width:100%;height:120px;font-family:monospace;"
				autocomplete="off"
				placeholder="-----BEGIN PRIVATE KEY-----"></textarea>
        <?php if (!empty($args['desc'])) : ?>
			<p class="description"><?php echo esc_html($args['desc']); ?></p>
        <?php endif; ?>
        <?php if (!empty($args['isFilled'])) : ?>
			<p class="description">
                <?php esc_html_e('A private key is already saved, leave this field empty to keep it.', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</p>
        <?php endif; ?>
	</td>
</tr>
