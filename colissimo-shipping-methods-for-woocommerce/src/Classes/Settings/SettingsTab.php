<?php

namespace Colissimo\Classes\Settings;

use Colissimo\Api\AccountApi;
use Colissimo\Classes\Order\OrderStatuses;
use Colissimo\Classes\Shipping\CapabilitiesPerCountry;
use Colissimo\Helpers\Logger;
use Colissimo\Helpers\Helper;
use DateTime;
use Exception;
use Colissimo\Classes\Shipping\Expert;
use Colissimo\Classes\Shipping\ExpertDdp;
use Colissimo\Core\Ajax;
use Colissimo\Core\Modal;
use Colissimo\Core\Register;
use WC_Admin_Settings;
use WC_Countries;
use WC_Shipping_Zones;

defined('ABSPATH') || die('Restricted Access');

/**
 * To handle Colissimo tab in WooCommerce settings
 */
class SettingsTab {
    const LPC_SETTINGS_TAB_ID = 'lpc';
    const AJAX_TASK_REFRESH_LOGS = 'logs/refresh';

    /**
     * @var array Options available
     */
    protected $configOptions;

    /** @var AdminNotices */
    protected $adminNotices;
    /** @var AccountApi */
    private $accountApi;
    /** @var Download */
    private $settingsDownload;
    /** @var Ajax */
    private $ajaxDispatcher;

    public function __construct(
        ?AdminNotices $adminNotices = null,
        ?AccountApi $accountApi = null,
        ?Download $settingsDownload = null
    ) {
        $this->adminNotices     = Register::get('lpcAdminNotices');
        $this->accountApi       = Register::get('accountApi');
        $this->settingsDownload = Register::get('settingsDownload');
        $this->ajaxDispatcher   = Register::get('ajaxDispatcher');
    }

    public function init() {
        $this->initSettingsPage();
        $this->initWarningMessages();
        $this->initOnboarding();
        $this->initSeeLog();
        $this->initMailto();
        $this->initTelsupport();
        $this->initMultiSelectOrderStatus();
        $this->initSelectOrderStatusOnLabelGenerated();
        $this->initSelectOrderStatusOnPackageDelivered();
        $this->initSelectOrderStatusOnBordereauGenerated();
        $this->initSelectOrderStatusPartialExpedition();
        $this->initSelectOrderStatusDelivered();
        $this->initDisplayCredentials();
        $this->initDisplayCBox();
        $this->initDisplayContractInformation();
        $this->initDisplayColissimoProducts();
        $this->initDisplayHazmatDescription();
        $this->initDisplayNumberInputWithWeightUnit();
        $this->initDisplaySelectAddressCountry();
        $this->initCheckStatus();
        $this->initDocumentation();
        $this->initDefaultCountry();
        $this->initBlockCode();
        $this->initSecuredReturn();
        $this->fixSavePassword();
        $this->initVideoTutorials();
        $this->initAdvancedPackaging();
        $this->initFeedback();
        $this->iniMidCode();
        $this->initShippingDate();
    }

    private function initSettingsPage() {
        // Add configuration tab in Woocommerce
        add_filter('woocommerce_settings_tabs_array', [$this, 'configurationTab'], 70);
        // Add configuration tab content
        add_action('woocommerce_settings_tabs_' . self::LPC_SETTINGS_TAB_ID, [$this, 'settingsPage']);
        // Save settings page
        add_action('woocommerce_update_options_' . self::LPC_SETTINGS_TAB_ID, [$this, 'saveLpcSettings']);
        // Settings tabs
        add_action('woocommerce_sections_' . self::LPC_SETTINGS_TAB_ID, [$this, 'settingsSections']);

        add_action('woocommerce_admin_field_lpc_text', [$this, 'displayTextField']);
    }

    private function initWarningMessages() {
        // Invalid weight warning
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningPackagingWeight']);
        // Invalid credentials warning
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningCredentials']);
        // DIVI breaking the pickup map in widget mode
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningDivi']);
        // CGV not accepted warning
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningCgv']);
        // Warn about deprecated shipping methods
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningDeprecatedMethods']);
    }

    protected function initVideoTutorials() {
        add_action('woocommerce_admin_field_videotutorials', [$this, 'displayVideoTutorials']);
    }

    protected function initFeedback() {
        add_action('woocommerce_admin_field_feedback', [$this, 'displayFeedback']);
    }

    protected function iniMidCode() {
        add_action('woocommerce_admin_field_lpc_mid', [$this, 'displayMidCode']);
    }

    protected function initShippingDate() {
        add_action('woocommerce_admin_field_lpc_deposit_location', [$this, 'displayDepositLocation']);
        add_action('woocommerce_admin_field_lpc_cuttoff', [$this, 'displayShippingDate']);
        add_action('woocommerce_admin_field_lpc_date_format', [$this, 'displayDateFormat']);
    }

    protected function fixSavePassword() {
        add_filter('woocommerce_admin_settings_sanitize_option_lpc_pwd_webservices', [$this, 'encryptPassword'], 10, 3);
    }

    protected function initOnboarding() {
        add_action('woocommerce_admin_field_onboarding', [$this, 'displayOnboarding']);
    }

    protected function initSeeLog() {
        add_action('woocommerce_admin_field_lpcmodal', [$this, 'displayModalButton']);
        $this->ajaxDispatcher->register(self::AJAX_TASK_REFRESH_LOGS, [$this, 'refreshLogs']);
    }

    protected function initMailto() {
        add_action('woocommerce_admin_field_mailto', [$this, 'displayMailtoButton']);
    }

    protected function initTelsupport() {
        add_action('woocommerce_admin_field_telsupport', [$this, 'displayPhoneSupport']);
    }

    protected function initCheckStatus() {
        add_action('woocommerce_admin_field_lpcstatus', [$this, 'displayStatusLink']);
    }

    protected function initDocumentation() {
        add_action('woocommerce_admin_field_lpcdoc', [$this, 'displayDocumentation']);
    }

