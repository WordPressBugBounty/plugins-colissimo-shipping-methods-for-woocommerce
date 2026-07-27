<?php

namespace Colissimo\Api;

use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class CustomsDocumentsApi extends RestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/api-document/rest/';

    protected function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    /**
     * @param array  $orderLabels  All the labels and their type for the current order, for multi-parcels
     * @param string $documentType The type among the ones provided in the WS documentation (see src/Classes/Order/Banner)
     * @param string $parcelNumber The label number
     * @param string $documentPath
     * @param string $documentName The uploaded file name for the error message
     *
     * @return string
     * @throws Exception When an error occurs.
     */
    public function storeDocument(array $orderLabels, string $documentType, string $parcelNumber, string $documentPath, string $documentName): string {
        $documentContent = Helper::getWpFilesystem()->get_contents($documentPath);
        if (false === $documentContent) {
            throw new Exception(esc_html__('The customs document file could not be read.', 'colissimo-shipping-methods-for-woocommerce'));
        }

        $document = [
            'name'    => $documentName,
            'type'    => mime_content_type($documentPath),
            'content' => $documentContent,
        ];

        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            // TODO remove this option once the documents API has been fixed
            $contractNumber = Helper::get_option('lpc_contract_number');
            $headers        = [
                'apiKey: ' . Helper::get_option('lpc_apikey'),
            ];
        } else {
            $login          = Helper::get_option('lpc_id_webservices');
            $contractNumber = Helper::get_option('lpc_parent_account');
            if (empty($contractNumber)) {
                $contractNumber = $login;
            }
            $headers = [
                'login: ' . $login,
                'password: ' . Helper::getPasswordWebService(),
            ];
        }

        $payload = [
            'accountNumber' => $contractNumber,
            'parcelNumber'  => $parcelNumber,
            'documentType'  => $documentType,
            'file'          => 'removed from logs',
            'filename'      => $parcelNumber . '-' . $documentType . '.' . pathinfo($documentName, PATHINFO_EXTENSION),
        ];

        // If it is a master parcel, add the follower parcels tracking numbers
        if (!empty($orderLabels[$parcelNumber]) && 'MASTER' === $orderLabels[$parcelNumber]) {
            $followerParcels = [];
            foreach ($orderLabels as $label => $type) {
                if ('FOLLOWER' === $type) {
                    $followerParcels[] = $label;
                }
            }
            $payload['parcelNumberList'] = implode(',', $followerParcels);
        }

        Logger::debug(
            'Customs Documents Sending Request',
            [
                'method'  => __METHOD__,
                'payload' => $payload,
            ]
        );

        $payload['file'] = $document;

        try {
            $response = $this->query('storedocument', $payload, $headers, self::DATA_TYPE_MULTIPART);

            Logger::debug(
                'Customs Documents Sending Response',
                [
                    'method'   => __METHOD__,
                    'response' => $response,
                ]
            );

            if ('000' != $response['errorCode']) {
                throw new Exception(esc_html($response['errors']['code'] . ' - ' . $response['errorLabel'] . ': ' . $response['errors']['message']));
            }

            // 50c82f93-015f-3c41-a841-07746eee6510.pdf for example, where 50c82f93-015f-3c41-a841-07746eee6510 is the uuid
            return $response['documentId'];
        } catch (Exception $e) {
            $message = [$e->getMessage()];

            if (!empty($this->lastResponse)) {
                $this->lastResponse = json_decode($this->lastResponse, true);
                if (!empty($this->lastResponse['errors'])) {
                    foreach ($this->lastResponse['errors'] as $oneError) {
                        $message[] = $oneError['code'] . ': ' . $oneError['message'];
                    }
                }
            }

            Logger::error(
                'Error during customs documents sending',
                [
                    'payload'   => $payload,
                    'exception' => implode(', ', $message),
                ]
            );

            if (1 < count($message)) {
                array_shift($message);
            }

            throw new Exception(
                sprintf(
            	// translators: %1$s is the document name, %2$s is the error message(s).
                    esc_html__('An error occurred when transmitting the file %1$s: %2$s', 'colissimo-shipping-methods-for-woocommerce'),
                    esc_html($documentName),
                    esc_html(implode(', ', $message)
                    )
                )
            );
        }
    }
}
