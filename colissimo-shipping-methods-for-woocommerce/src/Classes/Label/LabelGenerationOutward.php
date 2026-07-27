<?php

namespace Colissimo\Classes\Label;

use Colissimo\Classes\Pickup\PickupSelection;
use Colissimo\Classes\Pickup\PickupWebService;
use Colissimo\Classes\Shipping\CapabilitiesPerCountry;
use Colissimo\Classes\Shipping\ShippingMethods;
use Colissimo\Core\Register;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Api\LabelGenerationApi;
use Colissimo\Classes\Email\OutwardLabelEmailManager;
use Exception;
use WC_Order;

defined('ABSPATH') || die('Restricted Access');

class LabelGenerationOutward {
    const OUTWARD_PARCEL_NUMBER_META_KEY = 'lpc_outward_parcel_number';
    const ORDERS_OUTWARD_PARCEL_FAILED = 'lpc_orders_outward_parcel_failed';
    const LABEL_TYPE_CLASSIC = 'CLASSIC';
    const LABEL_TYPE_MASTER = 'MASTER';
    const LABEL_TYPE_FOLLOWER = 'FOLLOWER';

    protected $capabilitiesPerCountry;
    protected $labelGenerationApi;
    protected $labelGenerationInward;
    protected $shippingMethods;
    protected $outwardLabelDb;
    protected $lpcPickupWebService;

    public function __construct(
        ?CapabilitiesPerCountry $capabilitiesPerCountry = null,
        ?LabelGenerationApi $labelGenerationApi = null,
        ?LabelGenerationInward $labelGenerationInward = null,
        ?ShippingMethods $shippingMethods = null,
        ?OutwardLabelDb $outwardLabelDb = null,
        ?PickupWebService $lpcPickupWebService = null
    ) {
        $this->capabilitiesPerCountry = Register::get('capabilitiesPerCountry');
        $this->labelGenerationApi     = Register::get('labelGenerationApi');
        $this->labelGenerationInward  = Register::get('labelGenerationInward');
        $this->shippingMethods        = Register::get('shippingMethods');
        $this->outwardLabelDb         = Register::get('outwardLabelDb');
        $this->lpcPickupWebService    = Register::get('pickupWebService');
    }

