<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook, correctly prefixed with "lpc_".

namespace Colissimo\Api;

use Colissimo\Helpers\Logger;
use Exception;

defined('ABSPATH') || die('Restricted Access');

abstract class RestApi {
    const DATA_TYPE_JSON = 'json';
    const DATA_TYPE_URL = 'url';
    const DATA_TYPE_MULTIPART = 'multipart';
    /** @var bool|string */
    protected $lastResponse;

    abstract protected function getApiUrl(string $action): string;

    public function query(
        string $action,
        array $params = [],
        array $headers = [],
        string $dataType = self::DATA_TYPE_JSON
    ) {
        $url = $this->getApiUrl($action);

        /**
         * Filter on the API calls timeout used for slow servers
         *
         * @since 2.9.0
         */
        $timeout = apply_filters('lpc_api_calls_timeout', 10);

        $requestArgs = [
            'method'  => 'POST',
            'timeout' => $timeout,
            'headers' => [],
        ];

        switch ($dataType) {
            case self::DATA_TYPE_URL:
                $url                                    .= '?' . http_build_query($params);
                $requestArgs['method']                  = 'GET';
                $requestArgs['headers']['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
                break;
            case self::DATA_TYPE_MULTIPART:
                $boundary                               = wp_generate_password(24, false);
                $requestArgs['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
                $requestArgs['body']                    = $this->buildMultipartBody($params, $boundary);
                break;
            case self::DATA_TYPE_JSON:
            default:
                $requestArgs['headers']['Content-Type'] = 'application/json';
                $requestArgs['body']                    = wp_json_encode($params);
                break;
        }

        // Extra headers are provided in "Header-Name: value" string form.
        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);
            if (2 === count($parts)) {
                $requestArgs['headers'][trim($parts[0])] = trim($parts[1]);
            }
        }

        $response = wp_remote_request($url, $requestArgs);

        if (is_wp_error($response)) {
            Logger::error(
                __METHOD__,
                [
                    'error_code'    => $response->get_error_code(),
                    'error_message' => $response->get_error_message(),
                ]
            );
            throw new Exception(esc_html($response->get_error_message()));
        }

        $this->lastResponse = wp_remote_retrieve_body($response);
        $returnStatus       = (int) wp_remote_retrieve_response_code($response);

        if (self::DATA_TYPE_URL === $dataType) {
            return $this->lastResponse;
        }

        return $this->parseResponse($returnStatus, $this->lastResponse);
    }

    /**
     * Builds a multipart/form-data request body.
     *
     * WordPress' HTTP API has no native file-upload support, so the body is
     * assembled manually. A file field is an array with at least 'content' and
     * 'name' keys (and an optional 'type'); any other value is sent as a plain
     * form field.
     *
     * @param array  $fields
     * @param string $boundary
     *
     * @return string
     */
    protected function buildMultipartBody(array $fields, string $boundary): string {
        $eol  = "\r\n";
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . $eol;

            // Strip CR/LF and double quotes from any value interpolated into a header line to prevent header injection.
            $name = $this->sanitizeMultipartHeaderValue((string) $name);

            if (is_array($value) && isset($value['content'], $value['name'])) {
                $mimeType = $this->sanitizeMultipartHeaderValue(empty($value['type']) ? 'application/octet-stream' : $value['type']);
                $fileName = $this->sanitizeMultipartHeaderValue((string) $value['name']);
                $body     .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $fileName . '"' . $eol;
                $body     .= 'Content-Type: ' . $mimeType . $eol . $eol;
                $body     .= $value['content'] . $eol;
            } else {
                $body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
                $body .= $value . $eol;
            }
        }

        $body .= '--' . $boundary . '--' . $eol;

        return $body;
    }

    /**
     * Remove CR/LF and double quotes so a value cannot break out of the multipart header line it is embedded in.
     *
     * @param string $value
     *
     * @return string
     */
    private function sanitizeMultipartHeaderValue(string $value): string {
        return str_replace(["\r", "\n", '"'], '', $value);
    }

    protected function parseResponse($returnStatus, $response) {
        preg_match('/--(.*)\b/', $response, $boundary);

        $content = empty($boundary)
            ? $this->parseMonoPartBody($response)
            : $this->parseMultiPartBody($response, $boundary[0]);

        if (200 === $returnStatus) {
            return $content;
        }

        Logger::warn(
            __METHOD__,
            [
                'returnStatus' => $returnStatus,
                'jsonInfos'    => !empty($content['<jsonInfos>']) ? $content['<jsonInfos>'] : $content,
            ]
        );

        if (!empty($content['<jsonInfos>'])) {
            $content = $content['<jsonInfos>'];
        }

        if (isset($content['messages'])) {
            $message = $content['messages'][0]['id'] . ' : ' . $content['messages'][0]['messageContent'];
        } elseif (!empty($content['error'])) {
            $message = $content['error'];
            if (!empty($content['message'])) {
                $message .= ' : ' . $content['message'];
            }
        } elseif (!empty($content['errorCode']) && !empty($content['errorLabel'])) {
            $message = $content['errorCode'] . ': ' . $content['errorLabel'];
        } else {
            $message = __('Unknown error', 'colissimo-shipping-methods-for-woocommerce');
        }

        throw new Exception('CURL error: (' . intval($returnStatus) . ') ' . esc_html($message), (int) $returnStatus);
    }

    protected function parseMultiPartBody($body, $boundary) {
        $messages = array_filter(
            array_map(
                'trim',
                explode($boundary, $body)
            )
        );

        $parts = [];
        foreach ($messages as $message) {
            if ('--' === $message) {
                break;
            }

            if (strpos($message, "\r\n\r\n") === false) {
                Logger::error(
                    'Incomplete response from Colissimo API',
                    [
                        'response' => $message,
                    ]
                );
                continue;
            }

            $headers = [];
            [$headerLines, $body] = explode("\r\n\r\n", $message, 2);

            foreach (explode("\r\n", $headerLines) as $headerLine) {
                [$key, $value] = preg_split('/:\s+/', $headerLine, 2);
                $headers[strtolower($key)] = $value;
            }

            if (!empty($headers['content-type']) && 'application/json' === $headers['content-type']) {
                $body = json_decode($body, true);
            }

            if (!empty($headers['content-id'])) {
                $parts[$headers['content-id']] = '<jsonInfos>' === $headers['content-id']
                    ? json_decode($body, true)
                    : $body;
            }
        }

        return $parts;
    }

    protected function parseMonoPartBody($body) {
        return json_decode($body, true);
    }
}
