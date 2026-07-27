<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use Colissimo\Core\MergePdf;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class InwardDownloadAccountAction {
    const AJAX_TASK_NAME = 'account/label/inward/download';
    const PRODUCTS_VAR_NAME = 'lpc_label_products';
    const ORDER_ID_VAR_NAME = 'lpc_label_order_id';
    const LABEL_NUMBER_VAR_NAME = 'lpc_label_number';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var InwardLabelDb */
    protected $inwardLabelDb;
    /** @var LabelGenerationInward */
    protected $labelGenerationInward;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?InwardLabelDb $inwardLabelDb = null,
        ?LabelGenerationInward $labelGenerationInward = null,
        ?OutwardLabelDb $outwardLabelDb = null
    ) {
        $this->ajaxDispatcher        = Register::get('ajaxDispatcher');
        $this->inwardLabelDb         = Register::get('inwardLabelDb');
        $this->labelGenerationInward = Register::get('labelGenerationInward');
        $this->outwardLabelDb        = Register::get('outwardLabelDb');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control'], false);
    }

    public function control() {
        $orderId     = Helper::getVar(self::ORDER_ID_VAR_NAME);
        $products    = Helper::getVar(self::PRODUCTS_VAR_NAME);
        $labelNumber = Helper::getVar(self::LABEL_NUMBER_VAR_NAME);

        if (!empty($orderId)) {
            $order = wc_get_order($orderId);
            if (!$this->currentUserOwnsOrder($order)) {
                $this->handleErrorRedirect(__('You are not allowed to generate a return label for this order.', 'colissimo-shipping-methods-for-woocommerce'));
            }

            if (!empty($products)) {
                $this->generateCustomLabel($orderId, $products);
            } elseif (!empty($labelNumber)) {
                $label = $this->inwardLabelDb->getLabelFor($labelNumber);

                // Make sure the requested label actually belongs to the authorized order
                if (empty($label['order_id']) || (int) $label['order_id'] !== (int) $orderId) {
                    $this->handleErrorRedirect(__('You are not allowed to download this return label.', 'colissimo-shipping-methods-for-woocommerce'));
                }

                $this->downloadLabel($labelNumber, $label['label']);
            }
        }

        $this->handleErrorRedirect(__('There has been an error while downloading the return label, please contact us for more information.', 'colissimo-shipping-methods-for-woocommerce'));
    }

    private function currentUserOwnsOrder($order): bool {
        if (!is_user_logged_in() || empty($order)) {
            return false;
        }

        return (int) $order->get_user_id() === get_current_user_id();
    }

    public function getUrlForCustom(int $orderId): string {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::ORDER_ID_VAR_NAME . '=' . $orderId . '&' . self::PRODUCTS_VAR_NAME . '=';
    }

    public function getUrlForDownload(int $orderId, string $trackingNumber): string {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::ORDER_ID_VAR_NAME . '=' . $orderId . '&' . self::LABEL_NUMBER_VAR_NAME . '=' . $trackingNumber;
    }

    private function generateCustomLabel($orderId, $products) {
        $order = wc_get_order($orderId);

        // Make sure the user tries to download for their own order
        if (!$this->currentUserOwnsOrder($order)) {
            $this->handleErrorRedirect(__('You are not allowed to generate a return label for this order.', 'colissimo-shipping-methods-for-woocommerce'));
        }

        // Make sure they selected products from the order
        if (empty($products)) {
            $this->handleErrorRedirect(__('You need to select at least one item to generate a label', 'colissimo-shipping-methods-for-woocommerce'));
        }

        $orderedProducts = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (empty($product) || !$product->needs_shipping()) {
                continue;
            }

            $orderedProducts[$item->get_id()] = $item;
        }

        $products        = json_decode($products, true);
        $products        = array_combine(array_column($products, 'productId'), array_column($products, 'quantity'));
        $items           = [];
        $totalWeight     = wc_get_weight(Helper::get_option('lpc_packaging_weight', '0'), 'kg');
        $insuranceAmount = 0;
        foreach ($products as $productId => $quantity) {
            if (empty($orderedProducts[$productId]) || $orderedProducts[$productId]->get_quantity() < $quantity) {
                $this->handleErrorRedirect(__('Please only select products you ordered.', 'colissimo-shipping-methods-for-woocommerce'));
            }

            $product = $orderedProducts[$productId]->get_product();

            $items[$productId] = ['qty' => $quantity];
            $totalWeight       += wc_get_weight(floatval($product->get_weight()) * floatval($quantity), 'kg');
            $insuranceAmount   += $product->get_price() * $quantity;
        }

        try {
            $inwardTrackingNumber = $this->labelGenerationInward->generate(
                $order,
                [
                    'items'                => $items,
                    'outward_label_number' => 'no_outward',
                    'totalWeight'          => $totalWeight,
                    'insuranceAmount'      => $insuranceAmount,
                    'format'               => LabelGenerationPayload::LABEL_FORMAT_PDF,
                    'is_from_client'       => true,
                ]
            );
            $label                = $this->inwardLabelDb->getLabelFor($inwardTrackingNumber);
            $labelContent         = $label['label'];

            if (empty($labelContent)) {
                $this->handleErrorRedirect(__('There has been an error while downloading the return label, please contact us for more information.', 'colissimo-shipping-methods-for-woocommerce'));
            }

            echo wp_json_encode(
                [
                    'type'           => 'success',
                    'trackingNumber' => $inwardTrackingNumber,
                ]
            );
        } catch (Exception $e) {
            $this->handleErrorRedirect($e->getMessage());
        }
        exit;
    }

    private function downloadLabel(string $inwardTrackingNumber, string $labelContent) {
        $tempDir            = Helper::createUniqueTempDir();
        $fileToDownloadName = $tempDir . 'Colissimo.inward(' . sanitize_file_name($inwardTrackingNumber) . ').pdf';
        $labelFileName      = 'inward_label.pdf';
        $filesToMerge       = [];
        Helper::getWpFilesystem()->put_contents($tempDir . $labelFileName, $labelContent, FS_CHMOD_FILE);

        $filesToMerge[] = $tempDir . $labelFileName;

        $cn23Data    = $this->inwardLabelDb->getCn23For($inwardTrackingNumber);
        $cn23Content = LabelGenerationPayload::LABEL_FORMAT_PDF === $cn23Data['format'] ? $cn23Data['cn23'] : '';
        if (!empty($cn23Content)) {
            Helper::getWpFilesystem()->put_contents($tempDir . 'inward_cn23.pdf', $cn23Content, FS_CHMOD_FILE);
            $filesToMerge[] = $tempDir . 'inward_cn23.pdf';
        }

        MergePdf::merge($filesToMerge, MergePdf::DESTINATION__DISK_DOWNLOAD, $fileToDownloadName);
        foreach ($filesToMerge as $fileToMerge) {
            wp_delete_file($fileToMerge);
        }
        wp_delete_file($fileToDownloadName);
        Helper::getWpFilesystem()->rmdir($tempDir, true);
    }

    private function handleErrorRedirect(string $errorMessage) {
        echo wp_json_encode(
            [
                'type'  => 'error',
                'error' => $errorMessage,
            ]
        );
        exit;
    }
}