    /**
     * @param WC_Order $order
     * @param array    $customParams Accepted params : total_weight, items
     * @param bool     $isWholeOrder Is generating the label for the whole order
     *
     * @return bool
     * @throws Exception When lpcAdminNotices isn't available.
     */
    public function generate(WC_Order $order, array $customParams = [], bool $isWholeOrder = false) {
        if (is_admin()) {
            $lpc_admin_notices = Register::get('lpcAdminNotices');
        }

        $detail       = empty($customParams['items']) ? [] : $customParams['items'];
        $fullyShipped = $this->isFullyShipped($order, $detail);
        if ($isWholeOrder) {
            $customParams = [
                'isAutoGeneration' => $customParams['isAutoGeneration'] ?? false,
            ];
        }

        $time         = time();
        $orderId      = $order->get_order_number();
        $ordersFailed = get_option(self::ORDERS_OUTWARD_PARCEL_FAILED, []);
        if (!empty($ordersFailed)) {
            update_option(
                self::ORDERS_OUTWARD_PARCEL_FAILED,
                array_filter($ordersFailed, fn($error) => $error['time'] < $time - 604800),
                false
            );
        }

        try {
            $payload     = $this->buildPayload($order, $customParams);
            $response    = $this->labelGenerationApi->generateLabel($payload);
            $labelFormat = $payload->getLabelFormat();

            if (is_admin()) {
                $accountApi = Register::get('accountApi');
                if (!$accountApi->isCgvAccepted()) {
                    $urls       = $accountApi->getAutologinURLs();
                    $accountUrl = $urls['urlConnectedCbox'] ?? 'https://www.colissimo.entreprise.laposte.fr';
                    $lpc_admin_notices->add_notice(
                        'cgv_invalid',
                        'notice-error',
                        '<span style="color:red;font-weight: bold;">' .
                        esc_html__(
                            'We have detected that you have not yet signed the latest version of our GTC. Your consent is necessary in order to continue using Colissimo services. We therefore invite you to sign them on your Colissimo entreprise space, by clicking on the link below:',
                            'colissimo-shipping-methods-for-woocommerce'
                        ) . '<br/><a href="' . esc_url($accountUrl) . '" target="_blank">' . esc_html__('Sign the GTC', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
                        . '</span>'
                    );
                }
            }

            if (!empty($ordersFailed[$orderId])) {
                unset($ordersFailed[$orderId]);
                update_option(self::ORDERS_OUTWARD_PARCEL_FAILED, $ordersFailed, false);
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'outward_label_generate',
                    'notice-error',
                    // translators: %s is the order ID
                    sprintf(__('Order %s: Outward label was not generated:', 'colissimo-shipping-methods-for-woocommerce'), $orderId) . ' ' . $errorMessage
                );
            }

            $ordersFailed[$orderId] = [
                'message' => $errorMessage,
                'time'    => $time,
            ];
            update_option(self::ORDERS_OUTWARD_PARCEL_FAILED, $ordersFailed, false);

            return false;
        }

        $parcelNumber = $response['<jsonInfos>']['labelV31Response']['parcelNumber'];
        $label        = $response['<label>'];
        $cn23         = @$response['<cn23>'];

        $order->update_meta_data(self::OUTWARD_PARCEL_NUMBER_META_KEY, $parcelNumber);
        $order->save();

        if ($payload->isInsured()) {
            $detail['insured'] = 1;
        }

        $type = self::LABEL_TYPE_CLASSIC;
        if (!empty($customParams['multiParcels'])) {
            if ($customParams['multiParcelsCurrentNumber'] === $customParams['multiParcelsAmount']) {
                $type = self::LABEL_TYPE_MASTER;
            } else {
                $type = self::LABEL_TYPE_FOLLOWER;
            }
        }

        // PDF label is too big to be stored in an order meta
        try {
            $this->outwardLabelDb->insert($order->get_id(), $label, $parcelNumber, $type, $cn23, $labelFormat, $detail);
        } catch (Exception $e) {
            if (is_admin()) {
                $lpc_admin_notices->add_notice(
                    'outward_label_generate',
                    'notice-error',
                    // translators: %s is the order ID
                    sprintf(__('Order %s: Outward label was not generated:', 'colissimo-shipping-methods-for-woocommerce'), $orderId) . ' ' . $e->getMessage()
                );
            }

            return false;
        }

        if (is_admin()) {
            $actions = '';

            $labelQueries = new LabelQueries();
            if (current_user_can('lpc_download_labels')) {
                $actions .= '<span class="dashicons dashicons-download lpc_label_action_download" ' .
                            $labelQueries->getLabelOutwardDownloadAttr($parcelNumber, $labelFormat) . '></span>';
            }

            if (current_user_can('lpc_print_labels')) {
                $printerIcon = $GLOBALS['wp_version'] >= '5.5' ? 'dashicons-printer' : 'dashicons-media-default';
                $actions     .= '<span class="dashicons ' . $printerIcon . ' lpc_label_action_print" ' .
                                $labelQueries->getLabelOutwardPrintAttr($parcelNumber, $labelFormat) . ' ></span>';
            }

            $lpc_admin_notices->add_notice(
                'outward_label_generate',
                'notice-success',
                // translators: %s is the order ID
                sprintf(__('Order %s : Outward label generated', 'colissimo-shipping-methods-for-woocommerce'), $orderId) . $actions
            );
        }

        if ($fullyShipped) {
            $this->applyStatusAfterLabelGeneration($order);
        } else {
            $this->applyStatusAfterPartialExpedition($order);
        }

        $email_outward_label = Helper::get_option(OutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, 'no');
        if (OutwardLabelEmailManager::ON_OUTWARD_LABEL_GENERATION_OPTION === $email_outward_label) {
            /**
             * Action when the shipping label has been sent by email
             *
             * @since 1.6
             */
            do_action(
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook, correctly prefixed with "lpc_".
                'lpc_outward_label_generated_to_email',
                ['order' => $order]
            );
        }

        $customerReturn = 'no' !== Helper::get_option('lpc_customers_download_return_label', 'no');
        $securedReturn  = 'no' !== Helper::get_option('lpc_secured_return', 'no');
        $autoReturn     = 'yes' === Helper::get_option('lpc_createReturnLabelWithOutward', 'no');
        if ($autoReturn && (!$customerReturn || !$securedReturn)) {
            $this->labelGenerationInward->generate(
                $order,
                [
                    'isAutoGeneration' => true,
                ]
            );
        }

        return true;
    }

