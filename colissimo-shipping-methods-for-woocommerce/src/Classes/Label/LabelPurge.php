<?php

namespace Colissimo\Classes\Label;

use Colissimo\Classes\Slip\SlipDb;
use Colissimo\Core\Register;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class LabelPurge {
    /** @var InwardLabelDb */
    protected $inwardLabelDb;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var SlipDb */
    protected $bordereauDb;

    public function __construct(
        ?InwardLabelDb $inwardLabelDb = null,
        ?OutwardLabelDb $outwardLabelDb = null,
        ?SlipDb $bordereauDb = null
    ) {
        $this->inwardLabelDb = Register::get('inwardLabelDb');
        $this->outwardLabelDb = Register::get('outwardLabelDb');
        $this->bordereauDb = Register::get('bordereauDb');
    }

    public function purgeReadyLabels() {
        $nbDays = (int) Helper::get_option('lpc_day_purge', 30);
        if (empty($nbDays)) {
            return;
        }

        $this->purgeLabels($nbDays);
        $this->purgeDeliverySlips($nbDays);
    }

    public function purgeLabels(int $nbDays) {
        Logger::debug(__METHOD__ . ' purging old labels');

        $this->outwardLabelDb->purgeLabels($nbDays);
        $this->inwardLabelDb->purgeLabels($nbDays);
    }

    public function deleteLabels(array $orderIds): void {
        if (empty($orderIds)) {
            return;
        }

        Logger::debug(
            __METHOD__ . ' deleting labels from manual action',
            [
                'orderIds' => $orderIds,
            ]
        );

        $outwardLabels = $this->outwardLabelDb->getLabelsInfosForOrdersId($orderIds);
        foreach ($outwardLabels as $outwardLabel) {
            if (!empty($outwardLabel->tracking_number)) {
                $this->outwardLabelDb->delete($outwardLabel->tracking_number);
            }
        }

        $inwardLabels = $this->inwardLabelDb->getLabelsInfosForOrdersId($orderIds);
        foreach ($inwardLabels as $inwardLabel) {
            if (!empty($inwardLabel->tracking_number)) {
                $this->inwardLabelDb->delete($inwardLabel->tracking_number);
            }
        }

        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if (empty($order)) {
                continue;
            }

            $order->delete_meta_data(LabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY);
            $order->delete_meta_data(LabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY);
            $order->save();
        }
    }

    private function purgeDeliverySlips(int $nbDays) {
        Logger::debug(
            __METHOD__ . ' purge old delivery slips',
            [
                'days' => $nbDays,
            ]
        );

        $this->bordereauDb->purge($nbDays);
    }
}
