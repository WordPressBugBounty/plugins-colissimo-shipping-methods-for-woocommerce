<?php

namespace Colissimo\Api;

use Colissimo\Helpers\Logger;

defined('ABSPATH') || die('Restricted Access');

class RelaysApi extends RestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/pointretrait-ws-cxf/rest/v2/pointretrait/';

    public function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    public function getRelays($payload) {
        $paramsWithoutCredentials = $payload;
        unset($paramsWithoutCredentials['password']);
        unset($paramsWithoutCredentials['apiKey']);

        Logger::debug(
            'Get relays webservice query',
            [
                'method'  => __METHOD__,
                'payload' => $paramsWithoutCredentials,
                'url'     => $this->getApiUrl('findRDVPointRetraitAcheminement'),
            ]
        );

        $response = $this->query('findRDVPointRetraitAcheminement', $payload);

        Logger::debug(
            'Get relays webservice response',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        return $response;
    }
}
