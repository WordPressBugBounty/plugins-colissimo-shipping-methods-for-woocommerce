<?php

namespace Colissimo\Init;

defined('ABSPATH') || die('Restricted Access');

class InitPublic {
    public function __construct() {
        new Pickup();
        new Tracking();
        new ReturnProducts();
        new Checkout();
    }
}
