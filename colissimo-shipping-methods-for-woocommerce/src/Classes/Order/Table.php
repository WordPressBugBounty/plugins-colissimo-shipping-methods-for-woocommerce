<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- WooCommerce is loaded, using their template, including filters that require their translations
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional re-use of a documented WooCommerce/WordPress core hook.

namespace Colissimo\Classes\Order;

use Colissimo\Classes\Slip\SlipDb;
use Colissimo\Classes\Settings\AdminNotices;
use Colissimo\Classes\Slip\SlipQueries;
use Colissimo\Classes\Slip\SlipDownloadAction;
use Colissimo\Helpers\Helper;
use DateTime;
use Exception;
use Colissimo\Classes\Label\InwardLabelDb;
use Colissimo\Classes\Slip\SlipGeneration;
use Colissimo\Classes\Label\LabelStatus;
use Colissimo\Classes\Label\LabelGenerationInward;
use Colissimo\Classes\Label\LabelGenerationOutward;
use Colissimo\Classes\Label\PackagerDownloadAction;
use Colissimo\Classes\Label\LabelPrintAction;
use Colissimo\Classes\Label\LabelPurge;
use Colissimo\Classes\Label\LabelQueries;
use Colissimo\Helpers\OrderQueries;
use Colissimo\Core\Register;
use Colissimo\Classes\Label\UpdateStatusesAction;
use Colissimo\Classes\Label\OutwardLabelDb;
use Colissimo\Api\TrackingApi;
use WC_Admin_Settings;
use WC_Countries;
use WP_List_Table;

