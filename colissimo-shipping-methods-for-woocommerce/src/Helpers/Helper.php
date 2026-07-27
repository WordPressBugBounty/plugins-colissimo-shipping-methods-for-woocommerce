<?php

namespace Colissimo\Helpers;

use Exception;

defined('ABSPATH') || die('Restricted Access');

class Helper {
    const CONFIG_FILE = 'config_options.json';
    const ENCRYPTION_KEY = 'colissimo-key-encryption';
    const ENCRYPTION_METHOD = 'AES-128-CTR';
    const ENCRYPTION_OPTION = 0;
    const ENCRYPTION_IV = '1234567891011121';
    const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];
    const MONTHS = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];
    const KSES_TOOLTIP = [
        'span' => [
            'class'    => [],
            'data-tip' => [],
        ],
    ];
    const KSES_WC_TOOLTIP = [
        'span' => [
            'class'      => [],
            'tabindex'   => [],
            'aria-label' => [],
            'data-tip'   => [],
        ],
    ];
    const KSES_LINK = [
        'a' => [
            'href'   => [],
            'target' => [],
        ],
    ];

    protected static $configOptions;

    /**
     * Returns an initialised WP_Filesystem instance.
     *
     * Used for the plugin's local temp-file operations (labels, slips, exports).
     * Falls back to the direct method when the configured filesystem method
     * cannot be initialised without credentials, since those files always live
     * on the local filesystem (system temp / upload temp dir).
     */
    public static function getWpFilesystem() {
        global $wp_filesystem;

        if (!$wp_filesystem instanceof \WP_Filesystem_Base) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!$wp_filesystem instanceof \WP_Filesystem_Base) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- backup of backup, only used when in Colissimo context just before usage
            $wp_filesystem = new \WP_Filesystem_Direct(null);
        }

        return $wp_filesystem;
    }

    /**
     * Allowed HTML tags for modal content.
     *
     * Modal content is plugin-generated markup (maps, settings forms, log dumps,
     * hook descriptions) whose dynamic values are already escaped where they are
     * built. It still has to pass through an escaping function on output, so we
     * extend the standard "post" tag set with the form elements the modals use.
     *
     * @return array
     */
    public static function getModalAllowedHtml(): array {
        return array_merge(
            wp_kses_allowed_html('post'),
            [
                'form'     => [
                    'action' => [],
                    'method' => [],
                    'class'  => [],
                    'id'     => [],
                    'style'  => [],
                ],
                'input'    => [
                    'type'        => [],
                    'name'        => [],
                    'id'          => [],
                    'class'       => [],
                    'value'       => [],
                    'placeholder' => [],
                    'checked'     => [],
                    'disabled'    => [],
                    'readonly'    => [],
                    'required'    => [],
                    'min'         => [],
                    'max'         => [],
                    'step'        => [],
                    'style'       => [],
                ],
                'select'   => [
                    'name'             => [],
                    'id'               => [],
                    'class'            => [],
                    'multiple'         => [],
                    'disabled'         => [],
                    'data-lpc-product' => [],
                    'style'            => [],
                ],
                'option'   => [
                    'value'    => [],
                    'selected' => [],
                    'disabled' => [],
                ],
                'optgroup' => [
                    'label'    => [],
                    'disabled' => [],
                ],
                'textarea' => [
                    'name'        => [],
                    'id'          => [],
                    'class'       => [],
                    'rows'        => [],
                    'cols'        => [],
                    'placeholder' => [],
                    'disabled'    => [],
                    'readonly'    => [],
                    'style'       => [],
                ],
                'button'   => [
                    'type'     => [],
                    'name'     => [],
                    'id'       => [],
                    'class'    => [],
                    'value'    => [],
                    'disabled' => [],
                    'style'    => [],
                ],
                'label'    => [
                    'for'   => [],
                    'id'    => [],
                    'class' => [],
                    'style' => [],
                ],
            ]
        );
    }

    public static function renderPartialInLayout(string $name, array $args = []): void {
        self::renderPartial(
            'WebsiteTheme.php',
            [
                'name' => $name,
                'args' => $args,
            ]
        );
    }

    public static function renderPartial(string $path, array $args = []): void {
        $path = str_replace('/', DS, $path);
        if (false !== strpos($path, '..') || !file_exists(LPC_PARTIALS_FOLDER . $path)) {
            Logger::error('Partial not found', ['partial' => $path]);

            return;
        }

        include LPC_PARTIALS_FOLDER . $path;
    }

    public static function enqueueScript(string $handle, string $srcAdmin, array $dep = [], string $localizeObject = '', array $localizeVars = []) {
        self::enqueueScripts('admin_enqueue_scripts', $handle, $srcAdmin, $dep, $localizeObject, $localizeVars);
    }

    private static function enqueueScripts($hook, $handle, $src, $dep, $localizeObject, $localizeVars) {
        add_action(
            $hook,
            function () use ($handle, $src, $dep, $localizeObject, $localizeVars) {
                wp_register_script($handle, $src, $dep, LPC_VERSION, true);
                if (!empty($localizeObject)) {
                    wp_localize_script($handle, $localizeObject, $localizeVars);
                }
                wp_enqueue_script($handle);
            }
        );
    }

    public static function enqueueStyle(string $handle, string $src, bool $isAdmin = true, $dep = []) {
        if ($isAdmin) {
            add_action(
                'admin_enqueue_scripts',
                function () use ($handle, $src, $dep) {
                    wp_enqueue_style($handle, $src, $dep, LPC_VERSION);
                }
            );
        } else {
            add_action(
                'wp_enqueue_scripts',
                function () use ($handle, $src, $dep) {
                    wp_enqueue_style($handle, $src, $dep, LPC_VERSION);
                }
            );
        }
    }

    public static function displayNotice($type, $message) {
        self::renderPartial(
            'Notice.php',
            [
                'message' => $message,
                'type'    => $type,
            ]
        );
    }

    public static function displayNoticeException(Exception $e) {
        self::displayNotice('error', $e->getMessage());
    }

    public static function getVar(string $var, $default = '', string $type = 'string', string $source = 'REQUEST') {
        $source = strtoupper($source);

        switch ($source) {
            case 'GET':
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Generic request accessor; nonce verification is performed by the calling action handler / AJAX dispatcher.
                $input = &$_GET;
                break;
            case 'POST':
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $input = &$_POST;
                break;
            case 'FILES':
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Generic request accessor; nonce verification is performed by the calling action handler / AJAX dispatcher.
                $input = &$_FILES;
                break;
            case 'COOKIE':
                $input = &$_COOKIE;
                break;
            case 'ENV':
                $input = &$_ENV;
                break;
            case 'SERVER':
                $input = &$_SERVER;
                break;
            default:
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Generic request accessor; nonce verification is performed by the calling action handler / AJAX dispatcher.
                $input = &$_REQUEST;
                break;
        }

        if (!isset($input[$var])) {
            return $default;
        }

        $result = $input[$var];
        unset($input);

        if ('array' === $type) {
            $result = (array) $result;
        }

        if (in_array($source, ['POST', 'REQUEST', 'GET', 'COOKIE'])) {
            $result = self::stripSlashes($result);
        }

        return self::cleanVar($result, $type);
    }

    public static function stripSlashes($element) {
        if (is_array($element)) {
            foreach ($element as &$oneCell) {
                $oneCell = self::stripSlashes($oneCell);
            }
        } elseif (is_string($element)) {
            $element = stripslashes($element);
        }

        return $element;
    }

    public static function cleanVar($var, $type) {
        if (is_array($var)) {
            foreach ($var as $i => $val) {
                $var[$i] = self::cleanVar($val, $type);
            }

            return $var;
        }

        switch ($type) {
            case 'string':
                $var = strval($var);
                break;
            case 'int':
                $var = intval($var);
                break;
            case 'float':
                $var = floatval($var);
                break;
            case 'bool':
            case 'boolean':
                $var = boolval($var);
                break;
            case 'word':
                $var = preg_replace('#[^a-zA-Z_]#', '', $var);
                break;
            case 'cmd':
                $var = preg_replace('#[^a-zA-Z0-9_\.-]#', '', $var);
                $var = ltrim($var, '.');
                break;
            default:
                break;
        }

        if (!is_string($var)) {
            return $var;
        }

        $var = trim($var);

        if (!preg_match('//u', $var)) {
            // String contains invalid byte sequence, remove it
            $var = htmlspecialchars_decode(htmlspecialchars($var, ENT_IGNORE, 'UTF-8'));
        }

        return preg_replace('#<[a-zA-Z/]+[^>]*>#Ui', '', $var);
    }

    public static function get_option($option, $default = '') {
        // Return the saved option if available
        $value = get_option($option);
        if ($value) {
            return $value;
        }

        // Return the default value if provided
        if ('' !== $default) {
            return $default;
        }

        // Load the default values
        if (null === self::$configOptions) {
            $configStructure     = file_get_contents(LPC_RESOURCE_FOLDER . self::CONFIG_FILE);
            self::$configOptions = new \ArrayObject(json_decode($configStructure, true));
        }

        // Return the default value if available
        foreach (self::$configOptions as $configTab) {
            foreach ($configTab as $configOption) {
                if (array_key_exists('id', $configOption) && $configOption['id'] === $option) {
                    if (array_key_exists('default', $configOption)) {
                        return $configOption['default'];
                    } else {
                        return '';
                    }
                }
            }
        }

        return '';
    }

    public static function getWooCommerceDir() {
        if (file_exists(WPMU_PLUGIN_DIR . DS . 'woocommerce' . DS . 'woocommerce.php')) {
            return WPMU_PLUGIN_DIR . '/woocommerce';
        }

        return WP_PLUGIN_DIR . '/woocommerce';
    }

    public static function endAjax($success = true, $data = []) {
        echo json_encode(
            [
                'type' => $success ? 'success' : 'error',
                'data' => $data,
            ]
        );
        exit;
    }

    public static function encryptPassword($password) {
        return openssl_encrypt($password, self::ENCRYPTION_METHOD, self::ENCRYPTION_KEY, self::ENCRYPTION_OPTION, self::ENCRYPTION_IV);
    }

    public static function decryptPassword($encryptedPassword) {
        return openssl_decrypt($encryptedPassword, self::ENCRYPTION_METHOD, self::ENCRYPTION_KEY, self::ENCRYPTION_OPTION, self::ENCRYPTION_IV);
    }

    public static function getPasswordWebService() {
        return self::decryptPassword(self::get_option('lpc_pwd_webservices'));
    }

    public static function replaceAccents(string $text) {
        return str_replace(
            ['’', 'é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ô', 'ö', 'î', 'ï', 'ù', 'û', 'ü', 'ç', 'ÿ', 'É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Ä', 'Ô', 'Ö', 'Î', 'Ï', 'Ù', 'Û', 'Ü', 'Ç', 'Ÿ'],
            ['\'', 'e', 'e', 'e', 'e', 'a', 'a', 'a', 'o', 'o', 'i', 'i', 'u', 'u', 'u', 'c', 'y', 'E', 'E', 'E', 'E', 'A', 'A', 'A', 'O', 'O', 'I', 'I', 'U', 'U', 'U', 'C', 'Y'],
            $text
        );
    }

    public static function toAscii(string $text): string {
        $chars = [
            // Misc
            '’' => '\'',
            // German
            'Ä' => 'A',
            'Ö' => 'O',
            'Ü' => 'U',
            'ä' => 'a',
            'ö' => 'o',
            'ü' => 'u',
            'ß' => 'ss',
            // French
            'À' => 'A',
            'Â' => 'A',
            'Æ' => 'Ae',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Î' => 'I',
            'Ï' => 'I',
            'Ô' => 'O',
            'Œ' => 'Oe',
            'Ù' => 'U',
            'Û' => 'U',
            'Ÿ' => 'Y',
            'à' => 'a',
            'â' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'œ' => 'oe',
            'ù' => 'u',
            'û' => 'u',
            'ÿ' => 'y',
            // Spanish / Portuguese
            'Á' => 'A',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N',
            'Ã' => 'A',
            'Õ' => 'O',
            'á' => 'a',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
            'ã' => 'a',
            'õ' => 'o',
            // Polish
            'Ą' => 'A',
            'Ć' => 'C',
            'Ę' => 'E',
            'Ł' => 'L',
            'Ń' => 'N',
            'Ś' => 'S',
            'Ź' => 'Z',
            'Ż' => 'Z',
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',
            // Czech / Slovak / Croatian / Slovenian
            'Č' => 'C',
            'Ď' => 'D',
            'Ě' => 'E',
            'Ň' => 'N',
            'Ř' => 'R',
            'Š' => 'S',
            'Ť' => 'T',
            'Ů' => 'U',
            'Ž' => 'Z',
            'č' => 'c',
            'ď' => 'd',
            'ě' => 'e',
            'ň' => 'n',
            'ř' => 'r',
            'š' => 's',
            'ť' => 't',
            'ů' => 'u',
            'ž' => 'z',
            // Nordic
            'Å' => 'A',
            'Ø' => 'O',
            'å' => 'a',
            'ø' => 'o',
            'Ð' => 'D',
            'Þ' => 'Th',
            'ð' => 'd',
            'þ' => 'th',
            // Romanian
            'Ș' => 'S',
            'Ț' => 'T',
            'ș' => 's',
            'ț' => 't',
            // Turkish
            'Ğ' => 'G',
            'İ' => 'I',
            'Ş' => 'S',
            'ğ' => 'g',
            'ı' => 'i',
            'ş' => 's',
            // Vietnamese (common ones)
            'Đ' => 'D',
            'đ' => 'd',
            // Lithuanian
            'Ė' => 'E',
            'Į' => 'I',
            'Ų' => 'U',
            'Ū' => 'U',
            'ė' => 'e',
            'į' => 'i',
            'ų' => 'u',
            'ū' => 'u',
            // Latvian
            'Ā' => 'A',
            'Ē' => 'E',
            'Ģ' => 'G',
            'Ī' => 'I',
            'Ķ' => 'K',
            'Ļ' => 'L',
            'Ņ' => 'N',
            'Ŗ' => 'R',
            'ā' => 'a',
            'ē' => 'e',
            'ģ' => 'g',
            'ī' => 'i',
            'ķ' => 'k',
            'ļ' => 'l',
            'ņ' => 'n',
            'ŗ' => 'r',
            // Hungarian
            'Ő' => 'O',
            'Ű' => 'U',
            'ő' => 'o',
            'ű' => 'u',
            // Italian
            'Ì' => 'I',
            'Ò' => 'O',
            'ì' => 'i',
            'ò' => 'o',
            // Welsh
            'Ŵ' => 'W',
            'Ŷ' => 'Y',
            'ŵ' => 'w',
            'ŷ' => 'y',
            // Irish / Scottish Gaelic
            'Ầ' => 'A',
            'Ề' => 'E',
            'Ồ' => 'O',
            // Maltese
            'Ħ' => 'H',
            'ħ' => 'h',
            'Ġ' => 'G',
            'ġ' => 'g',
            // Esperanto
            'Ĉ' => 'C',
            'Ĝ' => 'G',
            'Ĥ' => 'H',
            'Ĵ' => 'J',
            'Ŝ' => 'S',
            'Ŭ' => 'U',
            'ĉ' => 'c',
            'ĝ' => 'g',
            'ĥ' => 'h',
            'ĵ' => 'j',
            'ŝ' => 's',
            'ŭ' => 'u',
            // Vietnamese
            'Ắ' => 'A',
            'Ặ' => 'A',
            'Ậ' => 'A',
            'Ả' => 'A',
            'Ế' => 'E',
            'Ệ' => 'E',
            'Ể' => 'E',
            'Ễ' => 'E',
            'Ố' => 'O',
            'Ộ' => 'O',
            'Ổ' => 'O',
            'Ỗ' => 'O',
            'Ơ' => 'O',
            'Ớ' => 'O',
            'Ợ' => 'O',
            'Ờ' => 'O',
            'Ở' => 'O',
            'Ỡ' => 'O',
            'Ứ' => 'U',
            'Ự' => 'U',
            'Ừ' => 'U',
            'Ử' => 'U',
            'Ữ' => 'U',
            'Ư' => 'U',
            'Ỳ' => 'Y',
            'Ỵ' => 'Y',
            'Ỷ' => 'Y',
            'Ỹ' => 'Y',
            'ắ' => 'a',
            'ặ' => 'a',
            'ầ' => 'a',
            'ậ' => 'a',
            'ả' => 'a',
            'ấ' => 'a',
            'ă' => 'a',
            'Ă' => 'A',
            'ế' => 'e',
            'ệ' => 'e',
            'ề' => 'e',
            'ể' => 'e',
            'ễ' => 'e',
            'ố' => 'o',
            'ộ' => 'o',
            'ồ' => 'o',
            'ổ' => 'o',
            'ỗ' => 'o',
            'ơ' => 'o',
            'ớ' => 'o',
            'ợ' => 'o',
            'ờ' => 'o',
            'ở' => 'o',
            'ỡ' => 'o',
            'ứ' => 'u',
            'ự' => 'u',
            'ừ' => 'u',
            'ử' => 'u',
            'ữ' => 'u',
            'ư' => 'u',
            'ỳ' => 'y',
            'ỵ' => 'y',
            'ỷ' => 'y',
            'ỹ' => 'y',
        ];

        $transliterated = str_replace(array_keys($chars), array_values($chars), $text);

        $clean = preg_replace('#[^a-zA-Z ]#', '', $transliterated);

        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    public static function tooltip(string $text): string {
        return '<span class="woocommerce-help-tip" data-tip="' . esc_attr($text) . '"></span>';
    }

    public static function getMatchingPackaging(float $numberOfProducts, float $cartWeight, array $productDimensions) {
        $packagings = self::get_option('lpc_packagings', []);
        usort($packagings, fn($a, $b) => $a['priority'] > $b['priority'] ? 1 : - 1);

        foreach ($packagings as $packaging) {
            if (!empty($packaging['max_products']) && $numberOfProducts > $packaging['max_products']) {
                continue;
            }

            if (!empty($packaging['max_weight']) && $cartWeight > $packaging['max_weight']) {
                continue;
            }

            $packagingDimensions = [
                $packaging['length'],
                $packaging['width'],
                $packaging['depth'],
            ];
            foreach ($productDimensions as $dimensions) {
                if (!self::isPackagingFitting($packagingDimensions, $dimensions)) {
                    continue 2;
                }
            }

            return $packaging;
        }

        return null;
    }

    public static function translateDate(string $date): string {
        foreach (self::DAYS as $day) {
            // Can't call __() in class constants
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            $date = str_replace($day, __($day, 'colissimo-shipping-methods-for-woocommerce'), $date);
        }

        foreach (self::MONTHS as $month) {
            // Can't call __() in class constants
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            $monthText = __($month, 'colissimo-shipping-methods-for-woocommerce');
            $date      = str_replace($month, $monthText, $date);
            $date      = str_replace(substr($month, 0, 3), mb_substr($monthText, 0, 3), $date);
        }

        return $date;
    }

    public static function getFont(string $option): ?string {
        $fontValue = self::get_option($option, null);
        if (empty($fontValue)) {
            return null;
        }

        $fontNames = [
            'georgia'       => 'Georgia, serif',
            'palatino'      => '"Palatino Linotype", "Book Antiqua", Palatino, serif',
            'times'         => '"Times New Roman", Times, serif',
            'arial'         => 'Arial, Helvetica, sans-serif',
            'arialblack'    => '"Arial Black", Gadget, sans-serif',
            'comic'         => '"Comic Sans MS", cursive, sans-serif',
            'impact'        => 'Impact, Charcoal, sans-serif',
            'lucida'        => '"Lucida Sans Unicode", "Lucida Grande", sans-serif',
            'tahoma'        => 'Tahoma, Geneva, sans-serif',
            'trebuchet'     => '"Trebuchet MS", Helvetica, sans-serif',
            'verdana'       => 'Verdana, Geneva, sans-serif',
            'courier'       => '"Courier New", Courier, monospace',
            'lucidaconsole' => '"Lucida Console", Monaco, monospace',
        ];

        return $fontNames[$fontValue] ?? null;
    }

    public static function getWooSession() {
        $woo = WC();

        if (!$woo->session) {
            $woo->initialize_session();
        }

        return $woo->session;
    }

    private static function isPackagingFitting(array $packagingDimensions, array $productDimensions): bool {
        sort($packagingDimensions);
        sort($productDimensions);

        if (empty($productDimensions[0])) {
            return true;
        }

        foreach ($productDimensions as $key => $dimension) {
            if ($dimension > $packagingDimensions[$key]) {
                return false;
            }
        }

        return true;
    }

    public static function getImageUrl(string $path): string {
        return plugins_url('/assets/images/' . $path, LPC_MAIN_FILE);
    }

    public static function getCssUrl(string $path): string {
        return plugins_url('/assets/css/' . $path, LPC_MAIN_FILE);
    }

    public static function getJsUrl(string $path): string {
        return plugins_url('/assets/js/' . $path, LPC_MAIN_FILE);
    }

    public static function createDirectory(string $logDir, bool $withHtaccess = false): void {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!$wp_filesystem->is_dir($logDir)) {
            $wp_filesystem->mkdir($logDir, FS_CHMOD_DIR);
        }

        if (!$withHtaccess) {
            return;
        }

        $htaccess = $logDir . '.htaccess';
        if (!$wp_filesystem->exists($htaccess)) {
            $content = "# Apache 2.4+\n";
            $content .= "<IfModule mod_authz_core.c>\n";
            $content .= "    Require all denied\n";
            $content .= "</IfModule>\n\n";
            $content .= "# Apache 2.3 and earlier\n";
            $content .= "<IfModule !mod_authz_core.c>\n";
            $content .= "    Order deny,allow\n";
            $content .= "    Deny from all\n";
            $content .= "</IfModule>\n";

            $wp_filesystem->put_contents($htaccess, $content, FS_CHMOD_FILE);
        }
    }

    /**
     * Create a unique, per-request temporary directory and return its trailing-slashed path.
     *
     * Using a fresh directory per request avoids reusing fixed file names in the shared system temp
     * directory, which could otherwise leak one request's PDF into another under concurrency, or be
     * targeted by a local symlink attack.
     *
     * @param string $prefix
     *
     * @return string
     */
    public static function createUniqueTempDir(string $prefix = 'lpc-'): string {
        $tmpBase = ini_get('upload_tmp_dir');
        if (empty($tmpBase) || !self::getWpFilesystem()->is_writable($tmpBase)) {
            $tmpBase = get_temp_dir();
        }

        $dir = trailingslashit($tmpBase) . uniqid($prefix, true);
        self::createDirectory($dir);

        return trailingslashit($dir);
    }
}
