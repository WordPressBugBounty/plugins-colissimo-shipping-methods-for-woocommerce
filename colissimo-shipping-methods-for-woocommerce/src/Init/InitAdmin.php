<?php

namespace Colissimo\Init;

use Colissimo\Classes\Label\LabelQueries;
use Colissimo\Classes\Label\LabelPrintAction;
use Colissimo\Classes\Label\LabelPurge;
use Colissimo\Classes\Label\InwardGenerateAction;
use Colissimo\Classes\Label\InwardDeleteAction;
use Colissimo\Classes\Label\InwardDownloadAction;
use Colissimo\Classes\Label\PackagerDownloadAction;
use Colissimo\Classes\Label\OutwardDownloadAction;
use Colissimo\Classes\Label\OutwardDeleteAction;
use Colissimo\Classes\Label\OutwardGenerateAction;
use Colissimo\Classes\Label\OutwardImportAction;
use Colissimo\Classes\Label\QzTraySigning;
use Colissimo\Classes\Label\ThermalLabelPrintAction;
use Colissimo\Classes\Order\Banner;
use Colissimo\Classes\Order\AffectMethod;
use Colissimo\Classes\Order\Table;
use Colissimo\Classes\Order\TableAction;
use Colissimo\Classes\Order\TableBulkActions;
use Colissimo\Classes\Pickup\AdminWidget;
use Colissimo\Classes\Pickup\AdminWebService;
use Colissimo\Classes\Pickup\RelayOnOrder;
use Colissimo\Classes\Product\Product;
use Colissimo\Classes\Product\ProductCategory;
use Colissimo\Classes\Settings\Coupons;
use Colissimo\Classes\Settings\Download;
use Colissimo\Classes\Settings\ShippingRates;
use Colissimo\Classes\Settings\SettingsTab;
use Colissimo\Classes\Settings\SimulationAction;
use Colissimo\Classes\Slip\CreationTable;
use Colissimo\Classes\Slip\HistoryTable;
use Colissimo\Classes\Slip\SlipQueries;
use Colissimo\Classes\Slip\SlipDeleteAction;
use Colissimo\Classes\Slip\SlipDownloadAction;
use Colissimo\Classes\Slip\SlipPrintAction;
use Colissimo\Core\Modal;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;
use DateTime;
use LpcCapabilitiesFile;

defined('ABSPATH') || die('Restricted Access');

if (file_exists(LPC_FOLDER . 'tools' . DS . 'capabilities' . DS . 'lpc_capabilities_file.php')) {
    require_once LPC_FOLDER . 'tools' . DS . 'capabilities' . DS . 'lpc_capabilities_file.php';
}

class InitAdmin {
    const NONCE_DISMISS_FEEDBACK = '_lpc_dismiss';
    const NONCE_NAME_DISMISS_FEEDBACK = 'colissimo_dismiss';

