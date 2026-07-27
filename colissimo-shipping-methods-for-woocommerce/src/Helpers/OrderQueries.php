<?php
// Legitimate SQL queries on custom takes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

namespace Colissimo\Helpers;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Colissimo\Api\TrackingApi;

defined('ABSPATH') || die('Restricted Access');

class OrderQueries {
    public static function getLpcOrders($currentPage = 0, $elementsPerPage = 0, $args = [], $filters = []): array {
        global $wpdb;

        $selection = ' DISTINCT ' . $wpdb->prefix . 'woocommerce_order_items.order_id';
        if (!empty($filters['no_slip'])) {
            $selection .= ', ' . $wpdb->prefix . 'lpc_outward_label.label_created_at, ' . $wpdb->prefix . 'lpc_outward_label.tracking_number';
        }

        if (empty($filters['woo_status']) || [''] === $filters['woo_status']) {
            $filters['no_draft'] = true;
        }

        $sqlParams = [];
        if (self::isHposActive()) {
            $query = 'SELECT ' . $selection . ' 
                    FROM ' . $wpdb->prefix . 'woocommerce_order_items 
                    JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta ON ' . $wpdb->prefix . 'woocommerce_order_itemmeta.order_item_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_item_id 
                    JOIN ' . $wpdb->prefix . 'wc_orders ON ' . $wpdb->prefix . 'wc_orders.id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id';

            if (!empty($filters['no_slip'])) {
                $query .= ' JOIN ' . $wpdb->prefix . 'lpc_outward_label ON ' . $wpdb->prefix . 'lpc_outward_label.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id AND ' . $wpdb->prefix . 'lpc_outward_label.bordereau_id IS NULL';
            } else {
                $query .= ' LEFT JOIN ' . $wpdb->prefix . 'lpc_outward_label ON ' . $wpdb->prefix . 'lpc_outward_label.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id';
            }

            $where = [];
            if (!empty($args['orderby'])) {
                $metaKey = '';
                switch ($args['orderby']) {
                    case 'shipping-method':
                        $where[] = $wpdb->prefix . 'woocommerce_order_items . order_item_type = "shipping"';
                        break;
                    case 'shipping-status':
                        $metaKey = TrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY;
                        break;
                    case 'lpc-bordereau':
                        $metaKey = 'lpc_bordereau_id';
                        break;
                }

                if (in_array($args['orderby'], ['customer', 'address', 'country'])) {
                    $query .= ' LEFT JOIN ' . $wpdb->prefix . 'wc_order_addresses ON ' . $wpdb->prefix . 'wc_order_addresses.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id AND ' . $wpdb->prefix . 'wc_order_addresses.address_type = "shipping" ';
                } elseif (!empty($metaKey)) {
                    $query       .= ' LEFT JOIN ' . $wpdb->prefix . 'wc_orders_meta ON ' . $wpdb->prefix . 'wc_orders_meta.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id AND ' . $wpdb->prefix . 'wc_orders_meta.meta_key = %s ';
                    $sqlParams[] = $metaKey;
                }
            }

            $query .= self::addFilter($sqlParams, $filters, $where);

            if (empty($args['orderby'])) {
                $query .= ' ORDER BY ' . $wpdb->prefix . 'wc_orders.date_created_gmt DESC ';
            } else {
                switch ($args['orderby']) {
                    case 'id':
                        $ord = $wpdb->prefix . 'woocommerce_order_items.order_id';
                        break;
                    case 'customer':
                        $ord = $wpdb->prefix . 'wc_order_addresses.first_name';
                        break;
                    case 'address':
                        $ord = $wpdb->prefix . 'wc_order_addresses.address_1';
                        break;
                    case 'country':
                        $ord = $wpdb->prefix . 'wc_order_addresses.country';
                        break;
                    case 'shipping-method':
                        $ord = $wpdb->prefix . 'woocommerce_order_items.order_item_name';
                        break;
                    case 'shipping-status':
                    case 'lpc-bordereau':
                        $ord = $wpdb->prefix . 'wc_orders_meta.meta_value';
                        break;
                    case 'woo-status':
                        $ord = $wpdb->prefix . 'wc_orders.status';
                        break;
                    default:
                        $ord = $wpdb->prefix . 'wc_orders.date_created_gmt';
                        break;
                }

                $ord = ' ORDER BY ' . $ord . ' ';
                if (!empty($args['order'])) {
                    $ord .= ('asc' === strtolower($args['order']) ? 'ASC' : 'DESC') . ' ';
                }

                $query .= $ord;
            }
        } else {
            $query = 'SELECT ' . $selection . ' 
                    FROM ' . $wpdb->prefix . 'woocommerce_order_items 
                    JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta ON ' . $wpdb->prefix . 'woocommerce_order_itemmeta.order_item_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_item_id 
                    JOIN ' . $wpdb->prefix . 'posts ON ' . $wpdb->prefix . 'posts.ID = ' . $wpdb->prefix . 'woocommerce_order_items.order_id';

            if (!empty($filters['no_slip'])) {
                $query .= ' JOIN ' . $wpdb->prefix . 'lpc_outward_label ON ' . $wpdb->prefix . 'lpc_outward_label.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id AND ' . $wpdb->prefix . 'lpc_outward_label.bordereau_id IS NULL';
            } else {
                $query .= ' LEFT JOIN ' . $wpdb->prefix . 'lpc_outward_label ON ' . $wpdb->prefix . 'lpc_outward_label.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id';
            }

            if (!empty($args['orderby'])) {
                switch ($args['orderby']) {
                    case 'customer':
                        $where = $wpdb->prefix . 'postmeta.meta_key = "_shipping_first_name"';
                        break;
                    case 'address':
                        $where = $wpdb->prefix . 'postmeta.meta_key = "_shipping_address_1"';
                        break;
                    case 'country':
                        $where = $wpdb->prefix . 'postmeta.meta_key = "_shipping_country"';
                        break;
                    case 'shipping-method':
                        $where = $wpdb->prefix . 'woocommerce_order_items.order_item_type = "shipping"';
                        break;
                    case 'shipping-status':
                        $where       = $wpdb->prefix . 'postmeta.meta_key = %s';
                        $sqlParams[] = TrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY;
                        break;
                    case 'lpc-bordereau':
                        $where = $wpdb->prefix . 'postmeta.meta_key = "lpc_bordereau_id"';
                        break;
                    default:
                        $where = ' ';
                        break;
                }

                if (' ' !== $where) {
                    $query .= ' LEFT JOIN ' . $wpdb->prefix . 'postmeta ON ' . $wpdb->prefix . 'postmeta.post_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id AND ' . $where . ' ';
                }
            }

            $query .= self::addFilter($sqlParams, $filters);

            if (empty($args['orderby'])) {
                $query .= ' ORDER BY ' . $wpdb->prefix . 'posts.post_date DESC ';
            } else {
                switch ($args['orderby']) {
                    case 'id':
                        $ord = $wpdb->prefix . 'woocommerce_order_items.order_id';
                        break;
                    case 'customer':
                    case 'address':
                    case 'country':
                    case 'shipping-status':
                    case 'lpc-bordereau':
                        $ord = $wpdb->prefix . 'postmeta.meta_value';
                        break;
                    case 'shipping-method':
                        $ord = $wpdb->prefix . 'woocommerce_order_items.order_item_name';
                        break;
                    case 'woo-status':
                        $ord = $wpdb->prefix . 'posts.post_status';
                        break;
                    default:
                        $ord = $wpdb->prefix . 'posts.post_date';
                        break;
                }

                $ord = ' ORDER BY ' . $ord . ' ';
                if (!empty($args['order'])) {
                    $ord .= ('asc' === strtolower($args['order']) ? 'ASC' : 'DESC') . ' ';
                }

                $query .= $ord;
            }
        }

        if (0 < $currentPage && 0 < $elementsPerPage) {
            $offset      = ($currentPage - 1) * $elementsPerPage;
            $query       .= ' LIMIT %d OFFSET %d';
            $sqlParams[] = $elementsPerPage;
            $sqlParams[] = $offset;
        }

        // Dynamically built query based on filters, all dynamic parts are escaped through $sqlParams
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results(
            $wpdb->prepare(
            // Dynamically built query based on filters, all dynamic parts are escaped through $sqlParams
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
                $query,
                $sqlParams
            )
        );

        $ordersId = [];
        if ($results) {
            foreach ($results as $result) {
                $ordersId[] = [
                    'order_id'         => $result->order_id,
                    'label_created_at' => $result->label_created_at ?? null,
                    'tracking_number'  => $result->tracking_number ?? null,
                ];
            }
        }

        return $ordersId;
    }

