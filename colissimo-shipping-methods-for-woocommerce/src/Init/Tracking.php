<?php

namespace Colissimo\Init;

defined('ABSPATH') || die('Restricted Access');

use Colissimo\Api\CheckoutApi;
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
        add_filter('render_block_woocommerce/order-confirmation-summary', [$this, 'addDeliveryDateToConfirmationSummary'], 10, 1);
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
        if (is_order_received_page()) {
            return;
        }

        $orderId        = $order->get_id();
        $outwardLabelDb = Register::get('outwardLabelDb');
        $labels         = $outwardLabelDb->getLabelsInfosForOrdersId([$orderId]);
        if (empty($labels)) {
            return;
        }

        $isWebsitePage    = 'website_tracking_page' === Helper::get_option('lpc_email_tracking_link', 'website_tracking_page');
        $showDeliveryDate = 'yes' === Helper::get_option('lpc_display_shipping_date') && 'FR' === $order->get_shipping_country();

        // Same computation as on the checkout, but based on the order date instead of the current time
        $deliveryDate = null;
        if ($showDeliveryDate) {
            $orderDate    = $order->get_date_created();
            $deliveryDate = $this->getOrderDeliveryDate($order, $orderDate ? $orderDate->getTimestamp() : null, true);
        }
        ?>
		<h2 class="woocommerce-column__title"><?php esc_html_e('Colissimo tracking', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
		<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
			<thead>
				<tr>
                    <?php if (count($labels) > 1) { ?>
						<th class="woocommerce-table__product-name parcel-number"><?php esc_html_e('Parcel', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
                    <?php } ?>
					<th class="woocommerce-table__product-table parcel-tracking"><?php esc_html_e('Tracking', 'colissimo-shipping-methods-for-woocommerce'); ?></th>
                    <?php if ($showDeliveryDate) { ?>
						<th class="woocommerce-table__product-table parcel-delivery-date"><?php esc_html_e('Estimated delivery',
                                                                                                           'colissimo-shipping-methods-for-woocommerce'); ?></th>
                    <?php } ?>
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
                        <?php if ($showDeliveryDate) { ?>
							<td class="woocommerce-table__parcel-delivery-date parcel-delivery-date">
                                <?php echo empty($deliveryDate) ? '-' : esc_html($deliveryDate); ?>
							</td>
                        <?php } ?>
					</tr>
                <?php } ?>
			</tbody>
		</table>
        <?php
    }

    /**
     * Add the estimated delivery date as a row of the block-based order confirmation summary list,
     * to match the native WooCommerce look and feel.
     *
     * @param string $block_content The rendered block content (the summary list <ul>).
     *
     * @return string
     */
    public function addDeliveryDateToConfirmationSummary(string $block_content): string {
        // When the viewer isn't allowed to see the order, the block renders nothing: don't inject anything either
        if (empty($block_content) || false === strpos($block_content, 'wc-block-order-confirmation-summary-list')) {
            return $block_content;
        }

        $orderId = absint(get_query_var('order-received'));
        if (empty($orderId)) {
            return $block_content;
        }

        $order = wc_get_order($orderId);
        if (empty($order)) {
            return $block_content;
        }

        $deliveryDate = $this->getOrderDeliveryDate($order, null, true);
        if (empty($deliveryDate)) {
            return $block_content;
        }

        $row = '<li class="wc-block-order-confirmation-summary-list-item">'
               . '<span class="wc-block-order-confirmation-summary-list-item__key">' . esc_html__('Estimated delivery:', 'colissimo-shipping-methods-for-woocommerce') . '</span> '
               . '<span class="wc-block-order-confirmation-summary-list-item__value">' . esc_html($deliveryDate) . '</span>'
               . '</li>';

        // Insert our row just before the end of the summary list
        $closingTagPosition = strrpos($block_content, '</ul>');
        if (false === $closingTagPosition) {
            return $block_content;
        }

        return substr_replace($block_content, $row, $closingTagPosition, 0);
    }

    /**
     * Get the estimated delivery date for an order, as a ready-to-display HTML string.
     *
     * @param WC_Order $order         The order to estimate the delivery date for.
     * @param int|null $baseTimestamp Timestamp the processing date is computed from (like on the checkout). When null, the current time is used.
     * @param bool     $dateOnly      When true, return only the raw formatted date, without the surrounding text and styling.
     *
     * @return string|null The formatted delivery date, or null if it can't be estimated.
     */
    private function getOrderDeliveryDate(WC_Order $order, ?int $baseTimestamp = null, bool $dateOnly = false): ?string {
        if (
            'yes' !== Helper::get_option('lpc_display_shipping_date')
            || 'FR' !== $order->get_shipping_country()
        ) {
            return null;
        }

        // Only estimate a delivery date for orders shipped with a Colissimo method
        $shippingMethods = Register::get('shippingMethods');
        if (empty($shippingMethods->getAllColissimoShippingMethodsOfOrder($order))) {
            return null;
        }

        $postCode = $order->get_shipping_postcode();
        if (empty($postCode)) {
            return null;
        }

        $checkoutApi = new CheckoutApi();

        return $checkoutApi->getDeliveryDate($postCode, $baseTimestamp, $dateOnly);
    }
}
