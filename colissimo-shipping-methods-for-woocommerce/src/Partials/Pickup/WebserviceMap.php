<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<div id="lpc_layer_relays">
	<div class="content">
        <?php
        $classes    = '';
        $mobileIcon = 'dashicons-editor-ul';
        if ('list' === Helper::get_option('lpc_show_list_only_mobile')) {
            $classes    = 'lpc_mobile_display_none';
            $mobileIcon = 'dashicons-location-alt';
        }
        ?>
		<span id="lpc_layer_relay_switch_mobile">
			<span class="lpc_layer_relay_switch_mobile_icon dashicons <?php echo esc_attr($mobileIcon); ?>"></span>
		</span>
        <?php if (is_admin() && !empty($args['orderId'])) { ?>
			<input type="hidden" id="lpc_layer_order_id" value="<?php echo esc_attr($args['orderId']); ?>">
        <?php } ?>
		<div id="lpc_search_address">
			<input
					id="lpc_modal_relays_search_address"
					type="text"
					class="lpc_modal_relays_search_input"
					value="<?php echo esc_attr($args['ceAddress']); ?>"
					placeholder="<?php esc_attr_e('Address', 'colissimo-shipping-methods-for-woocommerce'); ?>">
			<div id="lpc_modal_address_details">
				<input
						type="text"
						id="lpc_modal_relays_search_zipcode"
						class="lpc_modal_relays_search_input"
						value="<?php echo esc_attr($args['ceZipCode']); ?>"
						placeholder="<?php esc_attr_e('Zipcode', 'colissimo-shipping-methods-for-woocommerce'); ?>">
				<input
						type="text"
						id="lpc_modal_relays_search_city"
						class="lpc_modal_relays_search_input"
						value="<?php echo esc_attr($args['ceTown']); ?>"
						placeholder="<?php esc_attr_e('City', 'colissimo-shipping-methods-for-woocommerce'); ?>">
				<input type="hidden" id="lpc_modal_relays_country_id" value="<?php echo esc_attr($args['ceCountryId']); ?>">
				<button id="lpc_layer_button_search" type="button" class="wp-element-button">
					<span id="lpc_layer_button_search_desktop"><?php esc_html_e('Search', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
					<span class="dashicons dashicons-search" id="lpc_layer_button_search_mobile"></span>
				</button>
			</div>
            <?php if ($args['maxRelayPoint'] < 20) { ?>
				<a href="#" id="lpc_modal_relays_display_more"><?php esc_html_e('Display more pickup points', 'colissimo-shipping-methods-for-woocommerce'); ?></a>
            <?php } ?>
		</div>

		<div id="lpc_left" class="<?php echo esc_attr($classes); ?>">
			<div id="lpc_map"></div>
		</div>

		<div id="lpc_right">
			<div class="blockUI" id="lpc_layer_relays_loader" style="display: none;"></div>
			<div id="lpc_layer_error_message" style="display: none;"></div>
			<div id="lpc_layer_list_relays"></div>
		</div>
	</div>
</div>
