<?php

namespace Colissimo\Init;

defined('ABSPATH') || die('Restricted Access');

use Colissimo\Classes\Label\LabelGenerationInward;
use Colissimo\Classes\Label\LabelGenerationPayload;
use Colissimo\Classes\Shipping\Relay;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\OrderQueries;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use WC_Order;

class ReturnProducts {
    const RETURN_PAGE_ALIAS = 'lpcreturn';

    private $listMailBoxPickingDatesResponse;

    public function __construct() {
        add_action('woocommerce_order_details_after_order_table', [$this, 'addReturnLabelDownload'], 11, 1);
        add_action('init', [$this, 'addLpcReturnEndPoint']);
        add_action('woocommerce_account_' . self::RETURN_PAGE_ALIAS . '_endpoint', [$this, 'returnMenu']);
    }

    public function addReturnLabelDownload(WC_Order $order) {
        // Check if we allow return labels
        $returnGenerationType = Helper::get_option('lpc_customers_download_return_label', 'no');
        if ('no' === $returnGenerationType || is_order_received_page()) {
            return;
        }

        // We only allow return labels for a certain amount of days
        $returnGenerationDays = Helper::get_option('lpc_customers_download_return_label_days', 14);
        $limitDate            = $order->get_date_created();
        $limitDate->add(new DateInterval('P' . $returnGenerationDays . 'D'));
        if ($limitDate < new DateTime()) {
            return;
        }

        // If no parcel has been sent, no need for return label
        $outwardLabelDb  = Register::get('outwardLabelDb');
        $trackingNumbers = $outwardLabelDb->getOrderLabels($order->get_id());
        if (empty($trackingNumbers)) {
            return;
        }

        // Make sure the country is eligible for return labels
        $capabilitiesPerCountry = Register::get('capabilitiesPerCountry');
        if (false === $capabilitiesPerCountry->getReturnProductCodeForDestination($order->get_shipping_country())) {
            return;
        }

        $myAccountUrl = get_permalink(get_option('woocommerce_myaccount_page_id'));
        $myAccountUrl = add_query_arg('lpcreturn', '', $myAccountUrl);
        $myAccountUrl = add_query_arg('order_id', $order->get_id(), $myAccountUrl);

        Helper::renderPartial(
            'Return/Button.php',
            [
                'js'         => Helper::getJsUrl('orders/details.js'),
                'css'        => Helper::getCssUrl('orders/details.css'),
                'accountUrl' => $myAccountUrl,
            ]
        );
    }

    public function addLpcReturnEndPoint() {
        add_rewrite_endpoint(self::RETURN_PAGE_ALIAS, EP_PAGES);
    }

