<?php
// Legitimate SQL queries on custom takes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

namespace Colissimo\Classes\Slip;

use Colissimo\Api\SlipGenerationApi;
use Colissimo\Helpers\Logger;

defined('ABSPATH') || die('Restricted Access');

class SlipDb {
    /** @var SlipGenerationApi */
    protected $bordereauGenerationApi;

    public function __construct(?SlipGenerationApi $bordereauGenerationApi = null) {
        $this->bordereauGenerationApi = new SlipGenerationApi();
    }

    public function getTableDefinition(): string {
        global $wpdb;

        return 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'lpc_bordereau (
            id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            bordereau_external_id INT(20) UNSIGNED NOT NULL,
            created_at            DATETIME         NULL,
            delivery_slip         MEDIUMBLOB       NULL,
            PRIMARY KEY (id)
        ) ' . $wpdb->get_charset_collate();
    }

    public function insert($bordereauId, $creationDate, $deliverySlip) {
        if (empty($bordereauId) || empty($creationDate)) {
            return false;
        }

        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . $wpdb->prefix . 'lpc_bordereau(`bordereau_external_id`, `created_at`, `delivery_slip`) VALUES(%d, %s, %s)',
                $bordereauId,
                gmdate('Y-m-d H:i:s', strtotime($creationDate)),
                $deliverySlip
            )
        );
    }

    public function getBordereauIdByOrderId($orderId) {
        if (empty($orderId)) {
            return 0;
        }

        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT bordereau_id FROM ' . $wpdb->prefix . 'lpc_outward_label WHERE order_id = %d',
                $orderId
            )
        );

        if (!empty($results)) {
            return $results[0]->bordereau_id;
        }

        return 0;
    }

    public function getDeliverySlipByColissimoId(int $deliverySlipId): ?string {
        global $wpdb;

        $deliverySlip = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT delivery_slip FROM ' . $wpdb->prefix . 'lpc_bordereau WHERE `bordereau_external_id` = %d',
                $deliverySlipId
            )
        );

        return $deliverySlip->delivery_slip ?? null;
    }

    public function purge(int $nbDays) {
        if (empty($nbDays)) {
            return;
        }

        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . $wpdb->prefix . 'lpc_bordereau
                SET `delivery_slip` = null
                WHERE `created_at` < DATE_SUB(NOW(), INTERVAL %d DAY)',
                $nbDays
            )
        );
    }

    public function updateToVersion182() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($this->getTableDefinition());

        global $wpdb;

        $deliverySlipIds = $wpdb->get_col('SELECT DISTINCT bordereau_id FROM ' . $wpdb->prefix . 'lpc_outward_label WHERE bordereau_id IS NOT null and bordereau_id != 0');
        if (empty($deliverySlipIds)) {
            return;
        }

        Logger::error(
            'Previous version too old to load the delivery slips from the Colissimo API',
            [
                'delivery_slip_ids' => implode(',', $deliverySlipIds),
            ]
        );
    }

    public function updateToVersion282() {
        global $wpdb;

        $columns = $wpdb->get_results('SHOW COLUMNS FROM ' . $wpdb->prefix . 'lpc_bordereau');
        $updatedColumns = array_filter($columns,
            fn($column) => 'delivery_slip' === $column->Field
        );

        if (!empty($updatedColumns)) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . $wpdb->prefix . 'lpc_bordereau ADD COLUMN `delivery_slip` MEDIUMBLOB null');
    }
}
