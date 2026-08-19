<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
$relay = $args['relay'];
if (!empty($relay)) {
    $openingDays = [
        'Monday'    => 'horairesOuvertureLundi',
        'Tuesday'   => 'horairesOuvertureMardi',
        'Wednesday' => 'horairesOuvertureMercredi',
        'Thursday'  => 'horairesOuvertureJeudi',
        'Friday'    => 'horairesOuvertureVendredi',
        'Saturday'  => 'horairesOuvertureSamedi',
        'Sunday'    => 'horairesOuvertureDimanche',
    ];
    $openTime    = __('Opening hours', 'colissimo-shipping-methods-for-woocommerce') . '<br />';
    foreach ($openingDays as $day => $oneDay) {
        if (empty($relay[$oneDay]) || ' ' === $relay[$oneDay] || '00:00-00:00 00:00-00:00' === $relay[$oneDay]) {
            continue;
        }
        // Dynamic day to avoid code duplication
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        $openTime .= ucfirst(__($day, 'colissimo-shipping-methods-for-woocommerce')) . ' ' . str_replace(' 00:00-00:00', '', $relay[$oneDay]) . '<br />';
    }

    ?>
	<div id="lpc_pick_up_info" data-pickup-id="<?php echo esc_attr($relay['identifiant']); ?>">
		<div class="lpc_pickup_info_title"><?php esc_html_e('SELECTED RELAY', 'colissimo-shipping-methods-for-woocommerce'); ?></div>
		<div>
            <?php if (!empty($relay['distanceEnMetre'])) { ?>
				<div class="lpc_pickup_info_distance">
					<img class="lpc_pickup_marker" src="<?php echo esc_url(Helper::getImageUrl('map_marker.png')); ?>">
					<span class="lpc_pickup_info_distance_txt">
						<?php // translators: %d is the distance to the relay in meters ?>
                        <?php echo esc_html(sprintf(__('AT %dm', 'colissimo-shipping-methods-for-woocommerce'), $relay['distanceEnMetre'])); ?>
					</span>
				</div>
            <?php } ?>
			<div class="lpc_pickup_info_address">
				<div class="lpc_pickup_info_address_name">
                    <?php
                    echo esc_html($relay['nom']);
                    if (!empty($openTime)) {
                        ?>
						<div class="tooltip-box">
							<div class="tooltip-text">
                                <?php
                                echo wp_kses(
                                    $openTime,
                                    [
                                        'br' => [],
                                    ]
                                );
                                ?>
							</div>
						</div>
                    <?php } ?>
				</div>
				<div class="lpc_pickup_info_address_line"><?php echo esc_html($relay['adresse1']); ?></div>
				<div class="lpc_pickup_info_address_line"><?php echo esc_html($relay['codePostal']) . ' ' . esc_html($relay['localite']); ?></div>
				<div class="lpc_pickup_info_address_line"><?php echo esc_html(empty($relay['libellePays']) ? ($relay['codePays'] ?? '') : $relay['libellePays']); ?></div>
			</div>
		</div>
	</div>
<?php } else { ?>
	<div id="lpc_pick_up_info"></div>
<?php } ?>
