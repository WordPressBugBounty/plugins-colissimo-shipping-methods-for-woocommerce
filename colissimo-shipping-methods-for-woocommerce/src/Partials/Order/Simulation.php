<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

defined('ABSPATH') || die('Restricted Access');

$countries = $args['countries'] ?? [];

require_once __DIR__ . DS . 'ListingTabs.php';

$deliveryModes = [
    'home'           => __('Home delivery without signature', 'colissimo-shipping-methods-for-woocommerce'),
    'home_signature' => __('Home delivery with signature', 'colissimo-shipping-methods-for-woocommerce'),
    'pickup_bpr'     => __('Pickup point in a post office', 'colissimo-shipping-methods-for-woocommerce'),
    'pickup_a2p'     => __('Pickup point (Others)', 'colissimo-shipping-methods-for-woocommerce'),
    'return'         => __('Return parcel', 'colissimo-shipping-methods-for-woocommerce'),
];

$options = [
    'NON_MECANISABLE'      => __('Non-machinable parcel', 'colissimo-shipping-methods-for-woocommerce'),
    'CONTRE_REMBOURSEMENT' => __('Cash on delivery', 'colissimo-shipping-methods-for-woocommerce'),
    'DELIVERY_DUTY_PAID'   => __('Delivery duty paid', 'colissimo-shipping-methods-for-woocommerce'),
    'FRANC_TAXES_DROITS'   => __('Free of taxes and duties', 'colissimo-shipping-methods-for-woocommerce'),
    'MATIERES_DANGEREUSES' => __('Dangerous goods', 'colissimo-shipping-methods-for-woocommerce'),
    'AVIS_RECEPTION'       => __('Recommendation level', 'colissimo-shipping-methods-for-woocommerce'),
    'PARTENAIRE_POSTAL'    => __('Postal partner', 'colissimo-shipping-methods-for-woocommerce'),
    'VALEUR_ASSUREE'       => __('Insured value', 'colissimo-shipping-methods-for-woocommerce'),
];

asort($options);
?>
<div class="wrap lpc-simulation">
	<h1><?php esc_html_e('Shipping cost simulation', 'colissimo-shipping-methods-for-woocommerce'); ?></h1>

	<form id="lpc-simulation-form">
		<div class="lpc-simulation-columns">
			<div class="lpc-simulation-column">
				<h2><?php esc_html_e('Recipient', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
				<p>
					<label for="recipientCountry"><?php esc_html_e('Country', 'colissimo-shipping-methods-for-woocommerce'); ?></label>
					<select id="recipientCountry" class="lpc-simulation-country">
                        <?php
                        foreach ($countries as $code => $name) {
                            echo '<option value="' . esc_attr($code) . '" ' . selected($code, 'FR', false) . '>' . esc_html($name) . '</option>';
                        }
                        ?>
					</select>
				</p>
				<p>
					<label for="recipientPostcode"><?php esc_html_e('Postcode', 'colissimo-shipping-methods-for-woocommerce'); ?></label>
					<input type="text" id="recipientPostcode" value="" placeholder="75001" />
				</p>
			</div>
			<div class="lpc-simulation-column">
				<h2><?php esc_html_e('Parcel', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
				<p>
					<label for="deliveryMode"><?php esc_html_e('Delivery method', 'colissimo-shipping-methods-for-woocommerce'); ?></label>
					<select id="deliveryMode">
                        <?php foreach ($deliveryModes as $value => $label) { ?>
							<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php } ?>
					</select>
				</p>
				<p>
					<label for="weight"><?php esc_html_e('Parcel weight (kg)', 'colissimo-shipping-methods-for-woocommerce'); ?></label>
					<input type="number" id="weight" min="0" step="0.01" value="" placeholder="1,5" />
				</p>
				<p>
					<label for="typeTarif"><?php esc_html_e('Rate type', 'colissimo-shipping-methods-for-woocommerce'); ?></label>
					<select id="typeTarif">
						<option value="0"><?php esc_html_e('General rate', 'colissimo-shipping-methods-for-woocommerce'); ?></option>
						<option value="1"><?php esc_html_e('Discounted rate', 'colissimo-shipping-methods-for-woocommerce'); ?></option>
					</select>
				</p>
			</div>
		</div>

		<div class="lpc-simulation-section">
			<h2><?php esc_html_e('Options', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
			<div id="lpc-simulation-options"></div>
			<button type="button" class="button" id="lpc-simulation-add-option">
                <?php esc_html_e('Add an option', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</button>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary" id="lpc-simulation-submit">
                <?php esc_html_e('Calculate the cost', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</button>
		</p>
	</form>

	<div id="lpc-simulation-result" class="lpc-simulation-result" style="display:none;"></div>

    <?php // Template cloned by simulation.js for each added option ?>
	<script type="text/template" id="lpc-simulation-option-template">
		<div class="lpc-option-row">
			<select class="lpc-option-code">
                <?php foreach ($options as $value => $label) { ?>
					<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php } ?>
			</select>
			<input type="number" class="lpc-option-insured-value" min="0" step="0.01"
			       placeholder="<?php echo esc_attr__('Insured amount (i.e: 300)', 'colissimo-shipping-methods-for-woocommerce'); ?>"
			       style="display:none;" />
			<select class="lpc-option-recommendation" style="display:none;">
				<option value="R1">R1</option>
				<option value="R2">R2</option>
				<option value="R3">R3</option>
			</select>
			<button type="button" class="button lpc-option-remove">
                <?php esc_html_e('Remove', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</button>
		</div>
	</script>
</div>