    public function __construct() {
        // Add left menu
        add_action('admin_menu', [$this, 'add_menus'], 99);
        if (defined('LPC_DEV_MODE') && LPC_DEV_MODE) {
            add_action('admin_menu', [$this, 'add_dev_tool'], 99);
        }
        Register::register('settingsDownload', new Download());
        Register::register('settingsTab', new SettingsTab());
        Register::register('pickupRelayPointOnOrder', new RelayOnOrder());

        if ('widget' === Helper::get_option('lpc_pickup_map_type', 'widget')) {
            Register::register('adminPickupWidget', new AdminWidget());
        } else {
            Register::register('adminPickupWebService', new AdminWebService());
        }

        Register::register('labelPackagerDownloadAction', new PackagerDownloadAction());
        Register::register('labelInwardDownloadAction', new InwardDownloadAction());
        Register::register('labelOutwardDownloadAction', new OutwardDownloadAction());
        Register::register('labelPrintAction', new LabelPrintAction());
        Register::register('thermalLabelPrintAction', new ThermalLabelPrintAction());
        Register::register('qzTraySigning', new QzTraySigning());
        Register::register('bordereauDownloadAction', new SlipDownloadAction());
        Register::register('bordereauDeleteAction', new SlipDeleteAction());
        Register::register('bordereauPrintAction', new SlipPrintAction());
        Register::register('bordereauQueries', new SlipQueries());
        Register::register('labelOutwardDeleteAction', new OutwardDeleteAction());
        Register::register('labelInwardDeleteAction', new InwardDeleteAction());
        Register::register('lpcAdminOrderAffect', new AffectMethod());
        Register::register('LpcLabelOutwardGenerateAction', new OutwardGenerateAction());
        Register::register('LpcLabelInwardGenerateAction', new InwardGenerateAction());
        Register::register('labelQueries', new LabelQueries());
        Register::register('lpcAdminOrderBanner', new Banner());
        Register::register('labelOutwardImport', new OutwardImportAction());
        Register::register('LpcCouponsRestrictions', new Coupons());
        Register::register('wooOrdersTableAction', new TableAction());
        Register::register('wooOrdersTableBulkActions', new TableBulkActions());
        Register::register('shippingRates', new ShippingRates());
        Register::register('simulationAction', new SimulationAction());
        Register::register('LpcAdminProduct', new Product());
        Register::register('LpcAdminProductCategory', new ProductCategory());

        if (class_exists(LpcCapabilitiesFile::class)) {
            Register::register('capabilitiesDev', new LpcCapabilitiesFile());
        }

        Helper::enqueueScript('lpc_admin_notices', Helper::getJsUrl('admin_notices.js'), ['jquery-core']);

        add_action('admin_notices', [$this, 'lpc_notifications']);
        add_filter('set-screen-option', [$this, 'lpc_set_option'], 10, 3);
        add_action('woocommerce_settings_page_init', [$this, 'lpc_load_settings_script']);
        add_action('add_meta_boxes', [$this, 'lpc_add_meta_boxes']);
        add_filter('woocommerce_screen_ids', [$this, 'lpc_set_wc_screen_ids']);
        add_action('wp_ajax_lpc_feedback_dismissed', [$this, 'dismissFeedback']);
        add_action('woocommerce_page_wc-orders', [$this, 'showFeedbackModal']);
    }

    public function lpc_set_wc_screen_ids($screen) {
        $screen[] = 'woocommerce_page_wc_colissimo_view';

        return $screen;
    }

    /**
     * Add Colissimo sub-menu to WC in the WP left menu
     */
    public function add_menus() {
        $hook = add_submenu_page(
            'woocommerce',
            'Colissimo',
            'Colissimo',
            'lpc_colissimo_listing',
            'wc_colissimo_view',
            [$this, 'router']
        );

        add_action("load-$hook", [$this, 'lpc_load_orders_table']);
    }

    public function add_dev_tool() {
        if (!file_exists(LPC_FOLDER . 'tools' . DS . 'capabilities' . DS . 'lpc_capabilities_file.php')) {
            return;
        }
        $capabilitiesDev = new LpcCapabilitiesFile();

        $hook = add_submenu_page(
            'woocommerce',
            'Colissimo',
            'Devtool',
            'lpc_colissimo_listing',
            'wc_colissimo_devtool',
            [$capabilitiesDev, 'display']
        );
    }

    public function router() {
        $args = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing of a capability-gated admin page; navigation params carry no nonce and no state is changed here.
        $args['request'] = $_REQUEST;
        $args['tab']     = $args['request']['tab'] ?? 'orders';

        $this->askForFeedback();

        if ('orders' === $args['tab']) {
            $args['table'] = new Table();
            Helper::renderPartial('Order/Listing.php', $args);
        } elseif ('slip-creation' === $args['tab']) {
            $args['table_today'] = new CreationTable(true);
            $args['table_all']   = new CreationTable(false);
            Helper::renderPartial('Order/SlipCreation.php', $args);
        } elseif ('slip-history' === $args['tab']) {
            $args['table'] = new HistoryTable();
            Helper::renderPartial('Order/SlipHistory.php', $args);
        } elseif ('simulation' === $args['tab']) {
            $args['countries'] = WC()->countries->get_countries();
            Helper::renderPartial('Order/Simulation.php', $args);
        }
    }

