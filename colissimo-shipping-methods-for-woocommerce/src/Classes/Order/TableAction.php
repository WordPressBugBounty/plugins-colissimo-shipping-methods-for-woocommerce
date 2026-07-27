<?php

namespace Colissimo\Classes\Order;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Helpers\OrderQueries;
use Exception;
use Colissimo\Classes\Label\LabelGenerationOutward;
use Colissimo\Core\Register;

defined('ABSPATH') || die('Restricted Access');

class TableAction {
    const AJAX_TASK_NAME = 'woocommerce/listing/generate/outward';
    const ORDER_ID_VAR_NAME = 'lpc_order_id';
    const ACTION_NAME = 'lcp_generate_outward_label';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var LabelGenerationOutward */
    protected $labelGenerationOutward;

    public function __construct(?Ajax $ajaxDispatcher = null, ?LabelGenerationOutward $labelGenerationOutward = null) {
        $this->ajaxDispatcher         = Register::get('ajaxDispatcher');
        $this->labelGenerationOutward = Register::get('labelGenerationOutward');
    }

    public function init() {
        add_filter('woocommerce_admin_order_actions', [$this, 'addAction'], 10, 2);
        add_action(
            'current_screen',
            function ($currentScreen) {
                if ('woocommerce_page_wc-orders' === $currentScreen->base || ('edit' === $currentScreen->base && 'shop_order' === $currentScreen->post_type)) {
                    Helper::enqueueStyle(
                        'lpc_woocommerce_order_table_actions',
                        Helper::getCssUrl('orders/listing_actions.css')
                    );
                }
            }
        );
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_manage_labels')) {
            return;
        }

        $orderId = Helper::getVar(self::ORDER_ID_VAR_NAME);
        $order   = wc_get_order($orderId);

        try {
            $this->labelGenerationOutward->generate($order, ['items' => OrderQueries::getOrderItems($order)], true);
        } catch (Exception $e) {
            Logger::error(__METHOD__, [$e->getMessage()]);
        }

        wp_safe_redirect($this->getUrlWithWooCommerceFilters('edit.php?post_type=shop_order'));
    }

    public function addAction($actions, $order) {
        if (current_user_can('lpc_manage_labels')) {
            $actions[self::ACTION_NAME] = [
                'url'    => $this->generateUrl($order->get_id()),
                'name'   => __('Generate outward label', 'colissimo-shipping-methods-for-woocommerce'),
                'action' => self::ACTION_NAME,
            ];
        }

        return $actions;
    }

    public function generateUrl($orderId) {
        $url = $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::ORDER_ID_VAR_NAME . '=' . (int) $orderId;

        return $this->getUrlWithWooCommerceFilters($url);
    }

    private function getUrlWithWooCommerceFilters(string $url): string {
        $wcFilters = ['s', 'shop_order_subtype', 'post_status', '_customer_user', 'm'];
        foreach ($wcFilters as $oneParameter) {
            $parameterValue = Helper::getVar($oneParameter);
            if (!empty($parameterValue)) {
                $url .= '&' . $oneParameter . '=' . $parameterValue;
            }
        }

        return $url;
    }
}
