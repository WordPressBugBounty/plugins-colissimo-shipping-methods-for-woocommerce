<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hooks, correctly prefixed with "lpc_".

namespace Colissimo\Classes\Label;

use Colissimo\Api\AccountApi;
use Colissimo\Classes\Order\Banner;
use Colissimo\Classes\Shipping\ExpertDdp;
use Colissimo\Classes\Shipping\CapabilitiesPerCountry;
use Colissimo\Classes\Shipping\ShippingMethods;
use Colissimo\Classes\Shipping\Sign;
use Colissimo\Classes\Shipping\SignDdp;
use Colissimo\Core\Register;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Exception;
use WC_Admin_Settings;
use WC_Order;

defined('ABSPATH') || die('Restricted Access');

class LabelGenerationPayload {
    private const MAX_INSURANCE_AMOUNT = 5000;
    private const MAX_INSURANCE_AMOUNT_RELAY = 1000;
    private const FORCED_ORIGINAL_IDENT = 'A';
    private const RETURN_TYPE_CHOICE_RETURN = 2;
    private const RETURN_TYPE_CHOICE_NO_RETURN = 3;
    private const CUSTOMS_CATEGORY_RETURN_OF_ARTICLES = 6;
    private const BELGIAN_MOBILE_NUMBER_REGEX = '/^(?:(?:\+|00)32|0)4\d{8}$/';

    public const PRODUCT_CODE_RELAY = 'HD';
    public const PRODUCT_CODE_WITHOUT_SIGNATURE = 'DOM';
    public const PRODUCT_CODE_WITHOUT_SIGNATURE_OM = 'COM';
    public const PRODUCT_CODE_WITHOUT_SIGNATURE_INTRA_DOM = 'COLD';
    public const PRODUCT_CODE_WITH_SIGNATURE = 'DOS';
    public const PRODUCT_CODE_WITH_SIGNATURE_OM = 'CDS';
    public const PRODUCT_CODE_WITH_SIGNATURE_INTRA_DOM = 'COL';
    public const PRODUCT_CODE_RETURN_FRANCE = 'CORE';
    public const PRODUCT_CODE_RETURN_INT = 'CORI';
    public const PRODUCT_CODE_OM_TO_EU = 'COLI';
    public const PRODUCT_CODE_ECO_OM = 'ECO';

    private const ALL_PRODUCT_CODES = [
        self::PRODUCT_CODE_WITH_SIGNATURE_OM,
        self::PRODUCT_CODE_WITH_SIGNATURE_INTRA_DOM,
        self::PRODUCT_CODE_WITHOUT_SIGNATURE_INTRA_DOM,
        self::PRODUCT_CODE_WITHOUT_SIGNATURE_OM,
        self::PRODUCT_CODE_RETURN_FRANCE,
        self::PRODUCT_CODE_RETURN_INT,
        self::PRODUCT_CODE_WITHOUT_SIGNATURE,
        self::PRODUCT_CODE_WITH_SIGNATURE,
        self::PRODUCT_CODE_RELAY,
        self::PRODUCT_CODE_OM_TO_EU,
        self::PRODUCT_CODE_ECO_OM,
    ];
    private const PRODUCT_CODE_INSURANCE_AVAILABLE = [
        self::PRODUCT_CODE_WITH_SIGNATURE,
        self::PRODUCT_CODE_WITH_SIGNATURE_OM,
        self::PRODUCT_CODE_WITH_SIGNATURE_INTRA_DOM,
        self::PRODUCT_CODE_RELAY,
        self::PRODUCT_CODE_RETURN_FRANCE,
        self::PRODUCT_CODE_RETURN_INT,
        self::PRODUCT_CODE_OM_TO_EU,
    ];

    public const LABEL_FORMAT_PDF = 'PDF';
    public const LABEL_FORMAT_ZPL = 'ZPL';
    public const LABEL_FORMAT_DPL = 'DPL';
    private const LABEL_FORMATS = [
        self::LABEL_FORMAT_PDF,
        self::LABEL_FORMAT_ZPL,
        self::LABEL_FORMAT_DPL,
    ];
    const DEFAULT_FORMAT = 'PDF_A4_300dpi';

    private const FRENCH_COUNTRY_CODE = 'FR';
    private const BELGIAN_COUNTRY_CODE = 'BE';
    private const SWITZERLAND_COUNTRY_CODE = 'CH';
    private const US_COUNTRY_CODE = 'US';
    private const GB_COUNTRY_CODE = 'GB';
    private const SPANISH_COUNTRY_CODE = 'ES';
    // Spanish territories served through the postal network: Canary Islands (35 & 38), Ceuta (51), Melilla (52)
    public const SPANISH_POSTAL_NETWORK_ZIP_PREFIXES = ['35', '38', '51', '52'];
    public const COUNTRIES_NEEDING_STATE = ['CA', self::US_COUNTRY_CODE];
    public const COUNTRIES_FTD = ['GF', 'GP', 'MQ', 'RE'];
    public const COUNTRIES_WITH_PARTNER_SHIPPING = [
        'AT' => 'lpc_domicileas_SendingService_austria',
        'BE' => 'lpc_domicileas_SendingService_belgium',
        'DE' => 'lpc_domicileas_SendingService_germany',
        'DK' => 'lpc_domicileas_SendingService_denmark',
        'EE' => 'lpc_domicileas_SendingService_estonia',
        'ES' => 'lpc_domicileas_SendingService_spain',
        'FI' => 'lpc_domicileas_SendingService_finland',
        'IT' => 'lpc_domicileas_SendingService_italy',
        'LU' => 'lpc_domicileas_SendingService_luxembourg',
        'NL' => 'lpc_domicileas_SendingService_netherlands',
        'PL' => 'lpc_domicileas_SendingService_poland',
    ];
    public const HAZMAT_ATTRIBUTE = 'lpc-hazmat-category';
    public const HAZMAT_CATEGORIES = [
        'lpc-cata' => [
            'label'           => 'Category A - CLP hazardous product',
            'max_weight'      => 5000,
            'max_weight_text' => '< 5kg/5L',
            'code'            => 'A',
        ],
        'lpc-catb' => [
            'label'           => 'Category B - ADR/GPE 2 hazardous product',
            'max_weight'      => 500,
            'max_weight_text' => '< 0,5kg/0,5L',
            'code'            => 'B',
        ],
        'lpc-catc' => [
            'label'           => 'Category C - ADR/GPE 3 hazardous product',
            'max_weight'      => 1000,
            'max_weight_text' => '< 1kg/1L',
            'code'            => 'C',
        ],
        'lpc-catd' => [
            'label'           => 'Category D - Cosmetic hazardous product',
            'max_weight'      => 1000,
            'max_weight_text' => '< 1kg/1L',
            'code'            => 'D',
        ],
        'lpc-cate' => [
            'label'           => 'Category E - Other derogated sensitive product',
            'max_weight'      => 0,
            'max_weight_text' => 'According to contract',
            'code'            => 'E',
        ],
    ];

    protected $payload;
    protected $isReturnLabel;
    protected $capabilitiesPerCountry;
    protected $orderNumber;
    protected $eoriAdded;
    protected $dimensionsAdded;

    /** @var ShippingMethods */
    protected $lpcShippingMethods;

    /** @var OutwardLabelDb */
    protected $outwardLabelDb;

    /** @var AccountApi */
    protected $accountApi;

    public function __construct(
        ?CapabilitiesPerCountry $capabilitiesPerCountry = null,
        ?ShippingMethods $lpcShippingMethods = null,
        ?OutwardLabelDb $outwardLabelDb = null,
        ?AccountApi $accountApi = null
    ) {
        $this->capabilitiesPerCountry = Register::get('capabilitiesPerCountry');
        $this->lpcShippingMethods     = Register::get('shippingMethods');
        $this->outwardLabelDb         = Register::get('outwardLabelDb');
        $this->accountApi             = Register::get('accountApi');

        $this->payload = [
            'letter' => [
                'service' => [],
                'parcel'  => [],
            ],
        ];

        $this->isReturnLabel   = false;
        $this->eoriAdded       = false;
        $this->dimensionsAdded = false;
    }

