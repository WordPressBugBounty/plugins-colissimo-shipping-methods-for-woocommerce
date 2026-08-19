<?php

namespace Colissimo\Classes\Pickup;

use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use DateInterval;
use DateTime;
use Exception;

defined('ABSPATH') || die('Restricted Access');

class GetRelaysPayload {
    protected $payload;

    public function __construct() {
        $this->payload = [
            'origin' => 'CMS',
        ];
    }

    public function withCredentials() {
        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            $this->payload['apiKey'] = Helper::get_option('lpc_apikey');
        } else {
            $this->payload['accountNumber'] = Helper::get_option('lpc_id_webservices');
            $this->payload['password']      = Helper::getPasswordWebService();
        }

        $parentAccountId = Register::get('accountApi')->getParentAccountId();
        if (!empty($parentAccountId)) {
            $this->payload['codTiersPourPartenaire'] = $parentAccountId;
        }

        return $this;
    }

    public function withAddress(array $address) {
        $this->payload['address']     = $address['address'];
        $this->payload['zipCode']     = preg_replace('#[^0-9a-zA-Z]#', '', $address['zipCode']);
        $this->payload['city']        = $address['city'];
        $this->payload['countryCode'] = $address['countryCode'];

        return $this;
    }

    public function withShippingDate(?DateTime $shippingDate = null) {
        if (null === $shippingDate) {
            $shippingDate           = new DateTime();
            $numberOfDayPreparation = intval(Helper::get_option('lpc_preparation_time', '1'));
            $shippingDate->add(new DateInterval('P' . $numberOfDayPreparation . 'D'));
        }

        if (empty($shippingDate)) {
            unset($this->payload['shippingDate']);
        } else {
            $this->payload['shippingDate'] = $shippingDate->format('d/m/Y');
        }

        return $this;
    }

    public function withOptionInter() {
        if ('FR' === $this->payload['countryCode']) {
            $this->payload['optionInter'] = '0';
        } else {
            $this->payload['optionInter'] = '1';
        }

        return $this;
    }

    public function withRelayTypeFilter(?float $weight = null) {
        if (empty($weight)) {
            $cart = WC()->cart;
            if (!empty($cart)) {
                $weight = wc_get_weight(WC()->cart->get_cart_contents_weight(), 'kg');
            }
        }

        if (!empty($weight) && $weight > 20) {
            $this->payload['filterRelay'] = '0';

            return $this;
        }

        $relayTypes = Helper::get_option('lpc_relay_types');
        if (empty($relayTypes)) {
            $relayTypes = '1';
        } elseif ('-1' === $relayTypes) {
            $relayTypes = '0';
        }

        $this->payload['filterRelay'] = $relayTypes;

        return $this;
    }

    public function checkConsistency() {
        $this->checkLogin();
        $this->checkAddress();
        $this->checkOptions();
    }

    protected function checkLogin() {
        if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            if (empty($this->payload['apiKey'])) {
                throw new Exception(esc_html__('Application key required to get relay points', 'colissimo-shipping-methods-for-woocommerce'));
            }
        } else {
            if (empty($this->payload['accountNumber']) || empty($this->payload['password'])) {
                throw new Exception(esc_html__('Login and password required to get relay points', 'colissimo-shipping-methods-for-woocommerce'));
            }
        }
    }

    protected function checkAddress() {
        if (empty($this->payload['zipCode'])) {
            throw new Exception(esc_html__('Zipcode required to get relay points', 'colissimo-shipping-methods-for-woocommerce'));
        }

        if (empty($this->payload['city'])) {
            throw new Exception(esc_html__('City required to get relay points', 'colissimo-shipping-methods-for-woocommerce'));
        }

        if (empty($this->payload['countryCode'])) {
            throw new Exception(esc_html__('Country code required to get relay points', 'colissimo-shipping-methods-for-woocommerce'));
        }
    }

    protected function checkOptions() {
        if (empty($this->payload['shippingDate'])) {
            throw new Exception(esc_html__('Shipping date required to get relay points', 'colissimo-shipping-methods-for-woocommerce'));
        }

        if (!empty($this->payload['optionInter']) && '1' == $this->payload['optionInter'] && 'FR' == $this->payload['countryCode']) {
            throw new Exception(esc_html__('The international option can\'t be enabled if the country destination is France', 'colissimo-shipping-methods-for-woocommerce'));
        }
    }

    public function assemble() {
        // array_merge to make a copy
        return array_merge($this->payload);
    }
}
