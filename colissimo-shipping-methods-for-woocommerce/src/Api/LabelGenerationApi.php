<?php

namespace Colissimo\Api;

use Colissimo\Classes\Label\LabelGenerationPayload;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class LabelGenerationApi extends RestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/3.1/';

    protected function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    public function generateLabel(LabelGenerationPayload $payload, bool $isSecuredReturn = false) {
        try {
            $assembledPayload = $payload->assemble();
            Logger::debug(
                'Label generation request',
                [
                    'method'  => __METHOD__,
                    'payload' => $payload->getPayloadWithoutPassword(),
                ]
            );

            $headers = [];
            if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
                $headers[] = 'apiKey: ' . Helper::get_option('lpc_apikey');
            }

            $queryAction = $isSecuredReturn ? 'generateToken' : 'generateLabel';
            $response    = $this->query($queryAction, $assembledPayload, $headers);

            $jsonResponse = $response['<jsonInfos>'];

            Logger::debug(
                'Label generation response',
                [
                    'method'   => __METHOD__,
                    'response' => $jsonResponse,
                ]
            );

            if (0 != $jsonResponse['messages'][0]['id']) {
                throw new Exception(esc_html($jsonResponse['messages'][0]['messageContent']), $jsonResponse['messages'][0]['id']);
            }

            return $response;
        } catch (Exception $e) {
            $payloadWithoutPass = $assembledPayload;
            unset($payloadWithoutPass['password']);
            Logger::error(
                'Error during label generation."',
                [
                    'payload'   => $payloadWithoutPass,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    public function listMailBoxPickingDates(array $payload) {
        $payloadWithoutPass = $payload;
        unset($payloadWithoutPass['password']);

        Logger::debug(
            'List mail box picking dates query',
            [
                'method'  => __METHOD__,
                'payload' => $payloadWithoutPass,
            ]
        );

        $headers = [];
        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            $headers[] = 'apiKey: ' . Helper::get_option('lpc_apikey');
        }

        $response = $this->query('getListMailBoxPickingDates', $payload, $headers);

        Logger::debug(
            'List mail box picking dates response',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        return $response;
    }

    public function planPickup(array $payload) {
        if (defined('LPC_DEV_MODE') && LPC_DEV_MODE) {
            return [
                'id'             => 0,
                'messageContent' => 'by-passed for tests',
                'type'           => 'INFOS',
            ];
        }

        $payloadWithoutPass = $payload;
        unset($payloadWithoutPass['password']);
        Logger::debug(
            'Plan pickup query',
            [
                'method'  => __METHOD__,
                'payload' => $payloadWithoutPass,
            ]
        );

        $response = $this->query('planPickup', $payload);

        Logger::debug(
            'Plan pickup response',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        return $response;
    }
}
