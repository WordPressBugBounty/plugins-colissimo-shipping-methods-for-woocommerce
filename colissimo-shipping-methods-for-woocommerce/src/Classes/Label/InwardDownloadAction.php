<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;
use Colissimo\Core\MergePdf;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class InwardDownloadAction {
    const AJAX_TASK_NAME = 'label/inward/download';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_label_tracking_number';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var InwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?InwardLabelDb $inwardLabelDb = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
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
                    'message' => 'unauthorized access to inward label download',
                ]
            );
        }

        $trackingNumber = Helper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        try {
            $label        = $this->inwardLabelDb->getLabelFor($trackingNumber);
            $labelContent = $label['label'];
            if (empty($labelContent)) {
                throw new Exception('No label content');
            }

            $tempDir            = Helper::createUniqueTempDir();
            $fileToDownloadName = $tempDir . 'Colissimo.inward(' . sanitize_file_name($trackingNumber) . ').pdf';
            $labelFileName      = 'inward_label.pdf';
            $filesToMerge       = [];
            Helper::getWpFilesystem()->put_contents($tempDir . $labelFileName, $labelContent, FS_CHMOD_FILE);

            $filesToMerge[] = $tempDir . $labelFileName;

            $cn23Data    = $this->inwardLabelDb->getCn23For($trackingNumber);
            $cn23Content = LabelGenerationPayload::LABEL_FORMAT_PDF === $cn23Data['format'] ? $cn23Data['cn23'] : '';
            if (!empty($cn23Content)) {
                Helper::getWpFilesystem()->put_contents($tempDir . 'inward_cn23.pdf', $cn23Content, FS_CHMOD_FILE);
                $filesToMerge[] = $tempDir . 'inward_cn23.pdf';
            }
            MergePdf::merge($filesToMerge, MergePdf::DESTINATION__DISK_DOWNLOAD, $fileToDownloadName);
            foreach ($filesToMerge as $fileToMerge) {
                wp_delete_file($fileToMerge);
            }
            wp_delete_file($fileToDownloadName);
            Helper::getWpFilesystem()->rmdir($tempDir, true);
        } catch (Exception $e) {
            header('HTTP/1.0 404 Not Found');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function getUrlForTrackingNumber($trackingNumber) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $trackingNumber;
    }
}
