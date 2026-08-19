<?php

namespace Colissimo\Api;

use Colissimo\Core\Register;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Exception;
use stdClass;

defined('ABSPATH') || die('Restricted Access');

class SlipGenerationApi extends RestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/3.1/';

    public function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    public function generateBordereau(array $parcelNumbers) {
        $parcelNumbersObject                 = new stdClass();
        $parcelNumbersObject->parcelsNumbers = $parcelNumbers;

        $request = [
            'generateBordereauParcelNumberList' => $parcelNumbersObject,
        ];

        Logger::debug(
            'Generate bordereau query',
            [
                'method'  => __METHOD__,
                'payload' => $request,
            ]
        );

        $headers = [];
        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            $headers[] = 'apiKey: ' . Helper::get_option('lpc_apikey');
        } else {
            $request['contractNumber'] = Helper::get_option('lpc_id_webservices');
            $request['password']       = Helper::getPasswordWebService();
        }

        $parentAccountId = Register::get('accountApi')->getParentAccountId();
        if (!empty($parentAccountId)) {
            $request['fields']['field'][] = [
                'key'   => 'ACCOUNT_NUMBER',
                'value' => $parentAccountId,
            ];
        }

        $response = $this->query('generateBordereauByParcelsNumbers', $request, $headers);

        $jsonResponse = $response['<jsonInfos>'] ?? [];

        Logger::debug(
            'Generate bordereau response',
            [
                'method'   => __METHOD__,
                'response' => $jsonResponse,
            ]
        );

        if (!isset($jsonResponse['messages'][0]['id'])) {
            throw new Exception('Error when generating delivery slip.');
        }

        if (0 != $jsonResponse['messages'][0]['id']) {
            Logger::error(
                __METHOD__ . 'error in API response',
                ['response' => $jsonResponse['messages']]
            );
            throw new Exception('Error when generating bordereau: ' . esc_html($jsonResponse['messages'][0]['messageContent'] ?? ''));
        }

        return $response;
    }
}
