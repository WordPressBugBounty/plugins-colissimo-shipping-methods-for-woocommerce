<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;
use Colissimo\Core\MergePdf;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class OutwardDownloadAction {
    const AJAX_TASK_NAME = 'label/outward/download';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_label_tracking_number';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?OutwardLabelDb $outwardLabelDb = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
        $this->outwardLabelDb = Register::get('outwardLabelDb');
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
                    'message' => 'unauthorized access to outward label download',
                ]
            );
        }

        $trackingNumber = Helper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        try {
            $label        = $this->outwardLabelDb->getLabelFor($trackingNumber);
            $labelContent = $label['label'];
            if (empty($labelContent)) {
                throw new Exception('No label content');
            }

            $fileToDownloadName = 'Colissimo.outward(' . sanitize_file_name($trackingNumber) . ').pdf';
            $labelFile          = 'outward_label.pdf';
            $filesToMerge       = [];
            $tempDir            = Helper::createUniqueTempDir();
            Helper::getWpFilesystem()->put_contents($tempDir . $labelFile, $labelContent, FS_CHMOD_FILE);

            $labelFilename  = $tempDir . $labelFile;
            $filesToMerge[] = $labelFilename;

            $needInvoice = 'yes' === Helper::get_option('add_invoice_download_label', 'yes');

            $invoiceFilename = null;
            if ($needInvoice) {
                $lpcInvoiceGenerateAction = Register::get('invoiceGenerateAction');
                $invoiceFilename          = $tempDir . 'invoice.pdf';
                $lpcInvoiceGenerateAction->generateInvoice($label['order_id'], $invoiceFilename, MergePdf::DESTINATION__DISK);
                $filesToMerge[] = $invoiceFilename;
            }

            $cn23Filename = null;
            $cn23Data     = $this->outwardLabelDb->getCn23For($trackingNumber);
            $cn23Content  = LabelGenerationPayload::LABEL_FORMAT_PDF === $cn23Data['format'] ? $cn23Data['cn23'] : '';
            if (!empty($cn23Content)) {
                if ($needInvoice) {
                    $filesToMerge[] = $invoiceFilename;
                }
                Helper::getWpFilesystem()->put_contents($tempDir . 'outward_cn23.pdf', $cn23Content, FS_CHMOD_FILE);
                $cn23Filename   = $tempDir . 'outward_cn23.pdf';
                $filesToMerge[] = $cn23Filename;
            }

            /**
             * Filter on the content of the downloaded PDF label
             *
             * @since 1.7.6
             */
            $filesToMerge = apply_filters(
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook, correctly prefixed with "lpc_".
                'lpc_pdf_label',
                $filesToMerge,
                $label,
                $labelFilename,
                $invoiceFilename,
                $cn23Filename
            );

            MergePdf::merge($filesToMerge, MergePdf::DESTINATION__DISK_DOWNLOAD, $tempDir . $fileToDownloadName);
            foreach ($filesToMerge as $fileToMerge) {
                wp_delete_file($fileToMerge);
            }
            wp_delete_file($tempDir . $fileToDownloadName);
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
