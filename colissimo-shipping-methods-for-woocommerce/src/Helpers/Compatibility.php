<?php

namespace Colissimo\Helpers;

use Colissimo\Core\Register;

defined('ABSPATH') || die('Restricted Access');

class Compatibility {
    public static function checkCDI() {
        if (is_plugin_active('colissimo-delivery-integration/colissimo-delivery-integration.php')) {
            register_activation_hook(
                LPC_MAIN_FILE,
                [self::class, 'displayErrorCDI']
            );

            add_action(
                'load-woocommerce_page_wc-settings',
                [self::class, 'displayErrorCDI']
            );

            add_action(
                'load-woocommerce_page_wc_colissimo_view',
                [self::class, 'displayErrorCDI']
            );
        }
    }

    public static function displayErrorCDI() {
        $lpc_admin_notices = Register::get('lpcAdminNotices');
        $lpc_admin_notices->add_notice(
            'cdi_warning',
            'notice-warning',
            __(
                'We found that Colissimo Delivery Integration plugin is installed on your website. This plugin have some compatibility issues with the official Colissimo plugin. To avoid issues, please deactivate Colissimo Delivery Integration.',
                'colissimo-shipping-methods-for-woocommerce'
            )
        );
    }

    public static function checkJQueryMigrate() {
        if (
            !is_plugin_active('enable-jquery-migrate-helper/enable-jquery-migrate-helper.php')
            && version_compare($GLOBALS['wp_version'], '5.6-alpha', '<')
            && version_compare($GLOBALS['wp_version'], '5.4.2', '>')
            && false == get_option('lpc_jquery_warning_dismissed_notice', false)
        ) {

            register_activation_hook(
                LPC_MAIN_FILE,
                [self::class, 'displayWarningJQueryMigrate']
            );

            add_action(
                'load-woocommerce_page_wc-settings',
                [self::class, 'displayWarningJQueryMigrate']
            );

            add_action(
                'load-woocommerce_page_wc_colissimo_view',
                [self::class, 'displayWarningJQueryMigrate']
            );

            add_action(
                'wp_ajax_lpc-dismiss-notice',
                [self::class, 'dismissWarningJQueryMigrate']
            );
        }
    }

    public static function displayWarningJQueryMigrate() {
        $lpc_admin_notices = Register::get('lpcAdminNotices');

        if (false !== $lpc_admin_notices->get_notice('jquery_warning')) {
            return;
        }

        $lpc_admin_notices->add_notice(
            'jquery_warning',
            'notice-warning',
            sprintf(
                // translators: %s is a link to the "Enable jQuery Migrate Helper" plugin page.
                __(
                    'Colissimo shipping methods for WooCommerce : Our plugin may cause some errors in your website with the new WordPress version 5.5. To fix them, please download the plugin "Enable jQuery Migrate Helper" provided by the WordPress team at the following address: %s',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
                '<a href="https://wordpress.org/plugins/enable-jquery-migrate-helper/">https://wordpress.org/plugins/enable-jquery-migrate-helper/</a>'
            )
        );
    }

    public static function dismissWarningJQueryMigrate() {
        if (empty($_POST['lpc-dismiss-notice-nonce']) || !current_user_can('manage_options')) {
            return;
        }

        if (
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['lpc-dismiss-notice-nonce'])),
                'lpc-jquery_warning-notice'
            )
        ) {
            return;
        }

        update_option('lpc_jquery_warning_dismissed_notice', true, false);
    }

    public static function checkJQueryMigrateWP56() {
        if (
            is_plugin_active('enable-jquery-migrate-helper/enable-jquery-migrate-helper.php')
            && version_compare($GLOBALS['wp_version'], '5.6-alpha', '>=') && false == get_option('lpc_jquery_migrate_wp56_dismissed_notice', false)
        ) {

            register_activation_hook(
                LPC_MAIN_FILE,
                [self::class, 'displayJQueryMigrateWP56']
            );

            add_action(
                'load-woocommerce_page_wc-settings',
                [self::class, 'displayJQueryMigrateWP56']
            );

            add_action(
                'load-woocommerce_page_wc_colissimo_view',
                [self::class, 'displayJQueryMigrateWP56']
            );

            add_action(
                'wp_ajax_lpc-dismiss-notice',
                [self::class, 'dismissJQueryMigrateWP56']
            );
        }
    }

    public static function displayJQueryMigrateWP56() {
        $lpc_admin_notices = Register::get('lpcAdminNotices');

        if (false !== $lpc_admin_notices->get_notice('jquery_migrate_wp56')) {
            return;
        }

        $lpc_admin_notices->add_notice(
            'jquery_migrate_wp56',
            'notice-info',
            sprintf(
                __(
                    'Colissimo shipping methods for WooCommerce : Our plugin is fully compatible with WordPress 5.6. If you have installed "Enable jQuery Migrate Helper" to use our plugin with WordPress 5.5, it\'s no longer required.',
                    'colissimo-shipping-methods-for-woocommerce'
                )
            )
        );
    }

    public static function dismissJQueryMigrateWP56() {
        if (empty($_POST['lpc-dismiss-notice-nonce']) || !current_user_can('manage_options')) {
            return;
        }

        if (!wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['lpc-dismiss-notice-nonce'])),
            'lpc-jquery_migrate_wp56-notice'
        )) {
            return;
        }

        update_option('lpc_jquery_migrate_wp56_dismissed_notice', true, false);
    }
}
