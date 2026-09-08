<?php

namespace Colissimo\Classes\Label;

use Colissimo\Core\Ajax;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use Colissimo\Helpers\Logger;

defined('ABSPATH') || die('Restricted Access');

/**
 * Signs the calls sent to QZ Tray.
 *
 * QZ Tray only lets the operator tick "Remember this decision" on a signed request coming from a
 * certificate it trusts. Without a certificate every connection and every print shows the
 * "An anonymous request wants to connect to QZ Tray / Untrusted website" popup again.
 */
class QzTraySigning {
    const AJAX_TASK_NAME = 'label/qz_sign';
    const AJAX_TASK_GENERATE = 'label/qz_generate';
    const CERTIFICATE_OPTION = 'lpc_qz_certificate';
    const PRIVATE_KEY_OPTION = 'lpc_qz_private_key';
    const SIGNATURE_ALGORITHM = 'SHA512';
    const CERTIFICATE_VALIDITY_DAYS = 3650;
    const MAX_PEM_LENGTH = 65536;
    const MAX_REQUEST_LENGTH = 8192;

    /** @var Ajax */
    protected $ajaxDispatcher;

    public function __construct(?Ajax $ajaxDispatcher = null) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher', $ajaxDispatcher);
    }

    public function init() {
        // Label printers don't necessarily manage the settings, the capabilities are checked in the callback
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'signRequest'], false);
        $this->ajaxDispatcher->register(self::AJAX_TASK_GENERATE, [$this, 'generateCertificate']);
    }

    public function getSignActionUrl(): string {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }

    public function getGenerateActionUrl(): string {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_GENERATE);
    }

    public function getCertificate(): string {
        return trim((string) Helper::get_option(self::CERTIFICATE_OPTION, ''));
    }

    public function getPrivateKey(): string {
        $storedKey = Helper::get_option(self::PRIVATE_KEY_OPTION, '');

        if (empty($storedKey)) {
            return '';
        }

        // The key is normally encrypted, but stays usable if it was stored as a plain PEM
        if (false !== strpos($storedKey, '-----BEGIN')) {
            return trim($storedKey);
        }

        $privateKey = Helper::decryptPassword($storedKey);

        return false === $privateKey ? '' : trim($privateKey);
    }

    public function isConfigured(): bool {
        return '' !== $this->getCertificate() && '' !== $this->getPrivateKey();
    }

    /**
     * Data used by assets/js/qz/setup.js to configure qz.security.*
     */
    public function getScriptArgs(): array {
        return [
            'certificate' => $this->isConfigured() ? $this->getCertificate() : '',
            'signUrl'     => $this->getSignActionUrl(),
            'algorithm'   => self::SIGNATURE_ALGORITHM,
        ];
    }

    /**
     * Signs the payload QZ Tray asks the website to sign, with the merchant's private key.
     */
    public function signRequest() {
        if (!current_user_can('lpc_print_labels') && !current_user_can('lpc_manage_settings')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(['message' => 'unauthorized access to QZ Tray signing']);
        }

        $request = Helper::getVar('request', '', 'string', 'POST');

        if ('' === $request) {
            return $this->ajaxDispatcher->makeAndLogError(['message' => 'no QZ Tray request to sign']);
        }

        if (strlen($request) > self::MAX_REQUEST_LENGTH) {
            return $this->ajaxDispatcher->makeAndLogError(['message' => 'oversized QZ Tray request to sign']);
        }

        $storedKey = $this->getPrivateKey();

        if ('' === $storedKey) {
            return $this->ajaxDispatcher->makeAndLogError(
                ['message' => __('The QZ Tray private key is missing or invalid.', 'colissimo-shipping-methods-for-woocommerce')]
            );
        }

        $privateKey = openssl_pkey_get_private($storedKey);

        if (false === $privateKey) {
            // Describes the stored key without exposing it, to tell a mangled PEM from a bad decryption
            Logger::error(
                __METHOD__ . ' unusable QZ Tray private key',
                [
                    'openssl_error' => openssl_error_string(),
                    'length'        => strlen($storedKey),
                    'is_pem'        => false !== strpos($storedKey, '-----BEGIN'),
                    'lines'         => substr_count($storedKey, "\n") + 1,
                ]
            );

            return $this->ajaxDispatcher->makeAndLogError(
                ['message' => __('The QZ Tray private key is missing or invalid.', 'colissimo-shipping-methods-for-woocommerce')]
            );
        }

        $signature = '';

        if (!openssl_sign($request, $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
            return $this->ajaxDispatcher->makeAndLogError(
                ['message' => __('The QZ Tray request could not be signed.', 'colissimo-shipping-methods-for-woocommerce')]
            );
        }

        return $this->ajaxDispatcher->makeSuccess(['signature' => base64_encode($signature)]);
    }

    public function generateCertificate() {
        if (!current_user_can('lpc_manage_settings')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(['message' => 'unauthorized access to QZ Tray certificate generation']);
        }

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha512',
        ];

        $privateKeyResource = openssl_pkey_new($config);
        $csr                = false === $privateKeyResource ? false : openssl_csr_new($this->getDistinguishedName(), $privateKeyResource, $config);
        $certificateSigned  = false === $csr ? false : openssl_csr_sign($csr, null, $privateKeyResource, self::CERTIFICATE_VALIDITY_DAYS, $config, random_int(1, PHP_INT_MAX));

        $certificate = '';
        $privateKey  = '';

        if (
            false === $certificateSigned
            || !openssl_x509_export($certificateSigned, $certificate)
            || !openssl_pkey_export($privateKeyResource, $privateKey, null, $config)
        ) {
            Logger::error(__METHOD__ . ' QZ Tray certificate generation failed', ['openssl_error' => openssl_error_string()]);

            return $this->ajaxDispatcher->makeError(
                [
                    'message' => __(
                        'The certificate could not be generated on this server. Use the manual method with the files generated by QZ Tray instead.',
                        'colissimo-shipping-methods-for-woocommerce'
                    ),
                ]
            );
        }

        update_option(self::CERTIFICATE_OPTION, trim($certificate), false);
        update_option(self::PRIVATE_KEY_OPTION, Helper::encryptPassword(trim($privateKey)), false);

        return $this->ajaxDispatcher->makeSuccess(
            [
                'certificate' => $this->getCertificate(),
                'info'        => $this->getCertificateInfo(),
            ]
        );
    }

    /**
     * Identity shown by QZ Tray when it displays the certificate.
     */
    protected function getDistinguishedName(): array {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = empty($host) ? 'localhost' : $host;

        // OpenSSL refuses anything but letters, digits and simple punctuation in a DN
        $shopName = preg_replace('#[^A-Za-z0-9 \'.-]#', '', Helper::toAscii(get_bloginfo('name')));
        $shopName = trim(substr((string) $shopName, 0, 64));

        $country = wc_get_base_location()['country'] ?? '';
        $country = preg_match('#^[A-Za-z]{2}$#', $country) ? strtoupper($country) : 'FR';

        return [
            'countryName'            => $country,
            'organizationName'       => '' === $shopName ? $host : $shopName,
            'organizationalUnitName' => 'Colissimo',
            'commonName'             => $host,
        ];
    }

    public function getCertificateInfo(): string {
        $certificate = $this->getCertificate();

        if ('' === $certificate) {
            return '';
        }

        $parsedCertificate = openssl_x509_parse($certificate);

        if (false === $parsedCertificate) {
            return __('The saved certificate could not be read.', 'colissimo-shipping-methods-for-woocommerce');
        }

        $name   = $parsedCertificate['subject']['CN'] ?? '';
        $expiry = empty($parsedCertificate['validTo_time_t'])
            ? ''
            : wp_date(get_option('date_format'), $parsedCertificate['validTo_time_t']);

        if ('' === $expiry) {
            // translators: %s is the name the certificate was issued to.
            return sprintf(__('Certificate installed for %s.', 'colissimo-shipping-methods-for-woocommerce'), $name);
        }

        // translators: %1$s is the name the certificate was issued to, %2$s its expiry date.
        return sprintf(__('Certificate installed for %1$s, valid until %2$s.', 'colissimo-shipping-methods-for-woocommerce'), $name, $expiry);
    }

    /**
     * Checks that the given private key is usable and that it matches the saved certificate.
     *
     * @return string An error message, empty when the key is valid.
     */
    public function getPrivateKeyError(string $privateKey, string $certificate): string {
        $key = openssl_pkey_get_private($privateKey);

        if (false === $key) {
            Logger::error(__METHOD__ . ' invalid QZ Tray private key');

            return __(
                'The QZ Tray private key is invalid, please choose the private-key.pem file generated by QZ Tray.',
                'colissimo-shipping-methods-for-woocommerce'
            );
        }

        if ('' === $certificate) {
            return '';
        }

        // The certificate file generated by QZ Tray may hold the intermediate and root certificates too
        if (!preg_match_all('#-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----#s', $certificate, $matches)) {
            return '';
        }

        foreach ($matches[0] as $oneCertificate) {
            if (openssl_x509_check_private_key($oneCertificate, $key)) {
                return '';
            }
        }

        return __(
            'The QZ Tray private key does not match the certificate, please choose both files generated by QZ Tray.',
            'colissimo-shipping-methods-for-woocommerce'
        );
    }
}