    public function withSender(?array $sender = null, array $customParams = []) {
        if (null === $sender) {
            $sender = $this->getStoreAddress();
        }

        $payloadSender = [
            'companyName' => $sender['companyName'] ?? '',
            'firstName'   => Helper::toAscii($sender['firstName'] ?? ''),
            'lastName'    => Helper::toAscii($sender['lastName'] ?? ''),
            'line2'       => $sender['street'] ?? '',
            'countryCode' => $sender['countryCode'],
            'city'        => $sender['city'],
            'zipCode'     => $sender['zipCode'],
            'email'       => $sender['email'] ?? '',
        ];

        if (!empty($customParams['sender'])) {
            $payloadSender = array_merge($payloadSender, $customParams['sender']);
        } elseif (!empty($sender['street2'])) {
            $payloadSender['line3'] = $sender['street2'];
        }

        if (!empty($sender['mobileNumber'])) {
            $payloadSender['mobileNumber'] = $this->formatPhone($sender['mobileNumber']);
        }

        if (!empty($sender['phoneNumber'])) {
            $payloadSender['phoneNumber'] = $this->formatPhone($sender['phoneNumber']);
        }

        if (!empty($sender['phone'])) {
            $payloadSender['phoneNumber'] = $this->formatPhone($sender['phone']);
        }

        $zipDashPos = strpos($payloadSender['zipCode'], '-');
        if (self::US_COUNTRY_CODE === $payloadSender['countryCode'] && false !== $zipDashPos) {
            $payloadSender['zipCode'] = substr($payloadSender['zipCode'], 0, $zipDashPos);
        }

        /**
         * Filter on the sender when generating a label
         *
         * @since 1.6
         */
        $payloadSender = apply_filters('lpc_payload_letter_sender', $payloadSender, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['sender']['address'] = $payloadSender;

        return $this;
    }

    public function withCommercialName($commercialName = null) {
        /**
         * Filter on the commercial name when generating a label
         *
         * @since 1.6
         */
        $commercialName = apply_filters('lpc_payload_letter_service_commercial_name', $commercialName, $this->getOrderNumber(), $this->getIsReturnLabel());

        if (empty($commercialName)) {
            unset($this->payload['letter']['service']['commercialName']);
        } else {
            $this->payload['letter']['service']['commercialName'] = $commercialName;
        }

        return $this;
    }

    public function withCredentials() {
        if ('api_key' !== Helper::get_option('lpc_credentials_type', 'api_key')) {
            $contractNumber = Helper::get_option('lpc_id_webservices');

            /**
             * Filter on the contract number when generating a label
             *
             * @since 1.6
             */
            $contractNumber = apply_filters('lpc_payload_contract_number', $contractNumber, $this->getOrderNumber(), $this->getIsReturnLabel());

            if (!empty($contractNumber)) {
                $this->payload['contractNumber'] = $contractNumber;
            }

            $password = Helper::getPasswordWebService();

            if (empty($password)) {
                unset($this->payload['password']);
            } else {
                $this->payload['password'] = $password;
            }
        }

        $parentAccountId = $this->accountApi->getParentAccountId();
        if (!empty($parentAccountId)) {
            $this->payload['fields']['field'][] = [
                'key'   => 'ACCOUNT_NUMBER',
                'value' => $parentAccountId,
            ];
        }

        return $this;
    }

    public function withAddressee(array $addressee, string $shippingMethodUsed) {
        $payloadAddressee = [
            'address' => [
                'companyName' => $addressee['companyName'] ?? '',
                'firstName'   => Helper::toAscii($addressee['firstName'] ?? ''),
                'lastName'    => Helper::toAscii($addressee['lastName'] ?? ''),
                'line2'       => $addressee['street'],
                'countryCode' => $addressee['countryCode'],
                'city'        => $addressee['city'],
                'zipCode'     => $addressee['zipCode'],
                'email'       => $addressee['email'] ?? '',
            ],
        ];

        if (in_array($addressee['countryCode'], self::COUNTRIES_NEEDING_STATE) && !empty($addressee['stateCode'])) {
            $payloadAddressee['address']['stateOrProvinceCode'] = $addressee['stateCode'];
        }

        $zipDashPos = strpos($addressee['zipCode'], '-');
        if (self::US_COUNTRY_CODE === $addressee['countryCode'] && false !== $zipDashPos) {
            $payloadAddressee['address']['zipCode'] = substr($addressee['zipCode'], 0, $zipDashPos);
        }

        if (!$this->getIsReturnLabel() && !empty($addressee['phone'])) {
            $phoneNumber = str_replace(' ', '', $addressee['phone']);
            $countryCode = empty($addressee['countryCode']) ? self::FRENCH_COUNTRY_CODE : $addressee['countryCode'];

            if (self::BELGIAN_COUNTRY_CODE === $countryCode && preg_match(self::BELGIAN_MOBILE_NUMBER_REGEX, $phoneNumber)) {
                $phoneNumber = preg_replace('/(04|00324)([0-9]{8})/', '+324$2', $phoneNumber);
            }

            $phoneField = 'phoneNumber';
            if (
                self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']
                || in_array($shippingMethodUsed, [SignDdp::ID, ExpertDdp::ID])
                || self::US_COUNTRY_CODE === $addressee['countryCode']
            ) {
                $phoneField = 'mobileNumber';
            }

            $addressee[$phoneField] = $phoneNumber;
        }

        if (!empty($addressee['mobileNumber'])) {
            $payloadAddressee['address']['mobileNumber'] = $this->formatPhone($addressee['mobileNumber']);
        }

        if (!empty($addressee['phoneNumber'])) {
            $payloadAddressee['address']['phoneNumber'] = $this->formatPhone($addressee['phoneNumber']);
            if (self::BELGIAN_COUNTRY_CODE === $addressee['countryCode'] && empty($payloadAddressee['address']['mobileNumber'])) {
                $payloadAddressee['address']['mobileNumber'] = $payloadAddressee['address']['phoneNumber'];
            }
        }

        // Required bypass because Colissimo Labels for Belgium or Switzerland don't display line3
        $countryCodesNoLine3 = [self::BELGIAN_COUNTRY_CODE, self::SWITZERLAND_COUNTRY_CODE];
        if (in_array($addressee['countryCode'], $countryCodesNoLine3)) {
            if (!empty($addressee['street2'])) {
                $payloadAddressee['address']['line2'] = $payloadAddressee['address']['line2'] . ' ' . $addressee['street2'];
            }
        } else {
            if (!empty($addressee['street2'])) {
                $payloadAddressee['address']['line3'] = $addressee['street2'];
            } elseif (strlen($payloadAddressee['address']['line2']) > 35) {
                $payloadAddressee['address']['line2'] = substr($addressee['street'], 0, 35);
                $lastSpacePos                         = strrpos($payloadAddressee['address']['line2'], ' ');
                if (false === $lastSpacePos) {
                    $payloadAddressee['address']['line3'] = substr($addressee['street'], 35);
                } else {
                    $payloadAddressee['address']['line2'] = substr($addressee['street'], 0, $lastSpacePos);
                    $payloadAddressee['address']['line3'] = substr($addressee['street'], $lastSpacePos);
                }
            }
        }

        /**
         * Filter on the addressee when generating a label
         *
         * @since 1.6
         */
        $payloadAddressee = apply_filters('lpc_payload_letter_addressee', $payloadAddressee, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['addressee'] = $payloadAddressee;

        return $this;
    }

    public function withPackage(WC_Order $order, &$customParams = []) {
        if (isset($customParams['totalWeight'])) {
            $totalWeight = wc_get_weight($customParams['totalWeight'], 'kg');
        } else {
            $nbProductsToShip   = 0;
            $totalWeight        = 0;
            $productsDimensions = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (empty($product)) {
                    throw new Exception(
                        esc_html__('The product couldn\'t be found.', 'colissimo-shipping-methods-for-woocommerce')
                    );
                }

                // Compatibility with WPC Product Bundles for WooCommerce, don't count bundled products twice
                if (!$product->needs_shipping() || 'woosb' === $product->get_type()) {
                    continue;
                }

                $data                 = $item->get_data();
                $productWeight        = $product->get_weight() < 0.01 ? 0.01 : $product->get_weight();
                $nbProductsToShip     += (float) $data['quantity'];
                $weight               = (float) $productWeight * $data['quantity'];
                $productsDimensions[] = [
                    $product->get_length(),
                    $product->get_width(),
                    $product->get_height(),
                ];

                if ($weight < 0) {
                    throw new Exception(
                        esc_html__('Weight cannot be negative!', 'colissimo-shipping-methods-for-woocommerce')
                    );
                }

                $totalWeight += wc_get_weight($weight, 'kg');
            }

            $matchingPackaging = Helper::getMatchingPackaging($nbProductsToShip, $totalWeight, $productsDimensions);
            if (empty($matchingPackaging)) {
                $totalWeight += wc_get_weight(Helper::get_option('lpc_packaging_weight', '0'), 'kg');
            } else {
                $totalWeight += wc_get_weight($matchingPackaging['weight'], 'kg');
                if ($matchingPackaging['width'] + $matchingPackaging['length'] + $matchingPackaging['depth'] > 120) {
                    $customParams['nonMachinable'] = true;
                }
            }
        }

        if ($totalWeight < 0.01) {
            $totalWeight = 0.01;
        }

        $totalWeight = number_format($totalWeight, 2);

        /**
         * Filter on the parcel total weight when generating a label
         *
         * @since 1.6
         */
        $totalWeight = apply_filters('lpc_payload_letter_parcel_weight', $totalWeight, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['parcel']['weight'] = (string) $totalWeight;

        if (!empty($customParams['packageLength']) && !empty($customParams['packageWidth']) && !empty($customParams['packageHeight'])) {
            $dimensions = [
                intval($customParams['packageLength']),
                intval($customParams['packageWidth']),
                intval($customParams['packageHeight']),
            ];
        } elseif (!empty($matchingPackaging)) {
            $dimensions = [
                intval($matchingPackaging['width']),
                intval($matchingPackaging['length']),
                intval($matchingPackaging['depth']),
            ];
        }

        if (!empty($dimensions)) {
            sort($dimensions);

            $this->payload['fields']['field'][] = [
                'key'   => 'LENGTH',
                'value' => array_pop($dimensions),
            ];
            $this->payload['fields']['field'][] = [
                'key'   => 'WIDTH',
                'value' => array_pop($dimensions),
            ];
            $this->payload['fields']['field'][] = [
                'key'   => 'HEIGHT',
                'value' => array_pop($dimensions),
            ];

            $this->dimensionsAdded = true;
        }

        return $this;
    }

    public function withPickupLocationId($pickupLocationId) {
        /**
         * Filter on the pickup location id when generating a label
         *
         * @since 1.6
         */
        $pickupLocationId = apply_filters('lpc_payload_letter_parcel_pickup_location_id', $pickupLocationId, $this->getOrderNumber(), $this->getIsReturnLabel());

        if (null === $pickupLocationId) {
            unset($this->payload['letter']['parcel']['pickupLocationId']);
        } else {
            $this->payload['letter']['parcel']['pickupLocationId'] = $pickupLocationId;
        }

        return $this;
    }

    public function withProductCode($productCode, $countryCode = null) {
        /**
         * Filter on the product code when generating a label
         *
         * @since 1.6
         */
        $productCode = apply_filters('lpc_payload_letter_service_product_code', $productCode, $this->getOrderNumber(), $this->getIsReturnLabel());

        if (!in_array($productCode, self::ALL_PRODUCT_CODES)) {
            Logger::error(
                'Unknown product code',
                [
                    'given' => $productCode,
                    'known' => self::ALL_PRODUCT_CODES,
                ]
            );
            throw new Exception('Unknown Product code!');
        }

        $this->payload['letter']['service']['productCode'] = $productCode;

        if (self::US_COUNTRY_CODE === $countryCode) {
            $this->payload['letter']['service']['returnTypeChoice'] = self::RETURN_TYPE_CHOICE_RETURN;
        } else {
            $this->payload['letter']['service']['returnTypeChoice'] = self::RETURN_TYPE_CHOICE_NO_RETURN;
        }

        return $this;
    }

    public function withFtd($countryCode) {
        if (in_array($countryCode, self::COUNTRIES_FTD) && Helper::get_option('lpc_customs_isFtd') === 'yes') {
            $this->payload['letter']['parcel']['ftd'] = true;
        } else {
            unset($this->payload['letter']['parcel']['ftd']);
        }

        return $this;
    }

    public function withDepositDate(\DateTime $depositDate) {
        $now = new \DateTime();
        /**
         * Filter on the deposit date when generating a label
         *
         * @since 1.6
         */
        $depositDate = apply_filters('lpc_payload_letter_service_deposit_date', $depositDate, $this->getOrderNumber(), $this->getIsReturnLabel());

        if ($depositDate->getTimestamp() < $now->getTimestamp()) {
            Logger::warn(
                'Given DepositDate is in the past, using today instead.',
                [
                    'given' => $depositDate,
                    'now'   => $now,
                ]
            );
            $depositDate = $now;
        }

        $this->payload['letter']['service']['depositDate'] = $depositDate->format('Y-m-d');

        return $this;
    }

    public function withPreparationDelay($delay = null) {
        if (null === $delay) {
            $delay = Helper::get_option('lpc_preparation_time');
        }

        /**
         * Filter on the preparation delay when generating a label
         *
         * @since 1.6
         */
        $delay = apply_filters('lpc_payload_delay', $delay, $this->getOrderNumber(), $this->getIsReturnLabel());
        $delay = (int) $delay;

        $depositDate = new \DateTime();
        if ($delay > 0) {
            $depositDate->add(new \DateInterval("P{$delay}D"));
        }

        return $this->withDepositDate($depositDate);
    }

    public function withOutputFormat(array $customParams = []) {
        if ($this->getIsReturnLabel()) {
            $outputFormat = Helper::get_option('lpc_returnLabelFormat');
            if (self::PRODUCT_CODE_RETURN_INT === $this->payload['letter']['service']['productCode']) {
                $outputFormat = self::DEFAULT_FORMAT;
            }

            if (!empty($customParams['format']) && self::LABEL_FORMAT_PDF === $customParams['format'] && strpos($outputFormat, self::LABEL_FORMAT_PDF) !== 0) {
                $outputFormat = self::DEFAULT_FORMAT;
            }
        } else {
            $outputFormat = Helper::get_option('lpc_deliveryLabelFormat');
        }

        /**
         * Filter on the output format when generating a label
         *
         * @since 1.6
         */
        $outputFormat = apply_filters('lpc_payload_output_format', $outputFormat, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['outputFormat'] = [
            'x'                  => 0,
            'y'                  => 0,
            'outputPrintingType' => $outputFormat,
        ];

        return $this;
    }

    public function withOrderNumber($orderNumber) {
        $this->orderNumber = $orderNumber;

        /**
         * Filter on the order number when generating a label
         *
         * @since 1.6
         */
        $orderNumber = apply_filters('lpc_payload_letter_service_order_number', $orderNumber, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['service']['orderNumber']    = $orderNumber;
        $this->payload['letter']['sender']['senderParcelRef'] = $orderNumber;

        return $this;
    }

    public function withInsuranceValue($amount, $countryCode, $shippingMethodUsed, $customParams = []) {
        if (!empty($customParams['useInsurance'])) {
            $usingInsurance = $customParams['useInsurance'];
        } else {
            $option         = $this->getIsReturnLabel() ? 'lpc_using_insurance_inward' : 'lpc_using_insurance';
            $usingInsurance = Helper::get_option($option, 'no');
        }

        /**
         * Filter on the using insurance option when generating a label
         *
         * @since 1.6
         */
        $usingInsurance = apply_filters('lpc_payload_letter_parcel_using_insurance', $usingInsurance, $this->getOrderNumber(), $this->getIsReturnLabel());

        if ('yes' !== $usingInsurance || !in_array($this->payload['letter']['service']['productCode'], self::PRODUCT_CODE_INSURANCE_AVAILABLE)) {
            return $this;
        }

        if (is_admin()) {
            $lpc_admin_notices = Register::get('lpcAdminNotices');
        }

        // Insurance is set to yes
        if (!$this->capabilitiesPerCountry->getInsuranceAvailableForDestination($countryCode)) {
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'insurance_unavailable_for_country',
                    'notice-warning',
                    // translators: %s is the order number
                    sprintf(__('Order %s: insurance is not available for this country', 'colissimo-shipping-methods-for-woocommerce'),
                            $this->payload['letter']['service']['orderNumber'])
                );
            }

            return $this;
        }

        // Use user defined insurance amount if exists (from Colissimo banner in order edition)
        if (!empty($customParams['insuranceAmount'])) {
            $amount = $customParams['insuranceAmount'];
        }

        /**
         * Filter on the insurance value when generating a label
         *
         * @since 1.6
         */
        $amount             = (float) apply_filters('lpc_payload_letter_parcel_insurance_value', $amount, $this->getOrderNumber(), $this->getIsReturnLabel());
        $maxInsuranceAmount = $this->getMaxInsuranceAmountByProductCode($this->payload['letter']['service']['productCode']);

        if ($amount > $maxInsuranceAmount) {
            Logger::warn(
                'Selected insurance amount is too big, forced to ' . $maxInsuranceAmount,
                [
                    'given' => $amount,
                    'max'   => $maxInsuranceAmount,
                ]
            );

            if (is_admin()) {
                $shippingMethods = $this->lpcShippingMethods->getAllShippingMethods();
                $lpc_admin_notices->add_notice(
                    'outward_label_generate',
                    'notice-info',
                    sprintf(
                    // translators: %1$s is the order number, %2$s is the maximum insurance amount in euros, %3$s is the shipping method name
                        __('Order %1$s is insured up to %2$s euros, this is the maximum amount for parcels delivered with %3$s shipping method',
                           'colissimo-shipping-methods-for-woocommerce'),
                        $this->getOrderNumber(),
                        $maxInsuranceAmount,
                        $shippingMethods[$shippingMethodUsed]
                    )
                );
            }

            $amount = $maxInsuranceAmount;
        }

        if ($amount > 0) {
            // payload want centi-euros for this field.
            $this->payload['letter']['parcel']['insuranceValue'] = (int) ($amount * 100);
        } else {
            Logger::warn(
                'Insurance value was not applied because it was negative or zero!',
                [
                    'given' => $amount,
                ]
            );
        }

        return $this;
    }

    public function withNonMachinable($customParams = []) {
        if (self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']) {
            $this->payload['letter']['parcel']['nonMachinable'] = 'false';

            return $this;
        }

        if (empty($customParams['nonMachinable'])) {
            return $this;
        }

        $this->payload['letter']['parcel']['nonMachinable'] = 'true';

        return $this;
    }

    public function withDDP($shippingMethodUsed) {
        if (!in_array($shippingMethodUsed, [SignDdp::ID, ExpertDdp::ID])) {
            $this->payload['letter']['parcel']['ddp'] = 'false';

            return $this;
        }

        // Must have the state code for US and CA
        $address = $this->payload['letter']['addressee']['address'];
        if (in_array($address['countryCode'], self::COUNTRIES_NEEDING_STATE) && empty($address['stateOrProvinceCode'])) {
            Logger::error(
                'Shipping state missing for DDP label generation',
                [
                    'shippingMethod' => $shippingMethodUsed,
                ]
            );
            throw new Exception(esc_html__('Shipping state missing for label generation with this country', 'colissimo-shipping-methods-for-woocommerce'));
        }

        // Must have a phone number
        if (empty($address['mobileNumber'])) {
            Logger::error(
                'Phone number missing for DDP label generation',
                [
                    'shippingMethod' => $shippingMethodUsed,
                ]
            );
            throw new Exception(esc_html__('Please define a mobile phone number for SMS notification tracking', 'colissimo-shipping-methods-for-woocommerce'));
        }

        // Must have dimensions
        if (!$this->dimensionsAdded) {
            Logger::error(
                'Package dimensions missing for DDP label generation',
                [
                    'shippingMethod' => $shippingMethodUsed,
                ]
            );
            throw new Exception(esc_html__('Please enter the package dimensions', 'colissimo-shipping-methods-for-woocommerce'));
        }

        // Must have EORI
        if (!$this->eoriAdded) {
            Logger::error(
                'EORI missing for DDP label generation',
                [
                    'shippingMethod' => $shippingMethodUsed,
                ]
            );
            throw new Exception(esc_html__('Please enter the EORI code in the Colissimo configuration', 'colissimo-shipping-methods-for-woocommerce'));
        }

        $this->payload['letter']['parcel']['ddp'] = 'true';

        return $this;
    }

    public function withInstructions($instructions) {
        if (Helper::get_option('lpc_add_customer_notes', 'no') === 'no') {
            return $this;
        }

        /**
         * Filter on the shipping instructions
         *
         * @since 1.6
         */
        $instructions = apply_filters('lpc_payload_letter_parcel_instructions', $instructions, $this->getOrderNumber(), $this->getIsReturnLabel());

        if (empty($instructions)) {
            unset($this->payload['letter']['parcel']['instructions']);
        } else {
            $this->payload['letter']['parcel']['instructions'] = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $instructions), 0, 35);
        }

        return $this;
    }