    public function dismissFeedback() {
        if (1 === (int) check_ajax_referer(self::NONCE_NAME_DISMISS_FEEDBACK, self::NONCE_DISMISS_FEEDBACK, false)) {
            update_option('lpc_feedback_dismissed', true, false);
        }
    }

    public function showFeedbackModal() {
        $this->askForFeedback();
    }

    private function askForFeedback() {
        $deadline = new DateTime('2025-12-31');
        $now      = new DateTime();

        if ($now >= $deadline) {
            return;
        }

        $feedbackDismissed = Helper::get_option('lpc_feedback_dismissed', false);
        $lastAskedFeedback = Helper::get_option('lpc_asked_feedback', 0);

        if ($feedbackDismissed || (time() - $lastAskedFeedback) < 86400) {
            return;
        }

        update_option('lpc_asked_feedback', time(), false);

        // Get the number of labels generated
        $outwardLabelDb = Register::get('outwardLabelDb');
        $numberOfLabels = $outwardLabelDb->getNumberOfLabels();

        if (10 <= $numberOfLabels) {
            // Open a popup asking if the user wants to give feedback, with a dismiss button
            $content = '<div id="feedback_prompt_container">';
            $content .= '<div id="feedback_prompt_message">' . esc_html__('Would you like to help us improve our plugin by answering our questionnaire?',
                                                                          'colissimo-shipping-methods-for-woocommerce') . '</div>';
            $content .= '<button type="button" class="button-secondary" id="lpc-feedback-close-button">' . esc_html__('No, thanks',
                                                                                                                      'colissimo-shipping-methods-for-woocommerce') . '</button>';
            $formUrl = admin_url('admin.php?page=wc-settings&tab=lpc&section=feedback');
            $content .= '<a href="' . esc_url($formUrl) . '" class="button-primary">' . esc_html__('Sure, why not!', 'colissimo-shipping-methods-for-woocommerce') . '</a>';
            $content .= '</div>';

            $modal = new Modal($content, __('Plugin feedback', 'colissimo-shipping-methods-for-woocommerce'));
            $modal->loadScripts();
            $modal->open_modal('Feedback');
        }
    }

    public function lpc_notifications() {
        // Handle double admin_notices call with HPOS when saving an order
        if ('edit_order' === Helper::getVar('action')) {
            return;
        }

        $adminNotices = Register::get('lpcAdminNotices');
        $notices      = $adminNotices->get_notices(
            [
                'inward_label_sent',
                'outward_label_generate',
                'inward_label_generate',
                'cdi_warning',
                'outward_label_delete',
                'inward_label_delete',
                'label_migration',
                'jquery_warning',
                'jquery_migrate_wp56',
                'lpc_notice',
                'bordereau_delete',
                'insurance_unavailable_for_country',
                'shipment_change',
                'country_capabilities_import',
                'shipping_statuses_updated',
                'credentials_validity',
                'cgv_invalid',
                'deprecated_methods',
                'credentials_apikey',
                'credentials_account_number',
            ]
        );

        foreach ($notices as $notice_content) {
            echo wp_kses(
                $notice_content,
                [
                    'div'  => [
                        'class' => [],
                    ],
                    'p'    => [],
                    'br'   => [],
                    'a'    => [
                        'href'   => [],
                        'target' => [],
                    ],
                    'span' => [
                        'style' => [],
                    ],
                ]
            );
        }
    }

