<?php

namespace Colissimo\Classes\Settings;

defined('ABSPATH') || die('Restricted Access');

class AdminNotices {
    const NOTICES_OPTION = 'lpc_admin_notices';
    const MAX_MESSAGES_PER_NOTICE = 20;

    public function add_notice(string $notice, string $class, string $message) {
        $notices  = $this->readNotices();
        $messages = $notices[$notice][$class] ?? [];

        if (in_array($message, $messages, true)) {
            return;
        }

        $messages[]               = $message;
        $notices[$notice][$class] = array_slice($messages, - self::MAX_MESSAGES_PER_NOTICE);

        $this->writeNotices($notices);
    }

    public function get_notice(string $notice) {
        return $this->get_notices([$notice])[$notice] ?? false;
    }

    public function get_notices(array $notices): array {
        $stored = $this->readNotices();
        if (empty($stored)) {
            return [];
        }

        $found = [];
        foreach ($notices as $oneNotice) {
            if (empty($stored[$oneNotice])) {
                continue;
            }

            $notice_content = '';
            foreach ($stored[$oneNotice] as $oneClass => $oneMessages) {
                $notice_content .= '
					<div class="notice lpc-notice is-dismissible ' . esc_attr($oneClass) . '">
						<p>' . implode('<br />', $oneMessages) . ' </p>' . wp_nonce_field(
                        'lpc-' . $oneNotice . '-notice',
                        'lpc-' . $oneNotice . '-notice-nonce',
                        false,
                        false
                    ) . '</div>';
            }

            $found[$oneNotice] = $notice_content;
            unset($stored[$oneNotice]);
        }

        if (!empty($found)) {
            $this->writeNotices($stored);
        }

        return $found;
    }

    private function readNotices(): array {
        $notices = get_option(self::NOTICES_OPTION, []);

        return is_array($notices) ? $notices : [];
    }

    private function writeNotices(array $notices): void {
        if (empty($notices)) {
            delete_option(self::NOTICES_OPTION);

            return;
        }

        // The option must never be autoloaded, it would then be read on every front office request as well
        update_option(self::NOTICES_OPTION, $notices, false);
    }
}
