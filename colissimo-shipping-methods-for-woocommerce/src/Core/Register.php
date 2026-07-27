<?php

namespace Colissimo\Core;

use Exception;

defined('ABSPATH') || die('Restricted Access');

class Register {
    protected static array $components = [];

    public static function register($key, $component) {
        if (!empty(self::$components[$key])) {
            throw new Exception('Component ' . esc_html($key) . ' has already been registered!');
        }

        self::$components[$key] = $component;

        return $component;
    }

    /**
     * @throws Exception When the required object couldn't be found.
     */
    public static function get($key, $override = null) {
        if (!empty($override)) {
            return $override;
        }

        if (empty(self::$components[$key])) {
            throw new Exception('No such component ' . esc_html($key));
        }

        return self::$components[$key];
    }

    public static function init() {
        foreach (self::$components as $component) {
            if (method_exists($component, 'init')) {
                $component->init();
            }
        }
    }
}
