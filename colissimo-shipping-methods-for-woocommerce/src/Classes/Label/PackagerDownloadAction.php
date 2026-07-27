<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class PackagerDownloadAction {
    const AJAX_TASK_NAME = 'label/packager/download';
    const TRACKING_NUMBERS_VAR_NAME = 'lpc_tracking_numbers';

    /** @var LabelPackager */
    protected $labelPackager;
    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var InwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?LabelPackager $labelPackager = null,
        ?OutwardLabelDb $outwardLabelDb = null,
        ?InwardLabelDb $inwardLabelDb = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
        $this->labelPackager  = new LabelPackager();
        $this->outwardLabelDb = Register::get('outwardLabelDb');
        $this->inwardLabelDb  = Register::get('inwardLabelDb');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_download_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to labels package download',
                ]
            );
        }

        if (!class_exists('ZipArchive')) {
            header('HTTP/1.0 424 Failed Dependency');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'The ext-zip PHP extension is required to download labels package',
                ]
            );
        }

        $trackingNumbers = explode(',', Helper::getVar(self::TRACKING_NUMBERS_VAR_NAME));

        try {
            $filename = basename('Colissimo_' . gmdate('Y-m-d_H-i') . '.zip');
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: Binary');
            header("Content-disposition: attachment; filename=\"$filename\"");

            $this->labelPackager->generateZip($trackingNumbers);
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

    public function getUrlForTrackingNumbers(array $trackingNumbers, bool $isOutward = true) {
        $emptyLabels = [];
        foreach ($trackingNumbers as $trackingNumber) {
            if ($isOutward) {
                $label = $this->outwardLabelDb->getLabelFor($trackingNumber);
            } else {
                $label = $this->inwardLabelDb->getLabelFor($trackingNumber);
            }

            if (empty($label['label'])) {
                $emptyLabels[] = $trackingNumber;
            }
        }

        if (!empty($emptyLabels)) {
            $trackingNumbers = array_diff($trackingNumbers, $emptyLabels);

            if (empty($trackingNumbers)) {
                return false;
            }
        }

        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::TRACKING_NUMBERS_VAR_NAME . '=' . implode(',', $trackingNumbers);
    }
}
