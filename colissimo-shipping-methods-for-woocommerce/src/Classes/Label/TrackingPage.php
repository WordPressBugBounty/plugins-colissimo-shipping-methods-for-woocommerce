<?php

namespace Colissimo\Classes\Label;

use Exception;
use Colissimo\Helpers\Helper;
use Colissimo\Core\Register;
use WP;

defined('ABSPATH') || die('Restricted Access');

class TrackingPage {
    const ROUTE = '.*lpc/tracking/(.+)/?';
    const QUERY_VAR = 'lpc_tracking_hash';

    public function control(WP $wp) {
        Helper::enqueueStyle(
            'lpc_tracking',
            Helper::getCssUrl('tracking.css'),
            false
        );

        $trackingHash          = $wp->query_vars[self::QUERY_VAR];
        $lpcUnifiedTrackingApi = Register::get('unifiedTrackingApi');
        $decryptedVar          = (string) $lpcUnifiedTrackingApi->decrypt($trackingHash);

        $parts          = explode('-', $decryptedVar, 2);
        $orderId        = (int) ($parts[0] ?? 0);
        $trackingNumber = $parts[1] ?? '';

        if ($orderId <= 0 || '' === $trackingNumber || !$this->trackingNumberBelongsToOrder($orderId, $trackingNumber)) {
            $this->renderNotFound();

            return;
        }

        try {
            $order = wc_get_order($orderId);

            try {
                if (isset($_SERVER['REMOTE_ADDR'])) {
                    $trackingInfo = $lpcUnifiedTrackingApi->getTrackingInfo(
                        $orderId,
                        $trackingNumber,
                        sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
                    );
                }
            } catch (Exception $e) {
                if ($e->getMessage() === 'Numéro de colis inconnu') {
                    wp_safe_redirect(get_permalink(wc_get_page_id('myaccount')) . 'orders');
                    exit;
                }

                header('HTTP/1.0 500 Internal Server Error');
                wp_die(
                    sprintf(
                        wp_kses(
                        // translators: %1$s is the error message, %2$u is the error code, %3$s is the home page URL.
                            __('An error occured while retrieving tracking info (%1$s [%2$u])... <a href="%3$s">get back to the home page</a>.',
                               'colissimo-shipping-methods-for-woocommerce'),
                            [
                                'a' => [
                                    'href' => [],
                                ],
                            ]
                        ),
                        esc_html($e->getMessage()),
                        esc_html($e->getCode()),
                        esc_url(get_home_url())
                    )
                );
            }
            $trackingInfo['mainStatus'] = $this->getMainStatus($trackingInfo);

            Helper::renderPartialInLayout(
                'Tracking/TrackingPage.php',
                [
                    'order'        => $order,
                    'logoUrl'      => Helper::getImageUrl('colissimo.png'),
                    'trackingInfo' => $trackingInfo,
                ]
            );
            die();
        } catch (Exception $e) {
            $this->renderNotFound();
        }
    }

    private function renderNotFound(): void {
        header('HTTP/1.0 404 Not Found');
        wp_die(
            sprintf(
                wp_kses(
                // translators: %s is the home page URL.
                    __('Not found... <a href="%s">get back to the home page</a>.', 'colissimo-shipping-methods-for-woocommerce'),
                    [
                        'a' => [
                            'href' => [],
                        ],
                    ]
                ),
                esc_url(get_home_url())
            )
        );
    }

    /**
     * Verify that the parcel number actually belongs to the order (outward or return label).
     *
     * @param int    $orderId
     * @param string $trackingNumber
     *
     * @return bool
     */
    private function trackingNumberBelongsToOrder(int $orderId, string $trackingNumber): bool {
        $outwardLabelDb = Register::get('outwardLabelDb');
        if ((int) $outwardLabelDb->getOrderIdByTrackingNumber($trackingNumber) === $orderId) {
            return true;
        }

        $inwardLabelDb = Register::get('inwardLabelDb');
        $inwardLabel   = $inwardLabelDb->getLabelFor($trackingNumber);

        return !empty($inwardLabel['order_id']) && (int) $inwardLabel['order_id'] === $orderId;
    }

    public static function addRewriteRule() {
        // Detect our permalink structure and fill in our URL parameter so that it could be detected
        add_rewrite_rule(
            self::ROUTE,
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    private function getMainStatus(array $trackingInfo) {
        try {
            if (!empty($trackingInfo['statusDelivery'])) {
                return __('Delivered', 'colissimo-shipping-methods-for-woocommerce');
            }

            $lastEvent = end($trackingInfo['parcel']['event']);

            return $lastEvent['labelLong'];
        } catch (\Exception $e) {
            return '';
        }
    }
}
