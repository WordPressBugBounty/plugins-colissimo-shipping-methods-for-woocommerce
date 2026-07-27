<?php

namespace Colissimo\Classes\Settings;

defined('ABSPATH') || die('Restricted Access');

class AdminNotices {
    public function add_notice($notice, $class, $message) {
        if (PHP_SESSION_NONE === session_status()) {
            session_start();
        }
        $_SESSION[$notice][$class][] = $message;
    }

    public function get_notice(string $notice) {
        if (PHP_SESSION_NONE === session_status()) {
            @session_start();
        }

        if (isset($_SESSION[$notice])) {
            $notice_content = '';
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- plugin-controlled session state, not external input.
            foreach ($_SESSION[$notice] as $oneClass => $oneNotice) {
                $notice_content .= '
				<div class="notice lpc-notice is-dismissible ' . esc_attr($oneClass) . '">
					<p>' . implode('<br />', $oneNotice) . ' </p>' . wp_nonce_field(
                        'lpc-' . $notice . '-notice',
                        'lpc-' . $notice . '-notice-nonce',
                        false
                    ) . '</div>';
            }
            unset($_SESSION[$notice]);

            return $notice_content;
        } else {
            return false;
        }
    }
}
