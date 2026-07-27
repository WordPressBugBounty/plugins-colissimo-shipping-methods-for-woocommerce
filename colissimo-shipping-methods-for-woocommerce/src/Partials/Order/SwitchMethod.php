<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Classes\Order\AffectMethod;
use Colissimo\Classes\Shipping\Relay;

defined('ABSPATH') || die('Restricted Access');
$shippingMethods = $args['lpc_shipping_methods'] ?? [];
$buttonText      = $args['button_text'] ?? __('Click here to ship this order with Colissimo', 'colissimo-shipping-methods-for-woocommerce');
?>

<div class="lpc_order_affect" id="<?php echo esc_attr($args['lpc_partial_name']); ?>">
	<script type="text/javascript">
        if (window.lpc_bind_order_affect !== undefined) {
            window.lpc_bind_order_affect();
        }
	</script>

	<button type="button" class="button button-primary lpc_order_affect_toggle_methods">
        <?php echo esc_html($buttonText); ?>
	</button>

	<div class="lpc_order_affect_available_methods" style="display: none">
        <?php if (empty($shippingMethods)) { ?>
			<span class="lpc_order_affect_error_message">
				<?php esc_html_e('No Colissimo shipping methods are available for this order', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</span>
            <?php
        }

        foreach ($shippingMethods as $oneMethodId => $oneMethod) {
            ?>
			<div class="lpc_order_affect_method">
				<label>
					<input type="radio" value="<?php echo esc_attr($oneMethodId); ?>" name="lpc_new_shipping_method">
                    <?php echo esc_html($oneMethod); ?>
				</label>
                <?php if (Relay::ID === $oneMethodId) { ?>
					<div class="lpc_order_affect_relay" style="display: none">
                        <?php
                        if ('widget' === $args['map_type']) {
                            $args['adminPickupWidget']->addWidget($args['order']);
                        } else {
                            $args['adminPickupWebService']->addWebserviceMap($args['order']);
                        }
                        ?>
						<div class="lpc_order_affect_relay_information_displayed"></div>
					</div>
                <?php } ?>
			</div>
        <?php } ?>
		<input type="hidden" name="lpc_order_affect_relay_informations" value="{}">
		<input type="hidden" name="lpc_order_affect_shipping_item_id" value="0">
		<div class="lpc_order_affect_error_message">
			<span style="display: none" class="lpc_order_affect_error_message_pickup">
				<?php esc_html_e('Please select a pick-up point', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</span>
			<span style="display: none" class="lpc_order_affect_error_message_method">
				<?php esc_html_e('Please select a shipping method', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</span>
		</div>

        <?php if (!empty($shippingMethods)) { ?>
			<button
					type="button"
					class="button button-primary lpc_order_affect_validate_method"
					data-nonce-param="<?php echo esc_attr(AffectMethod::NONCE_SWITCH_METHOD); ?>"
					data-nonce="<?php echo esc_attr(wp_create_nonce(AffectMethod::NONCE_NAME_SWITCH_METHOD)); ?>">
                <?php esc_html_e('Choose', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</button>
        <?php } ?>
	</div>
</div>