    public function withCuserInfoText() {
        global $woocommerce;

        $woocommerceVersion = $woocommerce->version;
        $pluginData         = get_plugin_data(LPC_FOLDER . DS . 'index.php', false, false);
        $colissimoVersion   = $pluginData['Version'];

        $info = 'WC' . $woocommerceVersion . ';' . $colissimoVersion;

        $customFields = [
            'key'   => 'CUSER_INFO_TEXT',
            'value' => $info,
        ];

        $this->payload['fields']['field'][] = $customFields;
        $this->payload['fields']['field'][] = [
            'key'   => 'CUSER_INFO_TEXT_3',
            'value' => 'WOOCOMMERCE;' . $colissimoVersion,
        ];

        return $this;
    }

    public function withCustomsDeclaration(WC_Order $order, $customParams = [], $shippingMethodUsed = null) {
        // No need details if no CN23 required
        if (!$this->capabilitiesPerCountry->getIsCn23RequiredForDestination($order)) {
            return $this;
        }

        $destinationCountryId = $order->get_shipping_country();
        $isMasterParcel       = false;
        if (!empty($customParams['multiParcelsCurrentNumber']) && $customParams['multiParcelsCurrentNumber'] === $customParams['multiParcelsAmount']) {
            $isMasterParcel = true;
        }
        $isCustomItems       = isset($customParams['items']);
        $customsArticles     = [];
        $totalItemsAmount    = 0;
        $articleDescriptions = [];
        $lastMidCode         = '';
        $orderItems          = $order->get_items();

        foreach ($orderItems as $item) {
            $itemId = $item->get_id();

            if (!$isMasterParcel && $isCustomItems && !isset($customParams['items'][$itemId])) {
                continue;
            }

            $product = $item->get_product();
            if (empty($product)) {
                throw new Exception(
                    esc_html__('The product couldn\'t be found.', 'colissimo-shipping-methods-for-woocommerce')
                );
            }

            // Compatibility with WPC Product Bundles for WooCommerce, don't count bundled products twice
            if (!$product->needs_shipping() || 'woosb' === $product->get_type()) {
                continue;
            }

            $quantity = !$isMasterParcel && isset($customParams['items'][$itemId]['qty']) ? $customParams['items'][$itemId]['qty'] : $item->get_quantity();

            if (isset($customParams['items'][$itemId]['price'])) {
                $unitaryValue = $customParams['items'][$itemId]['price'];
            } elseif (!empty(wc_get_order_item_meta($item->get_id(), '_line_total'))) {
                $unitaryValue = wc_get_order_item_meta($item->get_id(), '_line_total');
                if (!empty(wc_get_order_item_meta($item->get_id(), '_qty'))) {
                    $unitaryValue /= wc_get_order_item_meta($item->get_id(), '_qty');
                }
            } else {
                $productPrice = wc_get_price_excluding_tax($product);
                $unitaryValue = empty($productPrice) ? 1 : $productPrice;
            }

            // Some users may have a free product, but the customs declaration needs a value > 0
            if (empty($unitaryValue)) {
                $unitaryValue = 1;
            }

            $totalItemsAmount += $unitaryValue * $quantity;

            $description = $this->getProductCustomsDescription($product);
            if (empty($description)) {
                $description = $item->get_name();
            }

            // Handle emojis in product description
            $encodedDescription = wp_json_encode($description);
            if (strpos($encodedDescription, '\u') !== false) {
                $description = trim(preg_replace('#\\\u.{4}#Ui', '', trim($encodedDescription, '"')));
            }

            // The Colissimo API returns an error if there is an accent
            $description = Helper::replaceAccents($description);

            $midCode     = $this->getProductMidCode($product);
            $lastMidCode = $midCode;
            if (!empty($midCode) && self::US_COUNTRY_CODE === $destinationCountryId) {
                $midCode     = ' - MID: ' . $midCode;
                $description = substr($description, 0, 64 - strlen($midCode));
                $description .= $midCode;
            } else {
                $description = substr($description, 0, 64);
            }

            $articleDescriptions[] = $description;

            $customsArticle = [
                'description'   => $description,
                'quantity'      => $quantity,
                'value'         => (string) round($unitaryValue, 2),
                'currency'      => $order->get_currency(),
                'artref'        => substr($product->get_sku(), 0, 44),
                'originalIdent' => self::FORCED_ORIGINAL_IDENT,
                'originCountry' => $this->getProductOriginCountry($product),
                'hsCode'        => $this->getProductHsCode($product),
            ];

            $itemWeight         = $customParams['items'][$itemId]['weight'] ?? $product->get_weight();
            $itemWeightWellUnit = wc_get_weight($itemWeight, 'kg');

            $customsArticle['weight'] = $itemWeightWellUnit < 0.01 ? '0.01' : (string) $itemWeightWellUnit;

            $customsArticles[] = $customsArticle;
        }

        $outwardCustomCategory = Helper::get_option('lpc_customs_defaultCustomsCategory');
        if (isset($customParams['customsCategory'])) {
            $outwardCustomCategory = $customParams['customsCategory'];
        }

        $this->payload['fields']['field'][] = [
            'key'   => 'OUTPUT_PRINT_TYPE_CN23',
            'value' => Helper::get_option('lpc_cn23_format', self::DEFAULT_FORMAT),
        ];

        $numberOfCopies = intval(Helper::get_option('lpc_cn23_number', 4));

        $customsDeclarationPayload = [
            'includeCustomsDeclarations' => 1,
            'numberOfCopies'             => empty($numberOfCopies) ? 4 : $numberOfCopies,
            'contents'                   => [
                'article'  => $customsArticles,
                'category' => [
                    'value' => $this->isReturnLabel ? self::CUSTOMS_CATEGORY_RETURN_OF_ARTICLES : $outwardCustomCategory,
                ],
            ],
            'invoiceNumber'              => $order->get_order_number(),
        ];

        if (!empty($lastMidCode) && self::US_COUNTRY_CODE === $destinationCountryId && count($orderItems) === 1) {
            $customsDeclarationPayload['comments'] = 'MID: ' . $lastMidCode;
        }

        if (!empty($shippingMethodUsed) && in_array($shippingMethodUsed, [SignDdp::ID, ExpertDdp::ID])) {
            $description = empty($customParams['description']) ? implode(' ', $articleDescriptions) : $customParams['description'];
            $description = substr($description, 0, 64);

            if (!empty($lastMidCode) && self::US_COUNTRY_CODE === $destinationCountryId) {
                $midCode     = 'MID: ' . $lastMidCode;
                $description = substr($description, 0, 64 - strlen(' - ' . $midCode));
                $description .= ' - ' . $midCode;
            }

            $customsDeclarationPayload['description'] = $description;
        }

        if (self::GB_COUNTRY_CODE === $destinationCountryId && !$this->isReturnLabel) {
            $vatNumber = Helper::get_option('lpc_vat_number', 0);

            if (0 === $vatNumber) {
                Logger::warn('No VAT number set in config');
            } else {
                $customsDeclarationPayload['comments'] = 'N. TVA : ' . $vatNumber;
            }
        }

        if ($this->getIsReturnLabel()) {
            $originalInvoiceDate = $order->get_date_created()
                                         ->date('Y-m-d');

            // For custom return labels this is not the "correct" original number since there is none, but this field is mandatory, so we add it
            $originalParcelNumber = $this->getOriginalParcelNumberFromInvoice($order);

            $customsDeclarationPayload['contents']['original'] =
                [
                    [
                        'originalIdent'         => self::FORCED_ORIGINAL_IDENT,
                        'originalInvoiceNumber' => $order->get_order_number(),
                        'originalInvoiceDate'   => $originalInvoiceDate,
                        'originalParcelNumber'  => $originalParcelNumber,
                    ],
                ];
        }

        /**
         * Filter on the customs declaration
         *
         * @since 1.6
         */
        $customsDeclarationPayload = apply_filters('lpc_payload_letter_customs_declarations', $customsDeclarationPayload, $this->getOrderNumber(), $this->getIsReturnLabel());

        $this->payload['letter']['customsDeclarations'] = $customsDeclarationPayload;

        $shippingCosts = $customParams['shippingCosts'] ?? $order->get_shipping_total();

        /**
         * Filter on the total shipping cost
         *
         * @since 1.6
         */
        $transportationAmount = apply_filters('lpc_payload_letter_service_total_amount', $shippingCosts, $this->getOrderNumber(), $this->getIsReturnLabel());
        if (empty($transportationAmount)) {
            // The Colissimo API rejects labels with a free shipping for the CN23
            throw new Exception(
                esc_html__(
                    'The shipping costs must not be free for the customs declaration to be valid, you can modify it manually by activating the Edit prices and weights option.',
                    'colissimo-shipping-methods-for-woocommerce'
                )
            );
        }

        // payload want centi-currency for these fields.
        $this->payload['letter']['service']['totalAmount']          = (int) ($transportationAmount * 100);
        $this->payload['letter']['service']['transportationAmount'] = (int) ($transportationAmount * 100);

        $eoriNumber = '';
        if (self::GB_COUNTRY_CODE === $destinationCountryId) {
            $eoriNumber = Helper::get_option('lpc_eori_uk_number');
            if ($totalItemsAmount >= 1000) {
                $eoriNumber .= ' ' . Helper::get_option('lpc_eori_number');
            }
        } elseif (self::US_COUNTRY_CODE === $destinationCountryId) {
            $eoriNumber = Helper::get_option('lpc_usa_eori_number');
            if (empty($eoriNumber)) {
                throw new Exception(
                    esc_html__('The EORI number is mandatory for shipments to the USA, please set it in the Colissimo customs settings.',
                               'colissimo-shipping-methods-for-woocommerce')
                );
            }
        }

        if (empty($eoriNumber)) {
            $eoriNumber = Helper::get_option('lpc_eori_number');
        }

        /**
         * Filter on the eori number when generating a label
         *
         * @since 1.6
         */
        $eoriNumber = apply_filters('lpc_payload_eori_number', $eoriNumber, $this->getOrderNumber(), $this->getIsReturnLabel());

        $eoriFields = [
            'key'   => 'EORI',
            'value' => $eoriNumber,
        ];

        $this->payload['fields']['field'][] = $eoriFields;
        $this->eoriAdded                    = true;

        return $this;
    }

