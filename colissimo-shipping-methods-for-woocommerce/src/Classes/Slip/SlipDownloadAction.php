<?php

namespace Colissimo\Classes\Slip;

use Colissimo\Api\SlipGenerationApi;
use Colissimo\Core\Ajax;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class SlipDownloadAction {
    const AJAX_TASK_NAME = 'bordereau/download';
    const BORDEREAU_ID_VAR_NAME = 'lpc_bordereau_id';

    /** @var SlipGenerationApi */
    protected $bordereauGenerationApi;
    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var SlipDb */
    protected $bordereauDb;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?SlipGenerationApi $bordereauGenerationApi = null,
        ?SlipDb $bordereauDb = null
    ) {
        $this->ajaxDispatcher         = Register::get('ajaxDispatcher');
        $this->bordereauGenerationApi = new SlipGenerationApi();
        $this->bordereauDb            = Register::get('bordereauDb');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_download_bordereau')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to bordereau download',
                ]
            );
        }

        $deliverySlipId = Helper::getVar(self::BORDEREAU_ID_VAR_NAME, 0, 'int');
        try {
            $deliverySlip = $this->bordereauDb->getDeliverySlipByColissimoId($deliverySlipId);
            if (empty($deliverySlip)) {
                throw new Exception(esc_html__('File not found', 'colissimo-shipping-methods-for-woocommerce'));
            }

            header('Content-Type: application/pdf');
            header('Content-Transfer-Encoding: Binary');
            header('Content-Disposition: attachment; filename="Bordereau(' . $deliverySlipId . ').pdf"');
            header('Content-Length: ' . mb_strlen($deliverySlip, '8bit'));

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF stream sent as a file download.
            echo $deliverySlip;
            exit;
        } catch (Exception $e) {
            header('HTTP/1.0 404 Not Found');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function getUrlForBordereau($bordereauId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::BORDEREAU_ID_VAR_NAME . '=' . (int) $bordereauId;
    }

    public function getBorderauDownloadLink($bordereauNumber) {
        if (!empty($bordereauNumber)) {
            $bordereauDownloadUrl = $this->getUrlForBordereau($bordereauNumber);

            return $bordereauDownloadUrl;
        }
    }
}
