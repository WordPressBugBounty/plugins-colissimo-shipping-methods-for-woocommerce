<?php

namespace Colissimo\Classes\Slip;

use Colissimo\Api\SlipGenerationApi;
use Colissimo\Classes\Order\Table;
use Colissimo\Core\Register;
use WP_List_Table;

defined('ABSPATH') || die('Restricted Access');

class HistoryTable extends WP_List_Table {

    /** @var SlipGenerationApi */
    protected $bordereauGenerationApi;
    /** @var SlipDownloadAction */
    protected $bordereauDownloadAction;
    /** @var SlipQueries */
    protected $bordereauQueries;

    public function __construct(
        ?SlipGenerationApi $bordereauGenerationApi = null,
        ?SlipDownloadAction $bordereauDownloadAction = null
    ) {
        parent::__construct();

        $this->bordereauGenerationApi  = new SlipGenerationApi();
        $this->bordereauDownloadAction = Register::get('bordereauDownloadAction');
        $this->bordereauQueries        = Register::get('bordereauQueries');
    }

    public function get_columns() {
        $columns = [
            'lpc-number'           => __('Bordereau ID', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-parcels-number'   => __('Number of parcels', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-order-ids'        => __('Order IDs', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-tracking-numbers' => __('Tracking numbers', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-creation-date'    => __('Creation date', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc-actions'          => __('Actions', 'colissimo-shipping-methods-for-woocommerce'),
        ];

        return array_map(
            fn($title) => '<span style="font-weight:bold;">' . $title . '</span>',
            $columns
        );
    }

    public function prepare_items($args = []) {
        $columns      = $this->get_columns();
        $hidden       = [];
        $sortable     = [];
        $total_items  = SlipQueries::countLpcBordereau();
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
        $this->items           = $this->get_data($current_page, $per_page, $args);
    }

    protected function column_default($item, $column_name) {
        return $item[$column_name];
    }

    protected function get_data($current_page = 0, $per_page = 0, $args = [], $filters = []): array {
        $data  = [];
        $slips = SlipQueries::getLpcBordereau($current_page, $per_page);

        $formatDate = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');

        foreach ($slips as $slip) {
            $date = '-';
            if (!empty($slip->created_at)) {
                $date = date_i18n($formatDate, strtotime($slip->created_at));
            }

            $bordereauLink = $this->bordereauDownloadAction->getBorderauDownloadLink($slip->bordereau_external_id);

            $orderIds      = $slip->order_ids ? explode(',', $slip->order_ids) : [];
            $orderIdsLinks = [];
            foreach ($orderIds as $orderId) {
                $orderIdsLinks[] = Table::getSeeOrderLink($orderId);
            }

            $data[] = [
                'data-id'              => $slip->bordereau_external_id,
                'lpc-number'           => $slip->bordereau_external_id,
                'lpc-parcels-number'   => $slip->number_parcels,
                'lpc-order-ids'        => str_replace(', N/A', '', implode(', ', $orderIdsLinks)),
                'lpc-tracking-numbers' => $slip->tracking_numbers,
                'lpc-creation-date'    => $date,
                'lpc-actions'          => $this->bordereauQueries->getBordereauActionsIcons(
                    $bordereauLink,
                    $slip->bordereau_external_id,
                    SlipQueries::REDIRECTION_COLISSIMO_BORDEREAU_LISTING
                ),
            ];
        }

        return $data;
    }
}
