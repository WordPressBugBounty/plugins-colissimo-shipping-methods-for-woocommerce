<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;
use Colissimo\Helpers\OrderQueries;

defined('ABSPATH') || die('Restricted Access');

class InwardGenerateAction {
    const AJAX_TASK_NAME = 'label/inward/create';
    const ACTION_ID_PARAM_NAME = 'lpc_label_inward_id';
    const ACTION_OUTWARD_LABEL_ID_PARAM_NAME = 'lpc_label_outward_id';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var LabelGenerationInward */
    protected $labelGenerationInward;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?LabelGenerationInward $labelGenerationInward = null
    ) {
        $this->ajaxDispatcher        = Register::get('ajaxDispatcher');
        $this->labelGenerationInward = Register::get('labelGenerationInward');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function generateUrl($oneOrderId, $outwardLabelId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME) . '&' . self::ACTION_ID_PARAM_NAME . '=' . (int) $oneOrderId
               . '&' . self::ACTION_OUTWARD_LABEL_ID_PARAM_NAME . '=' . $outwardLabelId;
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
        $outwardLabelId = Helper::getVar(self::ACTION_OUTWARD_LABEL_ID_PARAM_NAME);
        $order          = wc_get_order($orderId);
        if (empty($order)) {
            wp_safe_redirect($urlRedirection);

            return;
        }

        $customParams = [
            'items'                => OrderQueries::getOrderItems($order),
            'outward_label_number' => $outwardLabelId,
        ];
        $this->labelGenerationInward->generate($order, $customParams);

        wp_safe_redirect($urlRedirection);
    }
}
