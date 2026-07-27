<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional re-use of a documented WooCommerce core hook.

namespace Colissimo\Classes\Order;

use Colissimo\Classes\Settings\AdminNotices;
use Colissimo\Classes\Shipping\CapabilitiesPerCountry;
use Colissimo\Classes\Shipping\NoSign;
use Colissimo\Core\Register;
use Colissimo\Classes\Shipping\Relay;
use Colissimo\Classes\Shipping\Sign;
use Colissimo\Classes\Shipping\SignDdp;
use Colissimo\Helpers\OrderQueries;
use WC_Order_Item_Shipping;

defined('ABSPATH') || die('Restricted Access');

class TableBulkActions {
    /** @var CapabilitiesPerCountry */
    private $lpcCapabilitiesPerCountry;
    /** @var AdminNotices */
    private $lpcAdminNotices;
    private $actions;

    public function __construct(
        ?CapabilitiesPerCountry $lpcCapabilitiesPerCountry = null,
        ?AdminNotices $lpcAdminNotices = null
    ) {
        $this->lpcCapabilitiesPerCountry = Register::get('capabilitiesPerCountry');
        $this->lpcAdminNotices           = Register::get('lpcAdminNotices');
    }

    public function init() {
        // Old storage method
        add_filter('bulk_actions-edit-shop_order', [$this, 'define_bulk_actions'], 20);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_actions'], 10, 3);
        // HPOS
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'define_bulk_actions'], 20);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_actions'], 10, 3);

        add_action('admin_notices', [$this, 'bulk_admin_notices']);
        add_action(
            'woocommerce_init',
            function () {
                $this->actions = [
                    'ship_lpc_nosign'   => [
                        'id'   => NoSign::ID,
                        'name' => __('Colissimo without signature', 'colissimo-shipping-methods-for-woocommerce'),
                    ],
                    'ship_lpc_sign'     => [
                        'id'   => Sign::ID,
                        'name' => __('Colissimo with signature', 'colissimo-shipping-methods-for-woocommerce'),
                    ],
                    'ship_lpc_sign_ddp' => [
                        'id'   => SignDdp::ID,
                        'name' => __('Colissimo with signature - DDP option', 'colissimo-shipping-methods-for-woocommerce'),
                    ],
                ];
            }
        );
    }

    public function define_bulk_actions($actions) {
        foreach ($this->actions as $one_action => $action_description) {
            // translators: %s is the shipping method name.
            $actions[$one_action] = sprintf(__('Ship with: %s', 'colissimo-shipping-methods-for-woocommerce'), $action_description['name']);
        }

        return $actions;
    }

    public function handle_bulk_actions($redirect_to, $action, $ids) {
        if (
            !in_array($action, array_keys($this->actions))
            || empty($_REQUEST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), OrderQueries::isHposActive() ? 'bulk-orders' : 'bulk-posts')
        ) {
            return esc_url_raw($redirect_to);
        }

        /**
         * Filter on the order IDs passed to the hook
         *
         * @since 1.7.1
         */
        $ids                = apply_filters('woocommerce_bulk_action_ids', array_reverse(array_map('absint', $ids)), $action, 'order');
        $availableCountries = $this->lpcCapabilitiesPerCountry->getCountriesForMethod($this->actions[$action]['id']);
        $changed            = 0;

        foreach ($ids as $id) {
            $order = wc_get_order($id);
            if (empty($order)) {
                continue;
            }

            $orderShippingTotal = $order->get_shipping_total();
            $orderShippingTax   = $order->get_shipping_tax();

            // If the shipping country isn't allowed for this shipping method, add warning and skip it
            if (!in_array($order->get_shipping_country(), $availableCountries)) {
                $this->lpcAdminNotices->add_notice(
                    'shipment_change',
                    'notice-error',
                    // translators: %1$d is the order ID, %2$s is the shipping method name.
                    sprintf(__('The order #%1$d cannot be shipped with %2$s', 'colissimo-shipping-methods-for-woocommerce'), $id, $this->actions[$action]['name'])
                );
                continue;
            }

            $orderShippingItems = $order->get_items('shipping');
            $previouslyRelay    = false;
            if (!empty($orderShippingItems)) {
                // If the order already has the same shipping method, skip it
                foreach ($orderShippingItems as $oneItem) {
                    $methodId = $oneItem->get_method_id();
                    if ($methodId === $this->actions[$action]['id']) {
                        continue 2;
                    }

                    if (Relay::ID === $methodId) {
                        $previouslyRelay = true;
                    }
                }

                // Remove the old shipping method(s)
                foreach ($orderShippingItems as $oneItem) {
                    $order->remove_item($oneItem->get_id());
                }
            }

            if ($previouslyRelay) {
                $this->lpcAdminNotices->add_notice(
                    'shipment_change',
                    'notice-warning',
                    sprintf(
                    // translators: %d is the order ID.
                        __('The order #%d was made to be shipped to a relay point, make sure to change its shipping address!', 'colissimo-shipping-methods-for-woocommerce'),
                        $id
                    )
                );
            }

            // Add the new shipping method
            $item = new WC_Order_Item_Shipping();
            $item->set_props(
                [
                    'method_title' => $this->actions[$action]['name'],
                    'method_id'    => $this->actions[$action]['id'],
                    'total'        => $orderShippingTotal,
                    'taxes'        => $orderShippingTax,
                ]
            );
            $order->add_item($item);
            // translators: %s is the shipping method name.
            $order->add_order_note(sprintf(__('Shipping method changed to %s with bulk edit.', 'colissimo-shipping-methods-for-woocommerce'), $this->actions[$action]['name']));
            $order->save();
            $changed ++;
        }

        if (OrderQueries::isHposActive()) {
            $redirect_to = add_query_arg(
                [
                    'page'             => 'wc-orders',
                    'bulk_action'      => $action,
                    'changed'          => $changed,
                    '_lpc_bulk_notice' => wp_create_nonce('lpc_bulk_notice'),
                ],
                $redirect_to
            );
        } else {
            $redirect_to = add_query_arg(
                [
                    'post_type'        => 'shop_order',
                    'bulk_action'      => $action,
                    'changed'          => $changed,
                    '_lpc_bulk_notice' => wp_create_nonce('lpc_bulk_notice'),
                ],
                $redirect_to
            );
        }

        return esc_url_raw($redirect_to);
    }

    public function bulk_admin_notices() {
        global $post_type, $pagenow;

        $noticeNonce = isset($_REQUEST['_lpc_bulk_notice']) ? sanitize_text_field(wp_unslash($_REQUEST['_lpc_bulk_notice'])) : '';
        $isLegacy    = 'edit.php' === $pagenow && 'shop_order' === $post_type;
        $isHpos      = 'admin.php' === $pagenow && isset($_REQUEST['page']) && 'wc-orders' === sanitize_text_field(wp_unslash($_REQUEST['page']));

        // Bail out if not on shop order list page or if the notice nonce is invalid.
        if (
            !($isLegacy || $isHpos)
            || !wp_verify_nonce($noticeNonce, 'lpc_bulk_notice')
            || !isset($_REQUEST['bulk_action'])
            || empty($_REQUEST['changed'])
        ) {
            return;
        }

        $bulk_action = sanitize_text_field(wp_unslash($_REQUEST['bulk_action']));
        if (false === strpos($bulk_action, 'ship_lpc_')) {
            return;
        }

        $number  = absint($_REQUEST['changed']);
        $message = sprintf(
        // translators: %1$s is the shipping method name, %2$d is the number of orders.
            __('Shipping method changed to %1$s for %2$d orders.', 'colissimo-shipping-methods-for-woocommerce'),
            $this->actions[$bulk_action]['name'],
            number_format_i18n($number)
        );
        echo '<div class="updated"><p>' . esc_html($message) . '</p></div>';
    }
}
