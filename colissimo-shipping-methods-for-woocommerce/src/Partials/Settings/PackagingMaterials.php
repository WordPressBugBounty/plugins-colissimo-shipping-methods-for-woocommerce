<?php
defined('ABSPATH') || die('Restricted Access');
?>
</tbody>
</table>
<div class="forminp-lpc_products">
    <?php
    echo wp_kses_post(
        sprintf(
            // translators: %s is a link to the Colissimo Box / Labels and packagings page.
            __(
                'For your postage needs, order your labels in rolls or boxes directly from your dedicated space %s, and enjoy the best prices and quality standards on the market.',
                'colissimo-shipping-methods-for-woocommerce'
            ),
            '<a href="' . esc_url($args['url']) . '" target="_blank">' . esc_html__('Colissimo Box / Labels and packagings', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
        )
    );
    ?>
</div>
<table class="form-table">
	<tbody>
