<?php

namespace Colissimo\Core;

use Colissimo\Classes\Slip\SlipDb;
use Colissimo\Classes\Label\InwardLabelDb;
use Colissimo\Classes\Label\OutwardLabelDb;

defined('ABSPATH') || die('Restricted Access');

class DbDefinition {
    public function defineTableLabel() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if (is_multisite()) {
            $currentBlog = get_current_blog_id();
            $sites       = get_sites();

            foreach ($sites as $site) {
                if (is_object($site)) {
                    $site = get_object_vars($site);
                }
                switch_to_blog($site['blog_id']);
                $this->createTables();
            }

            switch_to_blog($currentBlog);
        } else {
            $this->createTables();
        }
    }

    private function createTables() {
        $outwardLabelDb = new OutwardLabelDb();
        $outwardSql     = $outwardLabelDb->getTableDefinition();
        dbDelta($outwardSql);

        $inwardLabelDb = new InwardLabelDb();
        $inwardSql     = $inwardLabelDb->getTableDefinition();
        dbDelta($inwardSql);

        $bordereauDb  = new SlipDb();
        $bordereauSql = $bordereauDb->getTableDefinition();
        dbDelta($bordereauSql);
    }
}