    protected function getProductCustomsDescription($product): string {
        $descriptionFieldName = Helper::get_option('lpc_customs_descriptionFieldName');

        if (empty($descriptionFieldName)) {
            return '';
        }

        $description = $product->get_attribute($descriptionFieldName);
        if (!empty($description)) {
            return $description;
        }

        $parentProduct = wc_get_product($product->get_parent_id());
        if (!empty($parentProduct)) {
            $description = $parentProduct->get_attribute($descriptionFieldName);
            if (!empty($description)) {
                return $description;
            }
        }

        return '';
    }

    /**
     * Retrieve product Origin Country
     *
     * @param object $product
     *
     * @return string
     */
    protected function getProductOriginCountry($product) {
        $countryOfManufactureFieldName = Helper::get_option('lpc_customs_countryOfManufactureFieldName');
        $countryOfManufacture          = $product->get_attribute($countryOfManufactureFieldName);

        if (!empty($countryOfManufacture)) {
            return $countryOfManufacture;
        }

        // If empty, we check is the parent product has the attribute (for variable product)
        $parentProduct = wc_get_product($product->get_parent_id());

        if (!empty($parentProduct)) {
            $countryOfManufacture = $parentProduct->get_attribute($countryOfManufactureFieldName);

            if (!empty($countryOfManufacture)) {
                return $countryOfManufacture;
            }
        }

        return Helper::get_option('lpc_default_country_for_product', '');
    }