    public static function countLpcOrders($filters = []) {
        global $wpdb;

        $query = 'SELECT COUNT(DISTINCT ' . $wpdb->prefix . 'woocommerce_order_items.order_id) AS nb FROM ' . $wpdb->prefix . 'woocommerce_order_items 
                    JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta ON ' . $wpdb->prefix . 'woocommerce_order_itemmeta.order_item_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_item_id ';

        if (self::isHposActive()) {
            $query .= 'JOIN ' . $wpdb->prefix . 'wc_orders ON ' . $wpdb->prefix . 'wc_orders.id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id';
        } else {
            $query .= 'JOIN ' . $wpdb->prefix . 'posts ON ' . $wpdb->prefix . 'posts.ID = ' . $wpdb->prefix . 'woocommerce_order_items.order_id';
        }

        if (!empty($filters['no_slip'])) {
            $query .= ' JOIN ' . $wpdb->prefix . 'lpc_outward_label ON ' . $wpdb->prefix . 'lpc_outward_label.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id AND bordereau_id IS NULL';
        } else {
            $query .= ' LEFT JOIN ' . $wpdb->prefix . 'lpc_outward_label ON ' . $wpdb->prefix . 'lpc_outward_label.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id';
        }

        if (empty($filters['woo_status']) || [''] === $filters['woo_status']) {
            $filters['no_draft'] = true;
        }

        $sqlParams = [];
        $query     .= self::addFilter($sqlParams, $filters);

        // Dynamically built query based on filters, all dynamic parts are escaped through $sqlParams
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results(
            $wpdb->prepare(
            // Dynamically built query based on filters, all dynamic parts are escaped through $sqlParams
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
                $query,
                $sqlParams
            )
        );

        if (!empty($results)) {
            return $results[0]->nb;
        }

        return 0;
    }

