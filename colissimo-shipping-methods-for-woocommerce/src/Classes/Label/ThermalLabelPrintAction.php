<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;

defined('ABSPATH') || die('Restricted Access');

class ThermalLabelPrintAction {
    const THERMAL_LABEL_INFOS_VAR_NAME = 'lpc_thermal_labels_infos';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_tracking_number';
    const AJAX_TASK_NAME = 'label/url_print_thermal';

    /** @var Ajax */
    protected $ajaxDispatcher;
    /** @var OutwardLabelDb */
    protected $outwardLabelDb;
    /** @var InwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?OutwardLabelDb $outwardLabelDb = null,
        ?InwardLabelDb $inwardLabelDb = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
        $this->outwardLabelDb = Register::get('outwardLabelDb');
        $this->inwardLabelDb  = Register::get('inwardLabelDb');
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'getUrlsForThermalPrint']);
    }

    public function getThermalPrintActionUrl() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }

    public function getUrlsForThermalPrint() {
        if (!current_user_can('lpc_print_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to thermal outward label print',
                ]
            );
        }

        $labels = [];

        $thermalLabelsInfos = Helper::getVar(self::THERMAL_LABEL_INFOS_VAR_NAME, [], 'array');

        foreach ($thermalLabelsInfos as $oneThermalInfo) {
            $trackingNumber = $oneThermalInfo[self::TRACKING_NUMBER_VAR_NAME] ?? '';

            if (empty($trackingNumber)) {
                Logger::error(
                    __METHOD__ . ' tracking number missing'
                );

                return json_encode($labels);
            }

            $isOutward = true;
            $label     = $this->getLabel($trackingNumber, $isOutward);

            if (false === $label || !in_array($label['format'], [LabelGenerationPayload::LABEL_FORMAT_DPL, LabelGenerationPayload::LABEL_FORMAT_ZPL])) {
                continue;
            }

            // Legacy print kit URL, kept as a fallback for merchants who haven't set up QZ Tray yet
            $legacyUrl = $this->generateUrl($label);
            if (!$legacyUrl['success'] && !empty($legacyUrl['info'])) {
                Logger::error(
                    __METHOD__ . ' ' . $legacyUrl['info'],
                    [
                        'tracking_number' => $trackingNumber,
                    ]
                );
            }

            $labels[] = [
                'trackingNumber' => $trackingNumber,
                'format'         => $label['format'],
                // Raw ZPL/DPL bytes, base64-encoded, sent as-is to the printer through QZ Tray
                'label'          => base64_encode($label['label']),
                'legacyUrl'      => $legacyUrl['success'] ? $legacyUrl['info'] : '',
            ];

            if ($isOutward) {
                $this->outwardLabelDb->updatePrintedLabel($trackingNumber);
            } else {
                $this->inwardLabelDb->updatePrintedLabel($trackingNumber);
            }
        }

        return json_encode($labels);
    }

    protected function generateUrl($label = []) {
        $response = [
            'success' => false,
            'info'    => '',
        ];

        if (empty($label['label'])) {
            $response['info'] = 'no outward label for order';

            return $response;
        }

        if (
            !empty($label['format'])
            && LabelGenerationPayload::LABEL_FORMAT_DPL !== $label['format']
            && LabelGenerationPayload::LABEL_FORMAT_ZPL !== $label['format']
        ) {
            $response['info'] = 'wrong label format';

            return $response;
        }

        $port        = Helper::get_option('lpc_zpldpl_labels_port', 'USB');
        $ipAddress   = Helper::get_option('lpc_zpldpl_labels_ip');
        $protocol    = Helper::get_option('lpc_zpldpl_labels_protocol', 'DATAMAX');
        $urlPort     = Helper::get_option('lpc_zpldpl_labels_urlport', '8000');
        $urlProtocol = strtolower(Helper::get_option('lpc_zpldpl_labels_urlprotocol', 'HTTP'));

        if ('USB' === $port && empty($protocol)) {
            $response['info'] = 'if USB is selected, a protocol has to be selected';

            return $response;
        } elseif ('ETHERNET' === $port && empty($ipAddress)) {
            $response['info'] = 'if Ethernet is selected, an IP address has to be set';

            return $response;
        }

        $labelContent = base64_encode($label['label']);

        if ('USB' === $port) {
            $ipAddress = '';
        }

        if ('ETHERNET' === $port) {
            $protocol = '';
        }

        $response['success'] = true;
        $response['info']    = $urlProtocol . '://localhost:' . $urlPort . '/imprimerEtiquetteThermique?port=' . $port . '&protocole=' . $protocol . '&adresseIp=' . $ipAddress . '&etiquette=' . $labelContent;

        return $response;
    }

    protected function getLabel($trackingNumber, &$isOutward) {
        $label = $this->outwardLabelDb->getLabelFor($trackingNumber);

        if (!empty($label['label'])) {
            $isOutward = true;

            return $label;
        }

        $label = $this->inwardLabelDb->getLabelFor($trackingNumber);

        if (!empty($label['label'])) {
            $isOutward = false;

            return $label;
        }

        return false;
    }
}
