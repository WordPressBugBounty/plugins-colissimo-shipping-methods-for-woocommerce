<?php

namespace Colissimo\Api;

use Colissimo\Core\Register;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class PickupWidgetApi extends RestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/widget-colissimo/rest/';
    const OPTION_TOKEN_KEY = 'lpc_pickup_widget_token';
    const OPTION_TOKEN_KEY_EXPIRATION = 'lpc_pickup_widget_token_expiration';
    const PICKUP_WIDGET_TOKEN_VALIDITY = 1700;

    public string $token = '';

    protected function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    public function authenticate(bool $forceReload = false): string {
        $token           = Helper::get_option(self::OPTION_TOKEN_KEY);
        $tokenExpiration = Helper::get_option(self::OPTION_TOKEN_KEY_EXPIRATION, 0);

        if (!$forceReload && !empty($token) && (time() < (int) $tokenExpiration)) {
            $this->token = $token;

            return $this->token;
        }

        try {
            if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
                $credentials = [
                    'apikey' => Helper::get_option('lpc_apikey'),
                ];
            } else {
                $credentials = [
                    'login'    => Helper::get_option('lpc_id_webservices'),
                    'password' => Helper::getPasswordWebService(),
                ];
            }

            $parentAccountId = Register::get('accountApi')->getParentAccountId();
            if (!empty($parentAccountId)) {
                $credentials['partnerClientCode'] = $parentAccountId;
            }

            $response = $this->query('authenticate.rest', $credentials);

            Logger::debug(
                'Widget authenticate response',
                [
                    'method'   => __METHOD__,
                    'response' => $response,
                ]
            );

            if (!empty($response['token'])) {
                $this->token = $response['token'];
                update_option(self::OPTION_TOKEN_KEY, $this->token);
                update_option(self::OPTION_TOKEN_KEY_EXPIRATION, time() + self::PICKUP_WIDGET_TOKEN_VALIDITY);
            }

            return $this->token;
        } catch (Exception $e) {
            Logger::error('Error during authentication. Check your credentials."', ['message' => $e->getMessage()]);

            return '';
        }
    }
}
