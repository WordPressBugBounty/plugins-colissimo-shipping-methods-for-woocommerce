<?php

namespace Colissimo\Core;

use Colissimo\Classes\Slip\SlipDb;
use Colissimo\Classes\Label\InwardLabelDb;
use Colissimo\Classes\Label\OutwardLabelDb;

defined('ABSPATH') || die('Restricted Access');

class DbDefinition {
    /**
     * Update the DB structure of every site
     *
     * @return array The changes applied, empty if the structure was already up to date
     */
    public function defineTableLabel(): array {
        if (!is_multisite()) {
            return $this->createOrUpdateTables();
        }

        $currentBlog = get_current_blog_id();
        $changes     = [];

        foreach (get_sites() as $site) {
            if (is_object($site)) {
                $site = get_object_vars($site);
            }
            switch_to_blog($site['blog_id']);
            $changes = array_merge($changes, $this->createOrUpdateTables());
        }

        switch_to_blog($currentBlog);

        return $changes;
    }

    /**
     * @return array The changes applied, empty if the structure was already up to date
     */
    public function createOrUpdateTables(): array {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $outwardLabelDb = new OutwardLabelDb();
        $outwardSql     = $outwardLabelDb->getTableDefinition();
        $changes        = $this->applyTableDefinition($outwardSql);

        $inwardLabelDb = new InwardLabelDb();
        $inwardSql     = $inwardLabelDb->getTableDefinition();
        $changes       = array_merge($changes, $this->applyTableDefinition($inwardSql));

        $bordereauDb  = new SlipDb();
        $bordereauSql = $bordereauDb->getTableDefinition();

        return array_merge($changes, $this->applyTableDefinition($bordereauSql));
    }

    /**
     * @param string $sql A CREATE TABLE statement
     *
     * @return array The changes applied
     */
    private function applyTableDefinition(string $sql): array {
        // dbDelta requires a single space as separator
        return dbDelta(preg_replace('/[ \t]+/', ' ', $sql));
    }
}