    protected function initMultiSelectOrderStatus() {
        add_action('woocommerce_admin_field_multiselectorderstatus', [$this, 'displayMultiSelectOrderStatus']);
    }

    protected function initSelectOrderStatusOnLabelGenerated() {
        add_action(
            'woocommerce_admin_field_selectorderstatusonlabelgenerated',
            [$this, 'displaySelectOrderStatusOnLabelGenerated']
        );
    }

    protected function initSelectOrderStatusOnPackageDelivered() {
        add_action(
            'woocommerce_admin_field_selectorderstatusonpackagedelivered',
            [$this, 'displaySelectOrderStatusOnPackageDelivered']
        );
    }

    protected function initSelectOrderStatusOnBordereauGenerated() {
        add_action(
            'woocommerce_admin_field_selectorderstatusonbordereaugenerated',
            [$this, 'displaySelectOrderStatusOnBordereauGenerated']
        );
    }

    protected function initSelectOrderStatusPartialExpedition() {
        add_action(
            'woocommerce_admin_field_selectorderstatuspartialexpedition',
            [$this, 'displaySelectOrderStatusPartialExpedition']
        );
    }

    protected function initSelectOrderStatusDelivered() {
        add_action(
            'woocommerce_admin_field_selectorderstatusdelivered',
            [$this, 'displaySelectOrderStatusDelivered']
        );
    }

    protected function initDisplayNumberInputWithWeightUnit() {
        add_action(
            'woocommerce_admin_field_numberinputwithweightunit',
            [$this, 'displayNumberInputWithWeightUnit']
        );
    }

    protected function initDisplaySelectAddressCountry() {
        add_action(
            'woocommerce_admin_field_addressCountry',
            [$this, 'displaySelectAddressCountry']
        );
    }

    protected function initDisplayCredentials() {
        add_action(
            'woocommerce_admin_field_lpcCredentials',
            [$this, 'displayCredentials']
        );
    }

    protected function initDisplayCBox() {
        add_action(
            'woocommerce_admin_field_lpc_cbox',
            [$this, 'displayCBox']
        );
    }

    protected function initDisplayContractInformation() {
        add_action(
            'woocommerce_admin_field_lpc_contract_information',
            [$this, 'displayContractInformation']
        );
    }

    protected function initDisplayColissimoProducts() {
        add_action(
            'woocommerce_admin_field_lpc_products',
            [$this, 'displayColissimoProducts']
        );
    }

    protected function initDisplayHazmatDescription() {
        add_action(
            'woocommerce_admin_field_lpc_hazmat',
            [$this, 'displayHazmatDescription']
        );
    }

    protected function initDefaultCountry() {
        add_action(
            'woocommerce_admin_field_defaultcountry',
            [$this, 'defaultCountry']
        );
    }

    protected function initAdvancedPackaging() {
        add_action(
            'woocommerce_admin_field_lpc_packaging_advanced',
            [$this, 'displayAdvancedPackaging']
        );
        add_action('wp_ajax_lpc_new_packaging', [$this, 'saveNewPackaging']);
        add_action('wp_ajax_lpc_switch_packagings', [$this, 'switchPackagings']);
        add_action('wp_ajax_lpc_delete_packagings', [$this, 'deletePackagings']);
    }

    public function encryptPassword($value, $option, $rawValue) {
        // password is already encrypt if not changed, so we don't touch it
        if (Helper::get_option('lpc_pwd_webservices') === $rawValue) {
            return $rawValue;
        }

        return Helper::encryptPassword($rawValue);
    }

    public function displayOnboarding($field) {
        wp_register_style('lpc_onboarding', Helper::getCssUrl('settings/home.css'), [], LPC_VERSION);
        wp_enqueue_style('lpc_onboarding');

        $urls = $this->accountApi->getAutologinURLs();
        $args = [
            'contractTypes' => $urls['urlContrats'] ?? 'https://www.colissimo.entreprise.laposte.fr/nos-contrats',
            'faciliteForm'  => $urls['urlInscription'] ?? 'https://www.colissimo.entreprise.laposte.fr/contrat-facilite',
            'privilegeForm' => $urls['urlContact'] ?? 'https://www.colissimo.entreprise.laposte.fr/contact',
        ];
        Helper::renderPartial('Settings/Onboarding.php', $args);
    }

