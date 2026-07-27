<?php

namespace Colissimo\Classes\Settings;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Core\Register;

defined('ABSPATH') || die('Restricted Access');

class Download {
    const AJAX_TASK_NAME_LOGS = 'logs/download';
    const AJAX_TASK_NAME_DOC = 'doc/download';
    const AJAX_TASK_NAME_DOC_EN = 'docEN/download';
    const DOC_FILE_PATH = LPC_RESOURCE_FOLDER . 'doc.pdf';
    const DOC_EN_FILE_PATH = LPC_RESOURCE_FOLDER . 'docEN.pdf';

    /** @var Ajax */
    protected $ajaxDispatcher;

    public function __construct(
        ?Ajax $ajaxDispatcher = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_LOGS, [$this, 'logs']);
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_DOC, [$this, 'doc']);
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_DOC_EN, [$this, 'docEN']);
    }

    public function logs() {
        if (!current_user_can('lpc_manage_settings')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'Unauthorized access',
                ]
            );
        }

        $logs = Logger::get_logs(Logger::ALL_LINES);
        if (!empty($logs)) {
            $this->downloadFile(
                [
                    'content'  => str_replace('<br />', PHP_EOL, $logs),
                    'fileName' => 'colissimo.log',
                ]
            );
        } else {
            esc_html_e('The logs file is empty', 'colissimo-shipping-methods-for-woocommerce');
        }
    }

    public function doc() {
        if (!current_user_can('manage_options')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'Unauthorized access',
                ]
            );
        }

        $this->downloadFile(
            [
                'filePath' => self::DOC_FILE_PATH,
                'fileName' => 'Guide Colissimo pour WordPress.pdf',
            ]
        );
    }

    public function docEN() {
        if (!current_user_can('manage_options')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'Unauthorized access',
                ]
            );
        }

        $this->downloadFile(
            [
                'filePath' => self::DOC_EN_FILE_PATH,
                'fileName' => 'Colissimo Guide for WordPress.pdf',
            ]
        );
    }

    public function getUrl(string $type): string {
        if ('logs' === $type) {
            return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_LOGS);
        } elseif ('doc' === $type) {
            return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_DOC);
        } elseif ('docEN' === $type) {
            return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_DOC_EN);
        } else {
            throw new \InvalidArgumentException('Unknown type for LpcSettingsDownload');
        }
    }

    private function downloadFile(array $options): void {
        if (!empty($options['filePath'])) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $fileContents = $wp_filesystem->get_contents($options['filePath']);
            if (false === $fileContents) {
                wp_die('Could not read file.');
            }
        } elseif (isset($options['content'])) {
            $fileContents = $options['content'];
        } else {
            wp_die('Could not read file.');
        }

        header('Content-Disposition: attachment; filename="' . sanitize_file_name($options['fileName']) . '"');
        header('Content-Type: text/plain');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . mb_strlen($fileContents, '8bit'));

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw file contents streamed as a file download.
        echo $fileContents;
        exit;
    }
}
