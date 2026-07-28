<?php
/**
 * Plugin Name: Colissimo shipping methods for WooCommerce
 * Description: This extension gives you the possibility to use the Colissimo shipping methods in WooCommerce
 * Version: 3.0.1
 * Author: Colissimo
 * Author URI: https://www.colissimo.entreprise.laposte.fr/fr
 *
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0.0
 * WC tested up to: 10.9.0
 *
 * @package colissimo-shipping-methods-for-woocommerce
 *
 * License: GNU General Public License v3.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Colissimo\Core\BlocksIntegration;
use Colissimo\Classes\Label\TrackingPage;
use Colissimo\Classes\Shipping\ExpertDdp;
use Colissimo\Classes\Shipping\SignDdp;
use Colissimo\Core\DbDefinition;
use Colissimo\Init\InitGlobal;
use Colissimo\Init\InitAdmin;
use Colissimo\Init\InitPublic;
use Colissimo\Core\Register;
use Colissimo\Core\Update;
use Colissimo\Helpers\Helper;
use Colissimo\Helpers\Compatibility;
use Colissimo\Helpers\OrderQueries;

defined('ABSPATH') || die('Restricted Access');

// Make sure WooCommerce is active before declaring the shipping methods
if (
    file_exists(__DIR__ . '/vendor_prefixed/autoload.php')
    && (
        file_exists(WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce' . DIRECTORY_SEPARATOR . 'woocommerce.php')
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional re-use of a documented WooCommerce/WordPress core hook.
        || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional re-use of a documented WooCommerce/WordPress core hook.
        || (is_multisite() && in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', array_keys(get_site_option('active_sitewide_plugins')))))
    )
) {
    include_once __DIR__ . '/vendor_prefixed/autoload.php';

    class LpcInit {
        public function __construct() {
            $this->constants();

            $register = new Register();

            new InitGlobal();

            if (defined('WP_ADMIN') && WP_ADMIN) {
                new InitAdmin();

                if (defined('DOING_AJAX') && DOING_AJAX) {
                    // needed for ajax calls from front
                    new InitPublic();
                }
            } else {
                new InitPublic();
            }

            $register->init();

            $this->initActivation();
            $this->initCapabilities();
            $this->register_rewrite_rules();
            $this->checkCompatibility();
            $this->checkCron();
            $this->handleDDP();
            $this->hposCompatibility();
            $this->initBlockCheckout();
            $this->initTranslations();
        }

        private function initActivation() {
            register_activation_hook(
                LPC_MAIN_FILE,
                function () {
                    $dbDefinition = new DbDefinition();
                    $dbDefinition->defineTableLabel();
                }
            );
        }

        public function initTranslations() {
            add_action('plugins_loaded', function () {
                if ('fr_FR' === get_locale()) {
                    load_textdomain('colissimo-shipping-methods-for-woocommerce', LPC_FOLDER . 'languages/colissimo-shipping-methods-for-woocommerce-fr_FR.mo');
                }
            });
        }

        public function initBlockCheckout() {
            add_action('woocommerce_blocks_loaded', function () {
                add_action(
                    'woocommerce_blocks_cart_block_registration',
                    function ($integration_registry) {
                        $integration_registry->register(new BlocksIntegration());
                    }
                );
                add_action(
                    'woocommerce_blocks_checkout_block_registration',
                    function ($integration_registry) {
                        $integration_registry->register(new BlocksIntegration());
                    }
                );
            });

            add_action('block_categories_all', [$this, 'registerLpcWcBlockBlockCategory'], 10, 2);
        }

        public function registerLpcWcBlockBlockCategory($categories) {
            return array_merge(
                $categories,
                [
                    [
                        'title' => 'LpcWcBlock Blocks',
                        'slug'  => 'lpc_wc_block',
                    ],
                ]
            );
        }

        private function handleDDP() {
            add_action('woocommerce_after_order_object_save', [$this, 'saveDdpInformation']);
        }

        /**
         * Only useful for a client that asked if we could record the DDP information in the order meta
         */
        public function saveDdpInformation($order) {
            $usingDdp = $order->get_meta('lpc_using_ddp');
            if (in_array($usingDdp, [0, 1])) {
                return;
            }

            $order->update_meta_data('lpc_using_ddp', 0);

            if (!OrderQueries::hasShippingMethod($order, ExpertDdp::ID) && !OrderQueries::hasShippingMethod($order, SignDdp::ID)) {
                $order->save();

                return;
            }

            $countryCode = strtoupper($order->get_shipping_country());
            $extraCost   = Helper::get_option('lpc_extracost_' . strtolower($countryCode), 0);

            $order->update_meta_data('lpc_ddp_cost', $extraCost);
            $order->update_meta_data('lpc_using_ddp', 1);
            $order->save();
        }

        public function initCapabilities() {
            register_activation_hook(
                LPC_MAIN_FILE,
                function () {
                    $lpcUpdate = new Update();
                    $lpcUpdate->createCapabilities();
                }
            );
        }

        protected function register_rewrite_rules() {
            add_action(
                'init',
                function () {
                    TrackingPage::addRewriteRule();
                }
            );

            register_deactivation_hook(LPC_MAIN_FILE, 'flush_rewrite_rules');
            register_activation_hook(
                LPC_MAIN_FILE,
                function () {
                    TrackingPage::addRewriteRule();

                    flush_rewrite_rules();
                }
            );
        }

        protected function checkCompatibility() {
            Compatibility::checkCDI();
            Compatibility::checkJQueryMigrate();
            Compatibility::checkJQueryMigrateWP56();
        }

        // Be sure that CRON task are running
        protected function checkCron() {
            if (!wp_next_scheduled('update_colissimo_statuses')) {
                wp_schedule_event(time(), 'hourly', 'update_colissimo_statuses');
            }
        }

        private function hposCompatibility() {
            add_action('before_woocommerce_init', function () {
                if (class_exists(FeaturesUtil::class)) {
                    FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                }
            });
        }

        private function constants() {
            if (!defined('DS')) {
                define('DS', DIRECTORY_SEPARATOR);
            }

            define('LPC_COMPONENT', basename(plugin_dir_path(__FILE__)));
            define('LPC_FOLDER', rtrim(plugin_dir_path(__FILE__), '\\/' . DS) . DS);
            define('LPC_MAIN_FILE', LPC_FOLDER . 'index.php');
            define('LPC_PARTIALS_FOLDER', LPC_FOLDER . 'src' . DS . 'Partials' . DS);
            define('LPC_RESOURCE_FOLDER', LPC_FOLDER . 'assets' . DS . 'misc' . DS);
            define('LPC_INCLUDES', LPC_FOLDER . 'includes' . DS);
            define('LPC_ADMIN', LPC_FOLDER . 'admin' . DS);
            define('LPC_PUBLIC', LPC_FOLDER . 'public' . DS);
            define('LPC_CONTACT_EMAIL', 'ecommerce@acyba.com');
            define('LPC_CONTACT_PHONE', '0241742088');

            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $pluginData = get_file_data(LPC_MAIN_FILE, ['version' => 'Version']);
            define('LPC_VERSION', $pluginData['version']);
        }
    }

    new LpcInit();
}
