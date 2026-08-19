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
    const ACCOUNT_INFORMATION_OPTION = 'lpc_account_information';
    const ACCOUNT_INFORMATION_VALIDITY = 15 * MINUTE_IN_SECONDS;
    const ACCOUNT_PROVIDER_OPTION = 'lpc_account_provider';
    const PROVIDER_OLD_ACCOUNT = 'COLISSIMO-V1';
    const PROVIDER_NEW_ACCOUNT = 'COLISSIMO-V2';

    protected function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    /**
     * Must be set to API calls for both advanced users and new Colissimo accounts
     */
    public function getParentAccountId(): string {
        $parentAccountId = (string) Helper::get_option('lpc_parent_account');

        // Some users enter their email address in here for some reason
        if (strpos($parentAccountId, '@') !== false) {
            $parentAccountId = '';
        }

        if (empty($parentAccountId) && $this->isNewAccount()) {
            $parentAccountId = 'api_key' === Helper::get_option('lpc_credentials_type', 'api_key')
                ? (string) Helper::get_option('lpc_contract_number')
                : (string) Helper::get_option('lpc_id_webservices');
        }

        return $parentAccountId;
    }

    public function isNewAccount(): bool {
        return self::PROVIDER_NEW_ACCOUNT === $this->getProvider();
    }

    public function getProvider(array $credentials = []): string {
        // Credentials that aren't saved yet are being tested, the stored provider doesn't apply to them
        if (!empty($credentials)) {
            return $this->requestProvider($credentials);
        }

        $credentials     = $this->getCredentials();
        $credentialsHash = md5(wp_json_encode($credentials));
        $storedProvider  = Helper::get_option(self::ACCOUNT_PROVIDER_OPTION, []);

        // The stored provider belongs to the credentials it was read with, so changing them refreshes it
        if (!empty($storedProvider['provider']) && ($storedProvider['credentials'] ?? '') === $credentialsHash) {
            return $storedProvider['provider'];
        }

        $provider = $this->requestProvider($credentials);
        if (empty($provider)) {
            return '';
        }

        update_option(
            self::ACCOUNT_PROVIDER_OPTION,
            [
                'credentials' => $credentialsHash,
                'provider'    => $provider,
            ],
            false
        );

        return $provider;
    }

    public function getAutologinURLs(): array {
        $payload = [
            'credential' => $this->getCredentials(),
        ];

        $parentAccountId = $this->getParentAccountId();
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

        // Only cache calls using the configured credentials, not the ones testing new credentials
        $useCache        = empty($payload);
        $credentialsHash = '';

        if ($useCache) {
            if (!empty($accountInformation)) {
                return $accountInformation;
            }

            $payload['credential'] = $this->getCredentials();

            $parentAccountId = $this->getParentAccountId();
            if (!empty($parentAccountId)) {
                $payload['partnerClientCode'] = $parentAccountId;
            }

            $credentialsHash = md5(wp_json_encode($payload));

            // The usage tag must reach the API, don't use the stored information in that case
            if (!$withTag) {
                $storedInformation = Helper::get_option(self::ACCOUNT_INFORMATION_OPTION);
                if (
                    !empty($storedInformation['data'])
                    && !empty($storedInformation['timestamp'])
                    && time() - $storedInformation['timestamp'] < self::ACCOUNT_INFORMATION_VALIDITY
                    && ($storedInformation['credentials'] ?? '') === $credentialsHash
                ) {
                    $accountInformation = $storedInformation['data'];

                    return $accountInformation;
                }
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

        if (empty($response['contractType'])) {
            return [];
        }

        if ($useCache) {
            $accountInformation = $response;
            update_option(
                self::ACCOUNT_INFORMATION_OPTION,
                [
                    'timestamp'   => time(),
                    'credentials' => $credentialsHash,
                    'data'        => $response,
                ],
                false
            );
        }

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

    private function getCredentials(): array {
        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            return [
                'apiKey' => Helper::get_option('lpc_apikey'),
            ];
        }

        return [
            'login'    => Helper::get_option('lpc_id_webservices'),
            'password' => Helper::getPasswordWebService(),
        ];
    }

    private function requestProvider(array $credentials): string {
        static $providers = [];

        $credentialsHash = md5(wp_json_encode($credentials));
        if (isset($providers[$credentialsHash])) {
            return $providers[$credentialsHash];
        }

        // Remember the failures too, the front office would otherwise ask again on every call of the request
        $providers[$credentialsHash] = '';

        foreach ($credentials as $oneCredential) {
            if (empty($oneCredential)) {
                return '';
            }
        }

        try {
            // Called without the account id on purpose, the API then answers a simplified response holding the provider
            $response = $this->query('v1/rest/additionalinformations', ['credential' => $credentials]);
            if (!empty($response['messageErreur'])) {
                throw new Exception(esc_html($response['messageErreur']));
            }
        } catch (Exception $e) {
            Logger::error(
                'Account provider request failed',
                [
                    'method' => __METHOD__,
                    'error'  => $e->getMessage(),
                ]
            );

            return '';
        }

        $providers[$credentialsHash] = (string) ($response['provider'] ?? '');

        return $providers[$credentialsHash];
    }
}
