<?php

namespace Colissimo\Classes\Label;

use Colissimo\Classes\Shipping\ShippingMethod;
use Colissimo\Helpers\Helper;
use Colissimo\Classes\Email\InwardLabelEmailManager;
use Colissimo\Core\Register;

defined('ABSPATH') || die('Restricted Access');

class LabelQueries {
    const REDIRECTION_WOO_ORDER_EDIT_PAGE = 'lpc_woocommerce_order_edit_page';
    const REDIRECTION_COLISSIMO_ORDERS_LISTING = 'lpc_colissimo_orders_listing';

    /** @var InwardLabelDb */
    protected $inwardLabelDb;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var OutwardDeleteAction */
    protected $labelOutwardDeleteAction;
    /** @var InwardDeleteAction */
    protected $labelInwardDeleteAction;
    /** @var PackagerDownloadAction */
    protected $labelPackagerDownloadAction;
    /** @var OutwardDownloadAction */
    protected $labelOutwardDownloadAction;
    /** @var InwardDownloadAction */
    protected $labelInwardDownloadAction;
    /** @var LabelPrintAction */
    protected $labelPrintAction;
    /** @var OutwardGenerateAction */
    protected $labelOutwardCreateAction;
    /** @var InwardGenerateAction */
    protected $labelInwardCreateAction;

    public function __construct() {
        $this->inwardLabelDb               = Register::get('inwardLabelDb');
        $this->outwardLabelDb              = Register::get('outwardLabelDb');
        $this->labelOutwardDeleteAction    = Register::get('labelOutwardDeleteAction');
        $this->labelInwardDeleteAction     = Register::get('labelInwardDeleteAction');
        $this->labelPackagerDownloadAction = Register::get('labelPackagerDownloadAction');
        $this->labelOutwardDownloadAction  = Register::get('labelOutwardDownloadAction');
        $this->labelInwardDownloadAction   = Register::get('labelInwardDownloadAction');
        $this->labelPrintAction            = Register::get('labelPrintAction');
        $this->labelOutwardCreateAction    = Register::get('LpcLabelOutwardGenerateAction');
        $this->labelInwardCreateAction     = Register::get('LpcLabelInwardGenerateAction');
    }

    /**
     * Retrieve an associative array where keys are order id, and values are matching tracking numbers.
     * Each tracking numbers are array where keys are outward tracking number, and values are array of inward tracking numbers
     *
     * @param array $trackingNumbersByOrders
     * @param array $labelFormatByTrackingNumber
     * @param array $labelInfoByTrackingNumber
     * @param array $ordersId
     */
    public function getTrackingNumbersByOrdersId(
        &$trackingNumbersByOrders,
        &$labelFormatByTrackingNumber,
        &$labelInfoByTrackingNumber,
        $ordersId = []
    ) {
        $outwardTrackingNumbers = $this->outwardLabelDb->getLabelsInfosForOrdersId($ordersId);
        $inwardTrackingNumbers  = $this->inwardLabelDb->getLabelsInfosForOrdersId($ordersId);

        foreach ($outwardTrackingNumbers as $oneOutwardTrackingNumber) {
            if (!empty($oneOutwardTrackingNumber->tracking_number)) {
                $labelInfoByTrackingNumber[$oneOutwardTrackingNumber->tracking_number] = $oneOutwardTrackingNumber;

                $trackingNumbersByOrders[$oneOutwardTrackingNumber->order_id][$oneOutwardTrackingNumber->tracking_number] = [];
                if (!empty($oneOutwardTrackingNumber->detail)) {
                    $oneOutwardTrackingNumber->detail = json_decode($oneOutwardTrackingNumber->detail, true);
                    if (isset($oneOutwardTrackingNumber->detail['insured']) && $oneOutwardTrackingNumber->detail['insured']) {
                        if (!isset($trackingNumbersByOrders['insured'])) {
                            $trackingNumbersByOrders['insured'] = [];
                        }
                        $trackingNumbersByOrders['insured'][] = $oneOutwardTrackingNumber->tracking_number;
                    }
                }

                $labelFormatByTrackingNumber[$oneOutwardTrackingNumber->tracking_number] =
                    !empty($oneOutwardTrackingNumber->label_format)
                        ? $oneOutwardTrackingNumber->label_format
                        : LabelGenerationPayload::LABEL_FORMAT_PDF;
            }
        }

        foreach ($inwardTrackingNumbers as $oneInwardTrackingNumber) {
            if (!empty($oneInwardTrackingNumber->tracking_number)) {
                if (
                    !empty($oneInwardTrackingNumber->outward_tracking_number)
                    && isset($trackingNumbersByOrders[$oneInwardTrackingNumber->order_id][$oneInwardTrackingNumber->outward_tracking_number])
                ) {
                    $trackingNumbersByOrders[$oneInwardTrackingNumber->order_id][$oneInwardTrackingNumber->outward_tracking_number][] = $oneInwardTrackingNumber->tracking_number;
                } else {
                    $trackingNumbersByOrders[$oneInwardTrackingNumber->order_id]['no_outward'][] = $oneInwardTrackingNumber->tracking_number;
                }

                $labelFormatByTrackingNumber[$oneInwardTrackingNumber->tracking_number] =
                    !empty($oneInwardTrackingNumber->label_format)
                        ? $oneInwardTrackingNumber->label_format
                        : LabelGenerationPayload::LABEL_FORMAT_PDF;
            }
        }
    }

