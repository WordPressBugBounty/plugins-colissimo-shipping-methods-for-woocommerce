<?php

namespace Colissimo\Classes\Settings;

use Colissimo\Api\AccountApi;
use Colissimo\Api\CotationApi;
use Colissimo\Core\Ajax;
use Colissimo\Core\Register;
use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');

class SimulationAction {
    const AJAX_TASK_NAME = 'settings/simulation';
    const PAGE_SCREEN_ID = 'woocommerce_page_wc_colissimo_view';
    const TAB_NAME = 'simulation';

    protected Ajax $ajaxDispatcher;

    public function __construct() {
        $this->ajaxDispatcher = Register::get('ajaxDispatcher');
    }

    public function init() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'calculate']);

        add_action('current_screen', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets($screen) {
        if (empty($screen) || self::PAGE_SCREEN_ID !== $screen->base) {
            return;
        }

        $tab = Helper::getVar('tab');
        if (empty($tab)) {
            $tab = 'orders';
        }

        if (self::TAB_NAME !== $tab) {
            return;
        }

        Helper::enqueueScript(
            'lpc_admin_simulation',
            Helper::getJsUrl('settings/simulation.js'),
            ['jquery'],
            'lpcSimulation',
            [
                'ajaxURL' => $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME),
                'i18n'    => [
                    'loading'       => __('Calculating…', 'colissimo-shipping-methods-for-woocommerce'),
                    'error'         => __('An error occurred while calculating the rate.', 'colissimo-shipping-methods-for-woocommerce'),
                    'noResult'      => __('No rate returned for these parameters.', 'colissimo-shipping-methods-for-woocommerce'),
                    'remove'        => __('Remove', 'colissimo-shipping-methods-for-woocommerce'),
                    'resultText'    => __('Result', 'colissimo-shipping-methods-for-woocommerce'),
                    'missingFields' => __('Please fill in the following fields:', 'colissimo-shipping-methods-for-woocommerce'),
                    'transportCost' => __('Transport cost (excl. tax)', 'colissimo-shipping-methods-for-woocommerce'),
                    'optionsTotal'  => __('Options total', 'colissimo-shipping-methods-for-woocommerce'),
                    'supplements'   => __('Supplements', 'colissimo-shipping-methods-for-woocommerce'),
                    'returnCost'    => __('Return cost (excl. tax)', 'colissimo-shipping-methods-for-woocommerce'),
                    'totalCost'     => __('Total cost (excl. tax)', 'colissimo-shipping-methods-for-woocommerce'),
                ],
            ]
        );

        Helper::enqueueStyle(
            'lpc_admin_simulation',
            Helper::getCssUrl('settings/simulation.css')
        );
    }

    public function calculate() {
        if (!current_user_can('lpc_manage_settings')) {
            return $this->ajaxDispatcher->makeError(['message' => __('You are not allowed to perform this action.', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        $params = [
            'codePaysExpediteur'     => Helper::get_option('lpc_origin_address_country', WC()->countries->get_base_country()),
            'codePostalExpediteur'   => Helper::get_option('lpc_origin_address_zipcode', WC()->countries->get_base_postcode()),
            'codePaysDestinataire'   => Helper::getVar('recipientCountry'),
            'codePostalDestinataire' => Helper::getVar('recipientPostcode'),
            'poids'                  => Helper::getVar('weight'),
            'typeTarif'              => Helper::getVar('typeTarif', 0, 'int'),
            'optionsValorisees'      => Helper::getVar('optionsValorisees', [], 'array'),
            // With/without commitment is deduced from the account contract type (Privilège vs Facilité).
            'avecEngagement'         => $this->hasCommitment(),
            // Economic shipping is not available for now.
            'economique'             => false,
            // Not used but the API returns an error if missing
            'offreEntreprise'        => true,
        ];

        $params += $this->getDeliveryModeParams(Helper::getVar('deliveryMode', 'home'));

        $cotationApi = new CotationApi();
        $response    = $cotationApi->calculateCost($params);

        if (empty($response)) {
            return $this->ajaxDispatcher->makeError(['message' => __('No rate returned for these parameters.', 'colissimo-shipping-methods-for-woocommerce')]);
        }

        return $this->ajaxDispatcher->makeSuccess(['result' => $response]);
    }

    private function hasCommitment(): bool {
        $accountApi         = new AccountApi();
        $accountInformation = $accountApi->getAccountInformation();

        if (empty($accountInformation['contractType'])) {
            return false;
        }

        return AccountApi::LPC_CONTRACT_TYPE_FACILITE !== $accountInformation['contractType'];
    }

    /**
     * Translate the front-end delivery mode into the web service fields.
     *
     * @param string $deliveryMode
     *
     * @return array
     */
    private function getDeliveryModeParams(string $deliveryMode): array {
        switch ($deliveryMode) {
            case 'home_signature':
                return [
                    'retour'            => false,
                    'livraisonDomicile' => true,
                    'avecSignature'     => true,
                    'typeSiteLivraison' => '',
                ];
            case 'pickup_bpr':
                return [
                    'retour'            => false,
                    'livraisonDomicile' => false,
                    'avecSignature'     => false,
                    'typeSiteLivraison' => 'BPR',
                ];
            case 'pickup_a2p':
                return [
                    'retour'            => false,
                    'livraisonDomicile' => false,
                    'avecSignature'     => false,
                    'typeSiteLivraison' => 'A2P',
                ];
            case 'return':
                return [
                    'retour'            => true,
                    'livraisonDomicile' => true,
                    'avecSignature'     => true,
                    'typeSiteLivraison' => '',
                ];
            case 'home':
            default:
                return [
                    'retour'            => false,
                    'livraisonDomicile' => true,
                    'avecSignature'     => false,
                    'typeSiteLivraison' => '',
                ];
        }
    }
}
