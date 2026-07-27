<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Classes\Email\OutwardLabelEmailManager;
use Colissimo\Core\Register;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class OutwardImportAction {
    const AJAX_TASK_NAME = 'label/import';

    /** @var Ajax */
    protected $ajaxDispatcher;

    /** @var OutwardLabelDb */
    protected $outwardLabelDb;

    /** @var LabelGenerationOutward */
    protected $labelGenerationOutward;

    public function __construct(
        ?OutwardLabelDb $outwardLabelDb = null,
        ?Ajax $ajaxDispatcher = null,
        ?LabelGenerationOutward $labelGenerationOutward = null
    ) {
        $this->outwardLabelDb         = Register::get('outwardLabelDb');
        $this->ajaxDispatcher         = Register::get('ajaxDispatcher');
        $this->labelGenerationOutward = Register::get('labelGenerationOutward');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_manage_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to import new outward label',
                ]
            );
        }

        check_ajax_referer(self::AJAX_TASK_NAME, Ajax::NONCE_NAME);

        if (!isset($_FILES['tracking_number_import']['name'])) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => __('File not found', 'colissimo-shipping-methods-for-woocommerce'),
                ])
            );
        }

        $uploadOverrides = [
            'test_form' => false,
            'mimes'     => ['csv' => 'text/csv'],
        ];

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $file = $_FILES['tracking_number_import'];
        $file = wp_handle_upload($file, $uploadOverrides);

        if (isset($file['error'])) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => $file['error'],
                ])
            );
        }

        try {
            $fileContent = file_get_contents($file['file']);
        } catch (Exception $exception) {
            Logger::error($exception->getMessage());
        }

        if (empty($fileContent)) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => __('The content of the file is empty', 'colissimo-shipping-methods-for-woocommerce'),
                ])
            );
        }

        $fileContent = str_replace(["\r\n", "\r"], "\n", $fileContent);
        $allLines    = explode("\n", $fileContent);

        $listSeparators = ["\t", ';'];
        $separator      = ',';
        foreach ($listSeparators as $sep) {
            if (strpos($allLines[0], $sep) !== false) {
                $separator = $sep;
                break;
            }
        }

        $columns = explode($separator, $allLines[0]);

        $requiredColumns = [
            'order_id'        => - 1,
            'tracking_number' => - 1,
        ];

        foreach ($requiredColumns as $requiredColumn => $pos) {
            if (!in_array($requiredColumn, $columns)) {
                continue;
            }

            $requiredColumns[$requiredColumn] = array_search($requiredColumn, $columns);
        }

        if (in_array(- 1, $requiredColumns)) {
            die(
            json_encode(
                [
                    'type'    => 'error',
                    'message' => sprintf(
                        // translators: %s is the comma-separated list of required column names.
                        __('Missing columns in the imported CSV file. Required columns: %s', 'colissimo-shipping-methods-for-woocommerce'),
                        implode(', ', array_keys($requiredColumns))
                    ),
                ]
            )
            );
        }

        $orderIdsIssues = [];

        foreach ($allLines as $key => $data) {
            if (0 === $key || empty($data)) {
                continue;
            }

            $data = explode($separator, $data);

            $orderId        = trim($data[$requiredColumns['order_id']]);
            $trackingNumber = trim($data[$requiredColumns['tracking_number']]);

            $insertion = $this->outwardLabelDb->insertFromThirdParty($orderId, $trackingNumber);

            if (empty($insertion)) {
                $orderIdsIssues[] = $orderId;
            }

            $order = wc_get_order($orderId);

            if (empty($order)) {
                $orderIdsIssues[] = $orderId;
                continue;
            }

            $order->update_meta_data(LabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY, $trackingNumber);
            $order->save();
            $this->labelGenerationOutward->applyStatusAfterLabelGeneration($order);

            $email_outward_label = Helper::get_option(OutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, 'no');
            if (OutwardLabelEmailManager::ON_OUTWARD_LABEL_GENERATION_OPTION === $email_outward_label) {
                /**
                 * Action called when a shipping label has been generated
                 *
                 * @since 1.6.4
                 */
                do_action(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook, correctly prefixed with "lpc_".
                    'lpc_outward_label_generated_to_email',
                    ['order' => $order]
                );
            }
        }

        if (!empty($orderIdsIssues)) {
            die(json_encode(
                [
                    'type'    => 'error',
                    // translators: %s is the comma-separated list of order IDs that failed.
                    'message' => sprintf(__('Could not insert tracking number for order(s): %s', 'colissimo-shipping-methods-for-woocommerce'), implode(', ', $orderIdsIssues)),
                ])
            );
        }

        die(json_encode(['type' => 'success']));
    }

    private function getFilenameExtension($filename) {
        $endPos = strpos($filename, '?');
        if (false !== $endPos) {
            $filename = substr($filename, 0, $endPos);
        }

        $dot = strrpos($filename, '.');
        if (false === $dot) {
            return '';
        }

        return substr($filename, $dot + 1);
    }

    public function getUrlToImportTrackingNumbers() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }
}
