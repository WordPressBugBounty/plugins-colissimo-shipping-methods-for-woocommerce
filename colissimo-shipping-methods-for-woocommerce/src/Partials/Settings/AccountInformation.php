<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label>
            <?php
            // Dynamic option
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            esc_html_e($args['title'], 'colissimo-shipping-methods-for-woocommerce');
            echo wp_kses(
                Helper::tooltip(
                    __('Services enabled on your Colissimo contract', 'colissimo-shipping-methods-for-woocommerce')
                ),
                Helper::KSES_TOOLTIP
            );
            ?>
		</label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr(sanitize_title($args['type'])); ?>">
		<ul>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Contract type:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value"><?php echo esc_html($args['contractType']); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Out-of-home contract type:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value"><?php echo esc_html($args['outOfHomeContract']); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Pickup neighbor-relay option:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value">
					<?php
                    // Dynamic contract information
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    esc_html_e($args['pickupNeighborRelay'], 'colissimo-shipping-methods-for-woocommerce');
                    ?>
				</span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Mimosa option:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value">
					<?php
                    // Dynamic contract information
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    esc_html_e($args['mimosa'], 'colissimo-shipping-methods-for-woocommerce');
                    ?>
				</span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Secured shipping option:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value">
					<?php
                    // Dynamic contract information
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    esc_html_e($args['securedShipping'], 'colissimo-shipping-methods-for-woocommerce');
                    ?>
				</span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Hazardous materials feature:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value">
					<?php
                    // Dynamic contract information
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    if (empty($args['hazmatCategories'])) {
                        esc_html_e('Deactivated', 'colissimo-shipping-methods-for-woocommerce');
                    } else {
                        esc_html_e('Activated', 'colissimo-shipping-methods-for-woocommerce');
                    }
                    ?>
				</span>
			</li>
            <?php if (!empty($args['hazmatCategories'])) { ?>
				<li>
					<ul class="lpc_hazmat_list">
                        <?php
                        foreach ($args['hazmatCategories'] as $category) {
                            echo '<li>';
                            // Cannot call __() in class constants
                            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                            esc_html_e($category['label'], 'colissimo-shipping-methods-for-woocommerce');
                            echo ' : ';
                            if ($category['active']) {
                                esc_html_e('Activated', 'colissimo-shipping-methods-for-woocommerce');
                            } else {
                                esc_html_e('Deactivated', 'colissimo-shipping-methods-for-woocommerce');
                            }
                            echo '</li>';
                        }
                        ?>
					</ul>
				</li>
            <?php } ?>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Estimated shipping date option:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value">
					<?php
                    // Dynamic contract information
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    esc_html_e($args['estimatedShippingDate'], 'colissimo-shipping-methods-for-woocommerce');
                    ?>
				</span>
			</li>
            <?php if ('Activated' === $args['estimatedShippingDate'] && !empty($args['estimatedShippingDateDepotList'])) { ?>
				<li>
					<span class="colissimo-account-information-label">
						<?php esc_html_e('Your Colissimo deposit places:', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</span>
					<ul class="lpc_depot_list">
                        <?php
                        foreach ($args['estimatedShippingDateDepotList'] as $depot) {
                            echo '<li>' . esc_html($depot['codeRegate']) . ' - ' . esc_html($depot['libellepfc']) . '</li>';
                        }
                        ?>
					</ul>
				</li>
            <?php } ?>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Secured return option:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value">
					<?php
                    // Dynamic contract information
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    esc_html_e($args['securedReturn'], 'colissimo-shipping-methods-for-woocommerce');
                    ?>
				</span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Return in mailbox option:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value">
					<?php
                    // Dynamic contract information
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    esc_html_e($args['returnMailbox'], 'colissimo-shipping-methods-for-woocommerce');
                    ?>
				</span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Return in post office option:', 'colissimo-shipping-methods-for-woocommerce'); ?></span>
				<span class="colissimo-account-information-value">
					<?php
                    // Dynamic contract information
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    esc_html_e($args['returnPostOffice'], 'colissimo-shipping-methods-for-woocommerce');
                    ?>
				</span>
			</li>
		</ul>
	</td>
</tr>