    private function isFullyShipped($order, $itemsInLabel) {
        $labelDetails = $this->outwardLabelDb->getAllLabelDetailByOrderId($order->get_id());

        $quantityPerSentItem = [];
        foreach ($labelDetails as $detail) {
            if (empty($detail)) {
                continue;
            }
            $detail = json_decode($detail, true);
            $this->prepareSentItemQuantities($quantityPerSentItem, $detail);
        }
        $this->prepareSentItemQuantities($quantityPerSentItem, $itemsInLabel);

        $allItemsOrders = $order->get_items();

        $fullyShipped = true;

        foreach ($allItemsOrders as $item) {
            $product = $item->get_product();
            if (empty($product) || !$product->needs_shipping()) {
                continue;
            }

            $itemId = intval($item->get_id());

            if (empty($quantityPerSentItem[$itemId]) || $quantityPerSentItem[$itemId] < $item->get_quantity()) {
                $fullyShipped = false;
                break;
            }
        }

        return $fullyShipped;
    }

    public function prepareSentItemQuantities(array &$quantityPerSentItem, array $items) {
        foreach ($items as $itemId => $oneItemDetail) {
            $itemId = intval($itemId);
            if (empty($itemId)) {
                continue;
            }

            if (!isset($quantityPerSentItem[$itemId])) {
                $quantityPerSentItem[$itemId] = 0;
            }

            foreach ($oneItemDetail as $itemParams => $itemParamsValue) {
                if ('qty' !== $itemParams) {
                    continue;
                }

                $quantityPerSentItem[$itemId] += (float) $itemParamsValue;
            }
        }
    }

