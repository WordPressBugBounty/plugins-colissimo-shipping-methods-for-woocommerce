<?php

namespace Colissimo\Classes\Shipping;

use Colissimo\Api\CheckoutApi;
use Colissimo\Classes\Label\LabelGenerationPayload;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use WC_Admin_Settings;
use WC_Order;
use WC_Order_item_Shipping;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

defined('ABSPATH') || die('Restricted Access');

class ShippingMethods {
    /** @var CheckoutApi */
    protected $checkoutApi;

    public function __construct(
        ?CheckoutApi $checkoutApi = null
    ) {
        $this->checkoutApi = Register::get('checkoutApi');
    }

    public function init() {
        add_filter(
            'woocommerce_shipping_methods',
            function ($shippingMethods) {
                $shippingMethods[EcoOverseas::ID] = EcoOverseas::class;
                $shippingMethods[Expert::ID]      = Expert::class;
                $shippingMethods[ExpertDdp::ID]   = ExpertDdp::class;
                $shippingMethods[NoSign::ID]      = NoSign::class;
                $shippingMethods[Relay::ID]       = Relay::class;
                $shippingMethods[Sign::ID]        = Sign::class;
                $shippingMethods[SignDdp::ID]     = SignDdp::class;

                return $shippingMethods;
            }
        );

        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'addShippingIcon'], 10, 2);
        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'addShippingDate'], 10, 2);
    }

    public function getAllShippingMethods(): array {
        // can't use ::ID here because WC may not yet be defined
        return [
            'lpc_ecoom'      => __('Colissimo ECO Overseas', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc_expert'     => __('Colissimo International', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc_expert_ddp' => __('Colissimo International - DDP Option', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc_nosign'     => __('Colissimo without signature', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc_relay'      => __('Colissimo relay', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc_sign'       => __('Colissimo with signature', 'colissimo-shipping-methods-for-woocommerce'),
            'lpc_sign_ddp'   => __('Colissimo with signature - DDP Option', 'colissimo-shipping-methods-for-woocommerce'),
        ];
    }

    public function getAllColissimoShippingMethodsOfOrder(WC_Order $order) {
        $shipping_methods  = $order->get_shipping_methods();
        $shippingMethodIds = array_map(
            fn(WC_Order_item_Shipping $v) => $v->get_method_id(),
            $shipping_methods
        );

        return array_intersect(array_keys($this->getAllShippingMethods()), $shippingMethodIds);
    }

    public function getColissimoShippingMethodOfOrder(WC_Order $order) {
        $shippingMethod = $this->getAllColissimoShippingMethodsOfOrder($order);

        return reset($shippingMethod);
    }

    public function addShippingIcon($label, $method) {
        $methodId = $method->get_method_id();
        if ('yes' !== WC_Admin_Settings::get_option('display_logo') || !in_array($methodId, array_keys($this->getAllShippingMethods()))) {
            return $label;
        }

        $img = '<img src="' . esc_url(Helper::getImageUrl('colissimo.png')) . '" 
                    style="max-width: 100px; display:inline; vertical-align: middle;" 
                    class="lpc_shipping_icon lpc_shipping_icon_' . $methodId . '"> ';

        $countryCode  = WC()->customer->get_shipping_country();
        $partnerLogo  = '';
        $partnerWidth = '80';
        if (in_array($countryCode, ['DK', 'EE', 'IE', 'NL', 'PL', 'PT'])) {
            $partnerLogo  = 'partners/dpd.png';
            $partnerWidth = '56';
        } elseif ('AT' === $countryCode) {
            if ('partner' === Helper::get_option('lpc_domicileas_SendingService_austria')) {
                $partnerLogo = 'partners/post_ag.jpg';
            } else {
                $partnerLogo  = 'partners/dpd.png';
                $partnerWidth = '56';
            }
        } elseif ('AU' === $countryCode) {
            $partnerLogo  = 'partners/australia_post.svg';
            $partnerWidth = '100';
        } elseif ('BE' === $countryCode) {
            if ('partner' === Helper::get_option('lpc_domicileas_SendingService_belgium')) {
                $partnerLogo  = 'partners/bpost.png';
                $partnerWidth = '70';
            } else {
                $partnerLogo  = 'partners/dpd.png';
                $partnerWidth = '56';
            }
        } elseif ('DE' === $countryCode) {
            if ('partner' === Helper::get_option('lpc_domicileas_SendingService_germany')) {
                $partnerLogo  = 'partners/deutschpost.jpg';
                $partnerWidth = '60';
            } else {
                $partnerLogo  = 'partners/dpd.png';
                $partnerWidth = '56';
            }
        } elseif ('CA' === $countryCode) {
            $partnerLogo  = 'partners/canada_post.jpg';
            $partnerWidth = '100';
        } elseif ('CH' === $countryCode) {
            $partnerLogo = 'partners/swiss_post.jpg';
        } elseif ('ES' === $countryCode) {
            if (LabelGenerationPayload::isSpanishPostalNetworkZipCode(WC()->customer->get_shipping_postcode())) {
                // Islands (Canary Islands, Ceuta, Melilla) are served through the postal network: keep only the default logo
                $partnerLogo = '';
            } elseif ('partner' === Helper::get_option('lpc_domicileas_SendingService_spain')) {
                $partnerLogo = 'partners/seur.png';
            } else {
                $partnerLogo  = 'partners/dpd.png';
                $partnerWidth = '56';
            }
        } elseif ('FI' === $countryCode) {
            if ('partner' === Helper::get_option('lpc_domicileas_SendingService_finland')) {
                $partnerLogo  = 'partners/posti.png';
                $partnerWidth = '50';
            } else {
                $partnerLogo  = 'partners/dpd.png';
                $partnerWidth = '56';
            }
        } elseif ('GB' === $countryCode) {
            $partnerLogo = 'partners/parcel_force.jpg';
        } elseif ('IT' === $countryCode) {
            if ('partner' === Helper::get_option('lpc_domicileas_SendingService_italy')) {
                $partnerLogo  = 'partners/poste_italiane.jpg';
                $partnerWidth = '100';
            } else {
                $partnerLogo  = 'partners/brt.png';
                $partnerWidth = '52';
            }
        } elseif ('LU' === $countryCode) {
            if ('partner' === Helper::get_option('lpc_domicileas_SendingService_luxembourg')) {
                $partnerLogo  = 'partners/deutschpost.jpg';
                $partnerWidth = '60';
            } else {
                $partnerLogo  = 'partners/dpd.png';
                $partnerWidth = '56';
            }
        } elseif ('NC' === $countryCode) {
            $partnerLogo  = 'partners/opt.png';
            $partnerWidth = '100';
        } elseif ('NO' === $countryCode) {
            $partnerLogo = 'partners/postnord.svg';
        } elseif ('PF' === $countryCode) {
            $partnerLogo  = 'partners/fare_rata.png';
            $partnerWidth = '40';
        } elseif ('SE' === $countryCode) {
            $partnerLogo = 'partners/postnord.svg';
        } elseif ('UA' === $countryCode) {
            $partnerLogo  = 'partners/ukrposhta.png';
            $partnerWidth = '100';
        }

        if (!empty($partnerLogo)) {
            $img .= ' <span style="font-weight: normal; font-size: 1rem;">x</span> <img alt="" 
                    src="' . esc_url(Helper::getImageUrl($partnerLogo)) . '" 
                    style="max-width: ' . $partnerWidth . 'px; display:inline; vertical-align: middle;" 
                    class="lpc_shipping_icon lpc_shipping_icon_' . $methodId . '">';
        }

        return $img . '<br />' . $label;
    }

    public function addShippingDate($label, $method) {
        $methodId = $method->get_method_id();
        if ('yes' !== Helper::get_option('lpc_display_shipping_date') || !in_array($methodId, array_keys($this->getAllShippingMethods()))) {
            return $label;
        }

        $customer = WC()->customer;
        $country  = $customer->get_shipping_country();
        if ('FR' !== $country) {
            return $label;
        }

        $postCode     = $customer->get_shipping_postcode();
        $deliveryDate = $this->checkoutApi->getDeliveryDate($postCode);
        if (empty($deliveryDate)) {
            return $label;
        }

        return $label . '<br />' . $deliveryDate;
    }

    public function moveAlwaysFreeOption() {
        $globalMethods = [
            'lpc_nosign'     => Helper::get_option('lpc_domiciless_IsAlwaysFree', 'no'),
            'lpc_sign'       => Helper::get_option('lpc_domicileas_IsAlwaysFree', 'no'),
            'lpc_sign_ddp'   => Helper::get_option('lpc_domicileas_IsAlwaysFree', 'no'),
            'lpc_expert'     => Helper::get_option('lpc_expert_IsAlwaysFree', 'no'),
            'lpc_expert_ddp' => Helper::get_option('lpc_expert_IsAlwaysFree', 'no'),
            'lpc_relay'      => Helper::get_option('lpc_relay_IsAlwaysFree', 'no'),
        ];

        // Collect every shipping method instance across all zones, including the "Rest of the World" zone (id 0) that get_zones() omits.
        $shippingMethods = (new WC_Shipping_Zone(0))->get_shipping_methods();
        foreach (WC_Shipping_Zones::get_zones() as $zone) {
            $shippingMethods = array_merge($shippingMethods, $zone['shipping_methods']);
        }

        foreach ($shippingMethods as $shippingMethod) {
            if (0 !== strpos($shippingMethod->id, 'lpc_')) {
                continue;
            }

            $optionName = 'woocommerce_' . $shippingMethod->id . '_' . $shippingMethod->instance_id . '_settings';
            $option     = Helper::get_option($optionName, []);
            if ('no' !== ($globalMethods[$shippingMethod->id] ?? 'no')) {
                if (!empty($option)) {
                    $option['always_free'] = $globalMethods[$shippingMethod->id];
                    update_option($optionName, $option);
                } else {
                    $option['always_free'] = $globalMethods[$shippingMethod->id];
                    add_option($optionName, $option, '', false);
                }
            }
        }
        delete_option('lpc_domiciless_IsAlwaysFree');
        delete_option('lpc_domicileas_IsAlwaysFree');
        delete_option('lpc_expert_IsAlwaysFree');
        delete_option('lpc_relay_IsAlwaysFree');
    }
}
