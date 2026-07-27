<?php

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<tr>
	<th scope="row">
		<label>
            <?php
            esc_html_e('Set secured code during delivery', 'colissimo-shipping-methods-for-woocommerce');
            echo wp_kses(
                wc_help_tip(
                    __(
                        'To benefit from the secured code during delivery, you must contact your Colissimo advisor and they will activate it for you. This option will be visible in your Colissimo Box enterprise space.',
                        'colissimo-shipping-methods-for-woocommerce'
                    )
                ),
                Helper::KSES_WC_TOOLTIP
            );
            ?>
		</label>
	</th>
	<td>
		<input type="checkbox" disabled="disabled" <?php checked($args['block_code']); ?> />
        <?php if (!$args['block_code']) { ?>
			<style>
				.wc-settings-row-lpc_domicileas_block_code_min, .wc-settings-row-lpc_domicileas_block_code_max{
					display: none;
				}
			</style>
        <?php } ?>
	</td>
</tr>
