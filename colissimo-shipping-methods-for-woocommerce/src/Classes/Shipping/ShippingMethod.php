<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- WooCommerce is loaded, using their translation for a better match given context
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the plugin's own hook, correctly prefixed with "lpc_".

namespace Colissimo\Classes\Shipping;

use Colissimo\Api\PickupWidgetApi;
use Colissimo\Helpers\Helper;
use Colissimo\Classes\Label\LabelGenerationPayload;
use Colissimo\Core\Register;
use Colissimo\Helpers\Logger;
use WC_Coupon;
use WC_Shipping_Method;
use WC_Shipping;

defined('ABSPATH') || die('Restricted Access');

abstract class ShippingMethod extends WC_Shipping_Method {
    const LPC_ALL_PRODUCT_CATEGORIES_CODE = 'all';
    const LPC_ALL_SHIPPING_CLASS_CODE = 'all';
    const LPC_NO_SHIPPING_CLASS_CODE = 'none';
    const LPC_LAPOSTE_TRACKING_URL = 'https://www.laposte.fr/outils/suivre-vos-envois?code={lpc_tracking_number}';
    const CUSTOMS_CATEGORY_COMMERCIAL = 3;

    protected $lpcCapabilitiesPerCountry;

    /**
     * LpcAbstractShipping constructor.
     *
     * @param int $instance_id
     */
    public function __construct($instance_id = 0) {
        $this->instance_id = absint($instance_id);
        $this->supports    = [
            'shipping-zones',
            'instance-settings',
        ];

        $this->lpcCapabilitiesPerCountry = new CapabilitiesPerCountry();
        $this->init();
    }

    /**
     * This method is used to initialize the configuration fields' values
     */
    public function init() {
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title      = $this->get_option('title');
        $this->tax_status = $this->get_option('tax_status');
    }

