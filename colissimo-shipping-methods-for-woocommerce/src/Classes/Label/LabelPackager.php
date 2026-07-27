<?php

namespace Colissimo\Classes\Label;

use Colissimo\Classes\Order\InvoiceGenerateAction;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use Colissimo\Core\MergePdf;
use ZipArchive;

defined('ABSPATH') || die('Restricted Access');

class LabelPackager {
    /** @var InvoiceGenerateAction */
    protected $invoiceGenerateAction;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var InwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        ?OutwardLabelDb $outwardLabelDb = null,
        ?InwardLabelDb $inwardLabelDb = null
    ) {
        $this->invoiceGenerateAction = Register::get('invoiceGenerateAction');
        $this->outwardLabelDb        = Register::get('outwardLabelDb');
        $this->inwardLabelDb         = Register::get('inwardLabelDb');
    }

    public function generateZip(array $trackingNumbers) {
        $zip      = new ZipArchive();
        $filename = tempnam(sys_get_temp_dir(), 'colissimo_');
        $tmpFiles = [];

        try {
            $zip->open($filename, ZipArchive::OVERWRITE);

            foreach ($trackingNumbers as $trackingNumber) {
                $label     = $this->outwardLabelDb->getLabelFor($trackingNumber);
                $isOutward = true;
                $isInward  = false;

                if (empty($label['label'])) {
                    $label = $this->inwardLabelDb->getLabelFor($trackingNumber);

                    $isOutward = false;
                    $isInward  = true;
                }

                if (empty($label['label'])) {
                    continue;
                }

                $orderId = $label['order_id'];

                $zipDirname = $orderId;
                $zip->addEmptyDir($zipDirname);

                $labelFormat = !empty($label['format']) ? $label['format'] : LabelGenerationPayload::LABEL_FORMAT_PDF;
                if ($isOutward) {
                    $zip->addFromString(
                        $zipDirname . '/outward_label(' . $trackingNumber . ').' . strtolower($labelFormat),
                        $label['label']
                    );

                    if ('yes' === Helper::get_option('add_invoice_zip_label', 'yes')) {
                        $tmpFiles[] = $invoiceFilename = sys_get_temp_dir() . DS . $orderId . '_invoice.pdf';

                        $this->invoiceGenerateAction->generateInvoice(
                            $orderId,
                            $invoiceFilename,
                            MergePdf::DESTINATION__DISK
                        );
                        $zip->addFile($invoiceFilename, $zipDirname . '/invoice(' . $orderId . ').pdf');
                    }

                    $outwardCn23 = $this->outwardLabelDb->getCn23For($trackingNumber);
                    if (!empty($outwardCn23['cn23'])) {
                        $cn23Format = !empty($outwardCn23['format']) ? $outwardCn23['format'] : LabelGenerationPayload::LABEL_FORMAT_PDF;
                        $zip->addFromString($zipDirname . '/outward_cn23(' . $trackingNumber . ').' . strtolower($cn23Format), $outwardCn23['cn23']);
                    }
                }

                if ($isInward) {
                    $zip->addFromString(
                        $zipDirname . '/inward_label(' . $trackingNumber . ').' . strtolower($labelFormat),
                        $label['label']
                    );

                    $inwardCn23 = $this->inwardLabelDb->getCn23For($trackingNumber);
                    if (!empty($inwardCn23['cn23'])) {
                        $cn23Format = !empty($inwardCn23['format']) ? $inwardCn23['format'] : LabelGenerationPayload::LABEL_FORMAT_PDF;
                        $zip->addFromString($zipDirname . '/inward_cn23(' . $trackingNumber . ').' . strtolower($cn23Format), $inwardCn23['cn23']);
                    }
                }
            }

            $zip->close();

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a generated ZIP file download to the browser.
            readfile($filename);
        } finally {
            array_map(
                function ($tmpFile) {
                    wp_delete_file($tmpFile);
                },
                $tmpFiles
            );

            wp_delete_file($filename);
        }
    }
}