    /**
     * Retrieve product HS code
     *
     * @param object $product
     *
     * @return array|string
     */
    protected function getProductHsCode($product) {
        $defaultHsCode   = Helper::get_option('lpc_customs_defaultHsCode');
        $hsCodeFieldName = Helper::get_option('lpc_customs_hsCodeFieldName');
        $hsCode          = $product->get_attribute($hsCodeFieldName);

        if (!empty($hsCode)) {
            return $hsCode;
        }

        // If empty, we check is the parent product has the attribute (for variable product)
        $parentProduct = wc_get_product($product->get_parent_id());

        if (!empty($parentProduct)) {
            $hsCode = $parentProduct->get_attribute($hsCodeFieldName);

            if (!empty($hsCode)) {
                return $hsCode;
            }
        }

        // Set default HS code if not defined on the product
        return $defaultHsCode;
    }

    protected function getProductMidCode($product): string {
        $defaultMidCode   = Helper::get_option('lpc_mid_code');
        $midCodeFieldName = Helper::get_option('lpc_customs_midFieldName');
        $midCode          = $product->get_attribute($midCodeFieldName);

        if (!empty($midCode)) {
            return $midCode;
        }

        // If empty, we check if the parent product has the attribute (for variable products)
        $parentProduct = wc_get_product($product->get_parent_id());

        if (!empty($parentProduct)) {
            $midCode = $parentProduct->get_attribute($midCodeFieldName);

            if (!empty($midCode)) {
                return $midCode;
            }
        }

        return $defaultMidCode;
    }