    /**
     * Define the "lpcmodal" field type for the main configuration page
     *
     * @param object $field containing parameters defined in the config_options.json
     */
    public function displayModalButton($field) {
        wp_register_style('lpc_settings_support', Helper::getCssUrl('settings/support.css'), [], LPC_VERSION);
        wp_enqueue_style('lpc_settings_support');
        if ('hooks' === $field['content']) {
            $modalContent = file_get_contents(LPC_RESOURCE_FOLDER . 'hooksDescriptions.php');
        } else {
            $modalContent = '<button type="button" class="button" id="lpc_logs_refresh">' . esc_html__('Refresh', 'colissimo-shipping-methods-for-woocommerce') . '</button>';
            $modalContent .= '<a class="button" id="colissimo_settings_logs_download_link" href="' . esc_url($this->settingsDownload->getUrl('logs')) . '">'
                             . esc_html__('Download logs', 'colissimo-shipping-methods-for-woocommerce') . '</a>';
            $modalContent .= '<pre id="lpc_logs_content">' . Logger::get_logs() . '</pre>';

            wp_register_script('lpc_settings_support', Helper::getJsUrl('settings/support.js'), ['jquery'], LPC_VERSION, true);
            wp_localize_script(
                'lpc_settings_support',
                'lpcSupportLogs',
                ['ajaxURL' => $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_REFRESH_LOGS)]
            );
            wp_enqueue_script('lpc_settings_support');
        }
        // Dynamic method
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        $modal = new Modal($modalContent, __($field['title'], 'colissimo-shipping-methods-for-woocommerce'), 'lpc-' . $field['content']);
        $modal->loadScripts();
        Helper::renderPartial(
            'Settings/Debug.php',
            [
                'title' => $field['title'],
                'type'  => sanitize_title($field['type']),
                // Dynamic method
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                'text'  => __($field['text'], 'colissimo-shipping-methods-for-woocommerce'),
                'modal' => $modal,
            ]
        );
    }

    public function refreshLogs() {
        return $this->ajaxDispatcher->makeSuccess(
            [
                'logs' => wp_kses(
                    Logger::get_logs(),
                    Helper::getModalAllowedHtml()
                ),
            ]
        );
    }

    public function displayMailtoButton($field) {
        Helper::renderPartial(
            'Settings/Contact.php',
            [
                'title' => $field['title'],
                'type'  => sanitize_title($field['type']),
                'text'  => $field['text'],
                'email' => $field['email'] ?? LPC_CONTACT_EMAIL,
            ]
        );
    }

    public function displayPhoneSupport($field) {
        Helper::renderPartial(
            'Settings/PhoneSupport.php',
            [
                'title' => $field['title'],
                'type'  => sanitize_title($field['type']),
                'phone' => LPC_CONTACT_PHONE,
            ]
        );
    }

    public function displayStatusLink($field) {
        Helper::renderPartial(
            'Settings/Status.php',
            [
                'title' => $field['title'],
                'type'  => sanitize_title($field['type']),
                'text'  => $field['text'],
            ]
        );
    }

    public function displayDocumentation($field) {
        Helper::renderPartial(
            'Settings/Doc.php',
            [
                'title'         => $field['title'],
                'type'          => sanitize_title($field['type']),
                'downloadUrl'   => $this->settingsDownload->getUrl('doc'),
                'downloadUrlEN' => $this->settingsDownload->getUrl('docEN'),
            ]
        );
    }

    public function displayMultiSelectOrderStatus() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_generate_label_on';
        $args['label']           = 'Generate label on';
        $args['values']          = array_merge(['disable' => __('Disable', 'colissimo-shipping-methods-for-woocommerce')], wc_get_order_statuses());
        $args['selected_values'] = get_option($args['id_and_name']);
        $args['multiple']        = true;
        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displaySelectOrderStatusOnLabelGenerated() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_order_status_on_label_generated';
        $args['label']           = 'Order status once label is generated';
        $args['values']          = array_merge(
            ['unchanged_order_status' => __('Keep order status as it is', 'colissimo-shipping-methods-for-woocommerce')],
            wc_get_order_statuses()
        );
        $args['selected_values'] = get_option($args['id_and_name']);
        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displaySelectOrderStatusOnPackageDelivered() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_order_status_on_package_delivered';
        $args['label']           = 'Order status once the package is delivered';
        $args['values']          = wc_get_order_statuses();
        $args['selected_values'] = get_option($args['id_and_name']);
        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displaySelectOrderStatusOnBordereauGenerated() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_order_status_on_bordereau_generated';
        $args['label']           = 'Order status once bordereau is generated';
        $args['values']          = array_merge(
            ['unchanged_order_status' => __('Keep order status as it is', 'colissimo-shipping-methods-for-woocommerce')],
            wc_get_order_statuses()
        );
        $args['selected_values'] = get_option($args['id_and_name']);
        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displaySelectOrderStatusPartialExpedition() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_status_on_partial_expedition';
        $args['label']           = 'Order status when order is partially shipped';
        $args['values']          = array_merge(
            ['unchanged_order_status' => __('Keep order status as it is', 'colissimo-shipping-methods-for-woocommerce')],
            wc_get_order_statuses()
        );
        $args['selected_values'] = get_option($args['id_and_name'], 'wc-lpc_partial_expedition');
        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displaySelectOrderStatusDelivered() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_status_on_delivered';
        $args['label']           = 'Order status when order is delivered';
        $args['values']          = array_merge(
            ['unchanged_order_status' => __('Keep order status as it is', 'colissimo-shipping-methods-for-woocommerce')],
            wc_get_order_statuses()
        );
        $args['selected_values'] = get_option($args['id_and_name'], OrderStatuses::WC_LPC_DELIVERED);
        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displayNumberInputWithWeightUnit() {
        $args                = [];
        $args['id_and_name'] = 'lpc_packaging_weight';
        $args['label']       = 'Default packaging weight (%s)';
        $args['value']       = get_option($args['id_and_name']);
        $args['desc']        = __('The packaging weight will be added to the products weight on label generation.', 'colissimo-shipping-methods-for-woocommerce');
        Helper::renderPartial('Settings/NumberInputWeight.php', $args);
    }

    public function defaultCountry($defaultArgs) {
        $args           = [];
        $countries_obj  = new WC_Countries();
        $args['values'] = $countries_obj->__get('countries');

        $value = Helper::get_option('lpc_default_country_for_product', '');

        $args['id_and_name']     = 'lpc_default_country_for_product';
        $args['label']           = $defaultArgs['title'];
        $args['desc']            = $defaultArgs['desc'];
        $args['selected_values'] = $value;

        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displaySelectAddressCountry($defaultArgs) {
        $args          = [];
        $countries_obj = new WC_Countries();
        $countries     = $countries_obj->__get('countries');

        $countryCodes = array_merge(CapabilitiesPerCountry::DOM1_COUNTRIES_CODE, CapabilitiesPerCountry::FRANCE_COUNTRIES_CODE);

        $args['values'][''] = '---';

        foreach ($countries as $countryCode => $countryName) {
            if (in_array($countryCode, $countryCodes)) {
                $args['values'][$countryCode] = $countryName;
            }
        }

        $value = Helper::get_option($defaultArgs['id'], '');
        if (empty($value)) {
            $value = '';
        }

        $args['id_and_name']     = $defaultArgs['id'];
        $args['label']           = $defaultArgs['title'];
        $args['desc']            = $defaultArgs['desc'];
        $args['selected_values'] = $value;

        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displayCredentials() {
        $args = [
            'id_and_name'     => 'lpc_credentials_type',
            'label'           => 'Connection type',
            'selected_values' => Helper::get_option('lpc_credentials_type', 'api_key'),
            'values'          => [
                'account' => __('Colissimo account', 'colissimo-shipping-methods-for-woocommerce'),
                'api_key' => __('Application key', 'colissimo-shipping-methods-for-woocommerce'),
            ],
        ];

        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displayCBox() {
        $urls = $this->accountApi->getAutologinURLs();

        $args = [
            'type'         => 'lpc_cbox',
            'url'          => $urls['urlConnectedCbox'] ?? 'https://www.colissimo.entreprise.laposte.fr',
            'urlServices'  => $urls['urlParamServices'] ?? 'https://www.applications.colissimo.entreprise.laposte.fr/entreprise/mes-services/',
            'urlMaterials' => $urls['urlEtiquettesEtEmballages'] ?? '',
            'text'         => 'Access Colissimo Box',
            'class'        => '',
        ];

        Helper::renderPartial('Settings/Link.php', $args);
    }

    public function displayContractInformation() {
        $accountInformation = $this->accountApi->getAccountInformation();
        if (empty($accountInformation['contractType'])) {
            return;
        }

        $args = [
            'type'                           => 'lpc_contract_information',
            'title'                          => __('Your contract information', 'colissimo-shipping-methods-for-woocommerce'),
            'contractType'                   => ucfirst(strtolower($accountInformation['contractType'])),
            'outOfHomeContract'              => ucfirst(strtolower($accountInformation['statutHD'])),
            'pickupNeighborRelay'            => $accountInformation['statutPickme'] ? 'Activated' : 'Deactivated',
            'mimosa'                         => empty($accountInformation['mimosaSubscribed']) ? 'Deactivated' : 'Activated',
            'securedShipping'                => $accountInformation['statutCodeBloquant'] ? 'Activated' : 'Deactivated',
            'estimatedShippingDate'          => $accountInformation['statutTunnelCommande'] ? 'Activated' : 'Deactivated',
            'estimatedShippingDateDepotList' => empty($accountInformation['siteDepotList']) ? [] : $accountInformation['siteDepotList'],
            'securedReturn'                  => $accountInformation['optionRetourToken'] ? 'Activated' : 'Deactivated',
            'returnMailbox'                  => $accountInformation['optionRetourBAL'] ? 'Activated' : 'Deactivated',
            'returnPostOffice'               => $accountInformation['optionRetourBP'] ? 'Activated' : 'Deactivated',
            'hazmatCategories'               => $this->accountApi->getHazmatCategories(),
        ];

        wp_enqueue_style('lpc_settings_account', Helper::getCssUrl('settings/account_information.css'), [], LPC_VERSION);
        Helper::renderPartial('Settings/AccountInformation.php', $args);
    }

    public function displayColissimoProducts() {
        $urls = $this->accountApi->getAutologinURLs();
        if (empty($urls['urlEtiquettesEtEmballages'])) {
            return;
        }

        $args = [
            'url' => $urls['urlEtiquettesEtEmballages'],
        ];

        Helper::renderPartial('Settings/PackagingMaterials.php', $args);
    }

    public function displayHazmatDescription() {
        wp_enqueue_style('lpc_settings_hazmat', Helper::getCssUrl('settings/hazmat.css'), [], LPC_VERSION);

        Helper::renderPartial(
            'Settings/HazmatDescription.php',
            [
                'hazmatCategories' => $this->accountApi->getHazmatCategories(),
            ]
        );
    }

    public function displayVideoTutorials() {
        wp_register_style('lpc_settings_videos', Helper::getCssUrl('settings/videos.css'), [], LPC_VERSION);
        wp_enqueue_style('lpc_settings_videos');
        $args                = [];
        $args['id_and_name'] = 'lpc_video_tutorials';
        $args['label']       = 'Video tutorials';
        Helper::renderPartial('Settings/VideoTutorials.php', $args);
    }

    public function displayAdvancedPackaging() {
        wp_enqueue_style('lpc_settings_packaging', Helper::getCssUrl('settings/advanced_packaging.css'), [], LPC_VERSION);

        wp_enqueue_script(
            'lpc_settings_packagingjs',
            Helper::getJsUrl('settings/advanced_packaging.js'),
            ['jquery'],
            LPC_VERSION,
            true
        );
        wp_localize_script(
            'lpc_settings_packagingjs',
            'lpcSettingsPackaging',
            [
                'messageDeleteMultiple' => __('Are you sure you want to delete the selected packagings?', 'colissimo-shipping-methods-for-woocommerce'),
                'messageDeleteOne'      => __('Are you sure you want to delete this packaging?', 'colissimo-shipping-methods-for-woocommerce'),
                'messageMissingField'   => __('Please fill in mandatory fields', 'colissimo-shipping-methods-for-woocommerce'),
                'messageDimensions'     => __(
                    'The sum of the dimensions exceeds 120cm, this packaging will not be mechanizable and may be subject to an extra cost.',
                    'colissimo-shipping-methods-for-woocommerce'
                ),
            ]
        );

        $packagings = Helper::get_option('lpc_packagings', []);
        usort($packagings, fn($a, $b) => $a['priority'] > $b['priority'] ? 1 : - 1);

        $args = [
            'packagings' => $packagings,
            'weightUnit' => Helper::get_option('woocommerce_weight_unit', 'kg'),
        ];

        ob_start();
        Helper::renderPartial('Settings/NewPackaging.php', $args);
        $modalContent = ob_get_clean();
        $modal        = new Modal($modalContent, __('New packaging', 'colissimo-shipping-methods-for-woocommerce'), 'lpc-packaging');
        $modal->loadScripts();

        $args['modal'] = $modal;

        Helper::renderPartial('Settings/AdvancedPackaging.php', $args);
    }

    public function saveNewPackaging() {
        if (empty($_POST['nonce']) || !current_user_can('manage_options')) {
            Helper::endAjax(false, ['message' => __('Access denied! (Security check failed)', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'lpc_packaging_nonce')) {
            Helper::endAjax(false, ['message' => __('Access denied! (Security check failed)', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        $newPackageData = Helper::getVar('packageData', [], 'array');
        $identifier     = Helper::getVar('identifier', 0, 'int');
        $packagings     = Helper::get_option('lpc_packagings', []);

        $mandatoryProperties = ['name', 'weight', 'width', 'length', 'depth'];
        foreach ($mandatoryProperties as $property) {
            if (empty($newPackageData[$property])) {
                Helper::endAjax(false, ['message' => __('Please fill in mandatory fields', 'colissimo-shipping-methods-for-woocommerce')]);
            }
        }

        try {
            $newPackaging = [
                'name'         => (string) $newPackageData['name'],
                'weight'       => floatval($newPackageData['weight']),
                'width'        => floatval($newPackageData['width']),
                'length'       => floatval($newPackageData['length']),
                'depth'        => floatval($newPackageData['depth']),
                'max_weight'   => empty($newPackageData['max_weight']) ? 0 : floatval($newPackageData['max_weight']),
                'max_products' => empty($newPackageData['max_products']) ? 0 : intval($newPackageData['max_products']),
                'extra_cost'   => empty($newPackageData['extra_cost']) ? 0 : floatval($newPackageData['extra_cost']),
            ];

            if (!empty($identifier)) {
                $key = $this->getPackagingKey($packagings, $identifier);

                if (null === $key) {
                    Helper::endAjax(false, ['message' => __('Packaging not found.', 'colissimo-shipping-methods-for-woocommerce')]);
                }

                $newPackaging['identifier'] = $identifier;
                $newPackaging['priority']   = $packagings[$key]['priority'];

                $packagings[$key] = $newPackaging;
            } else {
                $newPackaging['identifier'] = time();
                $newPackaging['priority']   = empty($packagings) ? 1 : max(array_column($packagings, 'priority')) + 1;

                $packagings[] = $newPackaging;
            }

            update_option('lpc_packagings', $packagings, false);
        } catch (Exception $e) {
            Helper::endAjax(false, ['message' => $e->getMessage()]);
        }

        Helper::endAjax(true, ['newPackaging' => end($packagings)]);
    }

    public function switchPackagings() {
        if (empty($_POST['nonce']) || !current_user_can('manage_options')) {
            Helper::endAjax(false, ['message' => __('Access denied! (Security check failed)', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'lpc_packaging_nonce')) {
            Helper::endAjax(false, ['message' => __('Access denied! (Security check failed)', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        $firstPackagingData  = json_decode(Helper::getVar('firstPackaging', '{}'), true);
        $secondPackagingData = json_decode(Helper::getVar('secondPackaging', '{}'), true);
        $packagings          = Helper::get_option('lpc_packagings', []);

        $firstKey  = $this->getPackagingKey($packagings, $firstPackagingData['identifier']);
        $secondKey = $this->getPackagingKey($packagings, $secondPackagingData['identifier']);

        if (null === $firstKey || null === $secondKey) {
            Helper::endAjax(false, ['message' => __('Packaging not found.', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        $firstPackaging  = $packagings[$firstKey];
        $secondPackaging = $packagings[$secondKey];

        $packagings[$firstKey]['priority']  = $secondPackaging['priority'];
        $packagings[$secondKey]['priority'] = $firstPackaging['priority'];

        update_option('lpc_packagings', $packagings, false);

        Helper::endAjax();
    }

    public function deletePackagings() {
        if (empty($_POST['nonce']) || !current_user_can('manage_options')) {
            Helper::endAjax(false, ['message' => __('Access denied! (Security check failed)', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'lpc_packaging_nonce')) {
            Helper::endAjax(false, ['message' => __('Access denied! (Security check failed)', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        $packagings  = Helper::get_option('lpc_packagings', []);
        $identifiers = Helper::getVar('identifiers', [], 'int');

        foreach ($identifiers as $identifier) {
            $key = $this->getPackagingKey($packagings, $identifier);

            if (null === $key) {
                Helper::endAjax(false, ['message' => __('Packaging not found.', 'colissimo-shipping-methods-for-woocommerce')]);
            }

            unset($packagings[$key]);
        }

        update_option('lpc_packagings', $packagings, false);

        Helper::endAjax();
    }

    private function getPackagingKey($packagings, $identifier) {
        foreach ($packagings as $key => $onePackaging) {
            if ($onePackaging['identifier'] === $identifier) {
                return $key;
            }
        }

        return null;
    }

    public function displayFeedback() {
        $args                = [];
        $args['id_and_name'] = 'lpc_feedback';
        $args['label']       = 'Plugin feedback';
        update_option('lpc_feedback_dismissed', true, false);
        Helper::renderPartial('Settings/Feedback.php', $args);
    }

    public function displayMidCode() {
        $args                = [];
        $args['id_and_name'] = 'lpc_mid_code';
        $args['label']       = 'MID code for USA';
        $args['value']       = Helper::get_option('lpc_mid_code');
        $args['desc']        = 'The MID code is used by the USA customs to track where a product comes from. It is required and can be found by following <a href="https://www.fedex.com/en-fr/customer-support/faq/customs/customs-codes/how-to-generate-mid-code.html" target="_blank">the Fedex guide</a>.';
        Helper::renderPartial('Settings/InputText.php', $args);
    }

    public function displayDepositLocation() {
        $accountData = $this->accountApi->getAccountInformation();

        if (empty($accountData['statutTunnelCommande']) || empty($accountData['siteDepotList'])) {
            return;
        }

        $args = [
            'id_and_name'     => 'lpc_delivery_date_deposit_location',
            'row_class'       => 'wc-settings-row-lpc_delivery_date_container',
            'label'           => 'Deposit location',
            'tips'            => __('Select the location used to deposit your parcels.', 'colissimo-shipping-methods-for-woocommerce'),
            'values'          => array_combine(
                array_column($accountData['siteDepotList'], 'codeRegate'),
                array_column($accountData['siteDepotList'], 'libellepfc')
            ),
            'selected_values' => Helper::get_option('lpc_delivery_date_deposit_location'),
        ];

        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displayShippingDate() {
        wp_enqueue_style('lpc_settings_packaging', Helper::getCssUrl('settings/cutt_off.css'), [], LPC_VERSION);

        wp_enqueue_script(
            'lpc_settings_cuttoffjs',
            Helper::getJsUrl('settings/cutt_off.js'),
            ['jquery'],
            LPC_VERSION,
            true
        );
        wp_localize_script(
            'lpc_settings_cuttoffjs',
            'lpcSettingsCuttOff',
            [
                'messageDeleteMultiple' => __('Are you sure you want to delete the selected packagings?', 'colissimo-shipping-methods-for-woocommerce'),
            ]
        );

        $hours = [
            'none' => __('No shipment this day', 'colissimo-shipping-methods-for-woocommerce'),
        ];
        for ($i = 1; $i < 24; $i ++) {
            $hour      = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
            $hours[$i] = $hour;
        }

        $args = [
            'id_and_name' => 'lpc_delivery_date_cuttoff_times',
            'row_class'   => 'wc-settings-row-lpc_delivery_date_container',
            'values'      => Helper::get_option('lpc_delivery_date_cuttoff_times'),
            'days'        => Helper::DAYS,
            'hours'       => $hours,
        ];
        Helper::renderPartial('Settings/CuttOff.php', $args);
    }

    public function displayDateFormat() {
        $wpDateFormat = Helper::get_option('date_format', __('l, F j', 'colissimo-shipping-methods-for-woocommerce'));

        $args = [
            'id_and_name'     => 'lpc_delivery_date_format',
            'row_class'       => 'wc-settings-row-lpc_delivery_date_container',
            'label'           => 'Date format',
            'values'          => [
                // translators: %s is the current date formatted according to the WordPress date format setting.
                'default' => sprintf(__('WordPress settings (%s)', 'colissimo-shipping-methods-for-woocommerce'), Helper::translateDate(gmdate($wpDateFormat))),
                'full'    => Helper::translateDate(gmdate(__('l, F j', 'colissimo-shipping-methods-for-woocommerce'))),
                'simple'  => Helper::translateDate(gmdate(__('F j', 'colissimo-shipping-methods-for-woocommerce'))),
                'short'   => Helper::translateDate(gmdate(__('M j', 'colissimo-shipping-methods-for-woocommerce'))),
                'm/d/Y'   => gmdate('m/d/Y'),
                'd/m/Y'   => gmdate('d/m/Y'),
                'Y-m-d'   => gmdate('Y-m-d'),
            ],
            'selected_values' => Helper::get_option('lpc_delivery_date_format', 'default'),
        ];

        Helper::renderPartial('Settings/SelectField.php', $args);
    }

    public function displayTextField($field) {
        Helper::renderPartial('Settings/Text.php', $field);
    }

    /**
     * Build tab
     *
     * @param array $tab
     *
     * @return mixed
     */
    public function configurationTab($tab) {
        if (!current_user_can('lpc_manage_settings')) {
            return $tab;
        }

        $tab[self::LPC_SETTINGS_TAB_ID] = 'Colissimo Officiel';

        return $tab;
    }

    /**
     * Content of the configuration page
     */
    public function settingsPage() {
        if (empty($this->configOptions)) {
            $this->initConfigOptions();
        }

        $section = $this->getCurrentSection();
        if (!in_array($section, array_keys($this->configOptions))) {
            $section = 'home';
        }

        WC_Admin_Settings::output_fields($this->configOptions[$section]);

        // Load custom styles
        wp_register_style('lpc_settings_styles', Helper::getCssUrl('settings/settings.css'), [], LPC_VERSION);
        wp_enqueue_style('lpc_settings_styles');
    }

    /**
     * Tabs of the configuration page
     */
    public function settingsSections() {
        wp_enqueue_script(
            'lpc_settings_settingsjs',
            Helper::getJsUrl('settings/settings.js'),
            ['jquery'],
            LPC_VERSION,
            true
        );

        $currentTab = $this->getCurrentSection();

        $sections = [
            'home'   => __('Home', 'colissimo-shipping-methods-for-woocommerce'),
            'main'   => __('General', 'colissimo-shipping-methods-for-woocommerce'),
            'label'  => __('Label', 'colissimo-shipping-methods-for-woocommerce'),
            'parcel' => __('Parcel', 'colissimo-shipping-methods-for-woocommerce'),
        ];

        if ($this->accountApi->isHazmatOptionActive()) {
            $sections['hazmat'] = __('Hazardous materials', 'colissimo-shipping-methods-for-woocommerce');
        }

        $sections = array_merge(
            $sections,
            [
                'shipping' => __('Shipping methods', 'colissimo-shipping-methods-for-woocommerce'),
                'checkout' => __('Checkout', 'colissimo-shipping-methods-for-woocommerce'),
                'custom'   => __('Custom', 'colissimo-shipping-methods-for-woocommerce'),
                'ddp'      => __('DDP', 'colissimo-shipping-methods-for-woocommerce'),
                'support'  => __('Support', 'colissimo-shipping-methods-for-woocommerce'),
                'video'    => __('Video tutorials', 'colissimo-shipping-methods-for-woocommerce'),
            ]
        );

        $deadline = new DateTime('2025-12-31');
        $now      = new DateTime();

        if ($now < $deadline) {
            $sections['feedback'] = __('Plugin feedback', 'colissimo-shipping-methods-for-woocommerce');
        }

        echo '<ul class="subsubsub">';

        $array_keys = array_keys($sections);

        foreach ($sections as $id => $label) {
            $url       = admin_url('admin.php?page=wc-settings&tab=' . self::LPC_SETTINGS_TAB_ID . '&section=' . sanitize_title($id));
            $class     = $currentTab === $id ? 'current' : '';
            $separator = end($array_keys) === $id ? '' : '|';
            echo '<li><a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a> ' . esc_html($separator) . ' </li>';
        }

        echo '</ul><br class="clear" />';
    }

    /**
     * Save using WooCommerce default method
     */
    public function saveLpcSettings() {
        if (empty($this->configOptions)) {
            $this->initConfigOptions();
        }

        try {
            $currentSection = $this->getCurrentSection();
            $this->checkColissimoCredentials($currentSection);
            WC_Admin_Settings::save_fields($this->configOptions[$currentSection]);
            // Handle relay types reset
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if (empty($_REQUEST['_wpnonce']) || !wp_verify_nonce(wp_unslash($_REQUEST['_wpnonce']), 'woocommerce-settings')) {
                die('Invalid Token');
            }

            if ('label' === $currentSection && !isset($_POST['lpc_secured_return'])) {
                delete_option('lpc_secured_return');
            }
        } catch (Exception $exc) {
            Logger::error(
                'Can\'t save field setting.',
                [
                    'error'   => $exc->getMessage(),
                    'options' => $this->configOptions,
                ]
            );
        }
    }

    /**
     * Initialize configuration options from resource file
     */
    protected function initConfigOptions() {
        $configStructure = file_get_contents(LPC_RESOURCE_FOLDER . Helper::CONFIG_FILE);
        $tempConfig      = json_decode($configStructure, true);

        $currentTab = $this->getCurrentSection();

        foreach ($tempConfig[$currentTab] as &$oneField) {
            if (!empty($oneField['title'])) {
                // Dynamic field
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                $oneField['title'] = __($oneField['title'], 'colissimo-shipping-methods-for-woocommerce');
            }

            if (!empty($oneField['desc'])) {
                // Dynamic field
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                $oneField['desc'] = __($oneField['desc'], 'colissimo-shipping-methods-for-woocommerce');
            }

            if (!empty($oneField['options'])) {
                foreach ($oneField['options'] as &$oneOption) {
                    // Dynamic field
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                    $oneOption = __($oneOption, 'colissimo-shipping-methods-for-woocommerce');
                }
            }
        }

        $this->configOptions = $tempConfig;
    }

    protected function getCurrentSection() {
        global $current_section;

        return empty($current_section) ? 'home' : $current_section;
    }

    public function warningPackagingWeight() {
        $currentTab = Helper::getVar('tab');

        if ('lpc' !== $currentTab) {
            return;
        }

        $packagingWeight = wc_get_weight(Helper::get_option('lpc_packaging_weight', '0'), 'kg');

        if ($packagingWeight > 1) {
            WC_Admin_Settings::add_error(
                __(
                    'The packaging weight you configured is high, the shipping methods may not show up on your store if the packaging weight + the cart weight are greater than 30kg.',
                    'colissimo-shipping-methods-for-woocommerce'
                )
            );
        }
    }

    private function checkColissimoCredentials(string $currentSection) {
        if ('main' !== $currentSection) {
            return;
        }

        $authentication = [];
        if ('api_key' === Helper::getVar('lpc_credentials_type', 'api_key')) {
            $oldApiKey = Helper::get_option('lpc_apikey');
            $newApiKey = Helper::getVar('lpc_apikey');

            if ($oldApiKey === $newApiKey) {
                return;
            }

            $authentication['credential']['apiKey'] = $newApiKey;
        } else {
            $oldLogin    = Helper::get_option('lpc_id_webservices');
            $newLogin    = Helper::getVar('lpc_id_webservices');
            $oldPassword = Helper::get_option('lpc_pwd_webservices');
            $newPassword = Helper::getVar('lpc_pwd_webservices');

            if ($oldLogin === $newLogin && $oldPassword === $newPassword) {
                return;
            }

            $authentication['credential']['login']    = $newLogin;
            $authentication['credential']['password'] = $newPassword;
        }

        // Reset accepted CGV status when credentials change
        update_option('lpc_accepted_cgv', false, false);

        $parentAccountId = Helper::getVar('lpc_parent_account');
        if (!empty($parentAccountId)) {
            $authentication['partnerClientCode'] = $parentAccountId;
        }

        $accountInformation = $this->accountApi->getAccountInformation($authentication);
        $isValid            = !empty($accountInformation);
        if ($isValid) {
            WC_Admin_Settings::add_message(__('Valid Colissimo credentials', 'colissimo-shipping-methods-for-woocommerce'));
        }

        $this->logCredentialsValidity($isValid);
    }

    public function warningCredentials() {
        $currentTab = Helper::getVar('tab');

        if ('lpc' !== $currentTab) {
            return;
        }

        if ('account' === Helper::get_option('lpc_credentials_type', 'api_key')) {
            $accountLink = __('https://www.colissimo.entreprise.laposte.fr/en/my-account#informations', 'colissimo-shipping-methods-for-woocommerce');
            $this->adminNotices->add_notice(
                'credentials_apikey',
                'notice-error',
                sprintf(
                // translators: %s is a link to the Colissimo Box account edit page.
                    __('The login/password connection type will be removed during 2026 in favor of application key authentication, to increase the security of your account. To avoid any interruption in your deliveries, make sure to generate an application key from the edit page of your %s account (Manage users menu) then enter it in the "General" section of the Colissimo settings.',
                       'colissimo-shipping-methods-for-woocommerce'),
                    '<a target="_blank" href="' . esc_url($accountLink) . '">Colissimo Box</a>'
                )
            );
        }

        $testedCredentials = Helper::get_option('lpc_current_credentials_tested');

        if (!$testedCredentials) {
            if ('api_key' === Helper::get_option('lpc_credentials_type', 'api_key')) {
                $apikey = Helper::get_option('lpc_apikey');

                if (empty($apikey)) {
                    $this->adminNotices->add_notice(
                        'credentials_validity',
                        'notice-info',
                        __('Please enter your Colissimo application key to be able to generate labels and show the pickup map.', 'colissimo-shipping-methods-for-woocommerce')
                    );

                    return;
                }
            } else {
                $login    = Helper::get_option('lpc_id_webservices');
                $password = Helper::getPasswordWebService();

                if (empty($login) || empty($password)) {
                    $this->adminNotices->add_notice(
                        'credentials_validity',
                        'notice-info',
                        __('Please enter your Colissimo credentials to be able to generate labels and show the pickup map.', 'colissimo-shipping-methods-for-woocommerce')
                    );

                    return;
                }
            }

            $accountInformation = $this->accountApi->getAccountInformation();
            $this->logCredentialsValidity(!empty($accountInformation));
        }

        $validCredentials = Helper::get_option('lpc_current_credentials_valid');

        if (!$validCredentials) {
            WC_Admin_Settings::add_error(
                __(
                    'Your credentials must correspond to an account on https://www.colissimo.entreprise.laposte.fr with a valid Facilité or Privilège contract.',
                    'colissimo-shipping-methods-for-woocommerce'
                ) . "\n" .
                __('Your Colissimo credentials are incorrect, you won\'t be able to generate labels or show the pickup map to your customers.',
                   'colissimo-shipping-methods-for-woocommerce')
            );
        }
    }

    public function warningDivi() {
        $currentTab = Helper::getVar('tab');

        if ('lpc' !== $currentTab) {
            return;
        }

        $mapType = Helper::get_option('lpc_pickup_map_type', 'widget');
        if ('widget' !== $mapType) {
            return;
        }

        $theme = wp_get_theme();
        if ('Divi' !== $theme->name || !function_exists('et_get_option')) {
            return;
        }

        global $shortname;
        $option = et_get_option($shortname . '_enable_jquery_body', 'on');
        if ('on' !== $option) {
            return;
        }

        WC_Admin_Settings::add_error(
            __(
                'The DIVI option General => Performance => Defer jQuery And jQuery Migrate is activated. Please disable it to prevent DIVI from breaking the Colissimo widget, or change the pickup map type.',
                'colissimo-shipping-methods-for-woocommerce'
            )
        );
    }

    public function warningCgv() {
        $currentTab = Helper::getVar('tab');

        if ('lpc' !== $currentTab) {
            return;
        }

        if (!$this->accountApi->isCgvAccepted()) {
            $urls       = $this->accountApi->getAutologinURLs();
            $accountUrl = $urls['urlConnectedCbox'] ?? 'https://www.colissimo.entreprise.laposte.fr';
            $this->adminNotices->add_notice(
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
    }

    public function warningDeprecatedMethods() {
        if ('shipping' === Helper::getVar('tab')) {
            if (!empty(Helper::getVar('zone_id'))) {
                // The expert methods are deprecated, but will never be removed to avoid breaking existing installations. Make sure they cannot be added on zones.
                Helper::enqueueScript('lpc_settings_zones', Helper::getJsUrl('shipping/zones.js'), ['jquery']);
            }
        }

        // Add a message if deprecated methods are used
        $zonesToChange = [];
        $zones         = WC_Shipping_Zones::get_zones();

        foreach ($zones as $zone) {
            $shippingMethods = $zone['shipping_methods'];
            foreach ($shippingMethods as $shippingMethod) {
                if (in_array($shippingMethod->id, [Expert::ID, ExpertDdp::ID]) && 'yes' === $shippingMethod->enabled) {
                    $zonesToChange[$zone['zone_id']] = '<a target="_blank" href="' . admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . intval($zone['zone_id'])) . '">' . $zone['zone_name'] . '</a>';
                }
            }
        }

        if (!empty($zonesToChange)) {
            $lpc_admin_notices = Register::get('lpcAdminNotices');
            $lpc_admin_notices->add_notice(
                'deprecated_methods',
                'notice-error',
                __(
                    'The method "Colissimo International" is soon to be removed, please replace it with the method "Colissimo with signature" to avoid any service interruption.<br />You can export then import your price grid to replace easily, the rates remain the same.',
                    'colissimo-shipping-methods-for-woocommerce'
                ) . '<br /><br />' .
                __('Here are the affected zones:', 'colissimo-shipping-methods-for-woocommerce') . '<br />' .
                implode(', ', $zonesToChange)
            );
        }
    }

    private function logCredentialsValidity(bool $isValid) {
        update_option('lpc_current_credentials_tested', true, false);
        update_option('lpc_current_credentials_valid', $isValid, false);
    }

    protected function initBlockCode() {
        add_action('woocommerce_admin_field_block_code', [$this, 'displayBlockCode']);
    }

    protected function initSecuredReturn() {
        add_action('woocommerce_admin_field_secured_return', [$this, 'displaySecuredReturn']);
    }

    public function displayBlockCode() {
        $accountInformation = $this->accountApi->getAccountInformation();
        if (isset($accountInformation['statutCodeBloquant'])) {
            $args = [
                'block_code' => !empty($accountInformation['statutCodeBloquant']),
            ];

            Helper::renderPartial('Settings/SecureCode.php', $args);
        }
    }

    public function displaySecuredReturn() {
        $accountInformation = $this->accountApi->getAccountInformation();
        $urls               = $this->accountApi->getAutologinURLs();
        $args               = [
            'secured_return'    => !empty($accountInformation['optionRetourToken']),
            'services_url'      => $urls['urlParamServices'] ?? 'https://colissimo.entreprise.laposte.fr',
            'checked'           => 1 === intval(Helper::get_option('lpc_secured_return', 0)),
            'return_from_front' => 'no' !== Helper::get_option('lpc_customers_download_return_label', 'no'),
        ];

        Helper::renderPartial('Settings/SecuredReturn.php', $args);
    }
}