    public function returnMenu($current_page = 1) {
        $orderId = Helper::getVar('order_id', 0);
        $order   = wc_get_order($orderId);

        $currentUser = wp_get_current_user();
        if (empty($order) || $order->get_user_id() !== $currentUser->ID) {
            esc_html_e('You are not allowed to access the return label for this order.', 'colissimo-shipping-methods-for-woocommerce');

            return;
        }

        $balStep = (int) Helper::getVar('lpc_bal_step', 0);

        $balUrl = get_permalink(get_option('woocommerce_myaccount_page_id'));
        $balUrl = add_query_arg('lpcreturn', '', $balUrl);
        $balUrl = add_query_arg('order_id', $orderId, $balUrl);
        $balUrl = add_query_arg('lpc_bal_step', $balStep + 1, $balUrl);

        $labelInwardDownloadAccountAction = Register::get('labelInwardDownloadAccountAction');

        $data = [
            'order'           => $order,
            'generateUrlBase' => $labelInwardDownloadAccountAction->getUrlForCustom($orderId),
            'downloadUrlBase' => $labelInwardDownloadAccountAction->getUrlForDownload($orderId, ''),
            'balReturn'       => 'yes' === Helper::get_option('lpc_bal_return', 'no') && 'FR' === $order->get_shipping_country(),
            'balReturnUrl'    => $balUrl,
            'securedReturn'   => false,
        ];

        $accountApi         = Register::get('accountApi');
        $accountInformation = $accountApi->getAccountInformation();
        if (!empty($accountInformation['optionRetourToken']) && 1 === intval(Helper::get_option('lpc_secured_return', 0))) {
            $data['securedReturn'] = true;
            $data['balReturn']     = false;
        }

        if (empty($balStep)) {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
            echo '<link rel="stylesheet" href="' . esc_url(Helper::getCssUrl('orders/return.css')) . '" />';
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
            echo '<script src="' . esc_url(Helper::getJsUrl('orders/return.js')) . '"></script>';

            Helper::renderPartial('Order/Return.php', $data);
        } else {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
            echo '<link rel="stylesheet" href="' . esc_url(Helper::getCssUrl('orders/bal_return.css')) . '" />';

            $data['products'] = Helper::getVar('lpc_label_products');

            if (1 === $balStep) {
                $data['address'] = [
                    'company'     => $order->get_formatted_shipping_full_name(),
                    'address_1'   => $order->get_shipping_address_1(),
                    'city'        => $order->get_shipping_city(),
                    'postcode'    => $order->get_shipping_postcode(),
                    'country'     => 'FR',
                    'countryCode' => 'FR',
                ];
                $address2        = $order->get_shipping_address_2();
                if (!empty($address2)) {
                    $data['address']['address_1'] .= ' ' . $address2;
                }

                if (OrderQueries::hasShippingMethod($order, Relay::ID)) {
                    $data['address'] = [
                        'company'     => $order->get_formatted_billing_full_name(),
                        'address_1'   => $order->get_billing_address_1(),
                        'city'        => $order->get_billing_city(),
                        'postcode'    => $order->get_billing_postcode(),
                        'country'     => 'FR',
                        'countryCode' => 'FR',
                    ];
                    $address2        = $order->get_billing_address_2();
                    if (!empty($address2)) {
                        $data['address']['address_1'] .= ' ' . $address2;
                    }
                }

                Helper::renderPartial('Order/ReturnBalAddress.php', $data);
            } else {
                $address                = Helper::getVar('address', [], 'array');
                $address['countryCode'] = 'FR';
                $data['address']        = $address;
                $data['addressDisplay'] = $this->formatAddress($address);
                $payload                = $this->getPayload($address);
                $this->prepareListMailBoxPickingDatesResponse($payload);

                if (2 === $balStep) {
                    $data['listMailBoxPickingDatesResponse'] = $this->listMailBoxPickingDatesResponse;

                    if ($data['listMailBoxPickingDatesResponse']) {
                        $data['mailBoxPickingDate'] = $this->getMailBoxPickingDate();
                    } else {
                        $data['mailBoxPickingDate'] = null;
                    }

                    Helper::renderPartial('Order/ReturnBalAvailability.php', $data);
                } elseif (3 === $balStep) {
                    $data['returnTrackingNumber'] = $this->getReturnTrackingNumber($order, $data);
                    if (!$data['returnTrackingNumber']) {
                        return;
                    }

                    $data['pickupConfirmation'] = $this->sendPickUpConfirmation($payload, $data['returnTrackingNumber']);
                    $data['labelDownloadUrl']   = $labelInwardDownloadAccountAction->getUrlForDownload($order->get_id(), $data['returnTrackingNumber']);

                    Helper::renderPartial('Order/ReturnBalConfirmation.php', $data);
                }
            }
        }
    }

    /**
     * Format address to display it using Woocommerce function
     *
     * @param array $address : got from user request
     *
     * @return array : address formatted
     */
    private function formatAddress(array $address): array {
        return [
            'company'   => $address['companyName'] ?? '',
            'address_1' => $address['street'] ?? '',
            'city'      => $address['city'] ?? '',
            'postcode'  => $address['zipCode'] ?? '',
            'country'   => $address['countryCode'] ?? '',
        ];
    }

    /**
     * Prepare data for the API calls
     *
     * @param array $sender : address to check
     *
     * @return array
     */
    private function getPayload($sender): array {
        $payload        = new LabelGenerationPayload();
        $payloadPicking = $payload
            ->withCredentials()
            ->withSender($sender)
            ->assemble();

        $payloadPicking['sender'] = $payloadPicking['letter']['sender']['address'];
        unset($payloadPicking['letter']);

        return $payloadPicking;
    }

    /**
     * Call API to check pickup availability at a specific address
     */
    private function prepareListMailBoxPickingDatesResponse($payload) {
        try {
            $labelGenerationApi                    = Register::get('labelGenerationApi');
            $this->listMailBoxPickingDatesResponse = $labelGenerationApi->listMailBoxPickingDates($payload);
        } catch (Exception $e) {
            Logger::debug(__METHOD__ . ' Error calling pickup', [$payload]);
            $this->listMailBoxPickingDatesResponse = false;
        }
    }

