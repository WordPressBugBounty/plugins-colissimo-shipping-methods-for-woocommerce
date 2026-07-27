<?php
// Legitimate SQL queries on custom takes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

namespace Colissimo\Classes\Label;

use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Helpers\OrderQueries;

defined('ABSPATH') || die('Restricted Access');

class OutwardLabelDb {
    const LABEL_TYPE_OUTWARD = 'outward';

    public function getTableDefinition(): string {
        global $wpdb;

        return 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'lpc_outward_label (
            id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            order_id         INT(20) UNSIGNED NOT NULL,
            label            MEDIUMBLOB       NULL,
            label_format     VARCHAR(10)      NULL,
            label_created_at DATETIME         NULL,
            cn23             MEDIUMBLOB       NULL,
            tracking_number  VARCHAR(20)      NULL,
            bordereau_id     INT(20) UNSIGNED NULL,
            detail           TEXT             NULL,
            printed          TINYINT(1)       NOT NULL DEFAULT 0,
            status_id        INT UNSIGNED     NULL,
            label_type       VARCHAR(10)      NOT NULL DEFAULT "CLASSIC",
            cn23_format      VARCHAR(10)      NULL,
            PRIMARY KEY (id),
            INDEX order_id (order_id),
            INDEX tracking_number (tracking_number)
        ) ' . $wpdb->get_charset_collate();
    }

    public function getOldTableOrdersToMigrate() {
        global $wpdb;

        return $wpdb->get_col('SELECT order_id FROM ' . $wpdb->prefix . 'lpc_label ORDER BY order_id DESC');
    }

    public function migrateDataFromLabelTableForOrderIds($orderIds = []) {
        global $wpdb;

        if (0 === count($orderIds)) {
            Logger::error(
                'Error during outward labels migration',
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
                'SELECT order_id, outward_label, outward_label_created_at, outward_cn23, outward_label_format
                FROM ' . $wpdb->prefix . 'lpc_label
                WHERE order_id IN (' . $placeholders . ') AND outward_label IS NOT NULL
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

            $trackingNumber = $order->get_meta(LabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY);

            if (empty($trackingNumber)) {
                continue;
            }

            $labelsToInsert[] = $wpdb->prepare(
                '(%d, %s, %s, %s, %s, %s)',
                $oneLabel->order_id,
                $oneLabel->outward_label,
                $oneLabel->outward_label_format,
                $oneLabel->outward_label_created_at,
                $oneLabel->outward_cn23,
                $trackingNumber
            );
        }

        $stringLabelsToInsert = implode(', ', $labelsToInsert);

        Logger::debug(
            'Migrate outward labels',
            [
                'order_ids' => $orderIds,
                'method'    => __METHOD__,
            ]
        );

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $stringLabelsToInsert is a list of value groups already built with prepare() above.
        $resultInsert = $wpdb->query(
            'INSERT INTO ' . $wpdb->prefix . 'lpc_outward_label (`order_id`, `label`, `label_format`, `label_created_at`, `cn23`, `tracking_number`)
VALUES ' . $stringLabelsToInsert
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        Logger::debug(
            'Result migration outward labels',
            [
                'result'    => $resultInsert,
                'order_ids' => $orderIds,
                'method'    => __METHOD__,
            ]
        );

        if (false === $resultInsert) {
            $errorDbMessage = $wpdb->last_error;
            Logger::error(
                'Error during outward labels migration',
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
        $type,
        $cn23 = null,
        string $labelFormat = LabelGenerationPayload::LABEL_FORMAT_PDF,
        array $detail = []
    ) {
        global $wpdb;

        $cn23Format = LabelGenerationPayload::LABEL_FORMAT_PDF;
        if (!empty($cn23)) {
            $cn23FormatOption = Helper::get_option('lpc_cn23_format');
            if (strpos($cn23FormatOption, 'ZPL') !== false) {
                $cn23Format = LabelGenerationPayload::LABEL_FORMAT_ZPL;
            } elseif (strpos($cn23FormatOption, 'DPL') !== false) {
                $cn23Format = LabelGenerationPayload::LABEL_FORMAT_DPL;
            }
        }

        // Custom plugin table — no WordPress API available for direct table access.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . $wpdb->prefix . 'lpc_outward_label (`order_id`, `label`, `label_format`, `label_created_at`, `cn23`, `tracking_number`, `detail`, `label_type`, `cn23_format`) 
                VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s)',
                $orderId,
                $label,
                $labelFormat,
                current_time('mysql'),
                $cn23,
                $trackingNumber,
                json_encode($detail),
                $type,
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

        $outwardLabelAndFormat = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT label, label_format, order_id, printed
                FROM ' . $wpdb->prefix . 'lpc_outward_label
                WHERE tracking_number = %s',
                $trackingNumber
            )
        );

        if (!empty($outwardLabelAndFormat[0])) {
            $label   = $outwardLabelAndFormat[0]->label;
            $orderId = $outwardLabelAndFormat[0]->order_id;
            $printed = !empty($outwardLabelAndFormat[0]->printed);

            $format = !empty($outwardLabelAndFormat[0]->label_format) ? $outwardLabelAndFormat[0]->label_format : LabelGenerationPayload::LABEL_FORMAT_PDF;
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

        $outwardCn23 = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT cn23, cn23_format
                FROM ' . $wpdb->prefix . 'lpc_outward_label
                WHERE tracking_number = %s',
                $trackingNumber
            )
        );

        $cn23   = '';
        $format = '';
        if (!empty($outwardCn23[0])) {
            $cn23   = $outwardCn23[0]->cn23;
            $format = !empty($outwardCn23[0]->cn23_format) ? $outwardCn23[0]->cn23_format : LabelGenerationPayload::LABEL_FORMAT_PDF;
        }

        return [
            'format' => $format,
            'cn23'   => $cn23,
        ];
    }

    public function getLabelsInfosForOrdersId($ordersId = [], $onlyNew = false) {
        global $wpdb;

        $ordersId = array_map(
            fn($orderId) => (int) $orderId,
            $ordersId
        );

        if (0 === count($ordersId)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ordersId), '%d'));

        if ($onlyNew) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated list of %d placeholders for the IN() clause; the actual values are passed to prepare().
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT order_id,
                            tracking_number,
                            label_format,
                            id,
                            detail,
                            label_created_at
                    FROM ' . $wpdb->prefix . 'lpc_outward_label
                    WHERE order_id IN (' . $placeholders . ') AND bordereau_id IS NULL
                    ORDER BY order_id DESC, label_created_at DESC',
                    $ordersId
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        }

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated list of %d placeholders for the IN() clause; the actual values are passed to prepare().
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT order_id,
                        tracking_number,
                        label_format,
                        id,
                        detail,
                        label_created_at
                FROM ' . $wpdb->prefix . 'lpc_outward_label
                WHERE order_id IN (' . $placeholders . ')
                ORDER BY order_id DESC, label_created_at DESC',
                $ordersId
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    }

    public function delete($trackingNumber) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . $wpdb->prefix . 'lpc_outward_label
                WHERE tracking_number = %s',
                $trackingNumber
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
                'UPDATE ' . $wpdb->prefix . 'lpc_outward_label
                SET `label` = NULL, `cn23` = NULL
                WHERE `label` IS NOT NULL 
                    AND `label_created_at` < DATE_SUB(NOW(), INTERVAL %d DAY)',
                $nbDays
            )
        );
    }

    public function truncate() {
        global $wpdb;

        return $wpdb->query('TRUNCATE TABLE ' . $wpdb->prefix . 'lpc_outward_label');
    }

    public function updateToVersion164() {
        global $wpdb;

        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $wpdb->prefix . 'lpc_outward_label');
        $updatedColumns = array_filter($columns,
            fn($column) => 'bordereau_id' === $column->Field || 'detail' === $column->Field);
        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label ADD COLUMN `bordereau_id` BIGINT(20) NULL');

        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label ADD COLUMN `detail` LONGTEXT NULL');

        if (OrderQueries::isHposActive()) {
            $wpdb->query(
                'INSERT INTO ' . $wpdb->prefix . 'lpc_outward_label (`order_id`, `bordereau_id`)
                SELECT `order_id`, `meta_value`
                FROM ' . $wpdb->prefix . 'wc_orders_meta
                WHERE `meta_key` = "lpc_bordereau_id"
                ON DUPLICATE KEY UPDATE `bordereau_id` = VALUES(`bordereau_id`)'
            );
        } else {
            $wpdb->query(
                'INSERT INTO ' . $wpdb->prefix . 'lpc_outward_label (`order_id`, `bordereau_id`)
                SELECT `post_id`, `meta_value`
                FROM ' . $wpdb->prefix . 'postmeta
                WHERE `meta_key` = "lpc_bordereau_id"
                ON DUPLICATE KEY UPDATE `bordereau_id` = VALUES(`bordereau_id`)'
            );
        }
    }

    public function updateToVersion165() {
        global $wpdb;

        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $wpdb->prefix . 'lpc_outward_label');
        $updatedColumns = array_filter(
            $columns,
            fn($column) => 'printed' === $column->Field
        );

        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label ADD COLUMN `printed` TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function updateToVersion172() {
        global $wpdb;

        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $wpdb->prefix . 'lpc_outward_label');
        $updatedColumns = array_filter(
            $columns,
            fn($column) => in_array($column->Field, ['status_id', 'label_type'])
        );
        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label ADD COLUMN `status_id` INT UNSIGNED NULL');
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label ADD COLUMN `label_type` VARCHAR(10) NOT NULL DEFAULT "CLASSIC"');
    }

    public function updateToVersion182() {
        global $wpdb;
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label CHANGE `order_id` `order_id` INT(20) UNSIGNED NOT NULL');
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label CHANGE `label_format` `label_format` VARCHAR(10) NULL');
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label CHANGE `tracking_number` `tracking_number` VARCHAR(20) NULL');
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label CHANGE `bordereau_id` `bordereau_id` INT(20) UNSIGNED NULL');
        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label CHANGE `detail` `detail` TEXT NULL');
    }

    public function updateToVersion192() {
        global $wpdb;

        $columns        = $wpdb->get_results('SHOW COLUMNS FROM ' . $wpdb->prefix . 'lpc_outward_label');
        $updatedColumns = array_filter(
            $columns,
            fn($column) => 'cn23_format' === $column->Field
        );

        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_outward_label ADD COLUMN `cn23_format` VARCHAR(10) NULL');
        $wpdb->query('UPDATE ' . $wpdb->prefix . 'lpc_outward_label SET `cn23_format` = "PDF" WHERE `cn23` IS NOT NULL');
    }

    public function addBordereauIdOnBordereauGeneration($outwardLabelIds, $bordereauId) {
        if (empty($outwardLabelIds) || empty($bordereauId)) {
            return;
        }

        global $wpdb;

        $bordereauId     = intval($bordereauId);
        $outwardLabelIds = array_map('intval', $outwardLabelIds);

        $values         = [];
        $preparedValues = [];
        foreach ($outwardLabelIds as $labelId) {
            $values[]         = '(%d, %d)';
            $preparedValues[] = $labelId;
            $preparedValues[] = $bordereauId;
        }

        $values = implode(', ', $values);

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $values is a generated list of (%d, %d) placeholder groups; the actual values are passed to prepare().
        return $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . $wpdb->prefix . 'lpc_outward_label (`id`, `bordereau_id`) VALUES ' . $values . ' ON DUPLICATE KEY UPDATE `bordereau_id`=VALUES(`bordereau_id`)',
                $preparedValues
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    }

    public function getOutwardLabelOrderIdOfTheDayWithoutBordereau() {
        global $wpdb;

        $daysPerPeriod  = intval(Helper::get_option('lpc_label_slip_day_to_substract', 1));
        $dayToSubstract = empty($daysPerPeriod) ? 0 : $daysPerPeriod - 1;

        $todayFirstHour = gmdate('Y-m-d 00:00:00', strtotime('-' . $dayToSubstract . ' day', time()));

        return $wpdb->get_col(
            $wpdb->prepare(
                'SELECT order_id FROM ' . $wpdb->prefix . 'lpc_outward_label WHERE label_created_at >= %s AND bordereau_id IS NULL',
                $todayFirstHour
            )
        );
    }

    public function getAllLabelDetailByOrderId($orderId) {
        global $wpdb;

        $orderId = intval($orderId);

        return $wpdb->get_col(
            $wpdb->prepare(
                'SELECT detail FROM ' . $wpdb->prefix . 'lpc_outward_label WHERE order_id = %d',
                $orderId
            )
        );
    }

    public function getBordereauFromTrackingNumber($trackingNumber) {
        global $wpdb;

        return $wpdb->get_col(
            $wpdb->prepare(
                'SELECT bordereau_id FROM ' . $wpdb->prefix . 'lpc_outward_label WHERE tracking_number = %s',
                $trackingNumber
            )
        );
    }

    public function getOrderLabels($orderId) {
        global $wpdb;

        $orderId = intval($orderId);

        return $wpdb->get_col(
            $wpdb->prepare(
                'SELECT tracking_number
                FROM ' . $wpdb->prefix . 'lpc_outward_label
                WHERE `order_id` = %d',
                $orderId
            )
        );
    }

    public function updatePrintedLabel($trackingNumbers) {
        if (empty($trackingNumbers)) {
            return;
        }

        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [$trackingNumbers];
        }

        global $wpdb;

        $placeholders = implode(', ', array_fill(0, count($trackingNumbers), '%s'));

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated list of %s placeholders for the IN() clause; the actual values are passed to prepare().
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . $wpdb->prefix . 'lpc_outward_label SET printed = 1 WHERE tracking_number IN (' . $placeholders . ')',
                $trackingNumbers
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    }

    public function insertFromThirdParty($orderId, $trackingNumber) {
        if (empty($orderId) || empty($trackingNumber)) {
            return false;
        }

        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . $wpdb->prefix . 'lpc_outward_label (`order_id`, `tracking_number`, `label_created_at`) VALUES (%d, %s, %s)',
                $orderId,
                $trackingNumber,
                current_time('mysql')
            )
        );
    }

    public function deleteBordereau($bordereauID) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . $wpdb->prefix . 'lpc_outward_label SET bordereau_id = NULL WHERE bordereau_id = %d',
                $bordereauID
            )
        );
    }

    public function getLabel($trackingNumber) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $wpdb->prefix . 'lpc_outward_label WHERE `tracking_number` = %s',
                $trackingNumber
            )
        );
    }

    public function setLabelStatusId($trackingNumber, $statusId) {
        if (empty($trackingNumber)) {
            return;
        }

        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . $wpdb->prefix . 'lpc_outward_label SET `status_id` = %d WHERE `tracking_number` = %s',
                $statusId,
                $trackingNumber
            )
        );
    }

    public function getMultiParcelsLabels($orderId): array {
        if (empty($orderId)) {
            return [];
        }

        global $wpdb;

        // Custom plugin table — no WordPress API available for direct table access.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT `tracking_number`, `label_type` 
                FROM ' . $wpdb->prefix . 'lpc_outward_label 
                WHERE `label_type` IN ("FOLLOWER", "MASTER") 
                    AND `order_id` = %d',
                $orderId
            )
        );

        $labels = [];
        foreach ($results as $oneResult) {
            $labels[$oneResult->tracking_number] = $oneResult->label_type;
        }

        return $labels;
    }

    public function getNumberOfLabels() {
        global $wpdb;

        // Custom plugin table — no WordPress API available for direct table access.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'lpc_outward_label');
    }

    public function getOrderIdByTrackingNumber($trackingNumber) {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                'SELECT order_id FROM ' . $wpdb->prefix . 'lpc_outward_label WHERE tracking_number = %s',
                $trackingNumber
            )
        );
    }
}
