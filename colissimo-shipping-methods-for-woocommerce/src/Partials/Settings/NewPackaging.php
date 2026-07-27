<?php
// WooCommerce is loaded, using their buttons for better accuracy
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

defined('ABSPATH') || die('Restricted Access');
?>
<form>
	<div id="lpc_new_packaging_options">
		<div class="lpc_new_packaging_row">
			<div id="lpc_new_packaging_option_name" class="lpc_new_packaging_option">
				<label class="lpc_new_packaging_option_label" for="lpc_new_packaging_option_name_input">
                    <?php esc_html_e('Name', 'colissimo-shipping-methods-for-woocommerce'); ?>*
				</label>
				<div class="lpc_new_packaging_option_field">
					<input id="lpc_new_packaging_option_name_input" type="text" />
				</div>
			</div>
		</div>

		<div class="lpc_new_packaging_row">
			<div id="lpc_new_packaging_option_packagingweight" class="lpc_new_packaging_option">
				<label class="lpc_new_packaging_option_label" for="lpc_new_packaging_option_packagingweight_input">
                    <?php // translators: %s is the weight unit (e.g. kg). ?>
                    <?php echo esc_html(sprintf(__('Packaging weight (%s)', 'colissimo-shipping-methods-for-woocommerce'), $args['weightUnit'])); ?>*
				</label>
				<div class="lpc_new_packaging_option_field">
					<input id="lpc_new_packaging_option_packaging_weight_input"
					       type="number"
					       step="<?php echo esc_attr('kg' === $args['weightUnit'] ? '.001' : '1'); ?>"
					       min="0" />
				</div>
			</div>
			<div id="lpc_new_packaging_option_extra_cost" class="lpc_new_packaging_option">
				<label class="lpc_new_packaging_option_label" for="lpc_new_packaging_option_extra_cost_input">
                    <?php esc_html_e('Extra cost', 'colissimo-shipping-methods-for-woocommerce'); ?>
				</label>
				<div class="lpc_new_packaging_option_field">
					<input id="lpc_new_packaging_option_extra_cost_input"
					       type="number"
					       step="any"
					       min="0" />
				</div>
			</div>
		</div>

		<div class="lpc_new_packaging_row">
			<div id="lpc_new_packaging_option_length" class="lpc_new_packaging_option">
				<label class="lpc_new_packaging_option_label" for="lpc_new_packaging_option_length_input">
                    <?php echo esc_html(__('Length', 'colissimo-shipping-methods-for-woocommerce') . ' (cm)'); ?>*
				</label>
				<div class="lpc_new_packaging_option_field">
					<input id="lpc_new_packaging_option_length_input"
					       type="number"
					       step="1"
					       min="1" />
				</div>
			</div>
			<div id="lpc_new_packaging_option_width" class="lpc_new_packaging_option">
				<label class="lpc_new_packaging_option_label" for="lpc_new_packaging_option_width_input">
                    <?php echo esc_html(__('Width', 'colissimo-shipping-methods-for-woocommerce') . ' (cm)'); ?>*
				</label>
				<div class="lpc_new_packaging_option_field">
					<input id="lpc_new_packaging_option_width_input"
					       type="number"
					       step="1"
					       min="1" />
				</div>
			</div>
			<div id="lpc_new_packaging_option_depth" class="lpc_new_packaging_option">
				<label class="lpc_new_packaging_option_label" for="lpc_new_packaging_option_depth_input">
                    <?php echo esc_html(__('Depth', 'colissimo-shipping-methods-for-woocommerce') . ' (cm)'); ?>*
				</label>
				<div class="lpc_new_packaging_option_field">
					<input id="lpc_new_packaging_option_depth_input"
					       type="number"
					       step="1"
					       min="1" />
				</div>
			</div>
		</div>

		<div class="lpc_new_packaging_row">
			<div id="lpc_new_packaging_option_max_products" class="lpc_new_packaging_option">
				<label class="lpc_new_packaging_option_label" for="lpc_new_packaging_option_max_products_input">
                    <?php esc_html_e('Max nb of products', 'colissimo-shipping-methods-for-woocommerce'); ?>
				</label>
				<div class="lpc_new_packaging_option_field">
					<input id="lpc_new_packaging_option_max_products_input" type="number" step="1" min="0" />
				</div>
			</div>

			<div id="lpc_new_packaging_option_max_weight" class="lpc_new_packaging_option">
				<label class="lpc_new_packaging_option_label" for="lpc_new_packaging_option_max_weight_input">
                    <?php // translators: %s is the weight unit (e.g. kg). ?>
                    <?php echo esc_html(sprintf(__('Maximum weight (%s)', 'colissimo-shipping-methods-for-woocommerce'), $args['weightUnit'])); ?>
				</label>
				<div class="lpc_new_packaging_option_field">
					<input id="lpc_new_packaging_option_max_weight_input"
					       type="number"
					       step="<?php echo esc_attr('kg' === $args['weightUnit'] ? '.001' : '1'); ?>"
					       min="0" />
				</div>
			</div>
		</div>

		<div id="lpc_new_packaging_buttons">
			<button type="button" class="button" id="lpc_new_packaging_cancellation">
                <?php esc_html_e('Cancel', 'woocommerce'); ?>
			</button>
			<button type="button" class="button button-primary" id="lpc_new_packaging_validation">
                <?php esc_html_e('Save', 'woocommerce'); ?>
			</button>
		</div>

		<input type="hidden" id="lpc_new_packaging_identifier" value="" />
	</div>
</form>