    /**
     * This method allows you to define configuration fields shown in the shipping methdod's configuration page
     */
    public function init_form_fields() {
        $this->instance_form_fields = [
            'title'                                        => [
                'title'       => __('Title', 'colissimo-shipping-methods-for-woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'colissimo-shipping-methods-for-woocommerce'),
                'default'     => $this->method_title,
                'desc_tip'    => true,
            ],
            'tax_status'                                   => [
                'title'       => __('Tax status', 'woocommerce'),
                'description' => __('If a cost is defined, this controls if taxes are applied to that cost.', 'woocommerce'),
                'desc_tip'    => true,
                'type'        => 'select',
                'options'     => [
                    'taxable' => __('Taxable', 'woocommerce'),
                    'none'    => _x('None', 'Tax status', 'woocommerce'),
                ],
                'default'     => $this->get_option('tax_status', 'taxable'),
            ],
            'always_free'                                  => [
                'title'       => __('Always free?', 'colissimo-shipping-methods-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'If enabled, rates calculation for this shipping method will always be zero.',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
                'default'     => $this->get_option('always_free', 'no'),
                'desc_tip'    => true,
                'label'       => ' ',
            ],
            'title_free'                                   => [
                'title'       => __('Title if free', 'colissimo-shipping-methods-for-woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'This controls the title which the user sees during checkout if the shipping methods is free. Leave empty to always use standard title.',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
                'default'     => $this->get_option('title_free', ''),
                'desc_tip'    => true,
            ],
            'excluded_classes'                             => [
                'type' => 'classes_shipping',
            ],
            'classes_free_shipping'                        => [
                'type' => 'classes_shipping',
            ],
            'free_for_items_without_free_shipping_classes' => [
                'title'       => __('Free if at least one item in the cart has one of the free shipping classes above', 'colissimo-shipping-methods-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'If enabled, delivery will be free, even if the other items in the cart do not have one of the free shipping classes above',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
                'default'     => $this->get_option('free_for_items_without_free_shipping_classes', 'yes'),
                'desc_tip'    => true,
                'label'       => ' ',
            ],
            'shipping_rates'                               => [
                'type' => 'shipping_rates',
            ],
            'shipping_discount'                            => [
                'type' => 'shipping_discount',
            ],
        ];
    }

    public function generate_shipping_discount_html() {
        ob_start();
        Helper::renderPartial(
            'Shipping/DiscountsListing.php',
            [
                'shippingMethod' => $this,
            ]
        );

        return ob_get_clean();
    }

    public function generate_shipping_rates_html() {
        $shipping = new WC_Shipping();
        global $sitepress;
        if (!empty($sitepress)) {
            $removed = remove_filter('terms_clauses', [$sitepress, 'terms_clauses']);
        }
        $shippingClasses = $shipping->get_shipping_classes();
        array_unshift(
            $shippingClasses,
            (object) [
                'term_id' => self::LPC_NO_SHIPPING_CLASS_CODE,
                'name'    => __('No shipping class', 'colissimo-shipping-methods-for-woocommerce'),
            ]
        );
        if (!empty($sitepress) && $removed) {
            add_filter('terms_clauses', [$sitepress, 'terms_clauses'], 10, 3);
        }

        $shippingRates = Register::get('shippingRates');

        ob_start();
        Helper::renderPartial(
            'Shipping/RatesListing.php',
            [
                'shippingMethod'   => $this,
                'shippingClasses'  => $shippingClasses,
                'exportUrl'        => $shippingRates->getUrlExport($this->instance_id),
                'importUrl'        => $shippingRates->getUrlImport($this->instance_id),
                'importDefaultUrl' => $shippingRates->getUrlDefaultPrices($this->instance_id),
            ]
        );

        return ob_get_clean();
    }

    public function generate_classes_shipping_html($key) {
        $shipping = new WC_Shipping();
        global $sitepress;
        if (!empty($sitepress)) {
            $removed = remove_filter('terms_clauses', [$sitepress, 'terms_clauses']);
        }
        $shippingClasses = $shipping->get_shipping_classes();
        array_unshift(
            $shippingClasses,
            (object) [
                'term_id' => self::LPC_NO_SHIPPING_CLASS_CODE,
                'name'    => __('No shipping class', 'colissimo-shipping-methods-for-woocommerce'),
            ]
        );
        if (!empty($sitepress) && $removed) {
            add_filter('terms_clauses', [$sitepress, 'terms_clauses'], 10, 3);
        }
        $args = [];

        $args['values']   = $shippingClasses;
        $args['multiple'] = true;
        if ('classes_free_shipping' === $key) {
            $args['id_and_name']     = 'classes_free_shipping[]';
            $args['label']           = __('Free shipping classes', 'colissimo-shipping-methods-for-woocommerce');
            $args['selected_values'] = $this->get_option('classes_free_shipping', []);
            $args['description']     = __('These shipping classes qualify for free shipping', 'colissimo-shipping-methods-for-woocommerce');
        } else {
            $args['id_and_name']     = 'excluded_classes[]';
            $args['label']           = __('Excluded shipping classes', 'colissimo-shipping-methods-for-woocommerce');
            $args['selected_values'] = $this->get_option('excluded_classes', []);
            $args['description']     = __(
                'The current shipping method will not be displayed if one product in the cart has one of these shipping classes. This option takes precedence over the option Free shipping classes',
                'colissimo-shipping-methods-for-woocommerce'
            );
        }

        ob_start();
        Helper::renderPartial('Shipping/ShippingClassesSelect.php', $args);

        return ob_get_clean();
    }

    public function validate_shipping_discount_field($key) {
        $result   = [];
        $postData = $this->get_post_data();
        if (empty($postData[$key])) {
            return $result;
        }

        return $postData[$key];
    }

    public function validate_classes_shipping_field($key) {
        $result   = [];
        $postData = $this->get_post_data();
        if (empty($postData[$key])) {
            return $result;
        }

        return $postData[$key];
    }

    /**
     * Called by WooCommerce when saving shipping method settings
     */
    public function validate_shipping_rates_field(string $key): array {
        $result   = [];
        $postData = $this->get_post_data();
        if (empty($postData[$key])) {
            return $result;
        }

        foreach ($postData[$key] as $rate) {
            $minWeight = (float) str_replace(',', '.', $rate['min_weight']);
            $maxWeight = (float) str_replace(',', '.', $rate['max_weight']);
            $minPrice  = (float) str_replace(',', '.', $rate['min_price']);
            $maxPrice  = (float) str_replace(',', '.', $rate['max_price']);

            $minWeight = max($minWeight, 0);
            $maxWeight = max($maxWeight, 0);
            $minPrice  = max($minPrice, 0);
            $maxPrice  = max($maxPrice, 0);

            $item = [
                'min_weight'       => $minWeight,
                'max_weight'       => empty($maxWeight) ? '' : $maxWeight,
                'min_price'        => $minPrice,
                'max_price'        => empty($maxPrice) ? '' : $maxPrice,
                'shipping_class'   => $rate['shipping_class'],
                'product_category' => $rate['product_category'],
                'price'            => (float) str_replace(',', '.', $rate['price']),
            ];

            $result[] = $item;
        }

        usort(
            $result,
            function ($a, $b) {
                $result = 0;

                if ($a['price'] > $b['price']) {
                    $result = 1;
                } else {
                    if ($a['price'] < $b['price']) {
                        $result = - 1;
                    }
                }

                return $result;
            }
        );

        return $result;
    }

    public function getRates() {
        $rates = $this->get_option('shipping_rates', []);

        array_walk(
            $rates,
            function (&$rate) {
                if (isset($rate['shipping_class']) && !is_array($rate['shipping_class'])) {
                    $rate['shipping_class'] = [$rate['shipping_class']];
                }
                $rate['shipping_class'] ??= [];
            }
        );

        return $rates;
    }

    public function getDiscounts() {
        return $this->get_option('shipping_discount', []);
    }

    public function getFreeShippingClasses() {
        return $this->get_option('classes_free_shipping', []);
    }

    public function getFreeForItemsWithoutFreeShippingClasses() {
        return $this->get_option('free_for_items_without_free_shipping_classes', 'no');
    }

    abstract public function freeFromOrderValue();

    public function calculate_shipping($package = []): void {
        if (!$this->isShippingAvailableForPackage($package)) {
            return;
        }

        // Get possible prices for this method
        $rates = $this->getRates();
        // Extract the information from this cart to find the correct price
        $cart = $this->analyzeCartContents();

        if (!$this->isShippingAllowedForCart($cart, $package)) {
            return;
        }

        // Find the correct price based on cart
        $cost = $this->getPriceMatchingCart($rates, $cart, $package);
        if (null === $cost) {
            return;
        }

        // Make sure the credentials are correct and the contract can use the pickup method
        if (!$this->isMethodAllowed()) {
            return;
        }

        // Free, extra costs, discounts, etc...
        $cost = $this->applyAllCostAdjustments($cost, $cart, $package);

        $this->registerRate($cost, $package);
    }

    private function isShippingAvailableForPackage(array $package): bool {
        return (
            $this->checkPickupAvailability()
            && $this->lpcCapabilitiesPerCountry->getInfoForDestination($package['destination']['country'], $this->id)
        );
    }

    private function analyzeCartContents(): array {
        $noshipProductsCount = Helper::get_option('lpc_calculate_shipping_with_noship_products', 'no') === 'yes';
        $cartContents        = WC()->cart->get_cart();

        $lineTotal             = 0;
        $lineTax               = 0;
        $lineSubTotal          = 0;
        $lineSubTax            = 0;
        $articleQuantity       = 0;
        $nbProductsToShip      = 0;
        $totalWeight           = 0;
        $productsDimensions    = [];
        $cartShippingClasses   = [];
        $cartProductCategories = [];
        $cartHazmatCategories  = [];
        $bundlesHandled        = [];

        foreach ($cartContents as $item) {
            if (empty($item['data'])) {
                continue;
            }

            $product         = $item['data'];
            $quantity        = (float) $item['quantity'];
            $articleQuantity += $quantity;

            $isWcBundleChild = false;
            if (!empty($item['bundled_by'])) {
                $bundlesHandled[] = $item['bundled_by'];
                $isWcBundleChild  = $this->isWcProductBundlesChild($item);

                // YITH Bundles take care of their included products in the container item
                if (!$isWcBundleChild) {
                    continue;
                }
            }

            $itemData = $this->extractItemData($item);
            if (null === $itemData) {
                continue;
            }

            if (!$itemData['isShippable']) {
                // WooCommerce Product Bundles children carry the real price even when the bundle is shipped assembled (children flagged as virtual)
                if ($noshipProductsCount || $isWcBundleChild) {
                    $lineTotal    += $itemData['line_total'];
                    $lineTax      += $itemData['line_tax'];
                    $lineSubTotal += $itemData['line_subtotal'];
                    $lineSubTax   += $itemData['line_subtotal_tax'];
                }

                continue;
            }

            $cartHazmatCategories = array_merge(
                $cartHazmatCategories,
                $this->getProductHazmatCategories($product, $itemData['categories'])
            );

            $lineTotal    += $itemData['line_total'];
            $lineTax      += $itemData['line_tax'];
            $lineSubTotal += $itemData['line_subtotal'];
            $lineSubTax   += $itemData['line_subtotal_tax'];

            $productsDimensions[]  = $itemData['dimensions'];
            $nbProductsToShip      += $quantity;
            $totalWeight           += $itemData['weight'];
            $cartShippingClasses[] = empty($itemData['shipping_class_id'])
                ? self::LPC_NO_SHIPPING_CLASS_CODE
                : $itemData['shipping_class_id'];

            if (!empty($itemData['categories'])) {
                $cartProductCategories[] = $itemData['categories'];
            }
        }

        // Don't count the bundle container entry as an article
        $articleQuantity -= count(array_unique($bundlesHandled));

        $cartShippingClasses = array_unique($cartShippingClasses);

        $packaging = Helper::getMatchingPackaging(
            $nbProductsToShip,
            $totalWeight,
            $productsDimensions
        );

        $cart = compact(
            'lineTotal',
            'lineTax',
            'lineSubTotal',
            'lineSubTax',
            'articleQuantity',
            'nbProductsToShip',
            'totalWeight',
            'productsDimensions',
            'cartShippingClasses',
            'cartProductCategories',
            'cartHazmatCategories',
            'packaging'
        );

        $cart['totalPrice'] = $this->computeTotalPrice($cart);

        return $cart;
    }

    private function extractItemData(array $item): ?array {
        // YITH Bundles take care of their included products, WooCommerce Product Bundles children carry their own price and weight
        if (!empty($item['bundled_by']) && !$this->isWcProductBundlesChild($item)) {
            return null;
        }

        $product = $item['data'];

        return [
            'line_total'        => $item['line_total'],
            'line_tax'          => $item['line_tax'],
            'line_subtotal'     => $item['line_subtotal'],
            'line_subtotal_tax' => $item['line_subtotal_tax'],
            'weight'            => (float) $product->get_weight() * $item['quantity'],
            'dimensions'        => [$product->get_length(), $product->get_width(), $product->get_height()],
            'shipping_class_id' => $product->get_shipping_class_id(),
            'categories'        => ('variation' === $product->get_type())
                ? wc_get_product_term_ids($product->get_parent_id(), 'product_cat')
                : $product->get_category_ids('edit'),
            'isShippable'       => !is_callable([$product, 'needs_shipping']) || $product->needs_shipping(),
        ];
    }

    private function isWcProductBundlesChild(array $item): bool {
        if (empty($item['bundled_by']) || empty($item['bundled_item_id']) || !function_exists('wc_pb_get_bundled_cart_item_container')) {
            return false;
        }

        $container = wc_pb_get_bundled_cart_item_container($item);

        return !empty($container['data']) && is_a($container['data'], 'WC_Product') && 'bundle' === $container['data']->get_type();
    }

    private function isShippingAllowedForCart(array $cart, array $package): bool {
        if ($this->isCouponRestricted($package)) {
            return false;
        }

        $excludedClasses = $this->get_option('excluded_classes', []);
        if (!empty(array_intersect($excludedClasses, $cart['cartShippingClasses']))) {
            return false;
        }

        // DDP for GB must be commercial and between 160€ and 1050€
        $isCommercialSend = self::CUSTOMS_CATEGORY_COMMERCIAL === (int) Helper::get_option('lpc_customs_defaultCustomsCategory');
        if ('GB' === $package['destination']['country']
            && SignDdp::ID === $this->id
            && ($cart['totalPrice'] < 160 || $cart['totalPrice'] > 1050 || !$isCommercialSend)
        ) {
            return false;
        }

        return true;
    }

    private function computeTotalPrice(array $cart): float {
        if (Helper::get_option('lpc_calculate_shipping_before_taxes', 'no') === 'yes') {
            $totalPrice              = round($cart['lineTotal'], 2);
            $totalWithoutCouponPrice = round($cart['lineSubTotal'], 2);
        } else {
            $totalPrice              = round($cart['lineTax'] + $cart['lineTotal'], 2);
            $totalWithoutCouponPrice = round($cart['lineSubTax'] + $cart['lineSubTotal'], 2);
        }

        return 'yes' === Helper::get_option('lpc_calculate_shipping_before_coupon', 'no')
            ? $totalWithoutCouponPrice
            : $totalPrice;
    }

    private function computeTotalWeight(array $cart, array $package): float {
        $totalWeight = $cart['totalWeight'];
        $totalWeight += empty($cart['packaging'])
            ? Helper::get_option('lpc_packaging_weight', 0)
            : $cart['packaging']['weight'];

        /**
         * Filter on the package's total weight, before the checkout calculation
         *
         * @since 1.6.7
         */
        $totalWeight = (float) apply_filters('lpc_payload_letter_parcel_weight_checkout', $totalWeight, $package);

        return $totalWeight;
    }

    private function getPriceMatchingCart(array $rates, array $cart, array $package): ?float {
        $totalWeight = $this->computeTotalWeight($cart, $package);
        $cost        = null;

        // Current format
        $rateToChoose = Helper::get_option('lpc_choose_min_max_rate', 'lowest');

        foreach ($rates as $oneRate) {
            // All cart shipping classes must be specified in the rate
            $missingClasses = array_diff($cart['cartShippingClasses'], $oneRate['shipping_class']);
            if (!empty($missingClasses) && !in_array(self::LPC_ALL_SHIPPING_CLASS_CODE, $oneRate['shipping_class'])) {
                continue;
            }

            // All cart products must have at least 1 of their categories specified in the rate (product_category might not exist on older rates)
            if (!empty($oneRate['product_category'])) {
                foreach ($cart['cartProductCategories'] as $oneProductCategories) {
                    $matchingCategories = array_intersect($oneProductCategories, $oneRate['product_category']);
                    if (empty($matchingCategories) && !in_array(self::LPC_ALL_PRODUCT_CATEGORIES_CODE, $oneRate['product_category'])) {
                        continue 2;
                    }
                }
            }

            $weightMatches = $totalWeight >= ($oneRate['min_weight'] ?? 0) && (empty($oneRate['max_weight']) || $totalWeight < $oneRate['max_weight']);
            $priceMatches  = $cart['totalPrice'] >= ($oneRate['min_price'] ?? 0) && (empty($oneRate['max_price']) || $cart['totalPrice'] < $oneRate['max_price']);

            if (!$weightMatches || !$priceMatches) {
                continue;
            }

            if (null === $cost
                || ('lowest' === $rateToChoose && $oneRate['price'] < $cost)
                || ('highest' === $rateToChoose && $oneRate['price'] > $cost)
            ) {
                $cost = $oneRate['price'];
            }
        }

        return $cost;
    }

    private function isMethodAllowed(): bool {
        if (Relay::ID !== $this->id) {
            return true;
        }

        $pickupWidgetApi = new PickupWidgetApi();
        $mapDisplayToken = $pickupWidgetApi->authenticate();

        if (empty($mapDisplayToken)) {
            Logger::error('Pickup method unavailable: incorrect credentials');

            return false;
        }

        return true;
    }

    private function applyAllCostAdjustments(float $cost, array $cart, array $package): float {
        $cost = $this->applyFreeShipping($cost, $cart, $package);
        $cost = $this->applyExtraCosts($cost, $package);
        $cost = $this->applyDiscount($cost, $cart);

        // We add it after any discount as it is a fixed cost
        $cost = $this->applyFixedExtraCosts($cost, $cart, $package);

        return $this->applyHazmatExtraCost($cost, $cart);
    }

    private function applyFreeShipping(float $cost, array $cart, array $package): float {
        $classesFreeShipping     = $this->getFreeShippingClasses();
        $isClassesFreeShipping   = !empty(array_intersect($classesFreeShipping, $cart['cartShippingClasses']));
        $isMethodFreeForAllItems = $this->getFreeForItemsWithoutFreeShippingClasses();
        $areOtherPayingClasses   = !empty(array_diff($cart['cartShippingClasses'], $classesFreeShipping));
        $freeFromOrderValue      = $this->freeFromOrderValue();
        $isCouponFreeShipping    = $this->hasFreeShippingCoupon($package);

        $isFree = (
            'yes' === $this->get_option('always_free')
            || ($freeFromOrderValue > 0 && $cart['totalPrice'] >= $freeFromOrderValue)
            || $isCouponFreeShipping
            || ($isClassesFreeShipping && (!$areOtherPayingClasses || 'yes' === $isMethodFreeForAllItems))
        );

        return $isFree ? 0.0 : $cost;
    }

    private function hasFreeShippingCoupon(array $package): bool {
        foreach ($package['applied_coupons'] as $oneCouponCode) {
            $coupon = new WC_Coupon($oneCouponCode);
            if ($coupon->get_free_shipping()) {
                return true;
            }
        }

        return false;
    }

    private function isCouponRestricted(array $package): bool {
        foreach ($package['applied_coupons'] as $oneCouponCode) {
            $coupon            = new WC_Coupon($oneCouponCode);
            $couponRestriction = $coupon->get_meta('lpc_coupon_restriction');
            if (!empty($couponRestriction) && in_array($this->id, $couponRestriction)) {
                return true;
            }
        }

        return false;
    }

    private function applyExtraCosts(float $cost, array $package): float {
        $countryCode = $package['destination']['country'];
        $extraCost   = 0;

        $ftdActive = Helper::get_option('lpc_customs_isFtd') === 'yes';
        if ($ftdActive && in_array($countryCode, LabelGenerationPayload::COUNTRIES_FTD)) {
            $extraCost = Helper::get_option('lpc_extracost_outremer', 0);
        }

        if (false !== strpos($this->id, '_ddp')) {
            $extraCost = Helper::get_option('lpc_extracost_' . strtolower($countryCode), 0);
        }

        return $cost + (float) $extraCost;
    }

    private function applyDiscount(float $cost, array $cart): float {
        $discountToApply = 0.0;

        foreach ($this->getDiscounts() as $discount) {
            if ($discount['nb_product'] <= $cart['articleQuantity'] && $discountToApply < $discount['percentage']) {
                $discountToApply = (float) $discount['percentage'];
            }
        }

        if (empty($discountToApply)) {
            return $cost;
        }

        return $cost * (1 - $discountToApply * 0.01);
    }

    private function applyFixedExtraCosts(float $cost, array $cart, array $package): float {
        $extraCostFree = Helper::get_option('lpc_extra_cost_over_free', 'no');

        if (!empty($cost) || 'yes' === $extraCostFree) {
            $extraCost = Helper::get_option('lpc_extra_cost', 0);
            if (!empty($extraCost)) {
                $cost += $extraCost;
            }

            if (!empty($cart['packaging']['extra_cost'])) {
                $cost += $cart['packaging']['extra_cost'];
            }
        }

        return $cost;
    }

    private function applyHazmatExtraCost(float $cost, array $cart): float {
        $extraCostHazmat = Helper::get_option('lpc_hazmat_extra_cost_value');
        if (empty($extraCostHazmat) || empty($cart['cartHazmatCategories'])) {
            return $cost;
        }

        foreach ($cart['cartHazmatCategories'] as $hazmatCategorySlug) {
            if (empty(LabelGenerationPayload::HAZMAT_CATEGORIES[$hazmatCategorySlug])) {
                continue;
            }

            $cost += (float) str_replace(',', '.', $extraCostHazmat);
            break;
        }

        return $cost;
    }

    private function registerRate(float $cost, array $package): void {
        $titleFree = $this->get_option('title_free', '');
        $label     = empty($cost) && !empty($titleFree) ? $titleFree : $this->title;
        // No other way to translate the shipping method title
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        $translatedLabel = __($label, 'colissimo-shipping-methods-for-woocommerce');

        $this->add_rate(
            [
                'id'      => $this->get_rate_id(),
                'label'   => $translatedLabel,
                'cost'    => $cost,
                'package' => $package,
            ]
        );
    }

    private function checkPickupAvailability(): bool {
        if (Relay::ID !== $this->id) {
            return true;
        }

        $testedCredentials = Helper::get_option('lpc_current_credentials_tested');
        if ($testedCredentials) {
            return (bool) Helper::get_option('lpc_current_credentials_valid', false);
        } else {
            $pickUpWidgetApi = Register::get('pickupWidgetApi');
            $token           = $pickUpWidgetApi->authenticate(true);

            update_option('lpc_current_credentials_tested', true);
            update_option('lpc_current_credentials_valid', !empty($token));

            return !empty($token);
        }
    }

    private function getProductHazmatCategories(object $product, array $productCategories): array {
        $attributesContainer = 'variation' === $product->get_type() ? wc_get_product($product->get_parent_id()) : $product;
        $attributes          = $attributesContainer->get_attributes();
        if (!empty($attributes['pa_' . LabelGenerationPayload::HAZMAT_ATTRIBUTE])) {
            return $attributes['pa_' . LabelGenerationPayload::HAZMAT_ATTRIBUTE]->get_slugs();
        }

        $hazmatCategories = [];
        foreach ($productCategories as $categoryId) {
            $hazmatCategories[] = get_term_meta($categoryId, LabelGenerationPayload::HAZMAT_ATTRIBUTE, true);
        }

        return $hazmatCategories;
    }

    /**
     * Methods called by the Chronopost plugin, they don't check if the method is one of their own
     */
    public function refresh_methods() {

    }

    public function isAvailableForContract(): bool {
        return false;
    }
}
