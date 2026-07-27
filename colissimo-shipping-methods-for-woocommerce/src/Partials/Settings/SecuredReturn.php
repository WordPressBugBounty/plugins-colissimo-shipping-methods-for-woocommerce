<?php
defined('ABSPATH') || die('Restricted Access');
?>
<tr id="lpc_secured_return_container">
	<th scope="row">
		<label>
            <?php esc_html_e('Activate secured return', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</label>
	</th>
	<td>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e('Activate secured return', 'colissimo-shipping-methods-for-woocommerce'); ?></span></legend>
			<label for="lpc_secured_return">
				<input name="lpc_secured_return" id="lpc_secured_return" type="checkbox" value="1" <?php disabled(!$args['secured_return']) . checked($args['checked']); ?>>
			</label>
			<p class="description">
                <?php esc_html_e('If the secured return is enabled, only your customers will be able to generate return labels.', 'colissimo-shipping-methods-for-woocommerce'); ?>
				<br />
				<br />
                <?php
                esc_html_e(
                    'Generate a QR code that your clients can scan at a post office to print a label. This format is used to secure the return parcel deposit.',
                    'colissimo-shipping-methods-for-woocommerce'
                );
                ?>
				<br />
                <?php esc_html_e('Only active for return labels generated from the client\'s order page.', 'colissimo-shipping-methods-for-woocommerce'); ?>
				<br />
                <?php
                wp_kses(
                    printf(
                        // translators: %s is a link to activate the service in the Colissimo client space.
                        esc_html__('This option depends on the service activation in your Colissimo client space [%s]', 'colissimo-shipping-methods-for-woocommerce'),
                        '<a href="' . esc_url($args['services_url']) . '" target="_blank">' . esc_html__('activate the service', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
                    ),
                    [
                        'a' => [
                            'href' => [],
                            'target' => [],
                        ],
                    ]
                );
                ?>
			</p>
		</fieldset>
	</td>
</tr>
