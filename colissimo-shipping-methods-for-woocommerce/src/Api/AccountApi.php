<?php

namespace Colissimo\Api;

use Colissimo\Api\RestApi;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Classes\Label\LabelGenerationPayload;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class AccountApi extends RestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/api-ewe/';
    const LPC_CONTRACT_TYPE_FACILITE = 'FACILITE';

    protected function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    public function getAutologinURLs(): array {
        $payload = [];

        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            $payload['credential']['apiKey'] = Helper::get_option('lpc_apikey');
        } else {
            $payload['credential']['login']    = Helper::get_option('lpc_id_webservices');
            $payload['credential']['password'] = Helper::getPasswordWebService();
        }

        $parentAccountId = Helper::get_option('lpc_parent_account');
        if (!empty($parentAccountId)) {
            $payload['partnerClientCode'] = $parentAccountId;
        }

        try {
            $response = $this->query('v1/rest/urlCboxExt', $payload);

            if (!empty($response['messageErreur'])) {
                Logger::error(
                    'Auto login request failed',
                    [
                        'method' => __METHOD__,
                        'error'  => $response['messageErreur'],
                    ]
                );

                return [];
            }
        } catch (Exception $e) {
            Logger::error(
                'Auto login request failed',
                [
                    'method' => __METHOD__,
                    'error'  => $e->getMessage(),
                ]
            );

            return [];
        }

        return $response;
    }

    public function isCgvAccepted(): bool {
        $acceptedCgv = Helper::get_option('lpc_accepted_cgv');

        if (!empty($acceptedCgv)) {
            return true;
        }

        // Get contract type
        $accountInformation = $this->getAccountInformation();

        // We couldn't get the account information, we can't check the CGV
        if (empty($accountInformation['contractType'])) {
            return true;
        }

        if (self::LPC_CONTRACT_TYPE_FACILITE !== $accountInformation['contractType'] || !empty($accountInformation['cgv']['accepted'])) {
            update_option('lpc_accepted_cgv', true, false);

            return true;
        }

        return false;
    }

    public function getAccountInformation(array $payload = [], bool $withTag = false): array {
        static $accountInformation = null;
        if (!empty($accountInformation)) {
            return $accountInformation;
        }

        if (empty($payload)) {
            if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
                $payload['credential']['apiKey'] = Helper::get_option('lpc_apikey');
            } else {
                $payload['credential']['login']    = Helper::get_option('lpc_id_webservices');
                $payload['credential']['password'] = Helper::getPasswordWebService();
            }

            $parentAccountId = Helper::get_option('lpc_parent_account');
            if (!empty($parentAccountId)) {
                $payload['partnerClientCode'] = $parentAccountId;
            }
        }

        if ($withTag) {
            $payload['tagInfoPartner'] = 'WOOCOMMERCE';
        }

        try {
            $response = $this->query('v1/rest/additionalinformations', $payload);
            if (!empty($response['messageErreur'])) {
                throw new Exception(esc_html($response['messageErreur']));
            }
        } catch (Exception $e) {
            Logger::error(
                'Contract information request failed',
                [
                    'method' => __METHOD__,
                    'error'  => $e->getMessage(),
                ]
            );

            return [];
        }

        Logger::debug(
            'Getting contract information',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        if (empty($response['cgv'])) {
            return [];
        }

        $accountInformation = $response;

        return $response;
    }

    public function isHazmatOptionActive(): bool {
        $accountInformation = $this->getAccountInformation();

        return !empty($accountInformation['hazmatStatus']);
    }

    public function getHazmatCategories(): array {
        $accountInformation = $this->getAccountInformation();

        if (!$this->isHazmatOptionActive() || empty($accountInformation['hazmatCategories'])) {
            return [];
        }

        $hazmatCategories = LabelGenerationPayload::HAZMAT_CATEGORIES;
        foreach ($hazmatCategories as $key => $hazmatCategory) {
            $hazmatCategories[$key]['active'] = in_array($hazmatCategory['code'], $accountInformation['hazmatCategories']);
        }

        return $hazmatCategories;
    }
}
