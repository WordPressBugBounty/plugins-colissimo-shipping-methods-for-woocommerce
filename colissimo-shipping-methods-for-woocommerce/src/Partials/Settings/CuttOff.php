<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
?>
<tr class="<?php use Colissimo\Helpers\Helper;

echo esc_attr($args['row_class'] ?? ''); ?>">
	<th scope="row">
		<label>
            <?php
            esc_html_e('Cut Off hours', 'colissimo-shipping-methods-for-woocommerce');
            wp_kses(
                Helper::tooltip(
                    __('Set the daily cut-off time for order processing. Orders placed after this time will be shipped the next business day.',
                       'colissimo-shipping-methods-for-woocommerce')
                ),
                Helper::KSES_TOOLTIP
            );
            ?>
		</label>
	</th>
	<td>
		<template id="lpc_delivery_date_exception_template">
			<div class="exception-item">
				<input type="date" class="exception-date" aria-label="Exception date">
				<select class="exception-hour" aria-label="Exception cutt off hour">
                    <?php
                    foreach ($args['hours'] as $value => $time) {
                        echo '<option value="' . esc_attr($value) . '">' . esc_html($time) . '</option>';
                    }
                    ?>
				</select>
				<button class="remove-btn">×</button>
			</div>
		</template>

		<div class="lpc_cuttoff_container">
			<div class="lpc_cuttoff_content-wrapper">
				<!-- Weekly Schedule -->
				<div>
					<h2><?php esc_html_e('Weekly Schedule', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
					<div class="weekly-schedule">
						<table id="weeklyTable">
							<thead>
								<tr>
									<th><?php esc_html_e('Day', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
									<th><?php esc_html_e('Cutt Off times for orders processing', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
									<th><?php esc_html_e('Preparation delay (days)', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
								</tr>
							</thead>
							<tbody>
                                <?php foreach ($args['days'] as $day) { ?>
									<tr data-day="<?php echo esc_attr($day); ?>">
										<td>
                                            <?php
                                            // Dynamic weekdays to avoid code duplication
                                            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                                            esc_html_e($day, 'colissimo-shipping-methods-for-woocommerce');
                                            ?>
										</td>
										<td>
											<select class="day-select">
                                                <?php
                                                foreach ($args['hours'] as $value => $time) {
                                                    echo '<option value="' . esc_attr($value) . '">' . esc_html($time) . '</option>';
                                                }
                                                ?>
											</select>
										</td>
										<td>
											<input type="number" step="1" min="0" class="preparation-delay" />
										</td>
									</tr>
                                <?php } ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Exceptions -->
				<div>
					<h2><?php esc_html_e('Exceptions', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
					<div class="exceptions-section">
						<button class="add-exception-btn" id="addExceptionBtn">
							<span class="plus-icon">+</span>
                            <?php esc_html_e('Add Exception', 'colissimo-shipping-methods-for-woocommerce'); ?>
						</button>
						<div class="exceptions-list" id="exceptionsList"></div>
					</div>
				</div>
			</div>

			<input type="hidden" id="cuttOffInitialValues" value="<?php echo esc_attr($args['values']); ?>" />
			<input type="hidden" id="scheduleData" name="lpc_delivery_date_cuttoff_times" />
		</div>
	</td>
</tr>
