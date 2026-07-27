<?php

namespace Colissimo\Classes\Slip;

use Colissimo\Classes\Settings\AdminNotices;
use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;
use Colissimo\Classes\Label\OutwardLabelDb;

defined('ABSPATH') || die('Restricted Access');

class SlipDeleteAction {
    const AJAX_TASK_NAME = 'bordereau/delete';
    const BORDEREAU_ID_VAR_NAME = 'lpc_bordereau_id';
    const REDIRECTION_VAR_NAME = 'lpc_redirection';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var AdminNotices */
    protected $adminNotices;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?OutwardLabelDb $outwardLabelDb = null,
        ?AdminNotices $adminNotices = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
        $this->outwardLabelDb = Register::get('outwardLabelDb');
        $this->adminNotices   = Register::get('lpcAdminNotices');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_delete_bordereau')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to bordereau deletion',
                ]
            );
        }
        $bordereauID = Helper::getVar(self::BORDEREAU_ID_VAR_NAME);
        $redirection = Helper::getVar(self::REDIRECTION_VAR_NAME);

        if (SlipQueries::REDIRECTION_COLISSIMO_BORDEREAU_LISTING === $redirection) {
            $urlRedirection = admin_url('admin.php?page=wc_colissimo_view&tab=slip-history');
        } else {
            $urlRedirection = admin_url('admin.php?page=wc_colissimo_view');
        }

        Logger::debug(
            'Delete bordereau',
            [
                'bordereau_id' => $bordereauID,
                'method'       => __METHOD__,
            ]
        );

        $result = SlipQueries::deleteBordereauById($bordereauID);

        if ($result) {
            $this->adminNotices->add_notice(
                'bordereau_delete',
                'notice-success',
                // translators: %d is the bordereau (slip) ID number.
                sprintf(__('Bordereau n°%d deleted', 'colissimo-shipping-methods-for-woocommerce'), $bordereauID)
            );
        } else {
            $this->adminNotices->add_notice(
                'bordereau_delete',
                'notice-error',
                // translators: %d is the bordereau (slip) ID number.
                sprintf(__('Unable to delete bordereau n°%d', 'colissimo-shipping-methods-for-woocommerce'), $bordereauID));
        }
        wp_safe_redirect($urlRedirection);
    }

    public function getUrlForBordereau($bordereauId, $redirection) {
        $url = $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME)
               . '&' . self::BORDEREAU_ID_VAR_NAME . '=' . (int) $bordereauId
               . '&' . self::REDIRECTION_VAR_NAME . '=' . $redirection;

        return $url;
    }
}
