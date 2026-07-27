<?php

namespace Colissimo\Classes\Label;

use Colissimo\Classes\Order\OrderStatuses;
use Colissimo\Core\Register;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Helpers\OrderQueries;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class LabelGenerationAuto {
    protected $labelGenerationOutward;

    public function __construct(?LabelGenerationOutward $labelGenerationOutward = null) {
        $this->labelGenerationOutward = Register::get('labelGenerationOutward');
    }

    public function init() {
        add_action('woocommerce_order_status_changed', [$this, 'generateLabelsAuto'], 50, 4);
    }

    /**
     * Automatically generate the label if order status matches status from configuration
     *
     * @param int    $orderId
     * @param string $statusFrom
     * @param string $statusTo
     * @param object $order
     */
    public function generateLabelsAuto($orderId, $statusFrom, $statusTo, $order) {
        $orderStatuses = Helper::get_option('lpc_generate_label_on', '');

        if (!is_array($orderStatuses)) {
            $orderStatuses = [$orderStatuses];
        }

        $disabled = in_array(OrderStatuses::WC_LPC_DISABLE, $orderStatuses);
        if (empty($orderStatuses) || $statusFrom === $statusTo || $disabled) {
            return;
        }

        // WooCommerce removes the "wc-" prefix on the native order statuses that are sent in the hook
        if (in_array($statusTo, $orderStatuses) || in_array('wc-' . $statusTo, $orderStatuses)) {
            try {
                $this->labelGenerationOutward->generate(
                    $order,
                    [
                        'isAutoGeneration' => true,
                        'items'            => OrderQueries::getOrderItems($order),
                    ],
                    true
                );
            } catch (Exception $e) {
                Logger::error(__METHOD__, ['error' => $e->getMessage()]);
            }
        }
    }
}
