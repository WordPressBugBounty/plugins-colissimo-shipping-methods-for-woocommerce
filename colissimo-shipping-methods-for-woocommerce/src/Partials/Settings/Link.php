<?php
defined('ABSPATH') || die('Restricted Access');
?>
<tr>
	<th scope="row" class="titledesc">
        <?php if (!empty($args['label'])) { ?>
			<label>
                <?php
                // Dynamic option
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                esc_html_e($args['label'], 'colissimo-shipping-methods-for-woocommerce');
                ?>
			</label>
        <?php } ?>
	</th>
	<td class="forminp forminp-<?php echo esc_attr(sanitize_title($args['type'])); ?>">
		<a class="<?php echo esc_attr($args['class']); ?>" href="<?php echo esc_url($args['url']); ?>" target="_blank">
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($args['text'], 'colissimo-shipping-methods-for-woocommerce');
            ?>
		</a>
        <?php if (!empty($args['urlServices'])) { ?>
			|
			<a class="<?php echo esc_attr($args['class']); ?>" href="<?php echo esc_url($args['urlServices']); ?>" target="_blank">
                <?php esc_html_e('Services settings', 'colissimo-shipping-methods-for-woocommerce'); ?></a>
        <?php } ?>
        <?php if (!empty($args['urlMaterials'])) { ?>
			|
			<a class="<?php echo esc_attr($args['class']); ?>" href="<?php echo esc_url($args['urlMaterials']); ?>" target="_blank">
                <?php esc_html_e('Labels and packagings', 'colissimo-shipping-methods-for-woocommerce'); ?></a>
        <?php } ?>
	</td>
</tr>
