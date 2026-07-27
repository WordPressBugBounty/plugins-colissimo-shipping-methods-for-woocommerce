<?php

defined('ABSPATH') || die('Restricted Access');

?>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
<script src="<?php echo esc_url($args['js']); ?>"></script>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
<link rel="stylesheet" href="<?php echo esc_url($args['css']); ?>" />
<input type="hidden" id="lpc_return_label_url" value="<?php echo esc_url($args['accountUrl'], null, 'javascript'); ?>" />
<a href="#" class="button wp-element-button" id="lpc_return_products">
    <?php esc_html_e('Return products', 'colissimo-shipping-methods-for-woocommerce'); ?>
</a>
