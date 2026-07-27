<?php

namespace Colissimo\Classes\Product;

use Colissimo\Api\AccountApi;
use Colissimo\Helpers\Helper;
use Colissimo\Classes\Label\LabelGenerationPayload;
use Colissimo\Core\Register;

defined('ABSPATH') || die('Restricted Access');

class ProductCategory {
    /** @var AccountApi */
    private $accountApi;

    public function __construct(?AccountApi $accountApi = null) {
        $this->accountApi = Register::get('accountApi');
    }

    public function init() {
        add_action('product_cat_add_form_fields', [$this, 'addCategoryHazmatListing'], 11);
        add_action('product_cat_edit_form_fields', [$this, 'addCategoryHazmatEdition'], 11);
        add_action('created_term', [$this, 'saveCategoryHazmat'], 11, 3);
        add_action('edit_term', [$this, 'saveCategoryHazmat'], 11, 3);
    }

    public function addCategoryHazmatListing() {
        if (!$this->accountApi->isHazmatOptionActive()) {
            return;
        }
        ?>
		<div class="form-field term-lpc-hazmat-wrap">
			<label for="<?php echo esc_attr(LabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>">
                <?php esc_html_e('Default Colissimo hazmat category', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</label>
			<select name="<?php echo esc_attr(LabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>" id="<?php echo esc_attr(LabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>">
				<option value=""><?php esc_html_e('None', 'colissimo-shipping-methods-for-woocommerce'); ?></option>
                <?php foreach (LabelGenerationPayload::HAZMAT_CATEGORIES as $key => $category) { ?>
					<option value="<?php echo esc_attr($key); ?>">
                        <?php
                        // Cannot call __() in class constants
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                        esc_html_e($category['label'], 'colissimo-shipping-methods-for-woocommerce');
                        ?>
					</option>
                <?php } ?>
			</select>
		</div>
        <?php
    }

    public function addCategoryHazmatEdition($term) {
        if (!$this->accountApi->isHazmatOptionActive()) {
            return;
        }

        $selectedCategory = get_term_meta($term->term_id, LabelGenerationPayload::HAZMAT_ATTRIBUTE, true);
        ?>
		<tr class="form-field term-lpc-hazmat-wrap">
			<th scope="row">
				<label for="<?php echo esc_attr(LabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>">
                    <?php esc_html_e('Default Colissimo hazmat category', 'colissimo-shipping-methods-for-woocommerce'); ?>
				</label>
			</th>
			<td>
				<select name="<?php echo esc_attr(LabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>" id="<?php echo esc_attr(LabelGenerationPayload::HAZMAT_ATTRIBUTE); ?>">
					<option value=""><?php esc_html_e('None', 'colissimo-shipping-methods-for-woocommerce'); ?></option>
                    <?php foreach (LabelGenerationPayload::HAZMAT_CATEGORIES as $key => $category) { ?>
						<option value="<?php echo esc_attr($key); ?>" <?php selected($selectedCategory === $key); ?>>
                            <?php
                            // Cannot call __() in class constants
                            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                            esc_html_e($category['label'], 'colissimo-shipping-methods-for-woocommerce');
                            ?>
						</option>
                    <?php } ?>
				</select>
			</td>
		</tr>
        <?php
    }

    public function saveCategoryHazmat($termId, $termTaxonomyId = '', $taxonomySlug = '') {
        $category = Helper::getVar(LabelGenerationPayload::HAZMAT_ATTRIBUTE);
        if ('product_cat' !== $taxonomySlug || empty($category)) {
            return;
        }

        if ($this->accountApi->isHazmatOptionActive()) {
            update_term_meta($termId, LabelGenerationPayload::HAZMAT_ATTRIBUTE, $category);
        }
    }
}
