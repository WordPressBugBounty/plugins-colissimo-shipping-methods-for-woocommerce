<?php

namespace Colissimo\Core;

defined('ABSPATH') || die('Restricted Access');

/**
 * Makes TCPDF resolve its font definition files inside our own vendor folder.
 *
 * TCPDF locates them through the global K_PATH_FONTS constant, defined in tcpdf_autoconfig.php.
 * Strauss prefixes the class names but not that constant, and it is guarded by !defined(): the
 * first plugin loading a copy of TCPDF sets it for the whole request. When another plugin wins
 * that race, our own instances look for their fonts in its directory and TCPDF dies with
 * "Could not include font definition file: helvetica".
 *
 * Handing AddFont() an explicit $fontfile makes TCPDF include the file we give it, and search the
 * directory that file lives in before ever falling back to K_PATH_FONTS.
 *
 * To be used by classes extending ColissimoTCPDF.
 */
trait PdfFonts {
    /**
     * @see \ColissimoTCPDF::AddFont()
     */
    public function AddFont($family, $style = '', $fontfile = '', $subset = 'default') {
        if (empty($fontfile)) {
            $fontfile = $this->getPdfFontFile($family, $style);
        }

        return parent::AddFont($family, $style, $fontfile, $subset);
    }

    /**
     * Lists the fonts we ship instead of opendir()ing K_PATH_FONTS, which emits a warning when the
     * directory another plugin defined does not exist. Such a warning would be printed inside the
     * PDF stream and corrupt the document.
     *
     * @see \ColissimoTCPDF::getFontsList()
     */
    protected function getFontsList() {
        $this->fontlist = [];

        $definitionFiles = glob($this->getPdfFontsFolder() . '*.php');

        foreach ($definitionFiles ?: [] as $definitionFile) {
            $this->fontlist[] = strtolower(basename($definitionFile, '.php'));
        }
    }

    protected function getPdfFontsFolder() {
        return LPC_FOLDER . 'vendor_prefixed' . DS . 'tecnickcom' . DS . 'tcpdf' . DS . 'fonts' . DS;
    }

    /**
     * Definition file for a family and a style, following TCPDF's own naming: the lowercased family
     * name suffixed with the bold and italic flags. Returns an empty string when we ship nothing
     * usable, leaving TCPDF's default resolution untouched.
     */
    protected function getPdfFontFile($family, $style) {
        $folder = $this->getPdfFontsFolder();
        $family = str_replace(' ', '', strtolower((string) $family));
        $style  = strtolower(preg_replace('/[^BI]/', '', strtoupper((string) $style)));

        // Only the helvetica variants are shipped, so a font we do not have falls back to it
        // instead of making TCPDF die on a missing definition file.
        $fallbackFamily = 'helvetica';

        $candidates = [
            $family . $style,
            $family,
            $fallbackFamily . $style,
            $fallbackFamily,
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($folder . $candidate . '.php')) {
                return $folder . $candidate . '.php';
            }
        }

        return '';
    }
}
