<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
$i = $args['i'];
$oneRelay = $args['oneRelay'];
?>

<div class="lpc_layer_relay"
     id="lpc_layer_relay_<?php echo esc_attr($i); ?>"
     data-relayindex="<?php echo esc_attr($i); ?>"
     data-lpc-relay-id="<?php echo esc_attr($oneRelay['identifiant']); ?>"
     data-lpc-relay-country_code="<?php echo esc_attr($oneRelay['codePays']); ?>"
     data-lpc-relay-latitude="<?php echo esc_attr($oneRelay['coordGeolocalisationLatitude']); ?>"
     data-lpc-relay-longitude="<?php echo esc_attr($oneRelay['coordGeolocalisationLongitude']); ?>">
	<div class="lpc_pickup_info_distance">
		<img class="lpc_pickup_marker" src="<?php echo esc_url(Helper::getImageUrl('map_marker.png')); ?>">
		<span class="lpc_pickup_info_distance_txt">
			<?php // translators: %d is the distance to the relay in meters ?>
            <?php echo esc_html(sprintf(__('AT %dm', 'colissimo-shipping-methods-for-woocommerce'), $oneRelay['distanceEnMetre'])); ?>
		</span>
	</div>
	<div class="lpc_layer_relay_name"><?php echo esc_html($oneRelay['nom']); ?></div>
	<div class="lpc_layer_relay_address">
		<span class="lpc_layer_relay_type"><?php echo esc_html($oneRelay['typeDePoint']); ?></span>
		<span class="lpc_layer_relay_id"><?php echo esc_html($oneRelay['identifiant']); ?></span>
		<span class="lpc_layer_relay_address_street"><?php echo esc_html($oneRelay['adresse1']); ?></span>
		<span class="lpc_layer_relay_address_zipcode"><?php echo esc_html($oneRelay['codePostal']); ?></span>
		<span class="lpc_layer_relay_address_city"><?php echo esc_html($oneRelay['localite']); ?></span>
		<span class="lpc_layer_relay_address_country"><?php echo esc_html(empty($oneRelay['libellePays']) ? $oneRelay['codePays'] : $oneRelay['libellePays']); ?></span>
		<span class="lpc_layer_relay_latitude"><?php echo esc_html($oneRelay['coordGeolocalisationLatitude']); ?></span>
		<span class="lpc_layer_relay_longitude"><?php echo esc_html($oneRelay['coordGeolocalisationLongitude']); ?></span>
		<span class="lpc_layer_relay_distance_value"><?php echo esc_html($oneRelay['distanceEnMetre']); ?></span>
		<span class="lpc_layer_relay_hour_monday"><?php echo esc_html($oneRelay['horairesOuvertureLundi']); ?></span>
		<span class="lpc_layer_relay_hour_tuesday"><?php echo esc_html($oneRelay['horairesOuvertureMardi']); ?></span>
		<span class="lpc_layer_relay_hour_wednesday"><?php echo esc_html($oneRelay['horairesOuvertureMercredi']); ?></span>
		<span class="lpc_layer_relay_hour_thursday"><?php echo esc_html($oneRelay['horairesOuvertureJeudi']); ?></span>
		<span class="lpc_layer_relay_hour_friday"><?php echo esc_html($oneRelay['horairesOuvertureVendredi']); ?></span>
		<span class="lpc_layer_relay_hour_saturday"><?php echo esc_html($oneRelay['horairesOuvertureSamedi']); ?></span>
		<span class="lpc_layer_relay_hour_sunday"><?php echo esc_html($oneRelay['horairesOuvertureDimanche']); ?></span>

		<div class="lpc_layer_relay_schedule">
            <?php
            Helper::renderPartial(
                'Pickup/PickupHours.php',
                [
                    'openingDays' => $args['openingDays'],
                    'relay'       => $oneRelay,
                ]
            );
            ?>
		</div>
	</div>
	<div class="lpc_layer_relay_display_hours">
		<div class="lpc_layer_relay_hours_header">
			<div class="lpc_layer_relay_hours_icon_hour"></div>
			<div class="lpc_layer_relay_hours_title"><?php esc_html_e('Opening hours', 'colissimo-shipping-methods-for-woocommerce'); ?></div>
			<div class="lpc_layer_relay_hours_icon lpc_layer_relay_hours_icon_down"></div>
		</div>
		<div class="lpc_layer_relay_hours_details" style="display: none;">
            <?php
            Helper::renderPartial(
                'Pickup/PickupHours.php',
                [
                    'openingDays' => $args['openingDays'],
                    'relay'       => $oneRelay,
                ]
            );
            ?>
		</div>
	</div>
	<div class="lpc_relay_choose_btn">
		<a class="lpc_show_relay_details"><?php esc_html_e('Display on map', 'colissimo-shipping-methods-for-woocommerce'); ?></a>
		<button class="lpc_relay_choose" type="button" data-relayindex="<?php echo esc_attr($i); ?>">
            <?php esc_html_e('Choose', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</button>
	</div>
</div>

<?php if (($i + 1) < $args['relaysNb']) { ?>
	<hr class="lpc_relay_separator">
<?php } ?>
