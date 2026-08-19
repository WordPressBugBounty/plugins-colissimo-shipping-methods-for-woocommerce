<?php

namespace Colissimo\Classes\Email;

use Colissimo\Classes\Label\InwardLabelDb;
use Colissimo\Classes\Label\LabelQueries;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class InwardLabelEmailManager {
    protected $ajaxDispatcher;

    const AJAX_TASK_NAME = 'inward_label_emailing';
    const TRACKING_NUMBER_VAR_NAME = 'tracking_number';
    const EMAIL_RETURN_LABEL_OPTION = 'lpc_email_return_label';
    const REDIRECTION_VAR_NAME = 'lpc_redirection';

    protected $mailer;

    /** @var InwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(?InwardLabelDb $inwardLabelDb = null) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
        $this->inwardLabelDb  = Register::get('inwardLabelDb');
    }

    public function send_email($order_data) {
        $lpcInwardLabelGenerationEmail = WC()->mailer()->emails['LpcInwardLabelGenerationEmail'];
        if (isset($order_data['label_filename'])) {
            $lpcInwardLabelGenerationEmail->trigger($order_data['order'], $order_data['label'], $order_data['label_filename']);
        } else {
            $lpcInwardLabelGenerationEmail->trigger($order_data['order'], $order_data['label']);
        }
    }

    public function generate_inward_label_woocommerce_email($emails) {
        $emails['LpcInwardLabelGenerationEmail'] = new InwardLabelGenerationEmail();

        return $emails;
    }

    public function init() {
        add_action('lpc_inward_label_generated_to_email', [$this, 'send_email']);
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_send_emails')) {
            header('HTTP/1.0 401 Unauthorized');
            $this->handleErrorRedirect('Unauthorized access to inward label sending');
        }

        $trackingNumber = Helper::getVar(self::TRACKING_NUMBER_VAR_NAME);
        $redirection    = Helper::getVar(self::REDIRECTION_VAR_NAME);

        switch ($redirection) {
            case LabelQueries::REDIRECTION_WOO_ORDER_EDIT_PAGE:
                $orderId = $this->inwardLabelDb->getOrderIdByTrackingNumber($trackingNumber);
                $order   = wc_get_order($orderId);
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

        try {
            WC()->mailer();
            $lpcInwardLabelGenerationEmail = new InwardLabelGenerationEmail();
            $label                         = $this->inwardLabelDb->getLabelFor($trackingNumber);
            $order                         = wc_get_order($label['order_id']);
            if (empty($order)) {
                $this->handleErrorRedirect(__('Label was not sent', 'colissimo-shipping-methods-for-woocommerce'));
            }
            $sent = $lpcInwardLabelGenerationEmail->trigger($order, $label['label']);
            // TODO: Try to find a better way for the admin_notices
            $lpc_admin_notices = Register::get('lpcAdminNotices');
            if ($sent) {
                $lpc_admin_notices->add_notice('inward_label_sent', 'notice-success', __('Label sent', 'colissimo-shipping-methods-for-woocommerce'));
            } else {
                $lpc_admin_notices->add_notice(
                    'inward_label_sent',
                    'notice-error',
                    __('Label was not sent', 'colissimo-shipping-methods-for-woocommerce')
                );
            }

            wp_safe_redirect($urlRedirection);
            exit;
        } catch (Exception $e) {
            return $e->getCode();
        }
    }

    public function labelEmailingUrl($trackingNumber, $redirection) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $trackingNumber
               . '&' . self::REDIRECTION_VAR_NAME . '=' . $redirection;
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
