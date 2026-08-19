<?php

namespace Colissimo\Core;

defined('ABSPATH') || die('Restricted Access');

class MergePdf {
    const DESTINATION__INLINE = 'I';
    const DESTINATION__DISK = 'F';
    const DESTINATION__DISK_DOWNLOAD = 'FD';

    const DEFAULT_DESTINATION = self::DESTINATION__INLINE;
    const DEFAULT_MERGED_FILE_NAME = __DIR__ . '/merged-files.pdf';

    public static function merge($files, $destination = null, $outputPath = null) {
        if (empty($destination)) {
            $destination = self::DEFAULT_DESTINATION;
        }

        if (empty($outputPath)) {
            $outputPath = self::DEFAULT_MERGED_FILE_NAME;
        }

        $pdf = new FpdiPdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        self::join($pdf, $files);
        $pdf->Output($outputPath, $destination);
    }

    private static function join($pdf, $fileList) {
        if (empty($fileList) || !is_array($fileList)) {
            die('invalid file list');
        }

        foreach ($fileList as $file) {
            self::addFile($pdf, $file);
        }
    }

    private static function addFile($pdf, $file) {
        $numPages = $pdf->setSourceFile($file);

        if (empty($numPages) || $numPages < 1) {
            return;
        }

        for ($x = 1; $x <= $numPages; $x ++) {
            $pdf->AddPage();
            $pdf->useTemplate($pdf->importPage($x), 0, 0, null, null, true);
            $pdf->endPage();
        }
    }
}
