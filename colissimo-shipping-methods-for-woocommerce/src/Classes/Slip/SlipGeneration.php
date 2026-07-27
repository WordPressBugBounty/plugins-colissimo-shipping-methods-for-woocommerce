<?php

namespace Colissimo\Classes\Slip;

use Colissimo\Api\SlipGenerationApi;
use Colissimo\Classes\Settings\AdminNotices;
use Colissimo\Core\Ajax;
use Colissimo\Helpers\Helper;
use Colissimo\Classes\Email\OutwardLabelEmailManager;
use Colissimo\Core\Register;
use Colissimo\Classes\Label\OutwardLabelDb;
use Exception;
use WC_Order;

defined('ABSPATH') || die('Restricted Access');

class SlipGeneration {
    const MAX_LABEL_PER_BORDEREAU = 50;
    const AJAX_TASK_NAME = 'bordereau/generate_day';

    /** @var SlipGenerationApi */
    protected $bordereauGenerationApi;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var AdminNotices */
    protected $lpcAdminNotices;
    /** @var SlipDb */
    protected $bordereauDb;

    public function __construct(
        ?SlipGenerationApi $bordereauGenerationApi = null,
        ?OutwardLabelDb $outwardLabelDb = null,
        ?Ajax $ajaxDispatcher = null,
        ?AdminNotices $lpcAdminNotices = null,
        ?SlipDb $bordereauDb = null
    ) {
        $this->bordereauGenerationApi = new SlipGenerationApi();
        $this->outwardLabelDb         = Register::get('outwardLabelDb');
        $this->ajaxDispatcher         = Register::get('ajaxDispatcher');
        $this->lpcAdminNotices        = Register::get('lpcAdminNotices');
        $this->bordereauDb            = Register::get('bordereauDb');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    /**
     * @param WC_Order[] $orders
     *
     * @return string|null Return the bordereau if only one bordereau was generated, else null.
     */
    public function generate(array $orders) {
        $ordersId = array_map(
            fn(WC_Order $order) => $order->get_id(),
            $orders
        );

        return $this->generateFromOrdersId($ordersId);
    }

    protected function prepareBatch(array $parcelNumbers) {
        return array_chunk($parcelNumbers, self::MAX_LABEL_PER_BORDEREAU, true);
    }

    private function getOutwardLabelIdByTrackingNumber($trackingNumbers, $outwardIdByTrackingNumber) {
        $outwardLabelIds = [];

        foreach ($trackingNumbers as $trackingNumber) {
            if (in_array($trackingNumber, array_keys($outwardIdByTrackingNumber))) {
                $outwardLabelIds[] = intval($outwardIdByTrackingNumber[$trackingNumber]);
            }
        }

        return $outwardLabelIds;
    }

    public function control() {
        if (!current_user_can('lpc_manage_bordereau')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'Unauthorized access',
                ]
            );
        }

        $outwardLabelsOrderIds = $this->outwardLabelDb->getOutwardLabelOrderIdOfTheDayWithoutBordereau();

        if (!empty($outwardLabelsOrderIds)) {
            $this->generateFromOrdersId($outwardLabelsOrderIds);
        }

        wp_safe_redirect(admin_url('admin.php?page=wc_colissimo_view&tab=slip-history'));
    }

    private function generateFromOrdersId($ordersId) {
        $ordersLabelsInformation = $this->outwardLabelDb->getLabelsInfosForOrdersId($ordersId, true);

        $orderIdByOutwardsTrackingNumbers = [];
        $outwardIdByTrackingNumber        = [];

        foreach ($ordersLabelsInformation as $oneOrdersLabelsInformation) {
            if (!empty($oneOrdersLabelsInformation->tracking_number) && !empty($oneOrdersLabelsInformation->order_id)) {
                $orderIdByOutwardsTrackingNumbers[$oneOrdersLabelsInformation->tracking_number] = $oneOrdersLabelsInformation->order_id;
                $outwardIdByTrackingNumber[$oneOrdersLabelsInformation->tracking_number]        = $oneOrdersLabelsInformation->id;
            }
        }

        $trackingNumbersPerBatch = $this->prepareBatch($orderIdByOutwardsTrackingNumbers);

        foreach ($trackingNumbersPerBatch as $batchOfTrackingNumbers) {
            $outwardLabelIds = $this->getOutwardLabelIdByTrackingNumber(array_keys($batchOfTrackingNumbers), $outwardIdByTrackingNumber);
            try {
                $response = $this->bordereauGenerationApi->generateBordereau(array_keys($batchOfTrackingNumbers));
                $retrievedBordereau = $response['<jsonInfos>'];
                $deliverySlip = $response['<deliveryPaper>'];
            } catch (Exception $e) {
                $this->lpcAdminNotices->add_notice('lpc_notice', 'notice-error', $e->getMessage());
                continue;
            }
            $bordereauId = $retrievedBordereau['bordereauHeader']['bordereauNumber'];

            $this->bordereauDb->insert(
                $bordereauId,
                gmdate(
                    'Y-m-d H:i:s',
                    substr(
                        $retrievedBordereau['bordereauHeader']['publishingDate'],
                        0,
                        strlen($retrievedBordereau['bordereauHeader']['publishingDate']) - 3
                    )
                ),
                $deliverySlip
            );

            $newStatus = Helper::get_option('lpc_order_status_on_bordereau_generated');

            $ordersIdForBatch = array_unique($batchOfTrackingNumbers);

            foreach ($ordersIdForBatch as $orderId) {
                $order = wc_get_order($orderId);
                if (empty($order)) {
                    continue;
                }

                $this->outwardLabelDb->addBordereauIdOnBordereauGeneration($outwardLabelIds, $bordereauId);
                if (!empty($newStatus) && 'unchanged_order_status' !== $newStatus) {
                    $order->update_status($newStatus);
                }

                $email_outward_label = Helper::get_option(OutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, 'no');
                if (OutwardLabelEmailManager::ON_BORDEREAU_GENERATION_OPTION === $email_outward_label) {
                    /**
                     * Action when the shipping label has been sent by email
                     *
                     * @since 1.6
                     */
                    do_action(
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook, correctly prefixed with "lpc_".
                        'lpc_outward_label_generated_to_email',
                        ['order' => $order]
                    );
                }
            }
        }

        if (!empty($bordereauId) && 1 === count($trackingNumbersPerBatch)) {
            // when only 1 bordereau is generated, we return it
            return $bordereauId;
        }

        return null;
    }

    public function getGenerationBordereauEndDayUrl() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }
}
