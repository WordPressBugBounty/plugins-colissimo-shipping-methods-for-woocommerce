<?php

namespace Colissimo\Classes\Label;

use Colissimo\Classes\Settings\AdminNotices;
use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;

defined('ABSPATH') || die('Restricted Access');

class InwardDeleteAction {
    const AJAX_TASK_NAME = 'label/inward/delete';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_label_tracking_number';
    const REDIRECTION_VAR_NAME = 'lpc_redirection';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var InwardLabelDb */
    protected $inwardLabelDb;
    /** @var AdminNotices */
    protected $adminNotices;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?InwardLabelDb $inwardLabelDb = null,
        ?AdminNotices $adminNotices = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
        $this->inwardLabelDb  = Register::get('inwardLabelDb');
        $this->adminNotices   = Register::get('lpcAdminNotices');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function getUrlForTrackingNumber($trackingNumber, $redirection) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $trackingNumber
               . '&' . self::REDIRECTION_VAR_NAME . '=' . $redirection;
    }

    public function control() {
        if (!current_user_can('lpc_delete_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to outward label deletion',
                ]
            );
        }

        $trackingNumber = Helper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        $redirection    = Helper::getVar(self::REDIRECTION_VAR_NAME);
        $orderId        = $this->inwardLabelDb->getOrderIdByTrackingNumber($trackingNumber);

        switch ($redirection) {
            case LabelQueries::REDIRECTION_WOO_ORDER_EDIT_PAGE:
                $order = wc_get_order($orderId);
                if (!empty($order)) {
                    $urlRedirection = $order->get_edit_order_url();
                    break;
                }
            // We didn't find the order, redirect to the default page
            case LabelQueries::REDIRECTION_COLISSIMO_ORDERS_LISTING:
            default:
                $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
                break;
        }

        Logger::debug(
            'Delete inward label',
            [
                'tracking_number' => $trackingNumber,
                'method'          => __METHOD__,
            ]
        );

        $result = $this->inwardLabelDb->delete($trackingNumber);

        if (1 != $result) {
            Logger::error(
                'Unable to delete label',
                [
                    'tracking_number' => $trackingNumber,
                    'result'          => $result,
                    'method'          => __METHOD__,
                ]
            );

            $this->adminNotices->add_notice(
                'inward_label_delete',
                'notice-error',
                // translators: %s is the tracking number of the label.
                sprintf(__('Unable to delete label %s', 'colissimo-shipping-methods-for-woocommerce'), $trackingNumber)
            );
        } else {
            $this->adminNotices->add_notice(
                'inward_label_delete',
                'notice-success',
                // translators: %s is the tracking number of the deleted label.
                sprintf(__('Label %s deleted', 'colissimo-shipping-methods-for-woocommerce'), $trackingNumber)
            );

            // Remove the related order meta
            $order = wc_get_order($orderId);
            if (!empty($order)) {
                $order->update_meta_data(LabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY, '');
                $order->save();
            }
        }

        wp_safe_redirect($urlRedirection);
    }
}
