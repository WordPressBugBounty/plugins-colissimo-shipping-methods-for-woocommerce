<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
?>
<style>
	.lpc_tracking{
		max-width: 1000px;
		margin: auto;
	}

	.lpc_tracking_summary td, .lpc_tracking_events td, .lpc_tracking_steps td{
		padding: 10px;
	}
</style>

<div class="lpc_tracking">
	<div class="lpc_tracking_logo">
		<img src="<?php echo esc_html($args['logoUrl']); ?>" alt="Logo colissimo" style="margin: auto;" />
	</div>

	<h2 class="lpc_tracking_title">
        <?php esc_html_e('Tracking information for order', 'colissimo-shipping-methods-for-woocommerce'); ?>
		<b>#<?php echo esc_html($args['order']->get_order_number()); ?></b>
	</h2>
	<p class="lpc_tracking_method">
        <?php esc_html_e('Shipping method', 'colissimo-shipping-methods-for-woocommerce'); ?> :
		<b><?php echo esc_html($args['order']->get_shipping_method()); ?></b>
	</p>

    <?php
    $trackingNumber = $args['trackingInfo']['parcel']['parcelNumber'];
    ?>

	<hr class="lpc_tracking_separator" />

	<div class="lpc_tracking_summary">
		<table cellspacing="0">
			<thead>
				<tr>
					<th>
                        <?php esc_html_e('Tracking number', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</th>
					<th>
                        <?php esc_html_e('Status', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class="lpc_tracking_tracknumber">
                        <?php echo esc_html($trackingNumber); ?>
					</td>
					<td>
                        <?php echo esc_html($args['trackingInfo']['mainStatus']); ?>
					</td>
					<td>
						<a target="_blank"
						   href="https://www.laposte.fr/particulier/modification-livraison?code=<?php echo esc_attr($trackingNumber); ?>">
                            <?php esc_html_e('Change your shipping information and options', 'colissimo-shipping-methods-for-woocommerce'); ?>
						</a>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="lpc_tracking_message">
        <?php
        if (!empty($args['trackingInfo']['message']['message'])) {
            echo esc_html($args['trackingInfo']['message']['message']);
        }
        ?>
	</div>

	<h3>
        <?php esc_html_e('Status history', 'colissimo-shipping-methods-for-woocommerce'); ?>
	</h3>
	<div class="lpc_tracking_events">
		<table cellspacing="0">
			<thead>
				<tr>
					<th>
                        <?php esc_html_e('Status Date', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</th>
					<th>
                        <?php esc_html_e('Status', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</th>
				</tr>
			</thead>
			<tbody>
                <?php
                foreach ($args['trackingInfo']['parcel']['event'] as $event) {
                    ?>
					<tr>
						<td>
                            <?php
                            $date = new DateTime($event['date']);
                            $date = $date->format('d/m/Y');
                            echo esc_html($date);
                            ?>
						</td>
						<td>
                            <?php echo esc_html($event['labelLong']); ?>
						</td>
					</tr>
                <?php } ?>
			</tbody>
		</table>
	</div>
	<h3>
        <?php esc_html_e('Timeline', 'colissimo-shipping-methods-for-woocommerce'); ?>
	</h3>
	<div class="lpc_tracking_steps">
		<table cellspacing="0">
			<thead>
				<tr>
					<th>
                        <?php esc_html_e('Step number', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</th>
					<th>
                        <?php esc_html_e('Status', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</th>
				</tr>
			</thead>
			<tbody>
                <?php
                $stepNumber = 0;
                foreach ($args['trackingInfo']['parcel']['step'] as $step) {
                    if (empty($step['labelShort']) && empty($step['labelLong'])) {
                        continue;
                    }

                    $stepNumber ++;
                    $stepStatus = 'STEP_STATUS_INACTIVE' === $step['status'] ? 'lpc__timeline__inactive' : 'lpc__timeline__active';
                    ?>
					<tr class="<?php echo esc_attr($stepStatus); ?>">
						<td>
                            <?php echo esc_html($stepNumber); ?>
						</td>
						<td>
                            <?php echo esc_html(empty($step['labelLong']) ? $step['labelShort'] : $step['labelLong']); ?>
						</td>
					</tr>
                <?php } ?>
			</tbody>
		</table>
	</div>
</div>