    /**
     * Format date got from API
     */
    private function getMailBoxPickingDate() {
        $pickingPossibleDates = $this->listMailBoxPickingDatesResponse['mailBoxPickingDates'];

        if (empty($pickingPossibleDates)) {
            return null;
        }

        // Make sure we show
        $cmsOffset = get_option('timezone_string', null);

        // In WP there are multiple possible formats in the same option for the timezone
        if (empty($cmsOffset)) {
            $cmsOffset = get_option('gmt_offset', null);

            if (empty($cmsOffset)) {
                $cmsOffset = 'UTC';
            } elseif ($cmsOffset < 0) {
                $cmsOffset = 'GMT' . $cmsOffset;
            } else {
                $cmsOffset = 'GMT+' . $cmsOffset;
            }
        }

        $timezone = new DateTimeZone($cmsOffset);
        if (!is_numeric($cmsOffset)) {
            $cmsOffset = $timezone->getOffset(new DateTime());
        }

        return date_i18n(
            __('F j, Y', 'colissimo-shipping-methods-for-woocommerce'),
            $pickingPossibleDates[0] / 1000 + $cmsOffset,
            true
        );
    }

    /**
     * Get return label number and generate one if no return label found
     */
    private function getReturnTrackingNumber(WC_Order $order, $data) {
        try {
            $products = json_decode($data['products'], true);
            if (empty($products)) {
                return false;
            }

            $orderedProducts = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (empty($product) || !$product->needs_shipping()) {
                    continue;
                }

                $orderedProducts[$item->get_id()] = $item;
            }

            $products        = array_combine(array_column($products, 'productId'), array_column($products, 'quantity'));
            $items           = [];
            $totalWeight     = wc_get_weight(Helper::get_option('lpc_packaging_weight', '0'), 'kg');
            $insuranceAmount = 0;
            foreach ($products as $productId => $quantity) {
                if (empty($orderedProducts[$productId]) || $orderedProducts[$productId]->get_quantity() < $quantity) {
                    return false;
                }

                $product = $orderedProducts[$productId]->get_product();

                $items[$productId] = ['qty' => $quantity];
                $totalWeight       += wc_get_weight(floatval($product->get_weight()) * floatval($quantity), 'kg');
                $insuranceAmount   += $product->get_price() * $quantity;
            }

            $labelGenerationInward = Register::get('labelGenerationInward');
            $labelGenerationInward->generate(
                $order,
                [
                    'items'                => $items,
                    'outward_label_number' => 'no_outward',
                    'totalWeight'          => $totalWeight,
                    'insuranceAmount'      => $insuranceAmount,
                    'format'               => LabelGenerationPayload::LABEL_FORMAT_PDF,
                    'sender'               => [
                        'companyName' => $data['address']['companyName'] ?? $order->get_billing_company(),
                        'line2'       => $data['address']['street'] ?? $order->get_billing_address_1(),
                        'city'        => $data['address']['city'] ?? $order->get_billing_city(),
                        'zipCode'     => $data['address']['zipCode'] ?? $order->get_billing_postcode(),
                        'countryCode' => 'FR',
                    ],
                ]
            );
            $order->read_meta_data(true);

            return $order->get_meta(LabelGenerationInward::INWARD_PARCEL_NUMBER_META_KEY);
        } catch (Exception $exc) {
            Logger::debug(__METHOD__ . ' Error generating return label on pickup confirmation', ['order' => $order->get_id()]);
        }

        return false;
    }

    /**
     * Call API to confirm pickup
     */
    private function sendPickUpConfirmation($payload, $returnTrackingNumber) {
        if (empty($this->listMailBoxPickingDatesResponse['mailBoxPickingDates'][0])) {
            return false;
        }

        $payload['mailBoxPickingDate'] = $this->listMailBoxPickingDatesResponse['mailBoxPickingDates'][0];
        $payload['parcelNumber']       = $returnTrackingNumber;

        try {
            $labelGenerationApi = Register::get('labelGenerationApi');

            return $labelGenerationApi->planPickup($payload);
        } catch (Exception $e) {
            Logger::debug(__METHOD__ . ' Error confirming pickup', [$payload]);

            return false;
        }
    }
}
