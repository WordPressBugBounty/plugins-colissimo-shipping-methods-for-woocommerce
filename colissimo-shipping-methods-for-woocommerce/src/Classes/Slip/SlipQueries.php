<?php
// Legitimate SQL queries on custom takes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

namespace Colissimo\Classes\Slip;

use Colissimo\Helpers\Logger;

defined('ABSPATH') || die('Restricted Access');

class SlipQueries {
    const LABEL_TYPE_BORDEREAU = 'bordereau';
    const REDIRECTION_COLISSIMO_BORDEREAU_LISTING = 'lpc_colissimo_slip_history';

    public function getBordereauActionsIcons($bordereauLink, $bordereauID, $redirection) {
        $printerIcon = $GLOBALS['wp_version'] >= '5.5' ? 'dashicons-printer' : 'dashicons-media-default';

        $actions = '';

        if (current_user_can('lpc_download_bordereau')) {
            $actions .= '<span class="dashicons dashicons-download lpc_label_action_download" ' . $this->getBordereauDownloadAttr($bordereauLink) . '></span>';
        }

        if (current_user_can('lpc_print_bordereau')) {
            $actions .= '<span class="dashicons ' . $printerIcon . ' lpc_label_action_print" ' . $this->getBordereauPrintAttr($bordereauID) . ' ></span>';
        }

        if (current_user_can('lpc_delete_bordereau')) {
            $actions .= '<span class="dashicons dashicons-trash lpc_label_action_delete" ' . $this->getBordereauDeletionAttr($bordereauID, $redirection) . '></span>';
        }

        return $actions;
    }

    protected function getBordereauDeletionAttr($bordereauId, $redirection) {
        $slipDeleteAction = new SlipDeleteAction();

        return 'data-link="' . $slipDeleteAction->getUrlForBordereau($bordereauId, $redirection) . '" '
               . 'data-label-type="' . self::LABEL_TYPE_BORDEREAU . '" '
               // translators: %d is the bordereau (slip) ID number.
               . 'data-tracking-number="' . sprintf(__('Bordereau n°%d', 'colissimo-shipping-methods-for-woocommerce'), $bordereauId) . '" '
               . 'title="' . __('Delete bordereau', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }

    protected function getBordereauDownloadAttr($bordereauLink) {
        return 'data-link="' . $bordereauLink .
               '"title="' . __('Download bordereau', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }

    protected function getBordereauPrintAttr($bordereauId, $format = 'PDF') {
        $slipPrintAction = new SlipPrintAction();

        // translators: %d is the bordereau (slip) ID number.
        $slipNumber = sprintf(__('Bordereau n°%d', 'colissimo-shipping-methods-for-woocommerce'), $bordereauId);

        return 'data-link="' . esc_url($slipPrintAction->getUrlForBordereau($bordereauId)) . '" 
                data-label-type="' . esc_attr(self::LABEL_TYPE_BORDEREAU) . '"
                data-tracking-number="' . esc_attr($slipNumber) . '"
                data-format="' . esc_attr($format) . '" 
                title="' . esc_attr__('Print bordereau', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }


    public static function countLpcBordereau() {
        global $wpdb;

        $result = $wpdb->get_results('SELECT COUNT(DISTINCT bordereau_external_id) AS nb FROM ' . $wpdb->prefix . 'lpc_bordereau');

        if (!empty($result)) {
            return $result[0]->nb;
        }

        return 0;
    }

    public static function getLpcBordereau($current_page, $per_page) {
        global $wpdb;

        if (0 < $current_page && 0 < $per_page) {
            $offset = ($current_page - 1) * $per_page;

            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT bordereau.id, COUNT(out_label.order_id) AS number_parcels, bordereau.bordereau_external_id, bordereau.created_at, GROUP_CONCAT(DISTINCT out_label.order_id SEPARATOR ",") AS order_ids, GROUP_CONCAT(DISTINCT out_label.tracking_number SEPARATOR ", ") AS tracking_numbers 
                    FROM ' . $wpdb->prefix . 'lpc_bordereau AS bordereau 
                    LEFT JOIN ' . $wpdb->prefix . 'lpc_outward_label AS out_label ON out_label.bordereau_id = bordereau.bordereau_external_id 
                    GROUP BY bordereau.bordereau_external_id 
                    ORDER BY id DESC 
                    LIMIT %d OFFSET %d',
                    $per_page,
                    $offset
                )
            );
        }

        return $wpdb->get_results(
            'SELECT bordereau.id, COUNT(out_label.order_id) AS number_parcels, bordereau.bordereau_external_id, bordereau.created_at, GROUP_CONCAT(DISTINCT out_label.order_id SEPARATOR ",") AS order_ids, GROUP_CONCAT(DISTINCT out_label.tracking_number SEPARATOR ", ") AS tracking_numbers 
            FROM ' . $wpdb->prefix . 'lpc_bordereau AS bordereau 
            LEFT JOIN ' . $wpdb->prefix . 'lpc_outward_label AS out_label ON out_label.bordereau_id = bordereau.bordereau_external_id 
            GROUP BY bordereau.bordereau_external_id 
            ORDER BY id DESC'
        );
    }

    public static function deleteBordereauById($bordereauId): bool {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare('UPDATE ' . $wpdb->prefix . 'lpc_outward_label SET bordereau_id = NULL WHERE bordereau_id = %d', $bordereauId)
        );
        $result = $wpdb->query(
            $wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'lpc_bordereau WHERE bordereau_external_id = %d', $bordereauId)
        );

        if (!$result) {
            Logger::error(
                'Unable to delete slip',
                [
                    'slipId' => $bordereauId,
                    'result' => $result,
                    'method' => __METHOD__,
                ]
            );

            return false;
        }

        return true;
    }
}
