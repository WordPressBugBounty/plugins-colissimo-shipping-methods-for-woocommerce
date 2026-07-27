<?php

defined('ABSPATH') || die('Restricted Access');
echo esc_html__('Relay point', 'colissimo-shipping-methods-for-woocommerce') . ': ';
echo esc_html(empty($args['pickUpLocationLabel']) ? __('Unknown', 'colissimo-shipping-methods-for-woocommerce') : ucfirst(strtolower((string) $args['pickUpLocationLabel']))) . '<br />';
echo esc_html__('ID', 'colissimo-shipping-methods-for-woocommerce') . ': #';
echo esc_html(empty($args['pickUpLocationId']) ? __('Unknown', 'colissimo-shipping-methods-for-woocommerce') : $args['pickUpLocationId']) . '<br />';
echo esc_html__('Type', 'colissimo-shipping-methods-for-woocommerce') . ': ';
echo esc_html(empty($args['pickUpLocationType']) ? __('Unknown', 'colissimo-shipping-methods-for-woocommerce') : $args['pickUpLocationType']);
