<?php

namespace Colissimo\Classes\Product;

use Colissimo\Classes\Label\LabelGenerationPayload;
use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class Product {
    public function init() {
        add_action(
            'current_screen',
            function ($currentScreen) {
                if ('post' === $currentScreen->base && 'product' === $currentScreen->post_type) {
                    $terms = get_terms(
                        [
                            'taxonomy'   => 'pa_' . LabelGenerationPayload::HAZMAT_ATTRIBUTE,
                            'slug'       => array_keys(LabelGenerationPayload::HAZMAT_CATEGORIES),
                            'hide_empty' => false,
                        ]
                    );
                    if (is_wp_error($terms)) {
                        $terms = [];
                    }

                    $categories = [];
                    foreach ($terms as $term) {
                        $categories[$term->term_id] = LabelGenerationPayload::HAZMAT_CATEGORIES[$term->slug]['max_weight'];
                    }

                    $weightUnit = Helper::get_option('woocommerce_weight_unit', 'kg');

                    $weightToGrams = 1;
                    switch ($weightUnit) {
                        case 'kg':
                            $weightToGrams = 1000;
                            break;
                        case 'lbs':
                            $weightToGrams = 453.592;
                            break;
                        case 'oz':
                            $weightToGrams = 28.3495;
                            break;
                    }

                    Helper::enqueueScript(
                        'lpc_product',
                        Helper::getJsUrl('products/product.js'),
                        ['jquery-core'],
                        'lpcProduct',
                        [
                            'hazmat_attribute'       => LabelGenerationPayload::HAZMAT_ATTRIBUTE,
                            'hazmat_categories'      => $categories,
                            'weight_to_grams_factor' => $weightToGrams,
                            // translators: %1$s is the product weight in grams, %2$s is the allowed weight limit in grams.
                            'weight_limit_exceeded'  => __(
                                'This product\'s weight exceeds the limit allowed per parcel for this hazardous materials category: %1$sg / %2$sg',
                                'colissimo-shipping-methods-for-woocommerce'
                            ),
                        ]
                    );
                }
            }
        );
    }
}
