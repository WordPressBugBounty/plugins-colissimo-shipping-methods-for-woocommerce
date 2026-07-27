<?php
// Legitimate SQL queries on custom takes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

namespace Colissimo\Classes\Label;

use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class InwardLabelDb {
    const LABEL_TYPE_INWARD = 'inward';

    public function getTableDefinition(): string {
        global $wpdb;

        return 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'lpc_inward_label (
            id                      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            order_id                INT(20) UNSIGNED NOT NULL,
            label                   MEDIUMBLOB       NULL,
            label_format            VARCHAR(10)      NULL,
            label_created_at        DATETIME         NULL,
            cn23                    MEDIUMBLOB       NULL,
            tracking_number         VARCHAR(20)      NULL,
            outward_tracking_number VARCHAR(20)      NULL,
            printed                 TINYINT(1)       NOT NULL DEFAULT 0,
            cn23_format             VARCHAR(10)      NULL,
            PRIMARY KEY (id),
            INDEX order_id (order_id),
            INDEX tracking_number (tracking_number),
            INDEX outward_tracking_number (outward_tracking_number)  
        ) ' . $wpdb->get_charset_collate();
    }

    public function migrateDataFromLabelTableForOrderIds($orderIds = []) {
        global $wpdb;

        if (0 === count($orderIds)) {
            Logger::error(
                'Error during inward labels migration',
                [
                    'message' => 'No orders to migrate',
                    'method'  => __METHOD__,
                ]
            );

            return false;
        }

        $orderIds = array_map(
            fn($orderId) => (int) $orderId,
            $orderIds
        );

        $placeholders = implode(', ', array_fill(0, count($orderIds), '%d'));

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated list of %d placeholders for the IN() clause; the actual values are passed to prepare().
        $labelsToMigrate = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT order_id, inward_label, inward_label_created_at, inward_cn23, inward_label_format
                FROM  ' . $wpdb->prefix . 'lpc_label
                WHERE order_id IN (' . $placeholders . ') AND inward_label IS NOT NULL
                ORDER BY order_id ASC',
                $orderIds
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        if (0 === count($labelsToMigrate)) {
            return true;
        }

        $labelsToInsert = [];

        foreach ($labelsToMigrate as $oneLabel) {
            $order = wc_get_order($oneLabel->order_id);
            if (empty($order)) {
                continue;
            }

            $trackingNumber = $order->get_meta(LabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY);

            if (empty($trackingNumber)) {
                continue;
            }

            $outwardTrackingNumber = $order->get_meta(LabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY);

            $labelsToInsert[] = $wpdb->prepare(
                '(%d, %s, %s, %s, %s, %s, %s)',
                $oneLabel->order_id,
                $oneLabel->inward_label,
                $oneLabel->inward_label_format,
                $oneLabel->inward_label_created_at,
                $oneLabel->inward_cn23,
                $trackingNumber,
                $outwardTrackingNumber
            );
        }

        $stringLabelsToInsert = implode(', ', $labelsToInsert);

        Logger::debug(
            'Migrate inward labels',
            [
                'order_ids' => $orderIds,
                'method'    => __METHOD__,
            ]
        );

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $stringLabelsToInsert is a list of value groups already built with prepare() above.
        $resultInsert = $wpdb->query(
            'INSERT INTO ' . $wpdb->prefix . 'lpc_inward_label (`order_id`, `label`, `label_format`, `label_created_at`, `cn23`, `tracking_number`, `outward_tracking_number`) VALUES ' . $stringLabelsToInsert
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        Logger::debug(
            'Result migration inward labels',
            [
                'result'    => $resultInsert,
                'order_ids' => $orderIds,
                'method'    => __METHOD__,
            ]
        );

        if (false === $resultInsert) {
            $errorDbMessage = $wpdb->last_error;

            Logger::error(
                'Error during inward labels migration',
                [
                    'message' => $errorDbMessage,
                    'method'  => __METHOD__,
                ]
            );

            return false;
        }

        return true;
    }

    public function insert(
        $orderId,
        $label,
        $trackingNumber,
        $cn23 = null,
        $labelFormat = LabelGenerationPayload::LABEL_FORMAT_PDF,
        $outwardTrackingNumber = null
    ) {
        global $wpdb;

        if (is_null($outwardTrackingNumber)) {
            $order = wc_get_order($orderId);
            if (empty($order)) {
                return false;
            }

            $outwardTrackingNumber = $order->get_meta(LabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY);
        }

        $cn23Format = LabelGenerationPayload::LABEL_FORMAT_PDF;
        if (!empty($cn23)) {
            $cn23FormatOption = Helper::get_option('lpc_cn23_format');
            if (strpos($cn23FormatOption, 'ZPL') !== false) {
                $cn23Format = LabelGenerationPayload::LABEL_FORMAT_ZPL;
            } elseif (strpos($cn23FormatOption, 'DPL') !== false) {
                $cn23Format = LabelGenerationPayload::LABEL_FORMAT_DPL;
            }
        }

        return $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . $wpdb->prefix . 'lpc_inward_label (`order_id`, `label`, `label_format`, `label_created_at`, `cn23`, `tracking_number`, `outward_tracking_number`, `cn23_format`) VALUES (%d, %s, %s, %s, %s, %s, %s, %s)',
                $orderId,
                $label,
                $labelFormat,
                current_time('mysql'),
                $cn23,
                $trackingNumber,
                $outwardTrackingNumber,
                $cn23Format
            )
        );
    }

    public function getLabelFor($trackingNumber): array {
        global $wpdb;

        $label   = '';
        $format  = '';
        $orderId = '';
        $printed = false;

        $inwardLabelAndFormat = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT label, label_format, order_id, printed
                FROM ' . $wpdb->prefix . 'lpc_inward_label
                WHERE tracking_number = %s',
                $trackingNumber
            )
        );

        if (!empty($inwardLabelAndFormat[0])) {
            $label   = $inwardLabelAndFormat[0]->label;
            $orderId = $inwardLabelAndFormat[0]->order_id;
            $printed = !empty($inwardLabelAndFormat[0]->printed);

            $format = !empty($inwardLabelAndFormat[0]->label_format) ? $inwardLabelAndFormat[0]->label_format : LabelGenerationPayload::LABEL_FORMAT_PDF;
        }

        return [
            'format'   => $format,
            'label'    => $label,
            'order_id' => $orderId,
            'printed'  => $printed,
        ];
    }

    public function getCn23For($trackingNumber): array {
        global $wpdb;

        $inwardCn23 = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT cn23, cn23_format
                FROM ' . $wpdb->prefix . 'lpc_inward_label
                WHERE tracking_number = %s',
                $trackingNumber
            )
        );

        $cn23   = '';
        $format = '';
        if (!empty($inwardCn23[0])) {
            $cn23   = $inwardCn23[0]->cn23;
            $format = !empty($inwardCn23[0]->cn23_format) ? $inwardCn23[0]->cn23_format : LabelGenerationPayload::LABEL_FORMAT_PDF;
        }

        return [
            'format' => $format,
            'cn23'   => $cn23,
        ];
    }

    public function getLabelsInfosForOrdersId($ordersId = []) {
        global $wpdb;

        $ordersId = array_map(
            fn($orderId) => (int) $orderId,
            $ordersId
        );

        if (0 === count($ordersId)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ordersId), '%d'));

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated list of %d placeholders for the IN() clause; the actual values are passed to prepare().
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT order_id,
       					tracking_number,
       					outward_tracking_number,
       					label_format
					FROM ' . $wpdb->prefix . 'lpc_inward_label
					WHERE order_id IN (' . $placeholders . ')
					ORDER BY order_id DESC, label_created_at DESC',
                $ordersId
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    }

    public function getLabelsInfosForOutward($outwardTrackingNumber) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT order_id,
                    tracking_number,
                    outward_tracking_number,
                    label_format
                FROM ' . $wpdb->prefix . 'lpc_inward_label
                WHERE outward_tracking_number = %s',
                $outwardTrackingNumber
            )
        );
    }

    public function delete($trackingNumber) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . $wpdb->prefix . 'lpc_inward_label WHERE tracking_number = %s',
                $trackingNumber
            )
        );
    }

    public function deleteForOutward($outwardTrackingNumber) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . $wpdb->prefix . 'lpc_inward_label WHERE outward_tracking_number = %s',
                $outwardTrackingNumber
            )
        );
    }

    public function purgeLabels(int $nbDays) {
        if (empty($nbDays)) {
            return;
        }

        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . $wpdb->prefix . 'lpc_inward_label
                SET `label` = NULL, `cn23` = NULL
                WHERE `label` IS NOT NULL 
                    AND `label_created_at` < DATE_SUB(NOW(), INTERVAL %d DAY)',
                $nbDays
            )
        );
    }

    public function truncate() {
        global $wpdb;

        return $wpdb->query('TRUNCATE TABLE ' . $wpdb->prefix . 'lpc_inward_label');
    }

    public function updateToVersion174() {
        global $wpdb;

        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $wpdb->prefix . 'lpc_inward_label');
        $updatedColumns = array_filter($columns,
            fn($column) => 'printed' === $column->Field);
        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_inward_label ADD COLUMN `printed` TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function updateToVersion182() {
        global $wpdb;
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_inward_label CHANGE `order_id` `order_id` INT(20) UNSIGNED NOT NULL');
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_inward_label CHANGE `label_format` `label_format` VARCHAR(10) NULL');
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_inward_label CHANGE `tracking_number` `tracking_number` VARCHAR(20) NULL');
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_inward_label CHANGE `outward_tracking_number` `outward_tracking_number` VARCHAR(20) NULL');
    }

    public function updateToVersion192() {
        global $wpdb;

        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $wpdb->prefix . 'lpc_inward_label');
        $updatedColumns = array_filter($columns,
            fn($column) => 'cn23_format' === $column->Field
        );

        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_inward_label ADD COLUMN `cn23_format` VARCHAR(10) NULL');
        $wpdb->query('UPDATE ' . $wpdb->prefix . 'lpc_inward_label SET `cn23_format` = "PDF" WHERE `cn23` IS NOT NULL');
    }

    public function updatePrintedLabel($trackingNumbers) {
        if (empty($trackingNumbers)) {
            return;
        }

        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [$trackingNumbers];
        }

        global $wpdb;

        $trackingNumbers = array_values($trackingNumbers);

        $placeholders = implode(', ', array_fill(0, count($trackingNumbers), '%s'));

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated list of %s placeholders for the IN() clause; the actual values are passed to prepare().
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . $wpdb->prefix . 'lpc_inward_label SET printed = 1 WHERE tracking_number IN (' . $placeholders . ')',
                $trackingNumbers
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    }

    public function getOrderIdByTrackingNumber($trackingNumber) {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                'SELECT order_id FROM ' . $wpdb->prefix . 'lpc_inward_label WHERE tracking_number = %s',
                $trackingNumber
            )
        );
    }
}
