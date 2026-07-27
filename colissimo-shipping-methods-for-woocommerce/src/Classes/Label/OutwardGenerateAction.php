<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;
use Colissimo\Helpers\OrderQueries;

defined('ABSPATH') || die('Restricted Access');

class OutwardGenerateAction {
    const AJAX_TASK_NAME = 'label/outward/create';
    const ACTION_ID_PARAM_NAME = 'lpc_label_outward_id';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var LabelGenerationOutward */
    protected $labelGenerationOutward;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?LabelGenerationOutward $labelGenerationOutward = null
    ) {
        $this->ajaxDispatcher         = Register::get('ajaxDispatcher');
        $this->labelGenerationOutward = Register::get('labelGenerationOutward');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function generateUrl($oneOrderId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) .
               '&' . self::ACTION_ID_PARAM_NAME . '=' . (int) $oneOrderId;
    }

    public function control() {
        if (!current_user_can('lpc_manage_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to create new outward label',
                ]
            );
        }
        $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
        $orderId        = Helper::getVar(self::ACTION_ID_PARAM_NAME);
        $order          = wc_get_order($orderId);

        $items = OrderQueries::getOrderItems($order);
        $this->labelGenerationOutward->generate($order, ['items' => $items], true);
        wp_safe_redirect($urlRedirection);
    }
}