    /**
     * @throws Exception When the product code couldn't be found.
     */
    protected function buildPayload(WC_Order $order, array $customParams = []) {
        $recipient = [
            'companyName' => $order->get_shipping_company(),
            'firstName'   => $order->get_shipping_first_name(),
            'lastName'    => $order->get_shipping_last_name(),
            'street'      => $order->get_shipping_address_1(),
            'street2'     => $order->get_shipping_address_2(),
            'city'        => $order->get_shipping_city(),
            'zipCode'     => $order->get_shipping_postcode(),
            'countryCode' => $order->get_shipping_country(),
            'stateCode'   => $order->get_shipping_state(),
            'email'       => $order->get_billing_email(),
            'phone'       => $order->get_billing_phone(),
        ];

        // For Luxembourg, the zip code must not have the "L-" prefix
        if ('LU' === strtoupper($recipient['countryCode'])) {
            $recipient['zipCode'] = ltrim($recipient['zipCode'], 'lL-');
        }

        // United Arab Emirates don't use zip codes
        if ('AE' === strtoupper($recipient['countryCode'])) {
            $recipient['zipCode'] = '00000';
        }

        if (method_exists($order, 'get_shipping_phone')) {
            $shippingPhone = $order->get_shipping_phone();
            if (!empty($shippingPhone)) {
                $recipient['phone'] = $shippingPhone;
            }
        }

        $productCode = $this->capabilitiesPerCountry->getProductCodeForOrder($order);
        if (empty($productCode)) {
            Logger::error('Not allowed for this destination', ['order' => $order]);
            throw new Exception(esc_html__('Not allowed for this destination', 'colissimo-shipping-methods-for-woocommerce'));
        }

        $shippingMethodUsed = $this->shippingMethods->getColissimoShippingMethodOfOrder($order);

        if ('lpc_relay' === $shippingMethodUsed) {
            $relayId = $order->get_meta(PickupSelection::PICKUP_LOCATION_ID_META_KEY);

            if (empty($relayId)) {
                // The relay data isn't stored, try to get the closest relay from the shipping address
                $closestRelay = $this->lpcPickupWebService->getDefaultPickupLocationInfoWS(
                    [
                        'address'     => $recipient['street'],
                        'zipCode'     => $recipient['zipCode'],
                        'city'        => $recipient['city'],
                        'countryCode' => $recipient['countryCode'],
                    ]
                );

                if (!empty($closestRelay)) {
                    $relayId = $closestRelay['identifiant'];

                    Logger::debug(
                        'Applying closest relay data to label',
                        [
                            'orderID'      => $order->get_id(),
                            'closestRelay' => $closestRelay,
                        ]
                    );

                    $recipient['street']      = $closestRelay['adresse1'];
                    $recipient['street2']     = $closestRelay['adresse2'] ?? '';
                    $recipient['zipCode']     = $closestRelay['codePostal'];
                    $recipient['city']        = $closestRelay['localite'];
                    $recipient['countryCode'] = $closestRelay['codePays'];
                    $recipient['companyName'] = $closestRelay['nom'] ?? '';
                } else {
                    Logger::error(
                        'No relay found for the shipping address',
                        [
                            'orderID' => $order->get_id(),
                        ]
                    );
                    throw new Exception(esc_html__('No relay found for the shipping address', 'colissimo-shipping-methods-for-woocommerce'));
                }
            } else {
                $relayData = $order->get_meta(PickupSelection::PICKUP_LOCATION_DATA_META_KEY);
                if (!empty($relayData)) {
                    $relayData = json_decode($relayData, true);
                    Logger::debug('Applying saved relay data to label', [$relayData]);

                    if (!empty($relayData['adresse1']) && $recipient['street'] !== $relayData['adresse1']) {
                        $recipient['street']      = $relayData['adresse1'];
                        $recipient['street2']     = $relayData['adresse2'] ?? '';
                        $recipient['zipCode']     = $relayData['codePostal'];
                        $recipient['city']        = $relayData['localite'];
                        $recipient['countryCode'] = $relayData['codePays'];
                        $recipient['companyName'] = $relayData['nom'] ?? '';
                    }
                }
            }
        }

        $payload = new LabelGenerationPayload();
        $payload
            ->withOrderNumber($order->get_order_number())
            ->withProductCode($productCode)
            ->withCredentials()
            ->withCommercialName(Helper::get_option('lpc_origin_company_name'))
            ->withCuserInfoText()
            ->withSender()
            ->withAddressee($recipient, $shippingMethodUsed)
            ->withPackage($order, $customParams)
            ->withPreparationDelay()
            ->withInstructions($order->get_customer_note())
            ->withCustomsDeclaration($order, $customParams, $shippingMethodUsed)
            ->withOutputFormat()
            ->withPostalNetwork($recipient['countryCode'])
            ->withNonMachinable($customParams)
            ->withDDP($shippingMethodUsed)
            ->withFtd($recipient['countryCode'])
            ->withMultiParcels($order->get_id(), $customParams)
            ->withBlockingCode($shippingMethodUsed, $order, $customParams)
            ->withHazmat($order, $customParams);

        if ('lpc_relay' === $shippingMethodUsed) {
            $payload->withPickupLocationId($relayId);
        }

        $payload->withInsuranceValue($order->get_subtotal(), $order->get_shipping_country(), $shippingMethodUsed, $customParams);

        return $payload->checkConsistency();
    }

    public function applyStatusAfterLabelGeneration(WC_Order $order) {
        $statusToApply = Helper::get_option('lpc_order_status_on_label_generated', null);

        if (!empty($statusToApply) && 'unchanged_order_status' !== $statusToApply) {
            $order->set_status($statusToApply);
            $order->save();
        }
    }

    protected function applyStatusAfterPartialExpedition(WC_Order $order) {
        $statusToApply = Helper::get_option('lpc_status_on_partial_expedition', 'wc-lpc_partial_exp');

        if (!empty($statusToApply) && 'unchanged_order_status' !== $statusToApply) {
            $order->set_status($statusToApply);
            $order->save();
        }
    }
}