    public static function getLpcOrderIdsToRefreshDeliveryStatus(): array {
        global $wpdb;

        $numberOfDays = Helper::get_option('lpc_label_status_update_days', 90);
        $numberOfDays = empty($numberOfDays) ? 90 : $numberOfDays;
        $timePeriod   = '-' . $numberOfDays . ' days';
        $fromDate     = gmdate('Y-m-d', strtotime($timePeriod));

        if (self::isHposActive()) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT DISTINCT ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                    FROM ' . $wpdb->prefix . 'woocommerce_order_items
                    JOIN ' . $wpdb->prefix . 'lpc_outward_label ON ' . $wpdb->prefix . 'woocommerce_order_items.order_id = ' . $wpdb->prefix . 'lpc_outward_label.order_id
                    JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta ON ' . $wpdb->prefix . 'woocommerce_order_itemmeta.order_item_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_item_id
                    JOIN ' . $wpdb->prefix . 'wc_orders ON ' . $wpdb->prefix . 'wc_orders.id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                    LEFT JOIN ' . $wpdb->prefix . 'wc_orders_meta
                        ON ' . $wpdb->prefix . 'wc_orders_meta.order_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                        AND ' . $wpdb->prefix . 'wc_orders_meta.meta_key = %s
                    WHERE (' . $wpdb->prefix . 'woocommerce_order_itemmeta.meta_key = "method_id")
                        AND (' . $wpdb->prefix . 'woocommerce_order_itemmeta.meta_value LIKE %s)
                        AND (' . $wpdb->prefix . 'wc_orders.type = "shop_order")
                        AND (' . $wpdb->prefix . 'wc_orders.date_created_gmt > %s)
                        AND (' . $wpdb->prefix . 'wc_orders_meta.meta_value IS NULL OR ' . $wpdb->prefix . 'wc_orders_meta.meta_value = %s)',
                    TrackingApi::IS_DELIVERED_META_KEY,
                    'lpc_%',
                    $fromDate,
                    TrackingApi::IS_DELIVERED_META_VALUE_FALSE
                )
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT DISTINCT ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                    FROM ' . $wpdb->prefix . 'woocommerce_order_items
                    JOIN ' . $wpdb->prefix . 'lpc_outward_label ON ' . $wpdb->prefix . 'woocommerce_order_items.order_id = ' . $wpdb->prefix . 'lpc_outward_label.order_id
                    JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta ON ' . $wpdb->prefix . 'woocommerce_order_itemmeta.order_item_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_item_id
                    JOIN ' . $wpdb->prefix . 'posts ON ' . $wpdb->prefix . 'posts.ID = ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                    LEFT JOIN ' . $wpdb->prefix . 'postmeta
                        ON ' . $wpdb->prefix . 'postmeta.post_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                        AND ' . $wpdb->prefix . 'postmeta.meta_key = %s
                    WHERE (' . $wpdb->prefix . 'woocommerce_order_itemmeta.meta_key = "method_id")
                        AND (' . $wpdb->prefix . 'woocommerce_order_itemmeta.meta_value LIKE %s)
                        AND (' . $wpdb->prefix . 'posts.post_type = "shop_order")
                        AND (' . $wpdb->prefix . 'posts.post_date > %s)
                        AND (' . $wpdb->prefix . 'postmeta.meta_value IS NULL OR ' . $wpdb->prefix . 'postmeta.meta_value = %s)',
                    TrackingApi::IS_DELIVERED_META_KEY,
                    'lpc_%',
                    $fromDate,
                    TrackingApi::IS_DELIVERED_META_VALUE_FALSE
                )
            );
        }

        $ordersId = [];

        if ($results) {
            foreach ($results as $result) {
                $ordersId[] = $result->order_id;
            }
        }

        return $ordersId;
    }

    public static function getLpcOrdersPostMetaList(string $metaName, bool $isAddressMeta = false) {
        global $wpdb;

        if (self::isHposActive()) {
            if ($isAddressMeta) {
                // No WooCommerce API supports DISTINCT country aggregation across orders.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                return $wpdb->get_col(
                    'SELECT DISTINCT ' . $wpdb->prefix . 'wc_order_addresses.country
                    FROM ' . $wpdb->prefix . 'wc_order_addresses
                    WHERE ' . $wpdb->prefix . 'wc_order_addresses.address_type = "shipping"
                    ORDER BY ' . $wpdb->prefix . 'wc_order_addresses.country ASC'
                );
            }

            // No WooCommerce API supports DISTINCT meta_value aggregation across orders.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT DISTINCT ' . $wpdb->prefix . 'wc_orders_meta.meta_value
                        FROM ' . $wpdb->prefix . 'wc_orders_meta
                        WHERE ' . $wpdb->prefix . 'wc_orders_meta.meta_key = %s
                        ORDER BY ' . $wpdb->prefix . 'wc_orders_meta.meta_value ASC',
                    $metaName
                )
            );
        }

        // No WordPress API supports DISTINCT meta_value aggregation across posts.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col(
            $wpdb->prepare(
                'SELECT DISTINCT ' . $wpdb->prefix . 'postmeta.meta_value
                FROM ' . $wpdb->prefix . 'postmeta
                WHERE ' . $wpdb->prefix . 'postmeta.meta_key = %s
                ORDER BY ' . $wpdb->prefix . 'postmeta.meta_value ASC',
                $metaName
            )
        );
    }

    public static function getLpcOrdersShippingMethods() {
        global $wpdb;

        return $wpdb->get_col(
            'SELECT DISTINCT ' . $wpdb->prefix . 'woocommerce_order_items.order_item_name
            FROM ' . $wpdb->prefix . 'woocommerce_order_items
            JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta
                ON ' . $wpdb->prefix . 'woocommerce_order_itemmeta.order_item_id = ' . $wpdb->prefix . 'woocommerce_order_items.order_item_id
            WHERE ' . $wpdb->prefix . 'woocommerce_order_itemmeta.meta_key = "method_id"
                AND ' . $wpdb->prefix . 'woocommerce_order_itemmeta.meta_value LIKE "lpc_%"
                AND ' . $wpdb->prefix . 'woocommerce_order_items.order_item_type = "shipping"
            ORDER BY ' . $wpdb->prefix . 'woocommerce_order_items.order_item_name ASC'
        );
    }

    public static function getLpcOrdersWooStatuses() {
        global $wpdb;

        if (self::isHposActive()) {
            return $wpdb->get_col(
                'SELECT DISTINCT ' . $wpdb->prefix . 'wc_orders.status
                FROM ' . $wpdb->prefix . 'wc_orders
                WHERE ' . $wpdb->prefix . 'wc_orders.type = "shop_order"
                ORDER BY ' . $wpdb->prefix . 'wc_orders.status ASC'
            );
        } else {
            return $wpdb->get_col(
                'SELECT DISTINCT ' . $wpdb->prefix . 'posts.post_status
                FROM ' . $wpdb->prefix . 'posts
                WHERE ' . $wpdb->prefix . 'posts.post_type = "shop_order"
                ORDER BY ' . $wpdb->prefix . 'posts.post_status ASC'
            );
        }
    }

    protected static function addFilter(array &$sqlParams = [], array $requestFilters = [], array $filters = []): string {
        global $wpdb;

        $filters[]   = $wpdb->prefix . 'woocommerce_order_itemmeta.meta_key = "method_id"';
        $filters[]   = $wpdb->prefix . 'woocommerce_order_itemmeta.meta_value LIKE %s';
        $sqlParams[] = 'lpc_%';

        if (self::isHposActive()) {
            if (!empty($requestFilters['search'])) {
                // esc_like so user-supplied % / _ are matched literally instead of acting as extra wildcards.
                $search = $wpdb->esc_like($requestFilters['search']);

                $filters['search'] = '(';

                // ID
                $filters['search'] .= $wpdb->prefix . 'woocommerce_order_items.order_id LIKE %s';
                $sqlParams[]       = '%' . $search . '%';

                // Date
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'wc_orders.id
                    FROM ' . $wpdb->prefix . 'wc_orders
                    WHERE DATE_FORMAT(' . $wpdb->prefix . 'wc_orders.date_created_gmt, %s) LIKE %s)';
                $sqlParams[]       = '%d-%m-%Y';
                $sqlParams[]       = '%' . $search . '%';

                // Customer Name and Shipping Address
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'wc_order_addresses.order_id 
                    FROM ' . $wpdb->prefix . 'wc_order_addresses 
                    WHERE (
                        ' . $wpdb->prefix . 'wc_order_addresses.first_name LIKE %s
                        OR ' . $wpdb->prefix . 'wc_order_addresses.last_name LIKE %s
                        OR ' . $wpdb->prefix . 'wc_order_addresses.address_1 LIKE %s
                        OR ' . $wpdb->prefix . 'wc_order_addresses.address_2 LIKE %s
                        OR ' . $wpdb->prefix . 'wc_order_addresses.city LIKE %s
                        OR ' . $wpdb->prefix . 'wc_order_addresses.country LIKE %s
                        OR ' . $wpdb->prefix . 'wc_order_addresses.postcode LIKE %s
                    ) AND ' . $wpdb->prefix . 'wc_order_addresses.address_type = "shipping"
                )';
                $sqlParams         = array_merge($sqlParams, array_fill(0, 7, '%' . $search . '%'));

                // Slip ID and Outward label number
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'lpc_outward_label.order_id 
                    FROM ' . $wpdb->prefix . 'lpc_outward_label 
                    WHERE (
                        ' . $wpdb->prefix . 'lpc_outward_label.bordereau_id LIKE %s
                        OR ' . $wpdb->prefix . 'lpc_outward_label.tracking_number LIKE %s
                    )
                )';
                $sqlParams[]       = '%' . $search . '%';
                $sqlParams[]       = '%' . $search . '%';

                // Shipping method
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                    FROM ' . $wpdb->prefix . 'woocommerce_order_items
                    WHERE ' . $wpdb->prefix . 'woocommerce_order_items.order_item_type = "shipping"
                        AND ' . $wpdb->prefix . 'woocommerce_order_items.order_item_name LIKE %s)';
                $sqlParams[]       = '%' . $search . '%';

                // WooCommerce Order Status
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'wc_orders.status LIKE %s';
                $sqlParams[]       = '%' . $search . '%';

                // Inward label number
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'lpc_inward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_inward_label
                    WHERE ' . $wpdb->prefix . 'lpc_inward_label.tracking_number LIKE %s
			    )';
                $sqlParams[]       = '%' . $search . '%';

                $filters['search'] .= ')';
            }

            if (isset($requestFilters['country'])) {
                $countries = array_filter(
                    $requestFilters['country'],
                    fn($country) => !empty($country)
                );

                if (!empty($countries)) {
                    $placeholders = implode(', ', array_fill(0, count($countries), '%s'));
                    $filters[]    = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                        SELECT ' . $wpdb->prefix . 'wc_order_addresses.order_id 
                        FROM ' . $wpdb->prefix . 'wc_order_addresses 
                        WHERE
                            ' . $wpdb->prefix . 'wc_order_addresses.country IN (' . $placeholders . ')
                            AND ' . $wpdb->prefix . 'wc_order_addresses.address_type = "shipping"
                    )';
                    $sqlParams    = array_merge($sqlParams, array_values($countries));
                }
            }

            if (isset($requestFilters['status'])) {
                $status = array_filter(
                    $requestFilters['status'],
                    fn($oneStatus) => !empty($oneStatus)
                );

                if (!empty($status)) {
                    $placeholders = implode(', ', array_fill(0, count($status), '%s'));
                    $filters[]    = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                        SELECT ' . $wpdb->prefix . 'wc_orders_meta.order_id
                        FROM ' . $wpdb->prefix . 'wc_orders_meta
                        WHERE ' . $wpdb->prefix . 'wc_orders_meta.meta_key = %s
                            AND ' . $wpdb->prefix . 'wc_orders_meta.meta_value IN (' . $placeholders . '))';
                    $sqlParams[]  = TrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY;
                    $sqlParams    = array_merge($sqlParams, array_values($status));
                }
            }

            if (isset($requestFilters['woo_status'])) {
                $wooStatus = array_filter(
                    $requestFilters['woo_status'],
                    fn($oneWooStatus) => !empty($oneWooStatus)
                );

                if (!empty($wooStatus)) {
                    $placeholders = implode(', ', array_fill(0, count($wooStatus), '%s'));
                    $filters[]    = $wpdb->prefix . 'wc_orders.status IN (' . $placeholders . ')';
                    $sqlParams    = array_merge($sqlParams, array_values($wooStatus));
                }
            }

            if (!empty($requestFilters['no_draft'])) {
                $filters[] = $wpdb->prefix . 'wc_orders.status != "wc-checkout-draft"';
            }

            // Make sure we take only orders and not subscriptions
            $filters[] = $wpdb->prefix . 'wc_orders.type = "shop_order"';
        } else {
            if (!empty($requestFilters['search'])) {
                // esc_like so user-supplied % / _ are matched literally instead of acting as extra wildcards.
                $search = $wpdb->esc_like($requestFilters['search']);

                $filters['search'] = '(';

                // ID
                $filters['search'] .= $wpdb->prefix . 'woocommerce_order_items.order_id LIKE %s';
                $sqlParams[]       = '%' . $search . '%';

                // Date
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'posts.ID
                    FROM ' . $wpdb->prefix . 'posts
                    WHERE DATE_FORMAT(' . $wpdb->prefix . 'posts.post_date_gmt, %s) LIKE %s)';
                $sqlParams[]       = '%d-%m-%Y';
                $sqlParams[]       = '%' . $search . '%';

                // Customer Name, Shipping Address and Bordereau ID
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'postmeta.post_id
                    FROM ' . $wpdb->prefix . 'postmeta
                    WHERE (
                        ' . $wpdb->prefix . 'postmeta.meta_key = "_shipping_first_name"
                        OR ' . $wpdb->prefix . 'postmeta.meta_key = "_shipping_last_name"
                        OR ' . $wpdb->prefix . 'postmeta.meta_key = "_shipping_address_1"
                        OR ' . $wpdb->prefix . 'postmeta.meta_key = "_shipping_address_2"
                        OR ' . $wpdb->prefix . 'postmeta.meta_key = "_shipping_city"
                        OR ' . $wpdb->prefix . 'postmeta.meta_key = "_shipping_country"
                        OR ' . $wpdb->prefix . 'postmeta.meta_key = "_shipping_postcode"
                        OR ' . $wpdb->prefix . 'postmeta.meta_key = "lpc_bordereau_id"
                    ) AND ' . $wpdb->prefix . 'postmeta.meta_value LIKE %s
                )';
                $sqlParams[]       = '%' . $search . '%';

                // Shipping method
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                    FROM ' . $wpdb->prefix . 'woocommerce_order_items
                    WHERE ' . $wpdb->prefix . 'woocommerce_order_items.order_item_type = "shipping"
                        AND ' . $wpdb->prefix . 'woocommerce_order_items.order_item_name LIKE %s)';
                $sqlParams[]       = '%' . $search . '%';

                // WooCommerce Order Status
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'posts.post_status LIKE %s';
                $sqlParams[]       = '%' . $search . '%';

                // Outward label number
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'lpc_outward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_outward_label
                    WHERE ' . $wpdb->prefix . 'lpc_outward_label.tracking_number LIKE %s
                )';
                $sqlParams[]       = '%' . $search . '%';

                // Inward label number
                $filters['search'] .= ' OR ' . $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'lpc_inward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_inward_label
                    WHERE ' . $wpdb->prefix . 'lpc_inward_label.tracking_number LIKE %s
                )';
                $sqlParams[]       = '%' . $search . '%';

                $filters['search'] .= ')';
            }

            if (isset($requestFilters['country'])) {
                $countries = array_filter(
                    $requestFilters['country'],
                    fn($country) => !empty($country)
                );

                if (!empty($countries)) {
                    $placeholders = implode(', ', array_fill(0, count($countries), '%s'));
                    $filters[]    = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                        SELECT ' . $wpdb->prefix . 'postmeta.post_id 
                        FROM ' . $wpdb->prefix . 'postmeta 
                        WHERE ' . $wpdb->prefix . 'postmeta.meta_key = "_shipping_country"
                            AND ' . $wpdb->prefix . 'postmeta.meta_value IN (' . $placeholders . '))';
                    $sqlParams    = array_merge($sqlParams, array_values($countries));
                }
            }

            if (isset($requestFilters['status'])) {
                $status = array_filter(
                    $requestFilters['status'],
                    fn($oneStatus) => !empty($oneStatus)
                );

                if (!empty($status)) {
                    $placeholders = implode(', ', array_fill(0, count($status), '%s'));
                    $filters[]    = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                        SELECT ' . $wpdb->prefix . 'postmeta.post_id
                        FROM ' . $wpdb->prefix . 'postmeta
                        WHERE ' . $wpdb->prefix . 'postmeta.meta_key = %s
                            AND ' . $wpdb->prefix . 'postmeta.meta_value IN (' . $placeholders . '))';
                    $sqlParams[]  = TrackingApi::LAST_EVENT_INTERNAL_CODE_META_KEY;
                    $sqlParams    = array_merge($sqlParams, array_values($status));
                }
            }

            if (isset($requestFilters['woo_status'])) {
                $wooStatus = array_filter(
                    $requestFilters['woo_status'],
                    fn($oneWooStatus) => !empty($oneWooStatus)
                );

                if (!empty($wooStatus)) {
                    $placeholders = implode(', ', array_fill(0, count($wooStatus), '%s'));
                    $filters[]    = $wpdb->prefix . 'posts.post_status IN (' . $placeholders . ')';
                    $sqlParams    = array_merge($sqlParams, array_values($wooStatus));
                }
            }

            if (!empty($requestFilters['no_draft'])) {
                $filters[] = $wpdb->prefix . 'posts.post_status != "wc-checkout-draft"';
            }

            // Make sure we take only orders and not subscriptions
            $filters[] = $wpdb->prefix . 'posts.post_type = "shop_order"';
        }

        if (isset($requestFilters['label_type'])) {
            $labelTypes = array_filter(
                $requestFilters['label_type'],
                fn($labelType) => !empty($labelType)
            );

            if (in_array('inward', $labelTypes)) {
                $filters[] = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT DISTINCT ' . $wpdb->prefix . 'lpc_inward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_inward_label
                    WHERE ' . $wpdb->prefix . 'lpc_inward_label.tracking_number IS NOT NULL)';
            }

            if (in_array('outward', $labelTypes)) {
                $filters[] = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT DISTINCT ' . $wpdb->prefix . 'lpc_outward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_outward_label
                    WHERE ' . $wpdb->prefix . 'lpc_outward_label.tracking_number IS NOT NULL)';
            }

            if (in_array('outward_printed', $labelTypes)) {
                $filters[] = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT DISTINCT ' . $wpdb->prefix . 'lpc_outward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_outward_label
                    WHERE ' . $wpdb->prefix . 'lpc_outward_label.printed = 1)';
            }

            if (in_array('outward_not_printed', $labelTypes)) {
                $filters[] = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT DISTINCT ' . $wpdb->prefix . 'lpc_outward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_outward_label
                    WHERE ' . $wpdb->prefix . 'lpc_outward_label.printed = 0)';
            }

            if (in_array('none', $labelTypes)) {
                $filters[] = $wpdb->prefix . 'woocommerce_order_items.order_id NOT IN (
                    SELECT DISTINCT ' . $wpdb->prefix . 'lpc_inward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_inward_label)';

                $filters[] = $wpdb->prefix . 'woocommerce_order_items.order_id NOT IN (
                    SELECT DISTINCT ' . $wpdb->prefix . 'lpc_outward_label.order_id
                    FROM ' . $wpdb->prefix . 'lpc_outward_label)';
            }
        }

        if (!empty($requestFilters['label_start_date'])) {
            $filters[]   = $wpdb->prefix . 'lpc_outward_label.label_created_at > %s';
            $sqlParams[] = $requestFilters['label_start_date'];
        }

        if (!empty($requestFilters['label_end_date'])) {
            $filters[]   = $wpdb->prefix . 'lpc_outward_label.label_created_at < %s';
            $sqlParams[] = $requestFilters['label_end_date'];
        }

        if (isset($requestFilters['shipping_method'])) {
            $shippingMethods = array_filter(
                $requestFilters['shipping_method'],
                fn($shippingMethod) => !empty($shippingMethod)
            );

            if (!empty($shippingMethods)) {
                $placeholders = implode(', ', array_fill(0, count($shippingMethods), '%s'));
                $filters[]    = $wpdb->prefix . 'woocommerce_order_items.order_id IN (
                    SELECT ' . $wpdb->prefix . 'woocommerce_order_items.order_id
                    FROM ' . $wpdb->prefix . 'woocommerce_order_items
                    WHERE ' . $wpdb->prefix . 'woocommerce_order_items.order_item_type = "shipping"
                        AND ' . $wpdb->prefix . 'woocommerce_order_items.order_item_name IN (' . $placeholders . '))';
                $sqlParams    = array_merge($sqlParams, array_values($shippingMethods));
            }
        }

        return ' WHERE ' . implode(' AND ', $filters);
    }

    public static function isHposActive(): bool {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && method_exists(
                OrderUtil::class,
                'custom_orders_table_usage_is_enabled'
            ) && OrderUtil::custom_orders_table_usage_is_enabled()) {
            return true;
        } else {
            return false;
        }
    }

    public static function hasShippingMethod(object $order, string $method): bool {
        $shippings = $order->get_shipping_methods();
        if (empty($shippings)) {
            return false;
        }

        $shipping = current($shippings);

        return $shipping->get_method_id() === $method;
    }

    public static function getOrderItems(object $order): array {
        $items = [];
        foreach ($order->get_items() as $itemId => $item) {
            $product  = $item->get_product();
            $quantity = $item->get_quantity();

            $items[$itemId] = [
                'qty'    => $quantity,
                'price'  => $item->get_total() / $quantity,
                'weight' => $product ? $product->get_weight() : 0,
            ];
        }

        return $items;
    }
}
