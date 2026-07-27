<?php

namespace Colissimo\Init;

use Colissimo\Api\AccountApi;
use Colissimo\Api\CheckoutApi;
use Colissimo\Api\LabelGenerationApi;
use Colissimo\Api\PickupWidgetApi;
use Colissimo\Api\RelaysApi;
use Colissimo\Api\TrackingApi;
use Colissimo\Classes\Email\InwardLabelEmailManager;
use Colissimo\Classes\Email\OutwardLabelEmailManager;
use Colissimo\Classes\Label\InwardDownloadAccountAction;
use Colissimo\Classes\Label\InwardLabelDb;
use Colissimo\Classes\Label\LabelStatus;
use Colissimo\Classes\Label\LabelPurge;
use Colissimo\Classes\Label\LabelGenerationOutward;
use Colissimo\Classes\Label\LabelGenerationInward;
use Colissimo\Classes\Label\LabelGenerationAuto;
use Colissimo\Classes\Label\OutwardLabelDb;
use Colissimo\Classes\Label\UpdateStatusesAction;
use Colissimo\Classes\Order\InvoiceGenerateAction;
use Colissimo\Classes\Order\OrderStatuses;
use Colissimo\Classes\Pickup\PickupSelection;
use Colissimo\Classes\Pickup\PickupWebService;
use Colissimo\Classes\Settings\AdminNotices;
use Colissimo\Classes\Shipping\CapabilitiesPerCountry;
use Colissimo\Classes\Shipping\ShippingMethods;
use Colissimo\Classes\Shipping\ShippingZones;
use Colissimo\Classes\Slip\SlipDb;
use Colissimo\Classes\Slip\SlipGeneration;
use Colissimo\Core\Ajax;
use Colissimo\Core\Cron;
use Colissimo\Core\Register;
use Colissimo\Core\RegisterWCEmail;
use Colissimo\Core\Update;

defined('ABSPATH') || die('Restricted Access');

class InitGlobal {
    public function __construct() {
        Register::register('lpcAdminNotices', new AdminNotices());
        Register::register('outwardLabelDb', new OutwardLabelDb());
        Register::register('inwardLabelDb', new InwardLabelDb());
        Register::register('bordereauDb', new SlipDb());
        Register::register('ajaxDispatcher', new Ajax());
        Register::register('invoiceGenerateAction', new InvoiceGenerateAction());
        Register::register('orderStatuses', new OrderStatuses());
        Register::register('checkoutApi', new CheckoutApi());
        Register::register('shippingMethods', new ShippingMethods());
        Register::register('accountApi', new AccountApi());
        Register::register('pickupSelection', new PickupSelection());
        Register::register('pickupWebService', new PickupWebService());
        Register::register('pickupWidgetApi', new PickupWidgetApi());
        Register::register('relaysApi', new RelaysApi());
        Register::register('capabilitiesPerCountry', new CapabilitiesPerCountry());
        Register::register('shippingZones', new ShippingZones());
        Register::register('labelGenerationApi', new LabelGenerationApi());
        Register::register('colissimoStatus', new LabelStatus());
        Register::register('unifiedTrackingApi', new TrackingApi());
        Register::register('updateStatusesAction', new UpdateStatusesAction());
        Register::register('labelGenerationInward', new LabelGenerationInward());
        Register::register('labelGenerationOutward', new LabelGenerationOutward());
        Register::register('labelGenerationAuto', new LabelGenerationAuto());
        Register::register('lpcInwardLabelEmailManager', new InwardLabelEmailManager());
        Register::register('lpcOutwardLabelEmailManager', new OutwardLabelEmailManager());
        Register::register('lpcRegisterWCEmail', new RegisterWCEmail());
        Register::register('bordereauGeneration', new SlipGeneration());
        Register::register('labelPurge', new LabelPurge());
        Register::register('lpcCron', new Cron());
        Register::register('lpcUpdate', new Update());
        Register::register('labelInwardDownloadAccountAction', new InwardDownloadAccountAction());
    }
}