defined('ABSPATH') || die('Restricted Access');

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Table extends WP_List_Table {
    const BULK_ACTION_IDS_PARAM_NAME = 'bulk-lpc_action_id';
    const BULK_BORDEREAU_GENERATION_ACTION_NAME = 'bulk-bordereau_generation';
    const BULK_LABEL_DOWNLOAD_ACTION_NAME = 'bulk-label_download';
    const BULK_LABEL_GENERATION_OUTWARD_ACTION_NAME = 'bulk-label_generation_outward';
    const BULK_LABEL_GENERATION_INWARD_ACTION_NAME = 'bulk-label_generation_inward';
    const BULK_LABEL_PRINT_OUTWARD_ACTION_NAME = 'bulk-label_print_outward';
    const BULK_LABEL_PRINT_INWARD_ACTION_NAME = 'bulk-label_print_inward';
    const BULK_LABEL_PRINT_ACTION_NAME = 'bulk-label_print';
    const BULK_LABEL_DELETE_LABEL = 'bulk-delete_label';

    /** @var SlipGeneration */
    protected $bordereauGeneration;
    /** @var TrackingApi */
    protected $unifiedTrackingApi;
    /** @var SlipDownloadAction */
    protected $bordereauDownloadAction;
    /** @var PackagerDownloadAction */
    protected $labelPackagerDownloadAction;
    /** @var LabelGenerationOutward */
    protected $labelGenerationOutward;
    /** @var LabelGenerationInward */
    protected $labelGenerationInward;
    /** @var LabelPrintAction */
    protected $labelPrintAction;
    /** @var LabelStatus */
    protected $colissimoStatus;
    /** @var UpdateStatusesAction */
    protected $updateStatuses;
    /** @var LabelQueries */
    protected $labelQueries;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var InwardLabelDb */
    protected $inwardLabelDb;
    /** @var OutwardLabelDb */
    protected $labelOutwardImport;
    /** @var LabelPurge */
    protected $lpcLabelPurge;
    /** @var SlipQueries */
    protected $bordereauQueries;
    /** @var SlipDb */
    protected $bordereauDb;
    /** @var AdminNotices */
    protected $adminNotices;

    public function __construct() {
        parent::__construct();

        $this->bordereauGeneration         = Register::get('bordereauGeneration');
        $this->unifiedTrackingApi          = Register::get('unifiedTrackingApi');
        $this->bordereauDownloadAction     = Register::get('bordereauDownloadAction');
        $this->labelPackagerDownloadAction = Register::get('labelPackagerDownloadAction');
        $this->labelGenerationOutward      = Register::get('labelGenerationOutward');
        $this->labelGenerationInward       = Register::get('labelGenerationInward');
        $this->updateStatuses              = Register::get('updateStatusesAction');
        $this->labelPrintAction            = Register::get('labelPrintAction');
        $this->colissimoStatus             = Register::get('colissimoStatus');
        $this->labelQueries                = Register::get('labelQueries');
        $this->outwardLabelDb              = Register::get('outwardLabelDb');
        $this->labelOutwardImport          = Register::get('labelOutwardImport');
        $this->inwardLabelDb               = Register::get('inwardLabelDb');
        $this->lpcLabelPurge               = Register::get('labelPurge');
        $this->bordereauQueries            = Register::get('bordereauQueries');
        $this->bordereauDb                 = Register::get('bordereauDb');
        $this->adminNotices                = Register::get('lpcAdminNotices');
    }

    public function get_columns() {
        $columns = [
            'cb'                  => '<input type="checkbox" />',
            'lpc-id'              => __('ID', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-order-number'    => __('Order number', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-date'            => __('Date', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-customer'        => __('Customer', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-address'         => __('Address', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-country'         => __('Country', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-shipping-method' => __('Shipping method', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-woo-status'      => __('Order status', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-label'           => sprintf(
                '%s (<span id="lpc__orders_listing__title__outward">%s</span> / <span id="lpc__orders_listing__title__inward">%s</span> / <span id="lpc__orders_listing__title__bordereau">%s</span>)',
                __('Labels', 'colissimo-shipping-methods-for-woocommerce'),
                strtolower(__('Outward', 'colissimo-shipping-methods-for-woocommerce')),
                strtolower(__('Inward', 'colissimo-shipping-methods-for-woocommerce')),
                strtolower(__('Bordereau', 'colissimo-shipping-methods-for-woocommerce'))
            ),
        ];

        return array_map(
            fn($title) => '<span style="font-weight:bold;">' . $title . '</span>',
            $columns
        );
    }

    public function prepare_items($args = []) {
        $this->process_bulk_action();

        $optionsFiltersMatchRequestsKey = [
            'lpc_orders_filters_country'          => 'order_country',
            'lpc_orders_filters_shipping_method'  => 'order_shipping_method',
            'lpc_orders_filters_status'           => 'order_status',
            'lpc_orders_filters_label_type'       => 'label_type',
            'lpc_orders_filters_woo_status'       => 'order_woo_status',
            'lpc_orders_filters_label_start_date' => 'label_start_date',
            'lpc_orders_filters_label_end_date'   => 'label_end_date',
        ];

        $filtersNonce = isset($_REQUEST['lpc_orders_filters_nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc_orders_filters_nonce'])) : '';
        if (wp_verify_nonce($filtersNonce, 'lpc_orders_filters')) {
            foreach ($optionsFiltersMatchRequestsKey as $oneOptionFilter => $oneRequestKey) {
                if (isset($_REQUEST[$oneRequestKey])) {
                    if (is_array($_REQUEST[$oneRequestKey])) {
                        $requestValue = array_map('sanitize_text_field', wp_unslash($_REQUEST[$oneRequestKey]));
                    } else {
                        $requestValue = sanitize_text_field(wp_unslash($_REQUEST[$oneRequestKey]));
                    }

                    if (false === update_option($oneOptionFilter, $requestValue, false)) {
                        add_option($oneOptionFilter, $requestValue, '', false);
                    }
                }
            }
        }

        $filters = $this->lpcGetFilters();

        $columns      = $this->get_columns();
        $hidden       = [];
        $sortable     = $this->get_sortable_columns();
        $total_items  = OrderQueries::countLpcOrders($filters);
        $current_page = $this->get_pagenum();
        $user         = get_current_user_id();
        $screen       = get_current_screen();
        $option       = $screen->get_option('per_page', 'option');

        $per_page = get_user_meta($user, $option, true);

        if (empty($per_page) || $per_page < 1) {
            $per_page = $screen->get_option('per_page', 'default');
        }

        $this->set_pagination_args(
            [
                'total_items' => $total_items,
                'per_page'    => $per_page,
            ]
        );

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items           = $this->get_data($current_page, $per_page, $args, $filters);
    }

    protected function column_default($item, $column_name) {
        return $item[$column_name];
    }

    protected function get_data($current_page = 0, $per_page = 0, $args = [], $filters = []): array {
        $data      = [];
        $orders    = OrderQueries::getLpcOrders($current_page, $per_page, $args, $filters);
        $ordersIds = array_map(
            fn($order) => $order['order_id'],
            $orders
        );

        $trackingNumbers     = $this->getTrackingNumbersFormatted($ordersIds);
        $ordersOutwardFailed = Helper::get_option(LabelGenerationOutward::ORDERS_OUTWARD_PARCEL_FAILED, []);

        foreach ($ordersIds as $orderId) {
            try {
                $wc_order = wc_get_order($orderId);
            } catch (Exception $exception) {
                continue;
            }

            $address = $wc_order->get_shipping_address_1();
            $address .= !empty($wc_order->get_shipping_address_2()) ?
                '<br>' . $wc_order->get_shipping_address_2()
                : '';
            $address .= '<br>' . $wc_order->get_shipping_postcode() . ' ' . $wc_order->get_shipping_city();

            if (current_user_can('lpc_manage_labels')) {
                $labels = '<div class="lpc_generate_outward_label lpc_generate_label">
								<span class="dashicons dashicons-plus lpc_generate_label_dashicon" '
                          . $this->labelQueries->getLabelOutwardGenerateAttr($orderId) . '></span>'
                          . __('Generate outward label', 'colissimo-shipping-methods-for-woocommerce') . '
								</div><br>';

                if (!empty($ordersOutwardFailed[$orderId])) {
                    $labels .= '<div class="lpc_outward_label_error">';
                    $labels .= '<span class="dashicons dashicons-warning lpc_outward_label_error_icon"></span>';
                    $labels .= sprintf(
                    // translators: %s is the error message returned by the API.
                        __('The label couldn\'t be generated: %s', 'colissimo-shipping-methods-for-woocommerce'),
                        // English message saved in database to display the correct language to the current user
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                        __($ordersOutwardFailed[$orderId]['message'], 'colissimo-shipping-methods-for-woocommerce')
                    );
                    $labels .= '</div><br>';
                }
            } else {
                $labels = '';
            }
            $labels .= $trackingNumbers[$orderId] ?? '';

            /**
             * Filter on the date format shown in the Colissimo listing
             *
             * @since 1.6
             */
            $date = apply_filters('woocommerce_admin_order_date_format', __('M j, Y', 'woocommerce'));

            $orderDate = $wc_order->get_date_created();
            $data[]    = [
                'data-id'             => $orderId,
                'cb'                  => '<input type="checkbox" />',
                'lpc-id'              => self::getSeeOrderLink($orderId),
                'lpc-order-number'    => $wc_order->get_order_number(),
                'lpc-date'            => empty($orderDate) ? '-' : $orderDate->date_i18n($date),
                'lpc-customer'        => $wc_order->get_shipping_first_name() . ' ' . $wc_order->get_shipping_last_name(),
                'lpc-address'         => $address,
                'lpc-country'         => $wc_order->get_shipping_country(),
                'lpc-shipping-method' => $wc_order->get_shipping_method(),
                'lpc-woo-status'      => wc_get_order_status_name($wc_order->get_status()),
                'lpc-label'           => $labels,
            ];
        }

        return $data;
    }

    protected function getLabelTrackingInfo($outwardTrackingNumber): array {
        if (empty($outwardTrackingNumber)) {
            return [];
        }

        $label = $this->outwardLabelDb->getLabel($outwardTrackingNumber);

        if (empty($label)) {
            return [];
        }

        $result = [
            'trackingLink' => $this->labelQueries->getOutwardLabelLink($label->order_id, $outwardTrackingNumber),
            'status'       => '',
        ];

        if (empty($label->status_id)) {
            return $result;
        }

        $result['status'] = $this->colissimoStatus->getStatusInfo($label->status_id)['label'];

        return $result;
    }

    public static function getSeeOrderLink($orderId): string {
        $order = wc_get_order($orderId);
        if (empty($order)) {
            return 'N/A';
        }

        $orderUrl = $order->get_edit_order_url();

        return '<a href="' . $orderUrl . '">' . $orderId . '</a>';
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="%s[]" value="%s" />',
            self::BULK_ACTION_IDS_PARAM_NAME,
            $item['data-id']
        );
    }

    public function get_bulk_actions() {
        $actions = [];

        if (current_user_can('lpc_download_labels')) {
            $actions[self::BULK_LABEL_DOWNLOAD_ACTION_NAME] = __('Download label information', 'colissimo-shipping-methods-for-woocommerce');
        }

        if (current_user_can('lpc_manage_labels')) {
            $actions[self::BULK_LABEL_GENERATION_OUTWARD_ACTION_NAME] = __('Generate outward labels', 'colissimo-shipping-methods-for-woocommerce');
            $actions[self::BULK_LABEL_GENERATION_INWARD_ACTION_NAME]  = __('Generate inward labels', 'colissimo-shipping-methods-for-woocommerce');
        }

        if (current_user_can('lpc_print_labels')) {
            $actions[self::BULK_LABEL_PRINT_ACTION_NAME]         = __('Print label information', 'colissimo-shipping-methods-for-woocommerce');
            $actions[self::BULK_LABEL_PRINT_OUTWARD_ACTION_NAME] = __('Print outward labels', 'colissimo-shipping-methods-for-woocommerce');
            $actions[self::BULK_LABEL_PRINT_INWARD_ACTION_NAME]  = __('Print inward labels', 'colissimo-shipping-methods-for-woocommerce');
        }

        if (current_user_can('lpc_delete_labels')) {
            $actions[self::BULK_LABEL_DELETE_LABEL] = __('Delete labels', 'colissimo-shipping-methods-for-woocommerce');
        }

        if (current_user_can('lpc_manage_bordereau')) {
            $actions[self::BULK_BORDEREAU_GENERATION_ACTION_NAME] = __('Generate bordereau', 'colissimo-shipping-methods-for-woocommerce');
        }

        return $actions;
    }

    public function get_sortable_columns() {
        return [
            'lpc-id'              => ['id', true],
            'lpc-date'            => ['date', false],
            'lpc-customer'        => ['customer', false],
            'lpc-address'         => ['address', false],
            'lpc-country'         => ['country', false],
            'lpc-shipping-method' => ['shipping-method', false],
            'lpc-woo-status'      => ['woo-status', false],
            'lpc-bordereau'       => ['bordereau', false],
        ];
    }

    protected function extra_tablenav($which) {
        if ('top' === $which) {
            $filters = $this->lpcGetFilters();

            $filtersNumbers = 0;

            array_walk(
                $filters,
                function ($filter, $key) use (&$filtersNumbers) {
                    if ('search' === $key || (is_array($filter) && count($filter) === 1 && empty($filter[0]))) {
                        $filtersNumbers += 0;
                    } elseif (in_array($key, ['label_start_date', 'label_end_date']) && !empty($filter)) {
                        $filtersNumbers ++;
                    } elseif (is_array($filter)) {
                        $filtersNumbers += count($filter);
                    }
                }
            );

            ?>
			<div id="lpc__orders_listing__page__more_options--toggle">
				<a id="lpc__orders_listing__page__more_options--toggle--text">
                    <?php esc_html_e('Show filters', 'colissimo-shipping-methods-for-woocommerce'); ?>
				</a>
                <?php if ($filtersNumbers > 0) { ?>
					<span id="lpc__orders_listing__page__more_options--toggle--numbers_filters">
						<?php echo esc_html($filtersNumbers); ?>
				</span>
                <?php } ?>
			</div>

			<div id="lpc__orders_listing__page__more_options--options" style="display: none">
                <?php
                $this->countryFilters();
                $this->shippingMethodFilters();
                $this->wooStatusFilters();
                $this->statusFilters();
                $this->labelFilters();
                ?>
				<br>
				<div id="lpc__orders_listing__page__more_options--options__bottom-actions">
                    <?php
                    wp_nonce_field('lpc_orders_filters', 'lpc_orders_filters_nonce');
                    submit_button(__('Filter', 'colissimo-shipping-methods-for-woocommerce'), '', 'filter-action', false);
                    ?>
					<a id="lpc__orders_listing__page__more_options--options__bottom-actions__reset">
                        <?php esc_html_e('Reset', 'colissimo-shipping-methods-for-woocommerce'); ?>
					</a>
				</div>
			</div>
            <?php
        }
    }

    protected function countryFilters() {
        $orderCountries = OrderQueries::getLpcOrdersPostMetaList('_shipping_country', true);
        if (empty($orderCountries)) {
            return;
        }

        $selectedCountries = Helper::get_option('lpc_orders_filters_country', ['']);

        ?>
		<br>
		<p class="lpc__orders_listing__page__more_options--options__title">
            <?php esc_html_e('Country', 'colissimo-shipping-methods-for-woocommerce'); ?></p>

		<label>
			<input type="checkbox"
			       name="order_country[]" <?php checked(in_array('', $selectedCountries)); ?>
			       value="">
            <?php esc_html_e('All countries', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</label>
        <?php
        $wcCountries  = new WC_Countries();
        $countryNames = $wcCountries->__get('countries');
        foreach ($countryNames as $countryCode => $countryName) {
            if (!in_array($countryCode, $orderCountries)) {
                continue;
            }

            printf(
                '<label><input type="checkbox" name="order_country[]" %1$s value="%2$s">%3$s</label>',
                checked(in_array($countryCode, $selectedCountries), true, false),
                esc_attr($countryCode),
                esc_html($countryName)
            );
        }
    }

    protected function statusFilters() {
        $orderShippingStatuses = OrderQueries::getLpcOrdersPostMetaList(TrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY);
        if (empty($orderShippingStatuses)) {
            return;
        }

        $selectedStatuses = Helper::get_option('lpc_orders_filters_status', ['']);
        ?>
		<br>
		<p class="lpc__orders_listing__page__more_options--options__title">
            <?php esc_html_e('Status', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</p>

		<label>
			<input type="checkbox" name="order_status[]" <?php checked(in_array('', $selectedStatuses)); ?> value="">
            <?php esc_html_e('All statuses', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</label>
        <?php
        foreach ($orderShippingStatuses as $oneStatusCode) {
            printf(
                '<label><input type="checkbox" name="order_status[]" %1$s value="%2$s">%3$s</label>',
                checked(in_array($oneStatusCode, $selectedStatuses), true, false),
                esc_attr($oneStatusCode),
                esc_html($this->colissimoStatus->getStatusInfo($oneStatusCode)['label'])
            );
        }
    }

    protected function shippingMethodFilters() {
        $orderShippingMethods = OrderQueries::getLpcOrdersShippingMethods();
        if (empty($orderShippingMethods)) {
            return;
        }

        $selectedShippingMethods = Helper::get_option('lpc_orders_filters_shipping_method', ['']);
        ?>
		<br>
		<p class="lpc__orders_listing__page__more_options--options__title"><?php esc_html_e('Shipping method', 'colissimo-shipping-methods-for-woocommerce'); ?></p>

		<label>
			<input type="checkbox" name="order_shipping_method[]" <?php checked(in_array('', $selectedShippingMethods)); ?> value="">
            <?php esc_html_e('All shipping methods', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</label>
        <?php

        foreach ($orderShippingMethods as $oneShippingMethod) {
            printf(
                '<label><input type="checkbox" name="order_shipping_method[]" %1$s value="%2$s">%3$s</label>',
                checked(in_array($oneShippingMethod, $selectedShippingMethods), true, false),
                esc_attr($oneShippingMethod),
                esc_html($oneShippingMethod)
            );
        }
    }

    protected function labelFilters() {
        $labelTypes = [
            'none'                => __('No label generated', 'colissimo-shipping-methods-for-woocommerce'),
            'outward'             => __('Outward label generated', 'colissimo-shipping-methods-for-woocommerce'),
            'inward'              => __('Inward label generated', 'colissimo-shipping-methods-for-woocommerce'),
            'outward_printed'     => __('Outward label printed', 'colissimo-shipping-methods-for-woocommerce'),
            'outward_not_printed' => __('Outward label not printed', 'colissimo-shipping-methods-for-woocommerce'),
        ];

        $selectedLabelTypes = Helper::get_option('lpc_orders_filters_label_type', ['']);
        ?>
		<br>
		<p class="lpc__orders_listing__page__more_options--options__title"><?php esc_html_e('Labels', 'colissimo-shipping-methods-for-woocommerce'); ?></p>

		<label>
			<input type="checkbox" name="label_type[]" <?php checked(in_array('', $selectedLabelTypes)); ?> value="">
            <?php esc_html_e('All', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</label>

        <?php
        foreach ($labelTypes as $oneLabelCode => $oneLabelType) {
            printf(
                '<label><input type="checkbox" name="label_type[]" %1$s value="%2$s">%3$s</label>',
                checked(in_array($oneLabelCode, $selectedLabelTypes), true, false),
                esc_attr($oneLabelCode),
                esc_html($oneLabelType)
            );
        }; ?>
		<br>

		<p class="lpc__orders_listing__page__more_options--options__title"><?php esc_html_e('Outward labels generation date', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
		<div>
			<label>
                <?php esc_html_e('From:', 'colissimo-shipping-methods-for-woocommerce'); ?>
				<input type="date" name="label_start_date" value="<?php echo esc_attr(Helper::get_option('lpc_orders_filters_label_start_date')); ?>">
			</label>
			<label>
                <?php esc_html_e('to:', 'colissimo-shipping-methods-for-woocommerce'); ?>
				<input type="date" name="label_end_date" value="<?php echo esc_attr(Helper::get_option('lpc_orders_filters_label_end_date')); ?>">
			</label>
		</div>
        <?php
    }

    public function wooStatusFilters() {
        $orderWooStatuses = OrderQueries::getLpcOrdersWooStatuses();
        if (empty($orderWooStatuses)) {
            return;
        }

        $selectedWooStatuses = Helper::get_option('lpc_orders_filters_woo_status', ['']);
        ?>
		<br>
		<p class="lpc__orders_listing__page__more_options--options__title"><?php esc_html_e('Order status', 'colissimo-shipping-methods-for-woocommerce'); ?></p>

		<label>
			<input type="checkbox" name="order_woo_status[]" <?php checked(in_array('', $selectedWooStatuses)); ?> value="">
            <?php esc_html_e('All order statuses', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</label>
        <?php

        foreach ($orderWooStatuses as $oneWooStatus) {
            if (empty($oneWooStatus)) {
                continue;
            }
            printf(
                '<label><input type="checkbox" name="order_woo_status[]" %1$s value="%2$s">%3$s</label>',
                checked(in_array($oneWooStatus, $selectedWooStatuses), true, false),
                esc_attr($oneWooStatus),
                esc_html(wc_get_order_status_name($oneWooStatus))
            );
        }
    }

    public function process_bulk_action() {
        if (empty($_REQUEST['_wpnonce'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $nonce  = wp_unslash($_REQUEST['_wpnonce']);
        $action = 'bulk-' . $this->_args['plural'];

        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(esc_html__('Access denied! (Security check failed)', 'colissimo-shipping-methods-for-woocommerce'));
        }

        $action = $this->current_action();
        $ids    = Helper::getVar(self::BULK_ACTION_IDS_PARAM_NAME, [], 'array');
        if (empty($ids)) {
            // No selected orders on bulk actions => nothing to do.
            return;
        }

        switch ($action) {
            case self::BULK_BORDEREAU_GENERATION_ACTION_NAME:
                if (current_user_can('lpc_manage_bordereau')) {
                    $this->bulkBordereauGeneration($ids);
                }
                break;

            case self::BULK_LABEL_DOWNLOAD_ACTION_NAME:
                if (current_user_can('lpc_download_labels')) {
                    $this->bulkLabelDownload($ids);
                }
                break;

            case self::BULK_LABEL_GENERATION_OUTWARD_ACTION_NAME:
                if (current_user_can('lpc_manage_labels')) {
                    $this->bulkLabelGeneration($this->labelGenerationOutward, $ids);
                }
                break;

            case self::BULK_LABEL_GENERATION_INWARD_ACTION_NAME:
                if (current_user_can('lpc_manage_labels')) {
                    $this->bulkLabelGeneration($this->labelGenerationInward, $ids);
                }
                break;

            case self::BULK_LABEL_PRINT_INWARD_ACTION_NAME:
                if (current_user_can('lpc_print_labels')) {
                    $this->bulkLabelPrint($ids, InwardLabelDb::LABEL_TYPE_INWARD);
                }
                break;

            case self::BULK_LABEL_PRINT_OUTWARD_ACTION_NAME:
                if (current_user_can('lpc_print_labels')) {
                    $this->bulkLabelPrint($ids, OutwardLabelDb::LABEL_TYPE_OUTWARD);
                }
                break;

            case self::BULK_LABEL_PRINT_ACTION_NAME:
                if (current_user_can('lpc_print_labels')) {
                    $this->bulkLabelPrint($ids, LabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD);
                }
                break;

            case self::BULK_LABEL_DELETE_LABEL:
                if (current_user_can('lpc_delete_labels')) {
                    $this->bulkDeleteLabel($ids);
                }
                break;
        }
    }

    protected function getOrdersByIds(array $ids) {
        return array_filter(
            array_map(
                fn($id) => wc_get_order($id),
                $ids
            )
        );
    }

    protected function bulkBordereauGeneration(array $ids) {
        $orders = $this->getOrdersByIds($ids);

        $bordereauId = $this->bordereauGeneration->generate($orders);
        /** Special handling of the generation result :
         *  - if its empty, certainly because multiple bordereaux were generated (remembering that one
         *    bordereau can only have 50 tracking numbers), we prefer not to download any of the generate
         *    bordereau, and thus only refresh/redict to the same listing page,
         *  - else, i.e. if its *not* empty, it means that only one bordereau was generated, as a convenience
         *    for the user, we directly initiate a download of it.
         */
        if (!empty($bordereauId)) {
            if (current_user_can('lpc_download_bordereau')) {
                $bordereauGenerationActionUrl = $this->bordereauDownloadAction->getUrlForBordereau($bordereauId);
                $i18n                         = __('Click here to download your created bordereau', 'colissimo-shipping-methods-for-woocommerce');
                echo '<div class="updated"><p><a href="' . esc_url($bordereauGenerationActionUrl) . '">' . esc_html($i18n) . '</a></p></div>';
            }
        } else {
            $requestURI = '';
            if (is_null(filter_input(INPUT_SERVER, 'REQUEST_URI'))) {
                if (isset($_SERVER['REQUEST_URI'])) {
                    $requestURI = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
                }
            } else {
                $requestURI = wp_unslash(filter_input(INPUT_SERVER, 'REQUEST_URI'));
            }

            wp_safe_redirect(
                remove_query_arg(
                    ['_wp_http_referer', '_wpnonce', self::BULK_ACTION_IDS_PARAM_NAME, 'action', 'action2'],
                    $requestURI
                )
            );
            exit;
        }
    }

    protected function bulkLabelDownload(array $ids) {
        $trackingNumbers = $this->labelQueries->getTrackingNumbersForOrdersId($ids);

        $labelDownloadActionUrl = $this->labelPackagerDownloadAction->getUrlForTrackingNumbers($trackingNumbers);

        if (!$labelDownloadActionUrl) {
            $i18n = __('The labels that you\'ve selected are imported tracking numbers, you cannot download them', 'colissimo-shipping-methods-for-woocommerce');
            echo '<div class="notice lpc-notice is-dismissible lpc-notice-error-notice notice-error"><p>' . esc_html($i18n) . '</p></div>';
        } else {
            $i18n = __('Click here to download your created label package', 'colissimo-shipping-methods-for-woocommerce');
            echo '<div class="updated"><p><a href="' . esc_url($labelDownloadActionUrl) . '">' . esc_html($i18n) . '</a></p></div>';
        }
    }

    protected function bulkLabelGeneration($generator, array $ids) {
        $orders = $this->getOrdersByIds($ids);

        try {
            foreach ($orders as $order) {
                $generator->generate($order, ['items' => OrderQueries::getOrderItems($order)], true);
            }

            $requestURI = '';
            if (is_null(filter_input(INPUT_SERVER, 'REQUEST_URI'))) {
                if (isset($_SERVER['REQUEST_URI'])) {
                    $requestURI = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
                }
            } else {
                $requestURI = wp_unslash(filter_input(INPUT_SERVER, 'REQUEST_URI'));
            }

            wp_safe_redirect(
                remove_query_arg(
                    ['_wp_http_referer', '_wpnonce', self::BULK_ACTION_IDS_PARAM_NAME, 'action', 'action2'],
                    $requestURI
                )
            );
            exit;
        } catch (Exception $e) {
            add_action(
                'admin_notice',
                function () use ($e) {
                    Helper::displayNoticeException($e);
                }
            );
        }
    }

    public function bulkLabelPrint($ids, $labelType = LabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD) {
        $trackingNumbers = $this->labelQueries->getTrackingNumbersForOrdersId($ids, $labelType);

        $stringTrackingNumbers = implode(',', $trackingNumbers);
        $needInvoice           = false;

        if (LabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD === $labelType) {
            $needInvoice = true;
        }

        $labelPrintActionUrl = $this->labelPrintAction->getUrlForTrackingNumbers($trackingNumbers, $needInvoice);

        if (!$labelPrintActionUrl) {
            $i18n = __('The labels that you\'ve selected are imported tracking numbers, you cannot print them', 'colissimo-shipping-methods-for-woocommerce');
            echo '<div class="notice lpc-notice is-dismissible lpc-notice-error-notice notice-error"><p>' . esc_html($i18n) . '</p></div>';

            return;
        }

        if (empty($stringTrackingNumbers)) {
            return;
        }

        $this->outwardLabelDb->updatePrintedLabel($trackingNumbers);
        $this->inwardLabelDb->updatePrintedLabel($trackingNumbers);

        // TODO add this script with actions.js as a dependency then remove the setTimeout
        ?>
		<script type="text/javascript">
            jQuery(function ($) {
                setTimeout(() => {
					const infos = {
						pdfUrl: <?php echo wp_json_encode(esc_url_raw($labelPrintActionUrl)); ?>,
						labelType: <?php echo wp_json_encode($labelType); ?>,
						trackingNumbers: <?php echo wp_json_encode($stringTrackingNumbers); ?>
					};

                    lpc_print_labels(infos);
                }, 1000);
            });
		</script>
        <?php
    }

    public function bulkDeleteLabel($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $this->lpcLabelPurge->deleteLabels($ids);
    }

    public function displayHeaders() {
        if ('account' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            $args = [
                'type'    => 'error',
                'message' => sprintf(
                // translators: %s is the Colissimo Box account link.
                    __('The login/password connection type will be removed during 2026 in favor of application key authentication, to increase the security of your account. To avoid any interruption in your deliveries, make sure to generate an application key from the edit page of your %s account (Manage users menu) then enter it in the "General" section of the Colissimo settings.',
                       'colissimo-shipping-methods-for-woocommerce'),
                    '<a target="_blank" href="https://www.colissimo.entreprise.laposte.fr/">Colissimo Box</a>'
                ),
            ];
            Helper::renderPartial('Notice.php', $args);
        }

        echo '<h1 class="wp-heading-inline">' . esc_html__('Colissimo Orders', 'colissimo-shipping-methods-for-woocommerce') . '</h1>';
        $buttonUpdateStatusAction = $this->updateStatuses->getUpdateAllStatusesUrl();
        $buttonUpdateStatusLabel  = __('Update Colissimo statuses', 'colissimo-shipping-methods-for-woocommerce');
        echo '<a id="colissimo_action_update" href="' . esc_url($buttonUpdateStatusAction) . '" class="page-title-action">' . esc_html($buttonUpdateStatusLabel) . '</a>';

        if (current_user_can('lpc_manage_labels') && WC_Admin_Settings::get_option('display_import_tracking_number', 'no') === 'yes') {
            $buttonImportTrackingNumberLabel = __('Import tracking numbers', 'colissimo-shipping-methods-for-woocommerce');

            $buttonImportTrackingNumberLabel  .= Helper::tooltip(
                __('Required format:<br/>order_id,tracking_number<br/>123,6C12345678910<br/>456,6C12345678911', 'colissimo-shipping-methods-for-woocommerce')
            );
            $buttonImportTrackingNumberAction = $this->labelOutwardImport->getUrlToImportTrackingNumbers();
            echo '<button type="button" class="page-title-action" id="colissimo-tracking_number_import-button">' . wp_kses(
                    $buttonImportTrackingNumberLabel,
                    [
                        'span' => [
                            'class'    => [],
                            'data-tip' => [],
                        ],
                        'br'   => [],
                    ]
                ) . '</button>';
            echo '<input name="tracking_number_import" id="colissimo-tracking_number_import" type="file" accept=".csv" colissimo-data-url="' . esc_url($buttonImportTrackingNumberAction) . '">';
        }

        echo '<hr class="wp-header-end">';

        $securedReturnActive = 'no' !== Helper::get_option('lpc_customers_download_return_label', 'no');
        $securedReturnActive = $securedReturnActive && 'no' !== Helper::get_option('lpc_secured_return', 'no');
        echo '<input type="hidden" id="lpc_secured_return" value="' . intval($securedReturnActive) . '" />';
    }

    protected function lpcGetFilters(): array {
        return [
            'country'          => Helper::get_option('lpc_orders_filters_country', ['']),
            'shipping_method'  => Helper::get_option('lpc_orders_filters_shipping_method', ['']),
            'status'           => Helper::get_option('lpc_orders_filters_status', ['']),
            'label_type'       => Helper::get_option('lpc_orders_filters_label_type', ['']),
            'woo_status'       => Helper::get_option('lpc_orders_filters_woo_status', ['']),
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table search term; the core search box submits no nonce and no state is changed.
            'search'           => isset($_REQUEST['s']) ? esc_attr(sanitize_text_field(wp_unslash($_REQUEST['s']))) : '',
            'label_start_date' => Helper::get_option('lpc_orders_filters_label_start_date'),
            'label_end_date'   => Helper::get_option('lpc_orders_filters_label_end_date'),
        ];
    }

    protected function getTrackingNumbersFormatted(
        $ordersId = []
    ) {
        $trackingNumbersByOrders         = [];
        $renderedTrackingNumbersByOrders = [];
        $labelFormatByTrackingNumber     = [];
        $ordersInwardFailed              = Helper::get_option(LabelGenerationInward::ORDERS_INWARD_PARCEL_FAILED, []);
        $securedReturnActive             = 'no' !== Helper::get_option('lpc_customers_download_return_label', 'no');
        $securedReturnActive             = $securedReturnActive && 'no' !== Helper::get_option('lpc_secured_return', 'no');

        $this->labelQueries->getTrackingNumbersByOrdersId($trackingNumbersByOrders, $labelFormatByTrackingNumber, $labelInfoByTrackingNumber, $ordersId);

        foreach ($trackingNumbersByOrders as $oneOrderId => $oneOrder) {
            if ('insured' === $oneOrderId) {
                continue;
            }

            $renderedTrackingNumbersByOrders[$oneOrderId] = '<div class="lpc__orders_listing__tracking-numbers">';
            foreach ($oneOrder as $outLabel => $inLabel) {
                if ('no_outward' !== $outLabel) {
                    $format        = $labelFormatByTrackingNumber[$outLabel];
                    $labelTracking = $this->getLabelTrackingInfo($outLabel);

                    if (empty($labelTracking)) {
                        $shownLabel = $outLabel;
                    } else {
                        $shownLabel = '<a target="_blank" href="' . esc_url($labelTracking['trackingLink']) . '">' . $outLabel . '</a>';
                    }

                    $labelTooltip = '';

                    if (!empty($labelInfoByTrackingNumber[$outLabel])) {
                        $creationDate  = $labelInfoByTrackingNumber[$outLabel]->label_created_at ?? gmdate('Y-m-d H:i:s', time());
                        $dateGenerated = new DateTime($creationDate);
                        // translators: %s is the date and time the label was generated.
                        $labelTooltip = sprintf(__('Label generated at %s', 'colissimo-shipping-methods-for-woocommerce'),
                                                $dateGenerated->format(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i')));
                    }

                    $renderedTrackingNumbersByOrders[$oneOrderId] .= '<span class="lpc__orders_listing__tracking-number">';
                    $renderedTrackingNumbersByOrders[$oneOrderId] .= '<span class="lpc__orders_listing__tracking_number--outward">' . $shownLabel . '</span>';
                    $renderedTrackingNumbersByOrders[$oneOrderId] .= wc_help_tip($labelTooltip, true);
                    $renderedTrackingNumbersByOrders[$oneOrderId] .= $this->labelQueries->getOutwardLabelsActionsIcons(
                        $outLabel,
                        $format,
                        LabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING
                    );
                    if (!empty($labelTracking['status'])) {
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= '<br />' . esc_html($labelTracking['status']);
                    }
                    $renderedTrackingNumbersByOrders[$oneOrderId] .= '</span><br>';

                    $bordereauID = $this->outwardLabelDb->getBordereauFromTrackingNumber($outLabel);
                    if (!empty($bordereauID[0])) {
                        $bordereauLink = $this->bordereauDownloadAction->getBorderauDownloadLink($bordereauID[0]);

                        if (!empty($bordereauLink)) {
                            $renderedTrackingNumbersByOrders[$oneOrderId] .=
                                '<span class="lpc__orders_listing__bordereau lpc-bordereau">
								<span class="lpc__orders_listing__id--bordereau">' . sprintf(
                                // translators: %d is the bordereau ID.
                                    __('Bordereau n°%d', 'colissimo-shipping-methods-for-woocommerce'),
                                    $bordereauID[0]
                                ) . '</span>
								<span>' .
                                $this->bordereauQueries->getBordereauActionsIcons(
                                    $bordereauLink,
                                    $bordereauID[0],
                                    LabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING
                                )
                                . '</span>
							</span><br>';
                        }
                    }
                }

                foreach ($inLabel as $oneInLabel) {
                    $format = $labelFormatByTrackingNumber[$oneInLabel];

                    $renderedTrackingNumbersByOrders[$oneOrderId] .=
                        '<span class="lpc__orders_listing__tracking-number">'
                        . '<span class="dashicons dashicons-undo lpc__orders_listing__inward_logo"></span>'
                        . '<span class="lpc__orders_listing__tracking_number--inward"> ' . $oneInLabel . '</span>' .
                        $this->labelQueries->getInwardLabelsActionsIcons($oneInLabel, $format, LabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING)
                        . '</span><br>';
                }

                if (!$securedReturnActive && current_user_can('lpc_manage_labels')) {
                    $renderedTrackingNumbersByOrders[$oneOrderId] .=
                        '<div class="lpc_generate_inward_label lpc_generate_label">
							 <i class="dashicons dashicons-plus lpc_generate_label_dashicon"'
                        . $this->labelQueries->getLabelInwardGenerateAttr($oneOrderId, $outLabel) . '></i>'
                        . __('Generate inward label', 'colissimo-shipping-methods-for-woocommerce') . '
						</div><br>';

                    if (!empty($ordersInwardFailed[$outLabel])) {
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= '<div class="lpc_outward_label_error">';
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= '<span class="dashicons dashicons-warning lpc_inward_label_error_icon"></span>';
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= sprintf(
                        // translators: %s is the error message returned by the API.
                            __('The label couldn\'t be generated: %s', 'colissimo-shipping-methods-for-woocommerce'),
                            // English message saved in database to display the correct language to the current user
                            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                            __($ordersInwardFailed[$outLabel]['message'], 'colissimo-shipping-methods-for-woocommerce')
                        );
                        $renderedTrackingNumbersByOrders[$oneOrderId] .= '</div><br>';
                    }
                }

                $renderedTrackingNumbersByOrders[$oneOrderId] .= '<br>';
            }
            $renderedTrackingNumbersByOrders[$oneOrderId] .= '</div>';
        }

        return $renderedTrackingNumbersByOrders;
    }
}
