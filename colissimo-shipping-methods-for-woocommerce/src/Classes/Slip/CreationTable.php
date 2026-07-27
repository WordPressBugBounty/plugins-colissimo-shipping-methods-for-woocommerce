<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- WooCommerce is loaded, using their template, including filters that require their translations
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional re-use of a documented WooCommerce/WordPress core hook.

namespace Colissimo\Classes\Slip;

use Colissimo\Helpers\Helper;
use Colissimo\Helpers\OrderQueries;
use Colissimo\Classes\Order\Table;
use Colissimo\Core\Register;
use Exception;
use WC_DateTime;
use WP_List_Table;

defined('ABSPATH') || die('Restricted Access');

class CreationTable extends WP_List_Table {

    const BULK_SLIP_CREATION = 'bulk-slip_creation_ids';

    private $needTodayOrder;

    /** @var SlipGeneration */
    protected $bordereauGeneration;
    /** @var SlipDownloadAction */
    protected $bordereauDownloadAction;

    public function __construct($needTodayOrder) {
        parent::__construct();

        $this->bordereauGeneration     = Register::get('bordereauGeneration');
        $this->bordereauDownloadAction = Register::get('bordereauDownloadAction');

        $this->needTodayOrder = $needTodayOrder;
    }

    public function get_columns() {
        $columns = [
            'cb'                  => '<input type="checkbox" />',
            'lpc-id'              => __('ID', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-tracking-number' => __('Tracking number', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-date-label'      => __('Label creation date', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-date-order'      => __('Order creation date', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-country'         => __('Country', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-shipping-method' => __('Shipping method', 'colissimo-shipping-methods-for-woocommerce'),
        ];

        return array_map(
            fn($title) => '<span style="font-weight:bold;">' . $title . '</span>',
            $columns
        );
    }

    public function get_pagenum() {
        $pageParamsName = $this->needTodayOrder ? 'paged_today' : 'paged_other';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination index; pagination links carry no nonce and no state is changed.
        $pagenum = isset($_REQUEST[$pageParamsName]) ? absint($_REQUEST[$pageParamsName]) : 0;

        if (isset($this->_pagination_args['total_pages']) && $pagenum > $this->_pagination_args['total_pages']) {
            $pagenum = $this->_pagination_args['total_pages'];
        }

        return max(1, $pagenum);
    }

    public function prepare_items($args = []) {
        $this->process_bulk_action();

        $filters = [
            'no_slip' => true,
        ];
        if ($this->needTodayOrder) {
            $filters['label_start_date'] = gmdate('Y-m-d 00:00:00', time());
        } else {
            $filters['label_end_date'] = gmdate('Y-m-d 00:00:00', time());
        }

        $columns      = $this->get_columns();
        $hidden       = [];
        $sortable     = [];
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
        $data   = [];
        $orders = OrderQueries::getLpcOrders($current_page, $per_page, $args, $filters);

        foreach ($orders as $order) {
            $orderId = $order['order_id'];

            try {
                $wc_order = wc_get_order($orderId);
            } catch (Exception $exception) {
                continue;
            }

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
                'lpc-id'              => Table::getSeeOrderLink($orderId),
                'lpc-tracking-number' => $order['tracking_number'],
                'lpc-date-label'      => (new WC_DateTime($order['label_created_at']))->date_i18n($date),
                'lpc-date-order'      => empty($orderDate) ? '-' : $orderDate->date_i18n($date),
                'lpc-country'         => $wc_order->get_shipping_country(),
                'lpc-shipping-method' => $wc_order->get_shipping_method(),
            ];
        }

        return $data;
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="%s[]" value="%s" />',
            self::BULK_SLIP_CREATION,
            $item['data-id']
        );
    }

    public function displayHeaders() {
        if (!current_user_can('lpc_manage_bordereau')) {
            return;
        }

        Helper::renderPartial(
            'Slip/Header.php',
            [
                'generateUrl' => $this->bordereauGeneration->getGenerationBordereauEndDayUrl(),
            ]
        );
    }

    protected function getOrdersByIds(array $ids) {
        return array_map(
            fn($id) => wc_get_order($id),
            $ids
        );
    }

    protected function process_bulk_action() {
        if (!current_user_can('lpc_manage_bordereau')) {
            return;
        }
        $ids = Helper::getVar(self::BULK_SLIP_CREATION, [], 'array');

        if (empty($ids)) {
            return;
        }

        $orders = $this->getOrdersByIds($ids);

        $bordereauId = $this->bordereauGeneration->generate($orders);

        if (!empty($bordereauId)) {
            wp_safe_redirect(admin_url('admin.php?page=wc_colissimo_view&tab=slip-history'));
        }
    }
}