    /**
     * Retrieve an array containing all tracking numbers for orders ids in param
     *
     * @param array  $ordersId
     * @param string $labelType
     *
     * @return array
     */
    public function getTrackingNumbersForOrdersId(
        $ordersId = [],
        $labelType = LabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD
    ) {
        $trackingNumbers = [];

        if (OutwardLabelDb::LABEL_TYPE_OUTWARD === $labelType || LabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD === $labelType) {
            $outwardTrackingNumbers = $this->outwardLabelDb->getLabelsInfosForOrdersId($ordersId);
            foreach ($outwardTrackingNumbers as $oneOutTrackingNumber) {
                if (!empty($oneOutTrackingNumber->tracking_number)) {
                    $trackingNumbers[] = $oneOutTrackingNumber->tracking_number;
                }
            }
        }

        if (InwardLabelDb::LABEL_TYPE_INWARD === $labelType || LabelPrintAction::PRINT_LABEL_TYPE_OUTWARD_AND_INWARD === $labelType) {
            $inwardTrackingNumbers = $this->inwardLabelDb->getLabelsInfosForOrdersId($ordersId);
            foreach ($inwardTrackingNumbers as $oneInTrackingNumber) {
                if (!empty($oneInTrackingNumber->tracking_number)) {
                    $trackingNumbers[] = $oneInTrackingNumber->tracking_number;
                }
            }
        }

        return $trackingNumbers;
    }

    public function getOutwardLabelLink($orderId, $trackingNumber) {
        if ('website_tracking_page' === Helper::get_option('lpc_email_tracking_link', 'website_tracking_page')) {
            return get_site_url() . Register::get('unifiedTrackingApi')->getTrackingPageUrlForOrder($orderId, $trackingNumber);
        } else {
            return str_replace(
                '{lpc_tracking_number}',
                $trackingNumber,
                ShippingMethod::LPC_LAPOSTE_TRACKING_URL
            );
        }
    }

    public function getOutwardLabelsActionsIcons($trackingNumber, $format, $redirection) {
        $printerIcon = $GLOBALS['wp_version'] >= '5.5' ? 'dashicons-printer' : 'dashicons-media-default';
        $label       = $this->outwardLabelDb->getLabelFor($trackingNumber);

        $disableActions = '';
        $disableText    = '';
        if (empty($label['label'])) {
            $disableActions = 'lpc_label_action_disabled';
            $disableText    = __('You cannot do this action on imported tracking numbers or purged labels.', 'colissimo-shipping-methods-for-woocommerce');
            $disableText    = ' lpc-data-text="' . esc_attr($disableText) . '"';
        }

        $actions = '';

        if (current_user_can('lpc_download_labels')) {
            $actions .= '<span class="dashicons dashicons-download lpc_label_action_download ' . $disableActions . '" ' .
                        $this->getLabelOutwardDownloadAttr($trackingNumber, $format) . $disableText . '></span>';
        }

        if (current_user_can('lpc_print_labels')) {
            $printedClass = $label['printed'] ? 'lpc_label_printed' : '';
            $actions      .= '<span class="dashicons ' . $printerIcon . ' lpc_label_action_print ' . $disableActions . ' ' . $printedClass . '" ' .
                             $this->getLabelOutwardPrintAttr($trackingNumber, $format) . $disableText . ' ></span>';
        }

        if (current_user_can('lpc_delete_labels')) {
            $actions .= '<span class="dashicons dashicons-trash lpc_label_action_delete" ' .
                        $this->getLabelOutwardDeletionAttr($trackingNumber, $redirection) . '></span>';
        }

        return $actions;
    }

