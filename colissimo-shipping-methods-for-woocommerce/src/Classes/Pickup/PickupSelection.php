<?php

namespace Colissimo\Classes\Pickup;

use Colissimo\Api\AccountApi;
use Colissimo\Core\Ajax;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use Colissimo\Helpers\OrderQueries;
use Exception;
use Colissimo\Core\Register;
use Colissimo\Classes\Shipping\Relay;
use WC_Order;
use YITH_Vendors_Orders;

defined('ABSPATH') || die('Restricted Access');

class PickupSelection {
    const AJAX_TASK_NAME = 'pickup_selection';
    const PICKUP_LOCATION_DATA_META_KEY = '_lpc_meta_pickUpLocationData';
    const PICKUP_LOCATION_ID_META_KEY = '_lpc_meta_pickUpLocationId';
    const PICKUP_LOCATION_LABEL_META_KEY = '_lpc_meta_pickUpLocationLabel';
    const PICKUP_PRODUCT_CODE_META_KEY = '_lpc_meta_pickUpProductCode';
    const PICKUP_LOCATION_SESSION_VAR_NAME = 'lpc_pickUpInfo';
    const PICKUP_ADDRESS_FORCED_MARKER = 'lpc_forced_shipping_address_on_relay';

    protected $ajaxDispatcher;
    protected AccountApi $accountApi;

    public function __construct(
        ?Ajax $ajaxDispatcher = null,
        ?AccountApi $accountApi = null
    ) {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
        $this->accountApi     = Register::get('accountApi');

        add_filter('woocommerce_order_button_html', [$this, 'preventPlaceOrderButton'], 10, 2);
        add_action('woocommerce_checkout_process', [$this, 'preventCheckoutProcess']);
    }

    public function init() {
        $this->listenToPickUpSelection();
        $this->savePickUpSelectionOnOrderProcessed();
        $this->applyPickupAddress();
    }

