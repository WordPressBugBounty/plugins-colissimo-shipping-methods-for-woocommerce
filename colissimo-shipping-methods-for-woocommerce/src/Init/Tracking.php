<?php

namespace Colissimo\Init;

defined('ABSPATH') || die('Restricted Access');

use Colissimo\Classes\Label\TrackingPage;
use Colissimo\Classes\Shipping\ShippingMethod;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use WP;
use WC_Order;

class Tracking {
    public function __construct() {
        // Whitelist our URL parameter
        add_filter(
            'query_vars',
            function (array $query_vars) {
                $query_vars[] = TrackingPage::QUERY_VAR;

                return $query_vars;
            }
        );

        // If our parameter is in the URL, show our tracking page
        add_action(
            'parse_request',
            function (WP $wp) {
                if (!empty($wp->query_vars[TrackingPage::QUERY_VAR])) {
                    $trackingPage = new TrackingPage();
                    $trackingPage->control($wp);
                }
            }
        );

        add_filter('woocommerce_account_orders_columns', [$this, 'addTrackingLinkTitle'], 10, 1);
        add_action('woocommerce_my_account_my_orders_column_order-tracking', [$this, 'addTrackingLinkData'], 10, 1);
        add_action('woocommerce_order_details_after_order_table', [$this, 'addTrackingInformation'], 10, 1);
    }

    public function addTrackingLinkTitle(array $columns): array {
        $addTrackingColumn = 'no' !== Helper::get_option('lpc_show_tracking_column_front', 'no');

        $newColumns = [];
        foreach ($columns as $key => $column) {
            if ('order-actions' === $key && $addTrackingColumn) {
                $newColumns['order-tracking'] = __('Colissimo order tracking', 'colissimo-shipping-methods-for-woocommerce');
            }
            $newColumns[$key] = $column;
        }

        return $newColumns;
    }

    public function addTrackingLinkData($order): void {
        $orderId         = $order->get_id();
        $outwardLabelDb  = Register::get('outwardLabelDb');
        $trackingNumbers = $outwardLabelDb->getOrderLabels($orderId);

        // No tracking number available yet, or Colissimo not used
        if (empty($trackingNumbers)) {
            echo '-';

            return;
        }

        $isWebsitePage = 'website_tracking_page' === Helper::get_option('lpc_email_tracking_link', 'website_tracking_page');
        $output        = [];
        foreach ($trackingNumbers as $oneTrackingNumber) {
            if ($isWebsitePage) {
                $unifiedTrackingApi = Register::get('unifiedTrackingApi');
                $trackingLink       = get_site_url() . $unifiedTrackingApi->getTrackingPageUrlForOrder($orderId, $oneTrackingNumber);
            } else {
                $trackingLink = str_replace(
                    '{lpc_tracking_number}',
                    $oneTrackingNumber,
                    ShippingMethod::LPC_LAPOSTE_TRACKING_URL
                );
            }

            $output[] = '<a target="_blank" href="' . esc_url($trackingLink) . '">' . esc_html($oneTrackingNumber) . '</a>';
        }

        echo wp_kses(
            implode('<br />', $output),
            Helper::KSES_LINK
        );
    }

    public function addTrackingInformation(WC_Order $order) {
        $orderId        = $order->get_id();
        $outwardLabelDb = Register::get('outwardLabelDb');
        $labels         = $outwardLabelDb->getLabelsInfosForOrdersId([$orderId]);
        if (empty($labels)) {
            return;
        }

        $isWebsitePage = 'website_tracking_page' === Helper::get_option('lpc_email_tracking_link', 'website_tracking_page');
        ?>
		<h2 class="woocommerce-column__title"><?php esc_html_e('Colissimo tracking', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
		<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
			<thead>
				<tr>
                    <?php if (count($labels) > 1) { ?>
						<th class="woocommerce-table__product-name parcel-number"><?php esc_html_e('Parcel', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
                    <?php } ?>
					<th class="woocommerce-table__product-table parcel-tracking"><?php esc_html_e('Tracking', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
				</tr>
			</thead>
			<tbody>
                <?php
                $i                  = 0;
                $unifiedTrackingApi = Register::get('unifiedTrackingApi');
                foreach ($labels as $oneLabel) {
                    $i ++;
                    ?>
					<tr class="woocommerce-table__line-item order_item">
                        <?php if (count($labels) > 1) { ?>
							<td class="woocommerce-table__parcel-number parcel-number">
                                <?php
                                // translators: %s is the parcel number.
                                echo esc_html(sprintf(__('Parcel n°%s', 'colissimo-shipping-methods-for-woocommerce'), $i)); ?>
							</td>
                        <?php } ?>
						<td class="woocommerce-table__parcel-tracking parcel-tracking">
                            <?php
                            if ($isWebsitePage) {
                                $trackingLink = get_site_url() . $unifiedTrackingApi->getTrackingPageUrlForOrder($orderId, $oneLabel->tracking_number);
                            } else {
                                $trackingLink = str_replace('{lpc_tracking_number}', $oneLabel->tracking_number, ShippingMethod::LPC_LAPOSTE_TRACKING_URL);
                            }
                            ?>
							<a target="_blank" href="<?php echo esc_url($trackingLink); ?>"><?php echo esc_html($oneLabel->tracking_number); ?></a>
						</td>
					</tr>
                <?php } ?>
			</tbody>
		</table>
        <?php
    }
}
