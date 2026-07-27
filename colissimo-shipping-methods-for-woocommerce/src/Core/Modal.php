<?php

namespace Colissimo\Core;

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class Modal {
    protected $templateId;
    protected $content;
    protected $title;
    protected $elementId;

    public function __construct($content, $title = null, $templateId = null) {
        if (empty($templateId)) {
            $templateId = uniqid();
        }

        $this->templateId = $templateId;
        $this->elementId  = 'lpc' . random_int(1000, 9999);
        $this->content    = $content;
        $this->title      = $title;
    }

    public function registerScripts() {
        wp_register_script(
            'lpc_modal',
            Helper::getJsUrl('modal.js'),
            [],
            LPC_VERSION,
            true
        );
        wp_register_style('lpc_modal', Helper::getCssUrl('modal.css'), [], LPC_VERSION);
    }

    public function enqueueScripts() {
        wp_enqueue_script('lpc_modal');
        wp_enqueue_style('lpc_modal');
        wp_enqueue_style('dashicons');
    }

    public function loadScripts() {
        $this->registerScripts();
        $this->enqueueScripts();
    }

    public function setContent($content) {
        $this->content = $content;

        return $this;
    }

    public function echo_modal() {
        Helper::renderPartial(
            'Modal/Modal.php',
            [
                'templateId' => $this->templateId,
                'title'      => $this->title,
                'content'    => $this->content,
            ]
        );

        return $this;
    }

    public function echo_button(?string $buttonContent = null, ?string $callback = null) {
        if (null === $buttonContent) {
            $buttonContent = __('Apply', 'colissimo-shipping-methods-for-woocommerce');
        }

        Helper::renderPartial(
            'Modal/Button.php',
            [
                'elementId'  => $this->elementId,
                'templateId' => $this->templateId,
                'callback'   => $callback,
                'content'    => $buttonContent,
            ]
        );

        return $this;
    }

    public function echo_link($aContent = null, $callback = null) {
        if (null === $aContent) {
            $aContent = __('Apply', 'colissimo-shipping-methods-for-woocommerce');
        }

        if (!empty($callback)) {
            $callback = 'data-lpc-callback="' . esc_attr($callback) . '"';
        }

        Helper::renderPartial(
            'Modal/Link.php',
            [
                'elementId'  => $this->elementId,
                'templateId' => $this->templateId,
                'callback'   => $callback,
                'content'    => $aContent,
            ]
        );

        return $this;
    }

    public function echo_modalAndButton(?string $buttonContent = null) {
        return $this->echo_button($buttonContent)->echo_modal();
    }

    public function open_modal(string $partial) {
        Helper::renderPartial(
            'Modal/' . $partial . '.php',
            [
                'elementId'  => $this->elementId,
                'templateId' => $this->templateId,
                'title'      => $this->title,
                'content'    => $this->content,
            ]
        );

        return $this->echo_link()->echo_modal();
    }

    public function getElementId() {
        return $this->elementId;
    }
}
