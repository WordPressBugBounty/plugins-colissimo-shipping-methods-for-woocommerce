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
use WC_Customer;
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
    const PICKUP_AUTO_SELECTION_SESSION_VAR_NAME = 'lpc_pickUpAutoSelectionAddress';
    const PHP_SESSION_FALLBACK_KEY = 'lpc_pickUpInfoFallback';
    const PHP_SESSION_AUTO_SELECTION_FALLBACK_KEY = 'lpc_pickUpAutoSelectionAddressFallback';
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
        // The checkout page reads the pickup selection while rendering, when it is too late to open a session.
        add_action(
            'template_redirect',
            function () {
                if (is_checkout() && $this->isRelayRequired()) {
                    $this->startPhpSession();
                }
            }
        );
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
        $pickUpInfo = wc_clean(Helper::getVar(self::PICKUP_LOCATION_SESSION_VAR_NAME, null, 'array'));
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
            $pickUpInfo = $this->getPhpSessionValue(self::PHP_SESSION_FALLBACK_KEY);

            if (!empty($pickUpInfo)) {
                Logger::debug('Pickup data read from the PHP session fallback', ['pickupData' => $pickUpInfo]);
            }
        }

        if (!empty($pickUpInfo) && !$this->isPickUpLocationStillValid($pickUpInfo)) {
            Logger::debug(
                'Ignoring the saved pickup data, it does not match the current shipping address',
                [
                    'pickupData'      => $pickUpInfo,
                    'customerAddress' => Helper::getCustomerShippingAddress(),
                ]
            );

            return null;
        }

        return $pickUpInfo;
    }

    public function setCurrentPickUpLocationInfo($pickUpInfo, $orderId = null) {
        $wcSession = Helper::getWooSession();

        $wcSession->set(self::PICKUP_LOCATION_SESSION_VAR_NAME, $pickUpInfo);
        $wcSession->set(self::PICKUP_AUTO_SELECTION_SESSION_VAR_NAME, null);

        $this->setPhpSessionValue(self::PHP_SESSION_FALLBACK_KEY, $pickUpInfo);
        $this->setPhpSessionValue(self::PHP_SESSION_AUTO_SELECTION_FALLBACK_KEY, null);

        Logger::debug(
            'Changing the saved pickup data',
            [
                'orderId'    => $orderId,
                'pickupData' => $pickUpInfo,
                'saved'      => $this->getCurrentPickUpLocationInfo(),
            ]
        );
    }

    /**
     * Save a relay the plugin picked itself, tied to the address it was computed from.
     * It is dropped as soon as that address changes, so a response computed for an outdated address cannot survive it.
     */
    public function setAutoSelectedPickUpLocationInfo(array $pickUpInfo, array $address): void {
        $this->setCurrentPickUpLocationInfo($pickUpInfo);

        $fingerprint = Helper::getAddressFingerprint($address);

        $wcSession = Helper::getWooSession();
        $wcSession->set(self::PICKUP_AUTO_SELECTION_SESSION_VAR_NAME, $fingerprint);

        $this->setPhpSessionValue(self::PHP_SESSION_AUTO_SELECTION_FALLBACK_KEY, $fingerprint);
    }

    private function isPickUpLocationStillValid($pickUpInfo): bool {
        $customerAddress = Helper::getCustomerShippingAddress();

        if (empty($customerAddress['countryCode'])) {
            return true;
        }

        if (!empty($pickUpInfo['codePays']) && $pickUpInfo['codePays'] !== $customerAddress['countryCode']) {
            return false;
        }

        $autoSelectionAddress = Helper::getWooSession()->get(self::PICKUP_AUTO_SELECTION_SESSION_VAR_NAME);
        if (empty($autoSelectionAddress)) {
            $autoSelectionAddress = $this->getPhpSessionValue(self::PHP_SESSION_AUTO_SELECTION_FALLBACK_KEY);
        }

        return empty($autoSelectionAddress) || Helper::getAddressFingerprint($customerAddress) === $autoSelectionAddress;
    }

    /**
     * Mirror of the WooCommerce session for the pickup selection.
     * Some hosting stacks drop the WooCommerce session cookie of a guest between the AJAX selection and the checkout
     * request, which leaves the order without a pickup point; the native PHP session survives there.
     */
    private function getPhpSessionValue(string $key) {
        if (!$this->startPhpSession() || empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            return null;
        }

        return json_decode(sanitize_text_field(wp_unslash($_SESSION[$key])), true);
    }

    private function setPhpSessionValue(string $key, $value): void {
        if (!$this->startPhpSession()) {
            return;
        }

        if (null === $value || '' === $value || [] === $value) {
            unset($_SESSION[$key]);

            return;
        }

        $_SESSION[$key] = wp_json_encode($value);
    }

    private function startPhpSession(): bool {
        // A logged-in customer is identified by their account in every context, so the WooCommerce session is enough
        if (is_user_logged_in()) {
            return false;
        }

        $status = session_status();

        if (PHP_SESSION_ACTIVE === $status) {
            return true;
        }

        if (PHP_SESSION_DISABLED === $status || headers_sent()) {
            return false;
        }

        return session_start();
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
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'restoreCustomerShippingAddress'], 20);
    }

    public function restoreCustomerShippingAddress($order): void {
        $customerId = $order->get_customer_id();
        if (empty($customerId) || !OrderQueries::hasShippingMethod($order, Relay::ID)) {
            return;
        }

        $sessionCustomer = WC()->customer;
        if (empty($sessionCustomer) || '' === $sessionCustomer->get_shipping_address_1()) {
            return;
        }

        $customer = new WC_Customer($customerId);
        $customer->set_shipping_company($sessionCustomer->get_shipping_company());
        $customer->set_shipping_address_1($sessionCustomer->get_shipping_address_1());
        $customer->set_shipping_address_2($sessionCustomer->get_shipping_address_2());
        $customer->set_shipping_postcode($sessionCustomer->get_shipping_postcode());
        $customer->set_shipping_city($sessionCustomer->get_shipping_city());
        $customer->set_shipping_state($sessionCustomer->get_shipping_state());
        $customer->set_shipping_country($sessionCustomer->get_shipping_country());
        $customer->save();
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
        $order->set_shipping_address_1(wc_clean($address['adresse1'] ?? ''));
        $order->set_shipping_address_2(!empty($address['adresse2']) ? wc_clean($address['adresse2']) : '');
        $order->set_shipping_postcode(!empty($address['codePostal']) ? wc_clean($address['codePostal']) : '');
        $order->set_shipping_city(!empty($address['localite']) ? wc_clean($address['localite']) : '');
        $order->set_shipping_country(!empty($address['codePays']) ? wc_clean($address['codePays']) : '');
        $order->set_shipping_company(!empty($address['nom']) ? wc_clean($address['nom']) : '');
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
        $wcCart = WC()->cart;
        if (empty($wcCart)) {
            return false;
        }

        $wcSession      = Helper::getWooSession();
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