    public function isReturnLabel() {
        $this->isReturnLabel = true;

        return $this;
    }

    public function getIsReturnLabel() {
        return $this->isReturnLabel;
    }

    public function checkConsistency() {
        $this->checkPickupLocationId();
        $this->checkCommercialName();

        if (!$this->getIsReturnLabel()) {
            $this->checkSenderAddress();
            $this->checkAddresseeAddress();
        }

        return $this;
    }

    public function assemble() {
        return array_merge($this->payload); // makes a copy
    }

    /**
     * Retrieve payload without password for log
     *
     * @return array
     */
    public function getPayloadWithoutPassword() {
        $payloadWithoutPass = $this->payload;
        unset($payloadWithoutPass['password']);

        return $payloadWithoutPass;
    }

    protected function checkPickupLocationId() {
        if (self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']
            && (!isset($this->payload['letter']['parcel']['pickupLocationId'])
                || empty($this->payload['letter']['parcel']['pickupLocationId']))) {
            throw new Exception(
                esc_html__('The ProductCode used requires that a pickupLocationId is set!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (self::PRODUCT_CODE_RELAY !== $this->payload['letter']['service']['productCode']
            && isset($this->payload['letter']['parcel']['pickupLocationId'])) {
            throw new Exception(
                esc_html__('The ProductCode used requires that a pickupLocationId is *not* set!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }
    }

    protected function checkCommercialName() {
        if (self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']
            && (!isset($this->payload['letter']['service']['commercialName'])
                || empty($this->payload['letter']['service']['commercialName']))) {
            throw new Exception(
                esc_html__('You must specify the name of your store company in the Origin address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }
    }

    protected function checkSenderAddress() {
        $address = $this->payload['letter']['sender']['address'];

        if (empty($address['companyName'])) {
            throw new Exception(
                esc_html__('The name of your store company must be set in Origin address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (empty($address['line2'])) {
            throw new Exception(
                esc_html__('The address line 1 must be set in the Origin address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (empty($address['countryCode'])) {
            throw new Exception(
                esc_html__('The Country / State must be set in the Origin address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (empty($address['zipCode'])) {
            throw new Exception(
                esc_html__('The Postcode / ZIP must be set in the Origin address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (empty($address['city'])) {
            throw new Exception(
                esc_html__('The city must be set in the Origin address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }
    }

    /**
     * @throws Exception When the address format is refused.
     */
    protected function checkAddresseeAddress() {
        $address = $this->payload['letter']['addressee']['address'];

        if (empty($address['companyName'])
            && (empty($address['firstName']) || empty($address['lastName']))
        ) {
            throw new Exception(
                esc_html__('The name of the company or (firstname + lastname) must be set in the addressee\'s address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (empty($address['line2'])) {
            throw new Exception(
                esc_html__('The address line 1 must be set in the Addressee address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (empty($address['countryCode'])) {
            throw new Exception(
                esc_html__('The Country / State must be set in the Addressee address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (empty($address['zipCode'])) {
            throw new Exception(
                esc_html__('The Postcode / ZIP must be set in the Addressee address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (empty($address['city'])) {
            throw new Exception(
                esc_html__('The city must be set in the Addressee address!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        if (self::PRODUCT_CODE_RELAY === $this->payload['letter']['service']['productCode']
            && empty($address['mobileNumber'])) {
            throw new Exception(
                esc_html__('The ProductCode used requires that a mobile number is set!', 'colissimo-shipping-methods-for-woocommerce')
            );
        }
    }

    public function getStoreAddress(): array {
        $optionsName = [
            'street'       => [
                'lpc'      => 'lpc_origin_address_line_1',
                'default'  => 'woocommerce_store_address',
                'required' => true,
            ],
            'street2'      => [
                'lpc'      => 'lpc_origin_address_line_2',
                'default'  => 'woocommerce_store_address_2',
                'required' => false,
            ],
            'countryCode'  => [
                'lpc'      => 'lpc_origin_address_country',
                'default'  => 'woocommerce_default_country',
                'required' => true,
            ],
            'city'         => [
                'lpc'      => 'lpc_origin_address_city',
                'default'  => 'woocommerce_store_city',
                'required' => true,
            ],
            'zipCode'      => [
                'lpc'      => 'lpc_origin_address_zipcode',
                'default'  => 'woocommerce_store_postcode',
                'required' => true,
            ],
            'email'        => [
                'lpc'      => 'lpc_origin_email',
                'default'  => '',
                'required' => false,
            ],
            'phoneNumber'  => [
                'lpc'      => 'lpc_origin_phone',
                'default'  => '',
                'required' => false,
            ],
            'mobileNumber' => [
                'lpc'      => 'lpc_origin_mobile',
                'default'  => '',
                'required' => false,
            ],
            'firstName'    => [
                'lpc'      => 'lpc_origin_firstname',
                'default'  => '',
                'required' => false,
            ],
            'lastName'     => [
                'lpc'      => 'lpc_origin_lastname',
                'default'  => '',
                'required' => false,
            ],
            'companyName'  => [
                'lpc'      => 'lpc_origin_company_name',
                'default'  => '',
                'required' => false,
            ],
        ];

        return $this->getAddress($optionsName);
    }

    public function getReturnAddress(): array {
        $optionsName = [
            'street'       => [
                'lpc'      => 'lpc_return_address_line_1',
                'default'  => 'lpc_origin_address_line_1',
                'required' => true,
            ],
            'street2'      => [
                'lpc'      => 'lpc_return_address_line_2',
                'default'  => 'lpc_origin_address_line_2',
                'required' => false,
            ],
            'countryCode'  => [
                'lpc'      => 'lpc_return_address_country',
                'default'  => 'lpc_origin_address_country',
                'required' => true,
            ],
            'city'         => [
                'lpc'      => 'lpc_return_address_city',
                'default'  => 'lpc_origin_address_city',
                'required' => true,
            ],
            'zipCode'      => [
                'lpc'      => 'lpc_return_address_zipcode',
                'default'  => 'lpc_origin_address_zipcode',
                'required' => true,
            ],
            'email'        => [
                'lpc'      => 'lpc_return_email',
                'default'  => 'lpc_origin_email',
                'required' => false,
            ],
            'phoneNumber'  => [
                'lpc'      => 'lpc_return_phone',
                'default'  => 'lpc_origin_phone',
                'required' => false,
            ],
            'mobileNumber' => [
                'lpc'      => 'lpc_return_mobile',
                'default'  => 'lpc_origin_mobile',
                'required' => false,
            ],
            'firstName'    => [
                'lpc'      => 'lpc_return_firstname',
                'default'  => 'lpc_origin_firstname',
                'required' => false,
            ],
            'lastName'     => [
                'lpc'      => 'lpc_return_lastname',
                'default'  => 'lpc_origin_lastname',
                'required' => false,
            ],
            'companyName'  => [
                'lpc'      => 'lpc_return_company_name',
                'default'  => 'lpc_origin_company_name',
                'required' => false,
            ],
        ];

        return $this->getAddress($optionsName);
    }

    private function getAddress($optionsName): array {
        $invalidAddress = false;
        $return         = [];

        foreach ($optionsName as $key => $optionName) {
            $option = Helper::get_option($optionName['lpc']);

            if ($optionName['required'] && empty($option)) {
                $invalidAddress = true;
                break;
            }

            $return[$key] = $option;
        }

        if (!$invalidAddress) {
            return $return;
        }

        $return = [];

        foreach ($optionsName as $key => $optionName) {
            if ('countryCode' == $key) {
                // woocommerce_default_country may be the sole country code or the format 'US:IL' (i.e. with the state / province)
                $countryWithState = explode(':', WC_Admin_Settings::get_option('woocommerce_default_country'));
                $option           = reset($countryWithState);
            } else {
                if (empty($optionName['default'])) {
                    $option = Helper::get_option($optionName['lpc']);
                } else {
                    if (0 === strpos($optionName['default'], 'lpc_')) {
                        $option = Helper::get_option($optionName['default']);
                    } else {
                        $option = WC_Admin_Settings::get_option($optionName['default']);
                    }
                }
            }

            $return[$key] = $option;
        }

        return $return;
    }

    protected function getOriginalParcelNumberFromInvoice(WC_Order $order) {
        return $order->get_meta(LabelGenerationOutward::OUTWARD_PARCEL_NUMBER_META_KEY);
    }

    public function getLabelFormat() {
        foreach (self::LABEL_FORMATS as $oneFormat) {
            if (false !== strpos($this->payload['outputFormat']['outputPrintingType'], $oneFormat)) {
                return $oneFormat;
            }
        }

        return '';
    }

    public function isInsured() {
        return isset($this->payload['letter']['parcel']['insuranceValue']);
    }

    public function getOrderNumber() {
        return $this->orderNumber;
    }

    /**
     * @param string $productCode
     *
     * @return false|int
     */
    protected function getMaxInsuranceAmountByProductCode($productCode) {
        if (!in_array($productCode, self::PRODUCT_CODE_INSURANCE_AVAILABLE)) {
            return false;
        }

        return self::PRODUCT_CODE_RELAY === $productCode ? self::MAX_INSURANCE_AMOUNT_RELAY : self::MAX_INSURANCE_AMOUNT;
    }

    public function withPostalNetwork($countryCode) {
        if (
            self::PRODUCT_CODE_WITH_SIGNATURE === $this->payload['letter']['service']['productCode']
            && !empty(self::COUNTRIES_WITH_PARTNER_SHIPPING[$countryCode])
        ) {

            $customSendingService = Helper::get_option(self::COUNTRIES_WITH_PARTNER_SHIPPING[$countryCode]);

            if (
                isset($_REQUEST['shipping_partner'])
                && 1 === (int) check_ajax_referer(Banner::NONCE_NAME_GENERATE_LABEL, Banner::NONCE_GENERATE_LABEL, false)
            ) {
                $customSendingService = sanitize_text_field(wp_unslash($_REQUEST['shipping_partner']));
            }

            $reseauPostal = 'partner' === $customSendingService ? 1 : 0;

            // Spanish islands (Canary Islands, Ceuta, Melilla) are always served through the postal network
            if (
                self::SPANISH_COUNTRY_CODE === $countryCode
                && self::isSpanishPostalNetworkZipCode($this->payload['letter']['addressee']['address']['zipCode'] ?? '')
            ) {
                $reseauPostal = 1;
            }

            $this->payload['letter']['service']['reseauPostal'] = $reseauPostal;
        }

        return $this;
    }

    /**
     * Whether a Spanish postcode belongs to a territory served through the postal network
     * (Canary Islands, Ceuta, Melilla) rather than the SEUR partner network.
     *
     * @param string $zipCode
     *
     * @return bool
     */
    public static function isSpanishPostalNetworkZipCode(string $zipCode): bool {
        return in_array(substr(trim($zipCode), 0, 2), self::SPANISH_POSTAL_NETWORK_ZIP_PREFIXES, true);
    }

    /**
     * @throws Exception When the number of parcels is incorrect or if all labels have been generated.
     */
    public function withMultiParcels($orderId, $customParams): LabelGenerationPayload {
        if (empty($customParams['multiParcels'])) {
            return $this;
        }

        if (empty($customParams['multiParcelsAmount']) || $customParams['multiParcelsAmount'] < 2) {
            throw new Exception(esc_html__('Incorrect number of parcels', 'colissimo-shipping-methods-for-woocommerce'));
        }

        if ($customParams['multiParcelsCurrentNumber'] > $customParams['multiParcelsAmount']) {
            throw new Exception(esc_html__('All labels have already been generated. To generate a new label, please uncheck the multi-parcels shipping.',
                                           'colissimo-shipping-methods-for-woocommerce'));
        }

        if ($customParams['multiParcelsCurrentNumber'] < $customParams['multiParcelsAmount']) {
            // Follower parcels first
            $this->payload['fields']['field'][] = [
                'key'   => 'TYPE_MULTI_PARCEL',
                'value' => 'FOLLOWER',
            ];
        } else {
            // Master parcel last
            $this->payload['fields']['field'][] = [
                'key'   => 'TYPE_MULTI_PARCEL',
                'value' => 'MASTER',
            ];

            $followerLabels                     = $this->outwardLabelDb->getMultiParcelsLabels($orderId);
            $this->payload['fields']['field'][] = [
                'key'   => 'LIST_FOLLOWER_PARCEL',
                'value' => implode('/', array_keys($followerLabels)),
            ];
        }

        $this->payload['fields']['field'][] = [
            'key'   => 'PARCEL_ITERATION_NUMBER',
            'value' => $customParams['multiParcelsCurrentNumber'],
        ];

        $this->payload['fields']['field'][] = [
            'key'   => 'TOTAL_NUMBER_PARCEL',
            'value' => $customParams['multiParcelsAmount'],
        ];

        return $this;
    }

    public function withBlockingCode($shippingMethodUsed, $order, $customParams) {
        if (!empty($customParams['blockCode'])) {
            if ('disabled' === $customParams['blockCode']) {
                $this->payload['letter']['parcel']['disabledDeliveryBlockingCode'] = '1';
            }
        } elseif (in_array($shippingMethodUsed, [Sign::ID, SignDdp::ID])) {
            $accountInformation = $this->accountApi->getAccountInformation();
            if (!empty($accountInformation['statutCodeBloquant'])) {
                $minimumOrderValue = Helper::get_option('lpc_domicileas_block_code_min');
                $maximumOrderValue = Helper::get_option('lpc_domicileas_block_code_max');

                if (!empty($minimumOrderValue) || !empty($maximumOrderValue)) {
                    $orderValue = 0;
                    foreach ($order->get_items() as $item) {
                        $product = $item->get_product();
                        if (empty($product)) {
                            throw new Exception(
                                esc_html__('The product couldn\'t be found.', 'colissimo-shipping-methods-for-woocommerce')
                            );
                        }

                        if (!$product->needs_shipping()) {
                            continue;
                        }

                        $quantity = $item->get_quantity();

                        if (!empty(wc_get_order_item_meta($item->get_id(), '_line_total'))) {
                            $unitaryValue = wc_get_order_item_meta($item->get_id(), '_line_total');
                            if (!empty(wc_get_order_item_meta($item->get_id(), '_qty'))) {
                                $unitaryValue /= wc_get_order_item_meta($item->get_id(), '_qty');
                            }
                        } else {
                            $productPrice = wc_get_price_excluding_tax($product);
                            $unitaryValue = $productPrice;
                        }

                        if (empty($unitaryValue)) {
                            $unitaryValue = 0;
                        }

                        $orderValue += $unitaryValue * $quantity;
                    }

                    if (!empty($minimumOrderValue) && $orderValue < $minimumOrderValue) {
                        $this->payload['letter']['parcel']['disabledDeliveryBlockingCode'] = '1';
                    } elseif (!empty($maximumOrderValue) && $orderValue > $maximumOrderValue) {
                        $this->payload['letter']['parcel']['disabledDeliveryBlockingCode'] = '1';
                    }
                }
            }
        }

        return $this;
    }

    public function withHazmat(WC_Order $order, array $customParams) {
        if (!$this->accountApi->isHazmatOptionActive()) {
            return $this;
        }

        // Only France to France
        if (
            self::FRENCH_COUNTRY_CODE !== $this->payload['letter']['addressee']['address']['countryCode']
            || self::FRENCH_COUNTRY_CODE !== $this->payload['letter']['sender']['address']['countryCode']
        ) {
            return $this;
        }

        $hazardousMaterials     = [];
        $totalHazardousQuantity = 0;
        $isCustomItems          = isset($customParams['items']);
        foreach ($order->get_items() as $item) {
            $itemId = $item->get_id();
            if ($isCustomItems && !isset($customParams['items'][$itemId])) {
                continue;
            }

            $product = $item->get_product();
            if (empty($product)) {
                throw new Exception(
                    esc_html__('The product couldn\'t be found.', 'colissimo-shipping-methods-for-woocommerce')
                );
            }

            if (!$product->needs_shipping()) {
                continue;
            }

            $productHazmatCategory = $this->getProductHazmatCategorySlug($product);
            if (empty($productHazmatCategory)) {
                continue;
            }

            if (empty($hazardousMaterials[$productHazmatCategory])) {
                $hazardousMaterials[$productHazmatCategory] = 0;
            }

            $quantity   = $customParams['items'][$itemId]['qty'] ?? $item->get_quantity();
            $itemWeight = $customParams['items'][$itemId]['weight'] ?? $product->get_weight();

            $hazardousQuantity                          = wc_get_weight((float) $itemWeight * $quantity, 'g');
            $hazardousMaterials[$productHazmatCategory] += $hazardousQuantity;
            $totalHazardousQuantity                     += $hazardousQuantity;
        }

        // No hazmat products in this parcel
        if (empty($hazardousMaterials)) {
            return $this;
        }

        // TODO maybe don't show the "Return products" button / don't show the hazmat products ?
        if ($this->getIsReturnLabel()) {
            throw new Exception(
                esc_html__('Hazardous materials are not allowed for return parcels.', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        $highestHazmatCategoryCode = 'A';
        $lowestHazmatLimit         = 30000;
        foreach (self::HAZMAT_CATEGORIES as $slug => $category) {
            if (!in_array($slug, array_keys($hazardousMaterials))) {
                continue;
            }

            $highestHazmatCategoryCode = $category['code'];

            if (!empty($category['max_weight']) && $category['max_weight'] < $lowestHazmatLimit) {
                $lowestHazmatLimit = $category['max_weight'];
            }

            // Auto generation and exceeding the maximum weight for this category
            if (!empty($customParams['isAutoGeneration']) && !empty($category['max_weight']) && $hazardousMaterials[$slug] > $category['max_weight']) {
                throw new Exception(
                    sprintf(
                    // translators: %1$s is the hazardous material category label, %2$d is the current weight in grams, %3$d is the maximum allowed weight in grams
                        esc_html__('Hazardous materials %1$s exceed the maximum allowed weight: %2$d/%3$dg.', 'colissimo-shipping-methods-for-woocommerce'),
                        // Can't call __() in class constants.
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                        esc_html__($category['label'], 'colissimo-shipping-methods-for-woocommerce'),
                        esc_html($hazardousMaterials[$slug]),
                        esc_html($category['max_weight'])
                    ) . ' ' . esc_html__('Please ship this order in multiple parcels.', 'colissimo-shipping-methods-for-woocommerce')
                );
            }
        }

        // Auto generation and exceeding the total maximum weight for hazmat products
        if (
            !empty($customParams['isAutoGeneration'])
            && $totalHazardousQuantity > $lowestHazmatLimit
        ) {
            throw new Exception(
                sprintf(
                // translators: %d is the maximum allowed weight in grams
                    esc_html__('The total amount of hazardous materials exceeds the maximum allowed weight of %dg.', 'colissimo-shipping-methods-for-woocommerce'),
                    esc_html($lowestHazmatLimit)
                ) . ' ' . esc_html__('Please ship this order in multiple parcels.', 'colissimo-shipping-methods-for-woocommerce')
            );
        }

        $this->payload['letter']['parcel']['hazmatFlag']      = true;
        $this->payload['letter']['parcel']['hazmatCategory']  = $highestHazmatCategoryCode;
        $this->payload['letter']['parcel']['hazmatPrintLogo'] = true;

        return $this;
    }

    private function formatPhone(string $phoneNumber): string {
        return preg_replace('/[^0-9+]/', '', $phoneNumber);
    }

    private function getProductHazmatCategorySlug(object $product): string {
        if ('variation' === $product->get_type()) {
            $attributesContainer = wc_get_product($product->get_parent_id());
            $productCategoryIds  = wc_get_product_term_ids($product->get_parent_id(), 'product_cat');
        } else {
            $attributesContainer = $product;
            $productCategoryIds  = $product->get_category_ids('edit');
        }

        $attributes = $attributesContainer->get_attributes();

        if (!empty($attributes['pa_' . self::HAZMAT_ATTRIBUTE])) {
            $productHazmatCategorySlugs = $attributes['pa_' . self::HAZMAT_ATTRIBUTE]->get_slugs();
        } else {
            $productHazmatCategorySlugs = [];
            foreach ($productCategoryIds as $categoryId) {
                $productHazmatCategorySlugs[] = get_term_meta($categoryId, self::HAZMAT_ATTRIBUTE, true);
            }
        }

        $existingSlugs = array_reverse(array_keys(self::HAZMAT_CATEGORIES));
        foreach ($existingSlugs as $oneSlug) {
            if (in_array($oneSlug, $productHazmatCategorySlugs)) {
                return $oneSlug;
            }
        }

        return '';
    }
}
