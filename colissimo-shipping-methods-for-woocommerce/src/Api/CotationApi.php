<?php

namespace Colissimo\Api;

use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;

class CotationApi extends RestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/cotation-ws-cxf/rest/external/servicesTarification/';

    const BOOLEAN_FIELDS = [
        'avecSignature',
        'livraisonDomicile',
        'retour',
        'avecEngagement',
        'offreEntreprise',
        'engagementDelai',
        'economique',
    ];

    const STRING_FIELDS = [
        'codePaysExpediteur',
        'codePostalExpediteur',
        'codePaysDestinataire',
        'codePostalDestinataire',
        'typeSiteLivraison',
        'sousCompteClient',
    ];

    // Options expecting a specific value as "choix" instead of "true"
    const OPTION_INSURED_VALUE = 'VALEUR_ASSUREE';
    const OPTION_RECOMMENDATION = 'AVIS_RECEPTION';

    protected function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    public function calculateCost(array $params): array {
        $payload = $this->prepareParams($params);

        Logger::debug(
            __METHOD__ . ' request',
            [
                'url' => $this->getApiUrl('calculerTarif'),
                'params' => $payload,
            ]
        );

        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            $credentials = [
                'apiKey: ' . Helper::get_option('lpc_apikey'),
            ];
        } else {
            $credentials = [
                'login: ' . Helper::get_option('lpc_id_webservices'),
                'password: ' . Helper::getPasswordWebService(),
            ];
        }

        try {
            $response = $this->query('calculerTarif', $payload, $credentials);
        } catch (\Exception $exception) {
            Logger::error(
                __METHOD__ . ' response',
                [
                    'error' => $exception->getMessage(),
                ]
            );

            // On failure the API answers with a non-200 status and an "errors" payload: pass it along so the real message can be displayed
            $errorResponse = json_decode((string) $this->lastResponse, true);
            if (!empty($errorResponse['errors'])) {
                return $errorResponse;
            }

            return [];
        }

        if (!is_array($response)) {
            $response = [];
        }

        Logger::debug(
            __METHOD__ . ' response',
            [
                'response' => $response,
            ]
        );

        return $response;
    }

    /**
     * Normalize and cast the raw form values before sending them to the web service.
     *
     * @param array $params
     *
     * @return array
     */
    protected function prepareParams(array $params): array {
        $payload = [];

        foreach (self::STRING_FIELDS as $field) {
            if (isset($params[$field]) && '' !== $params[$field]) {
                $payload[$field] = (string) $params[$field];
            }
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            if (isset($params[$field])) {
                $payload[$field] = filter_var($params[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (isset($params['poids']) && '' !== $params['poids']) {
            $payload['poids'] = (float) $params['poids'];
        }

        // 0 : general price, 1 : discounted price
        $payload['typeTarif'] = isset($params['typeTarif']) ? (int) $params['typeTarif'] : 0;

        if (!empty($params['optionsValorisees']) && is_array($params['optionsValorisees'])) {
            foreach ($params['optionsValorisees'] as $option) {
                if (empty($option['codeOption'])) {
                    continue;
                }

                $codeOption = (string) $option['codeOption'];

                // The insured value expects the amount and the recommendation its level, every other option is activated with "true"
                if (in_array($codeOption, [self::OPTION_INSURED_VALUE, self::OPTION_RECOMMENDATION], true)) {
                    $choice = isset($option['choix']) ? (string) $option['choix'] : '';
                } else {
                    $choice = 'true';
                }

                $payload['optionsValorisees'][] = [
                    'codeOption' => $codeOption,
                    'choix'      => $choice,
                ];
            }
        }

        return $payload;
    }
}
