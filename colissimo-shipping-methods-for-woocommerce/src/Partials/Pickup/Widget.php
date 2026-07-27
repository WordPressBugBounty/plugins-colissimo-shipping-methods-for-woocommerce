<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<?php if (!empty($args['widgetInfo']) && empty($args['gutenberg'])) { ?>
	<script type="text/javascript">
        window.lpc_widget_info = JSON.parse(<?php echo wp_json_encode($args['widgetInfo']); ?>);
	</script>
<?php } ?>

<?php $args['modal']->echo_modal(); ?>

<?php if ($args['showButton']) { ?>
	<div id="lpc_layer_error_message"></div>
    <?php
    if ($args['showInfo']) {
        Helper::renderPartial(
            'Pickup/PickupInformation.php',
            [
                'relay' => $args['currentRelay'],
            ]
        );
    }
    ?>
	<div id="lpc_layer_pickup_selection_button">
        <?php
        if (!empty($args['currentRelay'])) {
            $linkText = __('Change PickUp point', 'colissimo-shipping-methods-for-woocommerce');
        } else {
            $linkText = __('Choose PickUp point', 'colissimo-shipping-methods-for-woocommerce');
        }

        $gutenbergAttr = empty($args['gutenberg']) ? '0' : '1';
        if ('link' === $args['type']) {
            ?>
			<a id="lpc_pick_up_widget_show_map"
			   class="lpc_pick_up_widget_show_map"
			   data-lpc-isgutenberg="<?php echo esc_attr($gutenbergAttr); ?>">
                <?php echo esc_html($linkText); ?>
			</a>
        <?php } else { ?>
			<button type="button"
			        id="lpc_pick_up_widget_show_map"
			        class="lpc_pick_up_widget_show_map wp-element-button"
			        data-lpc-isgutenberg="<?php echo esc_attr($gutenbergAttr); ?>">
                <?php echo esc_html($linkText); ?>
			</button>
        <?php } ?>
	</div>
<?php } ?>
