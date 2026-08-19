<?php

namespace Colissimo\Api;

use Colissimo\Core\Register;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use DateTime;
use Exception;

class CheckoutApi extends RestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/tunnel-commande/rest/TunnelCommandeWS/';
    const MAX_NB_TRIES_SCHEDULE = 14;
    const SECONDS_IN_A_DAY = 86400;

    protected function getApiUrl(string $action): string {
        return self::API_BASE_URL . $action;
    }

    public function getDeliveryDate(string $postCode, ?int $baseTimestamp = null, bool $dateOnly = false): ?string {
        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            $payload['credentials']['apiKey'] = Helper::get_option('lpc_apikey');
        } else {
            $payload['credentials']['login']    = Helper::get_option('lpc_id_webservices');
            $payload['credentials']['password'] = Helper::getPasswordWebService();
        }

        $parentAccountId = Register::get('accountApi')->getParentAccountId();
        if (!empty($parentAccountId)) {
            $payload['credentials']['partnerClientCode'] = $parentAccountId;
        }

        $payload['data']['zipCodeDest']  = $postCode;
        $payload['data']['regateDepart'] = Helper::get_option('lpc_delivery_date_deposit_location');
        $payload['data']['depositDate']  = $this->getDepositDate($baseTimestamp);

        if (empty($payload['data']['depositDate'])) {
            return null;
        }

        Logger::debug(
            'Getting delivery date payload',
            [
                'method'  => __METHOD__,
                'payload' => $payload['data'],
            ]
        );

        try {
            $response = $this->query('getDateLivraison', $payload);

            if (empty($response['errorCode']) || 'OK' !== $response['errorCode']) {
                throw new Exception(esc_html($response['message'] ?? ($response['errorCode'] ?? 'Unknown error')));
            }
        } catch (Exception $e) {
            Logger::error(
                'Delivery date request failed',
                [
                    'method' => __METHOD__,
                    'error'  => $e->getMessage(),
                ]
            );

            return null;
        }

        Logger::debug(
            'Getting delivery date',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        return !empty($response['deliveryDate']) ? $this->formatDeliveryDate($response['deliveryDate'], $dateOnly) : null;
    }

    private function getDepositDate(?int $baseTimestamp = null): ?string {
        $cuttOffDates = Helper::get_option('lpc_delivery_date_cuttoff_times');
        if (empty($cuttOffDates)) {
            return null;
        }

        $cuttOffDates = @json_decode($cuttOffDates, true);
        if (empty($cuttOffDates['weekly_schedule'])) {
            return null;
        }

        $nbTries     = 0;
        $time        = $baseTimestamp ?? time();
        $currentTime = (int) wp_date('H', $time);

        // Find the processing day
        do {
            $processingTime    = $time + ($nbTries * self::SECONDS_IN_A_DAY);
            $processingDate    = wp_date('Y-m-d', $processingTime);
            $processingWeekday = wp_date('N', $processingTime);

            // Check exceptions first
            $cuttOffTimeFromRules = $this->getExceptionCuttOff($cuttOffDates, $processingDate);

            // Get global weekday rule as a fallback
            if (empty($cuttOffTimeFromRules)) {
                $cuttOffTimeFromRules = $cuttOffDates['weekly_schedule'][Helper::DAYS[$processingWeekday]]['cuttOff'] ?? null;
            }

            // For the first day, we accept orders placed before the cuttoff hour. For next days the order is ready the first business hour so don't check the time
            if (0 === $nbTries && !empty($cuttOffTimeFromRules) && ('none' === $cuttOffTimeFromRules || $currentTime > (int) $cuttOffTimeFromRules)) {
                $cuttOffTimeFromRules = null;
            }

            $nbTries ++;
        } while ($nbTries < self::MAX_NB_TRIES_SCHEDULE && (empty($cuttOffTimeFromRules) || 'none' === $cuttOffTimeFromRules));

        if (empty($cuttOffTimeFromRules) || 'none' === $cuttOffTimeFromRules) {
            return null;
        }

        // Apply the processing time
        $preparationTime = (int) Helper::get_option('lpc_preparation_time');

        if (!empty($cuttOffDates['weekly_schedule'][Helper::DAYS[$processingWeekday]]['delay'])) {
            $preparationTime = (int) $cuttOffDates['weekly_schedule'][Helper::DAYS[$processingWeekday]]['delay'];
        }

        if (!empty($preparationTime)) {
            $processingTime += $preparationTime * self::SECONDS_IN_A_DAY;
        }

        return wp_date('Y-m-d', $processingTime);
    }

    private function getExceptionCuttOff(array $cuttOffDates, string $date): ?string {
        if (empty($cuttOffDates['exceptions'])) {
            return null;
        }

        foreach ($cuttOffDates['exceptions'] as $oneException) {
            if ($oneException['date'] === $date) {
                return $oneException['hour'];
            }
        }

        return null;
    }

    private function formatDeliveryDate(string $deliveryDate, bool $dateOnly = false): ?string {
        $dateTime = DateTime::createFromFormat('d/m/Y', $deliveryDate);
        if (!$dateTime) {
            return null;
        }

        $format = Helper::get_option('lpc_delivery_date_format');
        switch ($format) {
            case 'default':
                $dateFormat = Helper::get_option('date_format', __('l, F j', 'colissimo-shipping-methods-for-woocommerce'));
                break;
            case 'full':
                $dateFormat = __('l, F j', 'colissimo-shipping-methods-for-woocommerce');
                break;
            case 'simple':
                $dateFormat = __('F j', 'colissimo-shipping-methods-for-woocommerce');
                break;
            case 'short':
                $dateFormat = __('M j', 'colissimo-shipping-methods-for-woocommerce');
                break;
            default:
                $dateFormat = $format;
        }

        $timestamp = $dateTime->getTimestamp();
        $date      = Helper::translateDate(gmdate($dateFormat, $timestamp));

        // Return only the raw formatted date, without the surrounding text and styling
        if ($dateOnly) {
            return $date;
        }

        $text = Helper::get_option('lpc_delivery_date_text');
        if (empty($text) || strpos($text, '{date}') === false) {
            $text = __('Delivery expected on {date}', 'colissimo-shipping-methods-for-woocommerce');
        }

        $styles    = '';
        $textColor = Helper::get_option('lpc_delivery_date_color');
        if (!empty($textColor)) {
            $styles .= 'color:' . $textColor . ';';
        }

        $textFont = Helper::getFont('lpc_delivery_date_font');
        if (!empty($textFont) && 'default' !== $textFont) {
            $styles .= 'font-family:' . $textFont . ';';
        }

        $textSize = Helper::get_option('lpc_delivery_date_size');
        if (!empty($textSize) && 'default' !== $textSize) {
            $styles .= 'font-size:' . $textSize . ';';
        }

        return '<span style="' . esc_attr($styles) . '">' . esc_html(str_replace('{date}', $date, $text)) . '</span>';
    }
}
