<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
if (is_admin()) {
    $currentScreen = get_current_screen();
    if (!empty($currentScreen) && in_array($currentScreen->base, ['woocommerce_page_wc-orders', 'post'])) {
        if ('gmaps' === $args['mapType'] && !empty($args['apiKey'])) { ?>
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
			<script src="https://maps.googleapis.com/maps/api/js?loading=async&libraries=marker&key=<?php echo esc_attr($args['apiKey']); ?>" async defer></script>
            <?php
        }

        if ('leaflet' === $args['mapType']) {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
            echo '<link rel="stylesheet" href="' . esc_url(Helper::getCssUrl('pickup/leaflet.css')) . '" />';
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
            echo '<script src="' . esc_url(Helper::getJsUrl('pickup/leaflet.js')) . '"></script>';
        }
    }
}
?>

<div>
    <?php
    if ($args['showButton']) {
        if ($args['showInfo']) {
            Helper::renderPartial('Pickup/PickupInformation.php', ['relay' => $args['currentRelay']]);
        }
        ?>
		<div>
            <?php
            if (!empty($args['currentRelay'])) {
                $linkText = __('Change PickUp point', 'colissimo-shipping-methods-for-woocommerce');
            } else {
                $linkText = __('Choose PickUp point', 'colissimo-shipping-methods-for-woocommerce');
            }

            if ('link' === $args['type']) {
                ?>
				<a
						id="lpc_pick_up_web_service_show_map"
						class="lpc_pick_up_webservice_show_map"
						data-lpc-template="lpc_pick_up_web_service"
						data-lpc-callback="lpcInitMapWebService">
                    <?php echo esc_html($linkText); ?>
				</a>
                <?php
            } else {
                ?>
				<button
						type="button"
						id="lpc_pick_up_web_service_show_map"
						class="lpc_pick_up_webservice_show_map wp-element-button"
						data-lpc-template="lpc_pick_up_web_service"
						data-lpc-callback="lpcInitMapWebService">
                    <?php echo esc_html($linkText); ?>
				</button>
                <?php
            }
            ?>
		</div>
        <?php
    }
    $args['modal']->echo_modal();
    ?>
</div>
