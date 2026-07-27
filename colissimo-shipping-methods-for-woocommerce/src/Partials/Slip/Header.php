<?php

defined('ABSPATH') || die('Restricted Access');

?>
<div class="lpc_slip_creation_header">
	<button type="button" id="colissimo_action_bordereau_selected" class="page-title-action">
        <?php esc_html_e('Generate with the selected parcels', 'colissimo-shipping-methods-for-woocommerce'); ?>
	</button>

	<a id="colissimo_action_bordereau_day" href="<?php echo esc_url($args['generateUrl']); ?>" class="page-title-action">
        <?php esc_html_e('Generate end of period slip', 'colissimo-shipping-methods-for-woocommerce'); ?>
	</a>
</div>