    public function getInwardLabelsActionsIcons($trackingNumber, $format, $redirection) {
        $printerIcon = $GLOBALS['wp_version'] >= '5.5' ? 'dashicons-printer' : 'dashicons-media-default';
        $label       = $this->inwardLabelDb->getLabelFor($trackingNumber);

        $disableActions = '';
        $disableText    = '';
        if (empty($label['label'])) {
            $disableActions = 'lpc_label_action_disabled';
            $disableText    = __('You cannot do this action on imported tracking numbers or purged labels.', 'colissimo-shipping-methods-for-woocommerce');
            $disableText    = ' lpc-data-text="' . esc_attr($disableText) . '"';
        }

        $output = '';

        if (current_user_can('lpc_download_labels')) {
            $output .= '<span class="dashicons dashicons-download lpc_label_action_download ' . esc_attr($disableActions) . '" ' .
                       $this->getLabelInwardDownloadAttr($trackingNumber, $format) . $disableText . '></span>';
        }

        if (current_user_can('lpc_print_labels')) {
            $printedClass = $label['printed'] ? 'lpc_label_printed' : '';
            $output       .= '<span class="dashicons lpc_label_action_print ' . esc_attr($printerIcon . ' ' . $disableActions . ' ' . $printedClass) . '" ' .
                             $this->getLabelInwardPrintAttr($trackingNumber, $format) . $disableText . '></span>';
        }

        if (current_user_can('lpc_delete_labels')) {
            $output .= '<span class="dashicons dashicons-trash lpc_label_action_delete" ' . $this->getLabelInwardDeletionAttr($trackingNumber, $redirection) . '></span>';
        }

        if (current_user_can('lpc_send_emails')) {
            $output .= '<span class="dashicons dashicons-email-alt lpc_label_action_send_email ' . esc_attr($disableActions) . '" ' .
                       $this->getLabelInwardSendAttr($trackingNumber, $redirection) . $disableText . '></span>';
        }

        return $output;
    }

