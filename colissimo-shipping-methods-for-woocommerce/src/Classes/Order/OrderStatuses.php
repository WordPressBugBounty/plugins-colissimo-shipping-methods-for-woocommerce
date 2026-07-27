<?php

namespace Colissimo\Classes\Order;

defined('ABSPATH') || die('Restricted Access');

class OrderStatuses {
    const WC_LPC_TRANSIT = 'wc-lpc_transit';
    const WC_LPC_DELIVERED = 'wc-lpc_delivered';
    const WC_LPC_ANOMALY = 'wc-lpc_anomaly';
    const WC_LPC_READY_TO_SHIP = 'wc-lpc_ready_to_ship';
    const WC_LPC_PARTIAL_EXPEDITION = 'wc-lpc_partial_exp';
    const WC_LPC_DISABLE = 'disable';
    const WC_LPC_UNKNOWN_STATUS_INTERNAL_CODE = - 1;

    public function init() {
        add_action('init', [$this, 'register_lpc_post_statuses']);
        add_filter('wc_order_statuses', [$this, 'register_lpc_order_statuses']);
        add_filter('woocommerce_reports_order_statuses', [$this, 'register_reports_lpc_order_statuses']);
        add_filter('woocommerce_order_is_paid_statuses', [$this, 'register_paid_lpc_order_statuses']);
    }

    public function register_lpc_order_statuses($order_statuses) {
        $order_statuses[self::WC_LPC_TRANSIT]            = __('Colissimo In-Transit', 'colissimo-shipping-methods-for-woocommerce');
        $order_statuses[self::WC_LPC_DELIVERED]          = __('Colissimo Delivered', 'colissimo-shipping-methods-for-woocommerce');
        $order_statuses[self::WC_LPC_ANOMALY]            = __('Colissimo Anomaly', 'colissimo-shipping-methods-for-woocommerce');
        $order_statuses[self::WC_LPC_READY_TO_SHIP]      = __('Colissimo Ready to ship', 'colissimo-shipping-methods-for-woocommerce');
        $order_statuses[self::WC_LPC_PARTIAL_EXPEDITION] = __('Colissimo Partial expedition', 'colissimo-shipping-methods-for-woocommerce');

        return $order_statuses;
    }

    public function register_lpc_post_statuses() {
        register_post_status(
            self::WC_LPC_PARTIAL_EXPEDITION,
            [
                'label'                     => __('Colissimo Partial expedition', 'colissimo-shipping-methods-for-woocommerce'),
                'public'                    => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false,
                // translators: %s is the number of orders with this status.
                'label_count'               => _n_noop(
                    'Colissimo Partial expedition <span class="count">(%s)</span>',
                    'Colissimo Partial expedition <span class="count">(%s)</span>',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
            ]
        );
        register_post_status(
            self::WC_LPC_TRANSIT,
            [
                'label'                     => __('Colissimo In-Transit', 'colissimo-shipping-methods-for-woocommerce'),
                'public'                    => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false,
                // translators: %s is the number of orders with this status.
                'label_count'               => _n_noop(
                    'Colissimo In-Transit <span class="count">(%s)</span>',
                    'Colissimo In-Transit <span class="count">(%s)</span>',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
            ]
        );
        register_post_status(
            self::WC_LPC_DELIVERED,
            [
                'label'                     => __('Colissimo Delivered', 'colissimo-shipping-methods-for-woocommerce'),
                'public'                    => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false,
                // translators: %s is the number of orders with this status.
                'label_count'               => _n_noop(
                    'Colissimo Delivered <span class="count">(%s)</span>',
                    'Colissimo Delivered <span class="count">(%s)</span>',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
            ]
        );
        register_post_status(
            self::WC_LPC_ANOMALY,
            [
                'label'                     => __('Colissimo Anomaly', 'colissimo-shipping-methods-for-woocommerce'),
                'public'                    => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false,
                // translators: %s is the number of orders with this status.
                'label_count'               => _n_noop(
                    'Colissimo Anomaly <span class="count">(%s)</span>',
                    'Colissimo Anomaly <span class="count">(%s)</span>',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
            ]
        );
        register_post_status(
            self::WC_LPC_READY_TO_SHIP,
            [
                'label'                     => __('Colissimo Ready to ship', 'colissimo-shipping-methods-for-woocommerce'),
                'public'                    => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false,
                // translators: %s is the number of orders with this status.
                'label_count'               => _n_noop(
                    'Colissimo Ready to ship <span class="count">(%s)</span>',
                    'Colissimo Ready to ship <span class="count">(%s)</span>',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
            ]
        );
    }

    public function register_reports_lpc_order_statuses($report_order_statuses) {
        if (!is_array($report_order_statuses)) {
            $report_order_statuses = [$report_order_statuses];
        }

        $report_order_statuses[] = str_replace('wc-', '', self::WC_LPC_TRANSIT);
        $report_order_statuses[] = str_replace('wc-', '', self::WC_LPC_DELIVERED);
        $report_order_statuses[] = str_replace('wc-', '', self::WC_LPC_ANOMALY);
        $report_order_statuses[] = str_replace('wc-', '', self::WC_LPC_READY_TO_SHIP);
        $report_order_statuses[] = str_replace('wc-', '', self::WC_LPC_PARTIAL_EXPEDITION);

        return $report_order_statuses;
    }

    public function register_paid_lpc_order_statuses($paid_order_statuses) {
        if (!is_array($paid_order_statuses)) {
            $paid_order_statuses = [$paid_order_statuses];
        }

        $paid_order_statuses[] = str_replace('wc-', '', self::WC_LPC_TRANSIT);
        $paid_order_statuses[] = str_replace('wc-', '', self::WC_LPC_DELIVERED);
        $paid_order_statuses[] = str_replace('wc-', '', self::WC_LPC_ANOMALY);
        $paid_order_statuses[] = str_replace('wc-', '', self::WC_LPC_READY_TO_SHIP);
        $paid_order_statuses[] = str_replace('wc-', '', self::WC_LPC_PARTIAL_EXPEDITION);

        return $paid_order_statuses;
    }
}
