<?php

namespace Colissimo\Core;

use Colissimo\Vendor\setasign\Fpdi\Tcpdf\Fpdi;

defined('ABSPATH') || die('Restricted Access');

class FpdiPdf extends Fpdi {
    use PdfFonts;
}