    protected function getLabelOutwardDeletionAttr($trackingNumber, $redirection) {
        return 'data-link="' . esc_url($this->labelOutwardDeleteAction->getUrlForTrackingNumber($trackingNumber, $redirection)) . '" '
               . 'data-label-type="' . esc_attr(OutwardLabelDb::LABEL_TYPE_OUTWARD) . '" '
               . 'data-tracking-number="' . esc_attr($trackingNumber) . '" '
               . 'title="' . esc_attr__('Delete outward label', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }

    protected function getLabelInwardDeletionAttr($trackingNumber, $redirection) {
        return 'data-link="' . esc_url($this->labelInwardDeleteAction->getUrlForTrackingNumber($trackingNumber, $redirection)) . '" '
               . ' data-label-type="' . esc_attr(InwardLabelDb::LABEL_TYPE_INWARD) . '" '
               . 'data-tracking-number="' . esc_attr($trackingNumber) . '" '
               . 'title="' . esc_attr__('Delete inward label', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }

    public function getLabelOutwardDownloadAttr($trackingNumber, $format): string {
        $cn23Data = $this->outwardLabelDb->getCn23For($trackingNumber);
        if (!empty($cn23Data['format']) && LabelGenerationPayload::LABEL_FORMAT_PDF !== $cn23Data['format']) {
            $outwardLabelDownloadLink = $this->labelPackagerDownloadAction->getUrlForTrackingNumbers(
                [$trackingNumber]
            );
        } else {
            switch ($format) {
                case LabelGenerationPayload::LABEL_FORMAT_ZPL:
                case LabelGenerationPayload::LABEL_FORMAT_DPL:
                    $outwardLabelDownloadLink = $this->labelPackagerDownloadAction->getUrlForTrackingNumbers(
                        [$trackingNumber]
                    );
                    break;
                case LabelGenerationPayload::LABEL_FORMAT_PDF:
                default:
                    $outwardLabelDownloadLink = $this->labelOutwardDownloadAction->getUrlForTrackingNumber($trackingNumber);
                    break;
            }
        }

        return 'data-link="' . esc_url($outwardLabelDownloadLink) . '" title="' . esc_attr__(
                'Download outward label',
                'colissimo-shipping-methods-for-woocommerce'
            ) . '"';
    }

    public function getLabelInwardDownloadAttr($trackingNumber, $format): string {
        $cn23Data = $this->inwardLabelDb->getCn23For($trackingNumber);
        if (!empty($cn23Data['format']) && LabelGenerationPayload::LABEL_FORMAT_PDF !== $cn23Data['format']) {
            $inwardLabelDownloadLink = $this->labelPackagerDownloadAction->getUrlForTrackingNumbers(
                [$trackingNumber],
                false
            );
        } else {
            switch ($format) {
                case LabelGenerationPayload::LABEL_FORMAT_ZPL:
                case LabelGenerationPayload::LABEL_FORMAT_DPL:
                    $inwardLabelDownloadLink = $this->labelPackagerDownloadAction->getUrlForTrackingNumbers(
                        [$trackingNumber],
                        false
                    );
                    break;
                case LabelGenerationPayload::LABEL_FORMAT_PDF:
                default:
                    $inwardLabelDownloadLink = $this->labelInwardDownloadAction->getUrlForTrackingNumber($trackingNumber);
                    break;
            }
        }

        return 'data-link="' . esc_url($inwardLabelDownloadLink) . '" title="' . esc_attr__('Download inward label', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }

    public function getLabelOutwardPrintAttr($trackingNumber, $format) {
        return 'data-link="' . esc_url($this->labelPrintAction->getUrlForTrackingNumbers(
                [$trackingNumber],
                false
            )) . '" data-label-type="' . esc_attr(OutwardLabelDb::LABEL_TYPE_OUTWARD) . '" '
               . 'data-tracking-number="' . esc_attr($trackingNumber) . '" '
               . 'data-format="' . esc_attr($format) . '" '
               . 'title="' . esc_attr__('Print outward label', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }

    public function getLabelInwardPrintAttr($trackingNumber, $format) {
        return 'data-link="' . esc_url($this->labelPrintAction->getUrlForTrackingNumbers(
                [$trackingNumber],
                false
            )) . '" data-label-type="' . esc_attr(InwardLabelDb::LABEL_TYPE_INWARD) . '" '
               . 'data-tracking-number="' . esc_attr($trackingNumber) . '" '
               . 'data-format="' . esc_attr($format) . '" '
               . 'title="' . esc_attr__('Print inward label', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }

    protected function getLabelInwardSendAttr($trackingNumber, $redirection) {
        $emailManager = new InwardLabelEmailManager();

        return 'data-link="' . esc_url($emailManager->labelEmailingUrl($trackingNumber, $redirection)) . '" '
               . 'title="' . esc_attr__('Email Return Label', 'colissimo-shipping-methods-for-woocommerce') . '"';
    }

    public static function enqueueLabelsActionsScript() {
        $thermalLabelPrintAction              = Register::get('thermalLabelPrintAction');
        $args['errorMsgPrintThermal']         = __('Print thermal error on some orders:', 'colissimo-shipping-methods-for-woocommerce');
        $args['deletionConfirmTextOutward']   = __('Do you confirm the deletion of label? All related inwards label will be deleted too',
                                                   'colissimo-shipping-methods-for-woocommerce');
        $args['deletionConfirmTextInward']    = __('Do you confirm the deletion of label?', 'colissimo-shipping-methods-for-woocommerce');
        $args['thermalLabelPrintActionUrl']   = $thermalLabelPrintAction->getThermalPrintActionUrl();
        $args['generateConfirmTextOutward']   = __('Do you confirm the creation of outward label?', 'colissimo-shipping-methods-for-woocommerce');
        $args['generateConfirmTextInward']    = __('Do you confirm the creation of inward label?', 'colissimo-shipping-methods-for-woocommerce');
        $args['deletionConfirmTextBordereau'] = __('Do you confirm the deletion of bordereau?', 'colissimo-shipping-methods-for-woocommerce');
        $args['qzPrinter']                    = Helper::get_option('lpc_zpldpl_labels_printer', '');

        Helper::enqueueScript(
            'lpc_qz_tray',
            Helper::getJsUrl('libraries/qz-tray.js'),
            []
        );

        Helper::enqueueScript(
            'lpc_labels_actions',
            Helper::getJsUrl('labels/actions.js'),
            ['jquery-core', 'lpc_qz_tray'],
            'lpcLabelsActions',
            $args
        );
    }

    public function getLabelOutwardGenerateAttr($oneOrderId) {
        return 'data-link="' . esc_url($this->labelOutwardCreateAction->generateUrl($oneOrderId)) . '" data-label-type="' . esc_attr(OutwardLabelDb::LABEL_TYPE_OUTWARD) . '"';
    }

    public function getLabelInwardGenerateAttr($oneOrderId, $outwardLabelId) {
        return 'data-link="' . esc_url($this->labelInwardCreateAction->generateUrl(
                $oneOrderId,
                $outwardLabelId
            )) . '" data-label-type="' . esc_attr(InwardLabelDb::LABEL_TYPE_INWARD) . '"';
    }
}
