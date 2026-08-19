<?php

namespace Colissimo\Core;

use ColissimoTCPDF;

defined('ABSPATH') || die('Restricted Access');

class Pdf extends ColissimoTCPDF {
    use PdfFonts;

    // TCPDF ships these as global PDF_* constants, which strauss does not prefix either: another
    // plugin bundling TCPDF may have defined them first, with its own values.
    const PAGE_ORIENTATION = 'P';
    const PAGE_FORMAT      = 'A4';
    const UNIT             = 'mm';
    const MARGIN_LEFT      = 15;
    const MARGIN_RIGHT     = 15;
    const MARGIN_HEADER    = 5;
    const MARGIN_FOOTER    = 10;
}
