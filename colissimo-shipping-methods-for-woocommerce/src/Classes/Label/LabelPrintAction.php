<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;
use Colissimo\Core\MergePdf;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class LabelPrintAction {
    const AJAX_TASK_NAME = 'label/print';
    const TRACKING_NUMBERS_VAR_NAME = 'lpc_tracking_numbers';
    const PRINT_LABEL_TYPE_OUTWARD_AND_INWARD = 'both';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var InwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?OutwardLabelDb $outwardLabelDb = null,
        ?InwardLabelDb $inwardLabelDb = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
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
        if (!current_user_can('lpc_print_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to all label print',
                ]
            );
        }

        $stringTrackingNumbers = Helper::getVar(self::TRACKING_NUMBERS_VAR_NAME);
        $needInvoice           = 'yes' === Helper::get_option('add_invoice_print_label', 'yes');

        $trackingNumbers = explode(',', $stringTrackingNumbers);
        try {
            $filesToMerge = [];
            $tempDir      = Helper::createUniqueTempDir();

            foreach ($trackingNumbers as $trackingNumber) {
                $isOutward = true;
                $label     = $this->getLabel($trackingNumber, $isOutward);

                if (false === $label || false === $label['label']) {
                    continue;
                }

                if (LabelGenerationPayload::LABEL_FORMAT_PDF === $label['format']) {
                    $labelFileName = $tempDir . 'label(' . sanitize_file_name($trackingNumber) . ').pdf';

                    Helper::getWpFilesystem()->put_contents($labelFileName, $label['label'], FS_CHMOD_FILE);
                    $filesToMerge[] = $labelFileName;
                }

                $cn23Data = $isOutward
                    ?
                    $this->outwardLabelDb->getCn23For($trackingNumber)
                    :
                    $this->inwardLabelDb->getCn23For($trackingNumber);

                $cn23Content = LabelGenerationPayload::LABEL_FORMAT_PDF === $cn23Data['format'] ? $cn23Data['cn23'] : '';

                if ($needInvoice) {
                    $lpcInvoiceGenerateAction = Register::get('invoiceGenerateAction');
                    $invoiceFilename          = $tempDir . 'invoice(' . (int) $label['order_id'] . ').pdf';

                    if (!in_array($invoiceFilename, $filesToMerge)) {
                        $lpcInvoiceGenerateAction->generateInvoice(
                            $label['order_id'],
                            $invoiceFilename,
                            MergePdf::DESTINATION__DISK
                        );
                        $filesToMerge[] = $invoiceFilename;

                        if (!empty($cn23Content)) {
                            $filesToMerge[] = $invoiceFilename;
                        }
                    }
                }

                if (!empty($cn23Content)) {
                    $cn23FileName    = $tempDir . 'cn23(' . sanitize_file_name($trackingNumber) . ').pdf';
                    Helper::getWpFilesystem()->put_contents($cn23FileName, $cn23Content, FS_CHMOD_FILE);
                    $filesToMerge[] = $cn23FileName;
                }

                if ($isOutward) {
                    $this->outwardLabelDb->updatePrintedLabel($trackingNumber);
                } else {
                    $this->inwardLabelDb->updatePrintedLabel($trackingNumber);
                }
            }

            if (!empty($filesToMerge)) {
                MergePdf::merge($filesToMerge, MergePdf::DESTINATION__INLINE);
                foreach ($filesToMerge as $fileToMerge) {
                    wp_delete_file($fileToMerge);
                }
            }

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

    public function getUrlForTrackingNumbers($trackingNumbers = [], $needInvoice = false) {
        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [$trackingNumbers];
        }

        $emptyLabels = [];
        foreach ($trackingNumbers as $trackingNumber) {
            $isOutward = true;
            $label     = $this->getLabel($trackingNumber, $isOutward);
            if ($isOutward && empty($label['label'])) {
                $emptyLabels[] = $trackingNumber;
            }
        }

        if (!empty($emptyLabels)) {
            $trackingNumbers = array_diff($trackingNumbers, $emptyLabels);

            if (empty($trackingNumbers)) {
                return false;
            }
        }

        $stringTrackingNumbers = implode(',', $trackingNumbers);

        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::TRACKING_NUMBERS_VAR_NAME . '=' . $stringTrackingNumbers;
    }

    protected function getLabel($trackingNumber, &$isOutward) {
        $label = $this->outwardLabelDb->getLabelFor($trackingNumber);

        if (!empty($label['label'])) {
            $isOutward = true;

            return $label;
        }

        $label = $this->inwardLabelDb->getLabelFor($trackingNumber);

        if (!empty($label['label'])) {
            $isOutward = false;

            return $label;
        }

        return false;
    }
}
