<?php

namespace Colissimo\Core;

use Colissimo\Api\TrackingApi;
use Colissimo\Classes\Label\LabelPurge;
use DateTimeZone;
use WC_DateTime;

defined('ABSPATH') || die('Restricted Access');

class Cron {
    const CRON_START_HOUR = 8;
    const CRON_END_HOUR = 20;

    public function init() {
        $this->updateAllStatuses();
        $this->purgeLabels();
    }

    protected function updateAllStatuses() {
        // Define action
        add_action(
            'update_colissimo_statuses',
            function () {
                $now = new WC_DateTime();
                $now->setTimezone(new DateTimeZone(wc_timezone_string()));
                $actualHour = $now->date('G');

                if (null !== $actualHour && $actualHour >= self::CRON_START_HOUR && $actualHour < self::CRON_END_HOUR) {
                    $unifiedTrackingApi = new TrackingApi();
                    $unifiedTrackingApi->updateAllStatuses();
                }
            }
        );

        // Define event
        register_activation_hook(
            LPC_MAIN_FILE,
            function () {
                if (!wp_next_scheduled('update_colissimo_statuses')) {
                    wp_schedule_event(time(), 'hourly', 'update_colissimo_statuses');
                }
            }
        );

        // Deactivation
        register_deactivation_hook(
            LPC_MAIN_FILE,
            function () {
                wp_clear_scheduled_hook('update_colissimo_statuses');
            }
        );
    }

    protected function purgeLabels() {
        // Define action
        add_action(
            'purge_colissimo_labels',
            function () {
                $lpcLabelPurge = new LabelPurge();
                $lpcLabelPurge->purgeReadyLabels();
            }
        );

        // Define event
        register_activation_hook(
            LPC_MAIN_FILE,
            function () {
                if (LabelPurge::getPurgeDelay() > 0 && !wp_next_scheduled('purge_colissimo_labels')) {
                    wp_schedule_event(time(), 'daily', 'purge_colissimo_labels');
                }
            }
        );

        // Deactivation
        register_deactivation_hook(
            LPC_MAIN_FILE,
            function () {
                wp_clear_scheduled_hook('purge_colissimo_labels');
            }
        );
    }
}