    protected function listenToPickUpSelection() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'pickUpLocationListener'], false);
    }

    public function pickUpLocationListener() {
        $pickUpInfo = Helper::getVar(self::PICKUP_LOCATION_SESSION_VAR_NAME, null, 'array');
        $this->setCurrentPickUpLocationInfo($pickUpInfo);

        ob_start();
        Helper::renderPartial(
            'Pickup/PickupInformation.php',
            ['relay' => $pickUpInfo]
        );
        $html = ob_get_clean();

        return $this->ajaxDispatcher->makeSuccess(
            [
                'html' => $html,
            ]
        );
    }

    public function getCurrentPickUpLocationInfo() {
        $wcSession  = Helper::getWooSession();
        $pickUpInfo = $wcSession->get(self::PICKUP_LOCATION_SESSION_VAR_NAME);
        if (empty($pickUpInfo)) {
            $this->initSession();
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- plugin-controlled session state, not external input.
            $pickUpInfo = $_SESSION[self::PICKUP_LOCATION_SESSION_VAR_NAME] ?? [];
        }

        return $pickUpInfo;
    }

    public function setCurrentPickUpLocationInfo($pickUpInfo, $orderId = null) {
        $wcSession = Helper::getWooSession();
        $wcSession->set(self::PICKUP_LOCATION_SESSION_VAR_NAME, $pickUpInfo);
        $this->initSession();
        $_SESSION[self::PICKUP_LOCATION_SESSION_VAR_NAME] = $pickUpInfo;

        Logger::debug(
            'Changing the saved pickup data',
            [
                'orderId'    => $orderId,
                'pickupData' => $pickUpInfo,
                'saved'      => $this->getCurrentPickUpLocationInfo(),
            ]
        );
    }

    private function initSession() {
        if (empty(session_id()) || session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    public function getAjaxUrl() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }

    public function savePickUpSelectionOnOrderProcessed() {
        add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'onCheckoutOrderUpdated']);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'onCheckoutOrderUpdated']);
        add_action('woocommerce_update_order', [$this, 'forceShippingAddressOnRelay']);
        add_action('woocommerce_checkout_order_processed', [$this, 'savePickupAddressOnOrder'], 10, 2);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'colissimoUsageApi'], 10, 1);
    }

    public function colissimoUsageApi($order): void {
        $shippings = $order->get_shipping_methods();
        if (empty($shippings)) {
            return;
        }

        $shipping = current($shippings);
        $this->colissimoUsagePing($shipping->get_method_id());
    }

    public function savePickupAddressOnOrder($orderId, $posted_data = []) {
        $order = wc_get_order($orderId);
        if (empty($order)) {
            return;
        }

        $shippings = $order->get_shipping_methods();
        $shipping  = current($shippings);

        $shippingMethod = '';
        if (!empty($shipping)) {
            $shippingMethod = $shipping->get_method_id();
            if (Relay::ID === $shippingMethod) {
                $pickUpInfo = $this->getCurrentPickUpLocationInfo();
                $this->updatePickupMeta($order, $pickUpInfo);
            }
        } elseif (!empty($posted_data['shipping_method'])) {
            // When activating the synced renewal on a subscription product, for some reason the shipping info isn't on the order
            $shippingMethod = array_pop($posted_data['shipping_method']);
            if (strpos($shippingMethod, Relay::ID) !== false) {
                // The action woocommerce_checkout_order_created didn't update the shipping address so we do it here
                $this->setPickupAsShippingAddress($order);
            }
        }

        $this->colissimoUsagePing($shippingMethod);
    }

    public function forceShippingAddressOnRelay(int $orderId) {
        $order = wc_get_order($orderId);
        if (empty($order) || !$order->has_shipping_method('lpc_relay')) {
            return;
        }

        $pickupData = $order->get_meta(self::PICKUP_LOCATION_DATA_META_KEY);
        if (empty($pickupData)) {
            return;
        }

        $decodedPickupData = json_decode($pickupData, true);
        if (empty($decodedPickupData['adresse1']) || $order->get_shipping_address_1() === $decodedPickupData['adresse1']) {
            return;
        }

        $forcedAddressNb = $order->get_meta(self::PICKUP_ADDRESS_FORCED_MARKER);
        if (empty($forcedAddressNb)) {
            $forcedAddressNb = 0;
        }

        if ($forcedAddressNb > 4) {
            return;
        }

        $this->applyAddress($order, $decodedPickupData);
        $order->update_meta_data(self::PICKUP_ADDRESS_FORCED_MARKER, $forcedAddressNb + 1);
        $order->save();
    }

    public function onCheckoutOrderUpdated($order): void {
        if (OrderQueries::hasShippingMethod($order, Relay::ID)) {
            $this->setPickupAsShippingAddress($order);
        }
    }

    private function updatePickupMeta($order, $pickUpInfo) {
        if (empty($order)) {
            Logger::error('Order missing when trying to save selected pickup during a purchase');

            return;
        }

        if (empty($pickUpInfo['identifiant'])) {
            Logger::error(
                'Pickup data missing when trying to save selected pickup during a purchase',
                [
                    'order'      => $order->get_id(),
                    'pickUpInfo' => $pickUpInfo,
                ]
            );

            return;
        }

        $order->update_meta_data(self::PICKUP_LOCATION_ID_META_KEY, $pickUpInfo['identifiant']);
        $order->update_meta_data(self::PICKUP_LOCATION_LABEL_META_KEY, $pickUpInfo['nom']);
        $order->update_meta_data(self::PICKUP_PRODUCT_CODE_META_KEY, $pickUpInfo['typeDePoint']);
        $order->update_meta_data(self::PICKUP_LOCATION_DATA_META_KEY, json_encode($pickUpInfo));
        $order->save();

        Logger::debug('Saved pickup data on order ' . $order->get_id(), ['pickUpInfo' => $pickUpInfo]);
    }

    private function setPickupAsShippingAddress($order, $isSubOrder = false) {
        $pickupData = $this->getCurrentPickUpLocationInfo();

        if (empty($pickupData['adresse1']) || empty($pickupData['identifiant'])) {
            Logger::error(
                'Could not save pickup data on order because the address was missing',
                [
                    'order'      => $order->get_id(),
                    'pickupData' => $pickupData,
                ]
            );

            return;
        } else {
            Logger::debug(
                'Saving pickup data on order',
                [
                    'order'      => $order->get_id(),
                    'pickupData' => $pickupData,
                ]
            );
        }

        $this->updatePickupMeta($order, $pickupData);
        $this->applyAddress($order, $pickupData);

        // To prevent Up2pay e-Transactions Crédit Agricole from messing with the shipping address
        $up2PayPlugin = 'e-transactions-wc/wc-etransactions.php';
        if (file_exists(WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . $up2PayPlugin) || is_plugin_active($up2PayPlugin)) {
            $order->update_meta_data('wc_etransactions_original_shipping_address_1', $order->get_shipping_address_1());
            $order->update_meta_data('wc_etransactions_original_shipping_address_2', $order->get_shipping_address_2());
            $order->update_meta_data('wc_etransactions_original_shipping_city', $order->get_shipping_city());
            $order->update_meta_data('wc_etransactions_original_shipping_postcode', $order->get_shipping_postcode());
            $order->update_meta_data('wc_etransactions_original_shipping_company', $order->get_shipping_company());
        }

        $order->save();

        if (!$isSubOrder && class_exists('YITH_Vendors_Orders') && method_exists('YITH_Vendors_Orders', 'get_suborders')) {
            $subOrderIds = YITH_Vendors_Orders::get_suborders($order->get_id());
            if (!empty($subOrderIds)) {
                foreach ($subOrderIds as $subOrderId) {
                    $subOrder = wc_get_order($subOrderId);
                    $this->setPickupAsShippingAddress($subOrder, true);
                }
            }
        }
    }

    private function applyAddress(WC_Order $order, array $address): void {
        $order->set_shipping_address_1($address['adresse1'] ?? '');
        $order->set_shipping_address_2(!empty($address['adresse2']) ? $address['adresse2'] : '');
        $order->set_shipping_postcode(!empty($address['codePostal']) ? $address['codePostal'] : '');
        $order->set_shipping_city(!empty($address['localite']) ? $address['localite'] : '');
        $order->set_shipping_country(!empty($address['codePays']) ? $address['codePays'] : '');
        $order->set_shipping_company(!empty($address['nom']) ? $address['nom'] : '');
        $order->set_shipping_state('');
    }

    public function preventPlaceOrderButton($orderButton) {
        if (!$this->isRelayRequired()) {
            return $orderButton;
        }

        $relayInfo = $this->getCurrentPickUpLocationInfo();

        if (!empty($relayInfo['adresse1']) && !empty($relayInfo['identifiant'])) {
            return $orderButton;
        }

        $textButton = __('Please select a pick-up point', 'colissimo-shipping-methods-for-woocommerce');

        return '<button type="submit" class="button alt wp-element-button" name="woocommerce_checkout_place_order" id="place_order">' . esc_html($textButton) . '</button>';
    }

    public function preventCheckoutProcess() {
        if (!$this->isRelayRequired()) {
            return;
        }

        $relayInfo = $this->getCurrentPickUpLocationInfo();

        if (empty($relayInfo['adresse1']) || empty($relayInfo['identifiant'])) {
            throw new Exception(esc_html__('Please select a pick-up point', 'colissimo-shipping-methods-for-woocommerce'));
        }

        $nonce = isset($_REQUEST['woocommerce-process-checkout-nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['woocommerce-process-checkout-nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'woocommerce-process_checkout')) {
            return;
        }

        $customerPhoneNumber = isset($_REQUEST['billing_phone']) ? sanitize_text_field(wp_unslash($_REQUEST['billing_phone'])) : '';

        // Even if we don't have a shipping phone natively in WooCommerce, we can check if a shipping phone exist if the billing one is empty
        // because a plugin or a theme can add it
        if (empty($customerPhoneNumber) && isset($_REQUEST['shipping_phone']) && !empty($_REQUEST['shipping_phone'])) {
            $customerPhoneNumber = sanitize_text_field(wp_unslash($_REQUEST['shipping_phone']));
        }

        $customerPhoneNumber     = str_replace(' ', '', $customerPhoneNumber);
        $wcSession               = Helper::getWooSession();
        $customerData            = $wcSession->get('customer');
        $customerShippingCountry = $customerData['shipping_country'];

        if (empty($customerPhoneNumber)) {
            throw new Exception(
                esc_html__(
                    'Please define a mobile phone number for SMS notification tracking',
                    'colissimo-shipping-methods-for-woocommerce'
                )
            );
        }

        if ('BE' !== $customerShippingCountry) {
            return;
        }

        if (!preg_match('/^\+324\d{8}$/', $customerPhoneNumber)) {
            $acceptableNumber = false;
        } else {
            $mobileNumbers = array_reverse(str_split($customerPhoneNumber));
            $mobileNumbers = array_map('intval', $mobileNumbers);
            $suiteAsc      = true;
            $suiteDesc     = true;
            $suiteEqual    = true;
            foreach ($mobileNumbers as $key => $val) {
                if (7 === $key) {
                    break;
                }

                if ($mobileNumbers[$key + 1] !== $val - 1) {
                    $suiteAsc = false;
                }
                if ($mobileNumbers[$key + 1] !== $val + 1) {
                    $suiteDesc = false;
                }
                if ($mobileNumbers[$key + 1] !== $val) {
                    $suiteEqual = false;
                }
            }

            $acceptableNumber = !$suiteAsc && !$suiteDesc && !$suiteEqual;
        }

        if (!$acceptableNumber) {
            throw new Exception(
                esc_html__(
                    'The mobile number for a Belgian destination must start with +324 and be 12 characters long. For example +324XXXXXXXX',
                    'colissimo-shipping-methods-for-woocommerce'
                )
            );
        }
    }

    public function applyPickupAddress() {
        add_action(
            'woocommerce_checkout_order_created',
            function ($order) {
                if (!$order->has_shipping_method('lpc_relay')) {
                    return;
                }

                $this->setPickupAsShippingAddress($order);
            }
        );
    }

    private function isRelayRequired(): bool {
        $wcSession      = Helper::getWooSession();
        $wcCart         = WC()->cart;
        $shippingMethod = $wcSession->get('chosen_shipping_methods');
        $needShipping   = $wcCart->needs_shipping();

        if (!$needShipping || empty($shippingMethod)) {
            return false;
        }

        $relayMethod = false;
        foreach ($shippingMethod as $oneMethod) {
            if (strpos($oneMethod, Relay::ID) !== false) {
                $relayMethod = true;
            }
        }

        if (!$relayMethod) {
            return false;
        }

        return true;
    }

    private function colissimoUsagePing(string $shippingMethodId) {
        if (strpos($shippingMethodId, 'lpc_') !== false) {
            $this->accountApi->getAccountInformation([], true);
        }
    }
}