    public function lpc_load_orders_table() {
        // Add JS
        Helper::enqueueScript(
            'lpc_orders_table',
            Helper::getJsUrl('orders/listing.js'),
            ['jquery-core']
        );
        Helper::enqueueScript(
            'lpc_order_slip_creation',
            Helper::getJsUrl('orders/slip_creation.js'),
            ['jquery-core']
        );

        LabelQueries::enqueueLabelsActionsScript();

        // Add CSS
        Helper::enqueueStyle(
            'lpc_orders_table',
            Helper::getCssUrl('orders/listing.css')
        );
        Helper::enqueueStyle(
            'lpc_orders_slip_creation',
            Helper::getCssUrl('orders/slip_creation.css')
        );
        Helper::enqueueStyle(
            'lpc_slip_history',
            Helper::getCssUrl('orders/slip_history.css')
        );

        // Add screen options
        $option = 'per_page';

        $args = [
            'label'   => __('Orders per page', 'colissimo-shipping-methods-for-woocommerce'),
            'default' => 25,
            'option'  => 'lpc_orders_per_page',
        ];

        add_screen_option($option, $args);

        $adminNotices = Register::get('lpcAdminNotices');
        $accountApi   = Register::get('accountApi');
        if (!$accountApi->isCgvAccepted()) {
            $urls       = $accountApi->getAutologinURLs();
            $accountUrl = $urls['urlConnectedCbox'] ?? 'https://www.colissimo.entreprise.laposte.fr';
            $adminNotices->add_notice(
                'cgv_invalid',
                'notice-error',
                '<span style="color:red;font-weight: bold;">' .
                __(
                    'We have detected that you have not yet signed the latest version of our GTC. Your consent is necessary in order to continue using Colissimo services. We therefore invite you to sign them on your Colissimo entreprise space, by clicking on the link below:',
                    'colissimo-shipping-methods-for-woocommerce'
                ) . '<br/><a href="' . $accountUrl . '" target="_blank">' . __('Sign the GTC', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
                . '</span>'
            );
        }

        if (LabelPurge::getPurgeDelay() > 0) {
            if (!wp_next_scheduled('purge_colissimo_labels')) {
                wp_schedule_event(time(), 'daily', 'purge_colissimo_labels');
            }
        } elseif (wp_next_scheduled('purge_colissimo_labels')) {
            // The purge has been disabled from the settings, unschedule the event instead of leaving it running
            wp_clear_scheduled_hook('purge_colissimo_labels');
        }
    }

    public function lpc_set_option($status, $option, $value) {
        if ('lpc_orders_per_page' == $option) {
            return $value;
        }

        return $status;
    }

    public function lpc_load_settings_script() {
        if ('shipping' !== Helper::getVar('tab')) {
            return;
        }

        $instanceId = Helper::getVar('instance_id', 0, 'int');
        if (empty($instanceId)) {
            return;
        }

        $shippingRates = Register::get('shippingRates');

        Helper::enqueueStyle('lpc_styles', Helper::getCssUrl('shipping/rates.css'));
        Helper::enqueueScript(
            'lpc_shipping_rates',
            Helper::getJsUrl('shipping/rates.js'),
            ['jquery-core'],
            'lpcShippingRates',
            [
                'pleaseSelectFile'           => __('Please select a file', 'colissimo-shipping-methods-for-woocommerce'),
                'errorWhileImporting'        => __('Error while saving imported rates', 'colissimo-shipping-methods-for-woocommerce'),
                'defaultPricesConfirmation'  => __('Are you sure you want to replace the current prices with the default ones?', 'colissimo-shipping-methods-for-woocommerce'),
                'deleteRateConfirmation'     => __('Delete the selected rates?', 'colissimo-shipping-methods-for-woocommerce'),
                'deleteDiscountConfirmation' => __('Delete the selected discounts?', 'colissimo-shipping-methods-for-woocommerce'),
                'searchCategories'           => __('Search for categories', 'colissimo-shipping-methods-for-woocommerce'),
                'searchCategoriesAjaxUrl'    => $shippingRates->getUrlSearchCategories(),
            ]
        );
    }

    public function lpc_add_meta_boxes($post) {
        if (!current_user_can('lpc_colissimo_bandeau')) {
            return;
        }

        // Colissimo Banner
        $adminOrderBanner = Register::get('lpcAdminOrderBanner');

        $screenId = class_exists('Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController') && wc_get_container()
            ->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)
            ->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
        add_meta_box(
            'lpc_banner-box',
            '<img src="' . esc_url(Helper::getImageUrl('colissimo_cropped.png')) . '" height="25">',
            [$adminOrderBanner, 'bannerContent'],
            $screenId,
            'normal',
            'high',
            ['post' => $post]
        );
    }
}
