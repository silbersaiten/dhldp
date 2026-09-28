<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.12
 * @link      https://www.silbersaiten.de
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/classes/DHLDPApiRest.php');
require_once(dirname(__FILE__) . '/classes/DHLDPLabel.php');
require_once(dirname(__FILE__) . '/classes/DHLDPPackage.php');
require_once(dirname(__FILE__) . '/classes/DHLDPOrder.php');
require_once(dirname(__FILE__) . '/classes/Helper/ConfigurationHelperTrait.php');
require_once(dirname(__FILE__) . '/classes/Service/DHLRestService.php');
require_once(dirname(__FILE__) . '/classes/DPRestApi.php');
require_once(dirname(__FILE__) . '/classes/DPLabel.php');
require_once(dirname(__FILE__) . '/classes/DHLDPRestClient.php');

class DhlDp extends Module
{
    use \PrestaShop\Module\dhldp\Helper\ConfigurationHelperTrait;

    const DHL_PROFILE = 'STANDARD_GRUPPENPROFIL';

    /** @var \PrestaShop\Module\dhldp\classes\DHLDPApiRest */
    public $dhldp_api_rest;
    public $is177;
    public $is16;

    /** @var DPRestApi */
    protected $dp_api;

    /** @var \PrestaShop\Module\dhldp\Service\DHLRestService */
    protected $dhl_service;

    public function __construct()
    {
        $this->name = 'dhldp';
        $this->tab = 'shipping_logistics';
        $this->version = '3.2.12';
        $this->author = 'Silbersaiten';
        $this->module_key = '96d5521c4c1259e8e87786597735aa4e';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DHL Deutschepost');
        $this->description = $this->l('DHL and Deutschepost shipment service');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->dp_api = new DPRestApi();
        $this->dhldp_api_rest = new \PrestaShop\Module\dhldp\classes\DHLDPApiRest($this);
        $this->dhl_service = new \PrestaShop\Module\dhldp\Service\DHLRestService($this, $this->dhldp_api_rest);
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->is177 = version_compare(_PS_VERSION_, '1.7.7.0') >= 0 ? 1 : 0;
        $this->is16 = (version_compare(_PS_VERSION_, '1.6.0.0') >= 0 && version_compare(_PS_VERSION_, '1.7.0.0') < 0) ? 1 : 0;
    }

    protected function hasRestErrors()
    {
        return is_array($this->dhldp_api_rest->errors) && count($this->dhldp_api_rest->errors) > 0;
    }

    protected function getRestErrors($clear = true)
    {
        if (!$this->hasRestErrors()) {
            return array();
        }

        $errors = $this->dhldp_api_rest->errors;
        if ($clear) {
            $this->dhldp_api_rest->errors = array();
        }
        return $errors;
    }

    public function getRestErrorsForController($clear = true)
    {
        return $this->getRestErrors($clear);
    }

    protected function appendRestErrors(array &$errors)
    {
        if ($this->hasRestErrors()) {
            $errors = array_merge($errors, $this->getRestErrors());
        }
    }

    public function install()
    {
        $return = true;
        $return &= parent::install();
        $return &= $this->createDbTables();
        $return &= $this->installTab('AdminDhldp', 'DHL', 'AdminParentShipping', true);
        $return &= $this->installTab('AdminDhldpSettingsDhl', 'DHL settings', 'AdminParentShipping', false);
        $return &= $this->installTab('AdminDhldpSettingsDp', 'DHL DP settings', 'AdminParentShipping', false);
        $return &= $this->installTab('AdminDhldpManifest', 'DHL manifest', 'AdminParentShipping', false);
        $return &= $this->installTab('AdminDhldpInformation', 'DHL Information', 'AdminParentShipping', false);
        $return &= $this->installTab('AdminDhldpAjax', 'DHL Ajax', 'AdminParentShipping', false);
        $return &= $this->registerHook('displayBackOfficeHeader');
        $return &= $this->registerHook('displayAdminOrder');
        $return &= $this->registerHook('actionOrderReturn');
        $return &= $this->registerHook('actionObjectOrderReturnUpdateAfter');
        $return &= $this->registerHook('displayHeader');
        $return &= $this->registerHook('actionProductAdd');
        $return &= $this->registerHook('actionProductUpdate');
        $return &= $this->registerHook('actionProductDelete');
        $return &= $this->registerHook('actionProductAttributeDelete');
        $return &= $this->registerHook('displayAdminProductsExtra');
        $return &= $this->registerHook('actionGetAdminOrderButtons');
        $return &= ((version_compare(_PS_VERSION_, '1.7', '<')) ? $this->registerHook('extraCarrier') : $this->registerHook('displayAfterCarrier'));
        $return &= $this->createHook('actionGetIDDeliveryAddressByIDCarrier');
        $return &= $this->createHook('actionGetIDOrderStateByIDCarrier');

        Configuration::updateValue('DHLDP_DHL_API_VERSION', '2.1');
        Configuration::updateValue('DHLDP_DHL_COUNTRY', 'DE');
        Configuration::updateValue('DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES', 0);
        Configuration::updateValue('DHLDP_DHL_DEF_GOGREEN_PLUS', 0);
        $return &= $this->installApiCredentials();

        if (!Configuration::hasKey('DHLDP_INTRANSIT_MAIL')) {
            Configuration::updateValue('DHLDP_INTRANSIT_MAIL', 1);
        }

        $this->dp_api->retrievePageFormats();
        return (bool)$return;
    }

    public function installApiCredentials()
    {
        $credentials = array(
            'PRODWS_USERNAME' => 'silbersaiten',
            'PRODWS_PASSWORD' => 'vZ&xu$B7o0',
            'DHL_SDX_CIGUSER' => 'prestashop',
            'DHL_SDX_CIGPASS' => ',E&Sg9z<Wq?>',
            'DHL_SDX_RETOURE_USER' => '2222222222_customer',
            'DHL_SDX_RETOURE_SIGN' => 'uBQbZ62!ZiBiVVbhc',
            'DHLDP_DHL_SDX_USER' => 'user-valid',
            'DHLDP_DHL_SDX_PASS' => 'SandboxPasswort2023!',
            'DHLDP_DHL_CLIENT_ID' => 'tbGdRSxemIWAoijCCSLBgTBARAWjUGTc',
            'DHLDP_DHL_CLIENT_SECRET' => 'hZaFXx3hfJ2Anhw9',
            'DHLDP_DP_CLIENT_ID' => 'prestashop',
            'DHLDP_DP_CLIENT_SECRET' => 'hZaFXx3hfJ2Anhw9',
            'APPNAME' => 'zt12345',
            'PASSWORD' => 'geheim',
        );

        $result = true;
        foreach ($credentials as $name => $value) {
            $result &= Configuration::updateGlobalValue($name, $value);
        }

        return (bool)$result;
    }

    public function uninstall()
    {
        $return = true;
        $return &= $this->uninstallTab('AdminDhldp');
        $return &= $this->uninstallTab('AdminDhldpSettingsDhl');
        $return &= $this->uninstallTab('AdminDhldpSettingsDp');
        $return &= $this->uninstallTab('AdminDhldpInformation');
        $return &= $this->uninstallTab('AdminDhldpManifest');
        $return &= $this->uninstallTab('AdminDhldpAjax');
        $return &= $this->removeHook('actionGetIDDeliveryAddressByIDCarrier');
        $return &= $this->removeHook('actionGetIDOrderStateByIDCarrier');
        $return &= parent::uninstall();
        return (bool)$return;
    }

    public function createHook($name, $title = '')
    {
        if (!Hook::getIdByName($name)) {
            $hook = new Hook();
            $hook->name = $name;
            $hook->title = $title;
            return $hook->add();
        }
        return true;
    }

    public function removeHook($name)
    {
        $id = Hook::getIdByName($name);
        if ($id) {
            $hook = new Hook($id);
            return $hook->delete();
        }
        return true;
    }

    public function createDbTables()
    {
        $return = true;
        $return &= (bool)Db::getInstance()->Execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dhldp_label` (
                `id_dhldp_label` int(11) NOT NULL AUTO_INCREMENT,
                `id_order_carrier` int(11) NOT NULL,
                `product_code` varchar(30) NOT NULL,
                `options` text,
                `shipment_number` varchar(255) DEFAULT NULL,
                `label_url` varchar(500) DEFAULT NULL,
                `export_label_url` varchar(500) DEFAULT NULL,
                `cod_label_url` varchar(500) DEFAULT NULL,
                `return_label_url` varchar(500) DEFAULT NULL,
                `is_complete` tinyint(1) NOT NULL DEFAULT \'0\',
                `is_return` tinyint(1) NOT NULL DEFAULT \'0\',
                `with_return` tinyint(1) NOT NULL DEFAULT \'0\',
                `api_version` varchar(10) NOT NULL DEFAULT \'1.0\',
                `shipment_date` datetime,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                `id_order_return` int(11),
                `routing_code` varchar(50),
                `idc` varchar(50),
                `idc_type` varchar(20),
                `int_idc` varchar(50),
                `int_idc_type` varchar(20),
                PRIMARY KEY (`id_dhldp_label`)
                ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8'
        );
        $return &= (bool)Db::getInstance()->Execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dhldp_package` (
                `id_dhldp_package` int(11) NOT NULL AUTO_INCREMENT,
                `id_dhldp_label` int(11) NOT NULL,
                `length` int(11) NOT NULL DEFAULT \'0\',
                `width` int(11) NOT NULL DEFAULT \'0\',
                `height` int(11) NOT NULL DEFAULT \'0\',
                `weight` decimal(20,6) NOT NULL DEFAULT \'0\',
                `package_type` varchar(30) NOT NULL,
                `shipment_number` varchar(255) DEFAULT NULL,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                `last_track_status` varchar(10),
                `last_track_descr` varchar(400),
                `last_track_date` datetime,
                `last_track_date_upd` datetime,
                `last_track_delivery` tinyint(1),
                PRIMARY KEY (`id_dhldp_package`)
                ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8'
        );

        $return &= (bool)Db::getInstance()->Execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dhldp_order` (
                `id_dhldp_order` int(11) NOT NULL AUTO_INCREMENT,
                `id_cart` int(11) NOT NULL,
                `id_order` int(11),
                `permission_tpd` tinyint(1) NOT NULL DEFAULT \'0\',
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_dhldp_order`)
                ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8'
        );

        $return &= (bool)Db::getInstance()->Execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dhldp_product_customs` (
                `id_product` int(11) NOT NULL,
                `id_product_attribute` int(11),
                `customs_tariff_number` varchar(10),
                `country_of_origin` varchar(2),
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_product`, `id_product_attribute`)
                ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8'
        );

        $return &= (bool)Db::getInstance()->Execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dhldp_dp_label` (
                `id_dhldp_dp_label` int(11) NOT NULL AUTO_INCREMENT,
                `id_order_carrier` int(11) NOT NULL,
                `product` int(11) NOT NULL,
                `total` decimal(20,6) NOT NULL DEFAULT \'0\',
                `wallet_ballance` decimal(20,6) NOT NULL DEFAULT \'0\',
                `additional_info` varchar(80) DEFAULT NULL,
                `dp_order_id` varchar(255) DEFAULT NULL,
                `dp_voucher_id` varchar(64) DEFAULT NULL,
                `dp_link` varchar(255) DEFAULT NULL,
                `is_complete` tinyint(1) NOT NULL DEFAULT \'0\',
                `dp_track_id` varchar(64) DEFAULT NULL,
                `manifest_link` varchar(255) DEFAULT NULL,
                `label_format` varchar(3) DEFAULT NULL,
                `label_position` varchar(255) DEFAULT NULL,
                `page_format_id` int(11) NOT NULL  DEFAULT \'0\',
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_dhldp_dp_label`)
                ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8'
        );
        $return &= (bool)Db::getInstance()->Execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dhldp_dp_productlist` (
                `id_dhldp_dp_productlist` int(11) NOT NULL AUTO_INCREMENT,
                `id` int(11) NOT NULL,
                `name` varchar(256) NOT NULL,
                `price` decimal(20,2) NOT NULL DEFAULT \'0\',
                `price_contract` decimal(20,2) NOT NULL DEFAULT \'0\',
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_dhldp_dp_productlist`)
                ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8'
        );
        return $return;
    }

    public function deleteDeliveryLabel($shipment_number, $id_shop = null)
    {
        $this->dhldp_api_rest->setApiVersionByIdShop($id_shop);
        return $this->dhl_service->deleteDeliveryLabel($shipment_number, $id_shop);
    }

    public function doManifest($shipment_number, $id_shop = null)
    {
        $this->dhldp_api_rest->setApiVersionByIdShop($id_shop);
        return $this->dhl_service->doManifest($shipment_number, $id_shop);
    }

    public function createDhlRetoureLabel($sender_address, $id_order_carrier, $reference_number, $id_order_return = 0, $id_shop = null)
    {
        $this->dhldp_api_rest->setApiVersionByIdShop($id_shop);
        return $this->dhl_service->createReturnLabel($sender_address, $id_order_carrier, $reference_number, $id_order_return, $id_shop);
    }

    public function createDhlDeliveryLabel(
        $dhldp_delivery_address,
        $product_code,
        $packages,
        $options,
        $id_order_carrier,
        $reference_number,
        $is_return = false,
        $with_return = false,
        $id_order_return = 0,
        $id_shop = null,
        $dhl_label_validation = false
    )
    {
        $order_carrier = new OrderCarrier((int)$id_order_carrier);
        $id_order = $order_carrier->id_order;
        $order = new Order((int)$id_order);

        if (isset($options['addit_services']['show_DHLDP_additional_services'])) {
            unset($options['addit_services']['show_DHLDP_additional_services']);
        }
        if (isset($options['export_docs']['show_DHLDP_export_documents'])) {
            unset($options['export_docs']['show_DHLDP_export_documents']);
        }

        $this->dhldp_api_rest->setApiVersionByIdShop($id_shop);
        $aproduct_code = explode(':', $product_code);
        $receiver = $dhldp_delivery_address;
        $shipper = $this->dhldp_api_rest->getShipper($id_shop);
        $details = $this->dhldp_api_rest->getShipperDetails($packages);
        $def = $this->dhldp_api_rest->getDefinedProducts($aproduct_code[0], $receiver['countryISOCode'], $shipper['countryISOCode'], $this->dhldp_api_rest->getApiVersion());

        if (self::getConfig('DHL_MODE', $id_shop) == 1) {
            $ekp = self::getConfig('DHL_LIVE_EKP', $id_shop);
        } else {
            $ekp = \PrestaShop\Module\dhldp\classes\DHLDPApiRest::$dhl_sbx_ekp[$this->dhldp_api_rest->getApiVersion()];
        }

        $shipment_order = array(
            'profile' => self::DHL_PROFILE,
            'dhl_label_validation' => $dhl_label_validation,
            'shipments' => array(
                'product' => $def['alias_v2'],
                'billingNumber' => $ekp . $def['procedure'] . $aproduct_code[1],
                'refNo' => $reference_number,
                'shipper' => $shipper,
                'consignee' => $receiver,
                'details' => $details,
            ),
        );

        if ((int)self::getConfig('DHL_SHIPPER_TYPE', $id_shop) === 1
            && self::getConfig('DHL_REFERENCE', $id_shop) != ''
        ) {
            $shipment_order['shipments']['shipper'] = array(
                'shipperRef' => self::getConfig('DHL_REFERENCE', $id_shop),
            );
        }

        if ($this->dhldp_api_rest->getMajorApiVersion() != 3) {
            $shipment_order['LabelResponseType'] = 'URL';
            $shipment_order['PRINTONLYIFCODEABLE'] = 0;
        } else {
            $shipment_order['PrintOnlyIfCodeable'] = array('active' => 0);
        }

        if ($with_return === true && in_array('DHLRetoure', $def['services'])) {
            $shipment_order['Shipment']['ShipmentDetails']['returnShipmentAccountNumber'] = $ekp . '07' .
                ((self::getConfig('DHL_RETURN_PARTICIPATION', $id_shop) != '') ? self::getConfig('DHL_RETURN_PARTICIPATION', $id_shop) : '01');
            $shipment_order['Shipment']['ShipmentDetails']['returnShipmentReference'] = 'Return for ' . $reference_number;
            $shipment_order['Shipment']['ReturnReceiver'] = $shipper;
            $shipment_order['Shipment']['ShipmentDetails']['dhlRetoure'] = [];
            $shipment_order['Shipment']['ShipmentDetails']['dhlRetoure']['billingNumber'] = $ekp . '07' . ((self::getConfig('DHL_RETURN_PARTICIPATION', $id_shop) != '') ? self::getConfig('DHL_RETURN_PARTICIPATION', $id_shop) : '01');
            $shipment_order['Shipment']['ShipmentDetails']['dhlRetoure']['returnAddress'] = $shipper;

        } else {
            $with_return = false;
        }

        if (isset($options['addit_services']['DayOfDelivery']) && $options['addit_services']['DayOfDelivery'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['DayOfDelivery'] = array(
                'active' => '1',
                'details' => $options['addit_services']['DayOfDelivery']
            );
        }
        if (isset($options['addit_services']['DeliveryTimeframe']) && $options['addit_services']['DeliveryTimeframe'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['DeliveryTimeframe'] = array(
                'active' => '1',
                'type' => $options['addit_services']['DeliveryTimeframe']
            );
        }
        if (isset($options['addit_services']['PreferredTime']) && $options['addit_services']['PreferredTime'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['PreferredTime'] = array(
                'active' => '1',
                'type' => $options['addit_services']['PreferredTime']
            );
        }
        if (isset($options['addit_services']['IndividualSenderRequirement']) && $options['addit_services']['IndividualSenderRequirement'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['IndividualSenderRequirement'] = array(
                'active' => '1',
                'details' => $options['addit_services']['IndividualSenderRequirement']
            );
        }
        if (isset($options['addit_services']['PackagingReturn']) && $options['addit_services']['PackagingReturn'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['PackagingReturn'] = array('active' => '1');
        }
        if (isset($options['addit_services']['ReturnImmediately']) && $options['addit_services']['ReturnImmediately'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['ReturnImmediately'] = array('active' => '1');
        }
        if (isset($options['addit_services']['NoticeOfNonDeliverability']) && $options['addit_services']['NoticeOfNonDeliverability'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['NoticeOfNonDeliverability'] = array('active' => '1');
        }
        if (isset($options['addit_services']['ShipmentHandling']) && $options['addit_services']['ShipmentHandling'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['ShipmentHandling'] = array(
                'active' => '1',
                'type' => $options['addit_services']['ShipmentHandling']
            );
        }
        if (isset($options['addit_services']['Endorsement']) && $options['addit_services']['Endorsement'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['Endorsement'] = array(
                'active' => '1',
                'type' => $options['addit_services']['Endorsement']
            );
        }
        if (isset($options['addit_services']['VisualCheckOfAge']) && $options['addit_services']['VisualCheckOfAge'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['VisualCheckOfAge'] = array(
                'active' => '1',
                'type' => $options['addit_services']['VisualCheckOfAge']
            );
        }
        if (isset($options['addit_services']['PreferredLocation']) && $options['addit_services']['PreferredLocation'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['PreferredLocation'] = array(
                'active' => '1',
                'details' => $options['addit_services']['PreferredLocation']
            );
        }
        if (isset($options['addit_services']['PreferredNeighbour']) && $options['addit_services']['PreferredNeighbour'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['PreferredNeighbour'] = array(
                'active' => '1',
                'details' => $options['addit_services']['PreferredNeighbour']
            );
        }
        if (isset($options['addit_services']['PreferredDay']) && trim($options['addit_services']['PreferredDay']) != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['PreferredDay'] = array(
                'active' => '1',
                'details' => $options['addit_services']['PreferredDay']
            );
        }
        $goGreenPlusEnabled = Configuration::get('DHLDP_DHL_DEF_GOGREEN_PLUS', null, null, $order->id_shop);
        $goGreenPlusSupported = $goGreenPlusEnabled
            && isset($def['services'])
            && in_array('GoGreenPlus', $def['services'])
            && $receiver['countryISOCode'] == 'DE'
            && $shipper['countryISOCode'] == 'DE';

        $goGreenEnabled = Configuration::get('DHLDP_DHL_DEF_GOGREEN', null, null, $order->id_shop);

        // GoGreen Plus supersedes the legacy GoGreen billing participation. If Plus is
        // enabled but unavailable for this route, keep the product's standard billing
        // number instead of silently switching to a legacy GoGreen participation that
        // may not be part of the customer's contract.
        if ($goGreenEnabled && !$goGreenPlusEnabled) {
            if (isset($shipment_order['shipments']['product']) && isset($shipment_order['shipments']['billingNumber'])) {
                $product = $shipment_order['shipments']['product'];
                $billingNumber = $shipment_order['shipments']['billingNumber'];
                $productChanges = [
                    'V01PAK' => '03',
                    'V53WPAK' => '02',
                    'V54EPAK' => '02',
                    'V62KP' => '02',
                    'V66WPI' => '04',
                ];
                if (isset($productChanges[$product])) {
                    $shipment_order['shipments']['billingNumber'] = substr($billingNumber, 0, -2) . $productChanges[$product];
                }
            }
            $shipment_order['Shipment']['ShipmentDetails']['Service']['GoGreen'] = array('active' => '1');
        }
        if ($goGreenPlusSupported) {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['GoGreenPlus'] = array('active' => '1');
        }
        if (isset($options['addit_services']['Perishables']) && $options['addit_services']['Perishables'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['Perishables'] = array('active' => '1');
        }
        if (isset($options['addit_services']['Personally']) && $options['addit_services']['Personally'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['Personally'] = array('active' => '1');
        }
        if (isset($options['addit_services']['NoNeighbourDelivery']) && $options['addit_services']['NoNeighbourDelivery'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['NoNeighbourDelivery'] = array('active' => '1');
        }
        if (isset($options['addit_services']['NamedPersonOnly']) && $options['addit_services']['NamedPersonOnly'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['NamedPersonOnly'] = array('active' => '1');
        }
        if (isset($options['addit_services']['NoticeOfNonDeliverability']) && $options['addit_services']['NoticeOfNonDeliverability'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['NoticeOfNonDeliverability'] = array('active' => '1');
        }
        if (isset($options['addit_services']['ReturnReceipt']) && $options['addit_services']['ReturnReceipt'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['ReturnReceipt'] = true;
        }
        if (isset($options['addit_services']['Premium']) && $options['addit_services']['Premium'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['premium'] = true;
        }
        if (isset($options['addit_services']['CashOnDelivery']) && $options['addit_services']['CashOnDelivery'] != '') {
            $CashOnDelivery_codAmount = $options['addit_services']['CashOnDelivery_codAmount'] == '' ? 0 : $options['addit_services']['CashOnDelivery_codAmount'];
            $shipment_order['Shipment']['ShipmentDetails']['Service']['CashOnDelivery'] = array(
                'active' => '1',
                'addFee' => (isset($options['addit_services']['CashOnDelivery_addFee']) && $options['addit_services']['CashOnDelivery_addFee'] == 1) ? 1 : 0,
                'codAmount' => $order->total_paid + $CashOnDelivery_codAmount
            );
            $shipment_order['Shipment']['ShipmentDetails']['BankData'] = array(
                'accountOwner' => self::getConfig('DHL_ACCOUNT_OWNER', $id_shop),
                'bankName' => self::getConfig('DHL_BANK_NAME', $id_shop),
                'iban' => self::getConfig('DHL_IBAN', $id_shop),
                'bic' => self::getConfig('DHL_BIC', $id_shop),
                'note1' => (isset($options['addit_services']['CashOnDelivery_bankdatanote'])) ? $options['addit_services']['CashOnDelivery_bankdatanote'] : str_replace(
                    '[order_reference_number]',
                    $reference_number,
                    self::getConfig('DHL_NOTE', $id_shop)
                ),
                'note2' => (isset($options['addit_services']['CashOnDelivery_bankdatanote2'])) ? $options['addit_services']['CashOnDelivery_bankdatanote2'] : str_replace(
                    '[order_reference_number]',
                    $reference_number,
                    self::getConfig('DHL_NOTE2', $id_shop)
                ),
            );
        }
        if (isset($options['addit_services']['AdditionalInsurance']) && $options['addit_services']['AdditionalInsurance'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['AdditionalInsurance'] = array(
                'active' => '1',
                'insuranceAmount' => $options['addit_services']['AdditionalInsurance_insuranceAmount']
            );
        }
        if (isset($options['addit_services']['ParcelOutletRouting']) && $options['addit_services']['ParcelOutletRouting'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['ParcelOutletRouting'] = array(
                'active' => '1',
                'details' => $options['addit_services']['ParcelOutletRouting_details']
            );
            if ($options['addit_services']['ParcelOutletRouting_details'] != '') {
                $shipment_order['Shipment']['ShipmentDetails']['Service']['ParcelOutletRouting']['details'] = $options['addit_services']['ParcelOutletRouting_details'];
            }
        }
        if (isset($options['addit_services']['BulkyGoods']) && $options['addit_services']['BulkyGoods'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['BulkyGoods'] = array('active' => '1');
        }
        if (isset($options['addit_services']['SignedForByRecipient']) && $options['addit_services']['SignedForByRecipient'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['SignedForByRecipient'] = array('active' => '1');
        }
        if (isset($options['addit_services']['IdentCheck']) && $options['addit_services']['IdentCheck'] != '') {
            $shipment_order['Shipment']['ShipmentDetails']['Service']['IdentCheck'] = array(
                'active' => '1',
                'Ident' => array(
                    'surname' => $options['addit_services']['IdentCheck_Ident_surname'],
                    'givenName' => $options['addit_services']['IdentCheck_Ident_givenName'],
                    'dateOfBirth' => $options['addit_services']['IdentCheck_Ident_dateOfBirth'],
                    'minimumAge' => $options['addit_services']['IdentCheck_Ident_minimumAge'],
                )
            );
        }

        if (isset($def['export_documents']) && isset($options['export_docs'])) {
            $customs = $this->dhldp_api_rest->getPreparationCustoms($options['export_docs']);
            $shipment_order['customs'] = isset($customs) ? $customs : '';
        }
        if (isset($shipment_order['Shipment']['ShipmentDetails'])) {
            $shipment_order['services'] = $this->dhldp_api_rest->getPreparationServices($shipment_order['Shipment']['ShipmentDetails']);
        }

        $shipment_order_request = array('ShipmentOrder' => $shipment_order);

        if ($this->dhldp_api_rest->getMajorApiVersion() == 3) {
            $shipment_order_request['labelResponseType'] = 'URL';
            if (self::getConfig('DHL_LABEL_FORMAT', $id_shop) != '') {
                $shipment_order_request['labelFormat'] = self::getConfig('DHL_LABEL_FORMAT', $id_shop);
            }
            if (self::getConfig('DHL_RETOURE_LABEL_FORMAT', $id_shop) != '') {
                $shipment_order_request['labelFormatRetoure'] = self::getConfig('DHL_RETOURE_LABEL_FORMAT', $id_shop);
            }
        }

        //If the create anyway button is pressed, then ignore the validation notes
        if (!$shipment_order_request["ShipmentOrder"]["dhl_label_validation"]) {
            $validationResponse = $this->dhldp_api_rest->validateDHLShipment(
                'validationCreateShipmentOrder',
                $shipment_order_request,
                $id_shop);
            $response = [];
            if (is_array($validationResponse) && isset($validationResponse['status']['status'])) {
                if ($validationResponse['status']['status'] === 200) {
                    $response = $this->dhldp_api_rest->callDHLApi(
                        'createShipmentOrder',
                        $shipment_order_request,
                        $id_shop
                    );
                }
            }
        } else {
            $response = $this->dhldp_api_rest->callDHLApi(
                'createShipmentOrder',
                $shipment_order_request,
                $id_shop
            );
        }

        if (is_array($response) && isset($response['items'][0]['shipmentNo'])) {
            $dhl_label = new DHLDPLabel();
            $dhl_label->id_order_carrier = (int)$id_order_carrier;
            $dhl_label->product_code = $product_code;
            $dhl_label->options = json_encode($options);
            $dhl_label->shipment_number = $response['items'][0]['shipmentNo'];
            $dhl_label->label_url = $response['items'][0]['label']['url'];
            $dhl_label->export_label_url = isset($response['items'][0]['customsDoc']['url']) ? $response['items'][0]['customsDoc']['url'] : '';
            if (isset($response['codLabelUrl'])) {
                $dhl_label->cod_label_url = $response['codLabelUrl'];
            } else {
                $dhl_label->cod_label_url = '';
            }
            if (isset($response['returnLabelUrl'])) {
                $dhl_label->return_label_url = $response['returnLabelUrl'];
            } else {
                $dhl_label->return_label_url = '';
            }
            $dhl_label->is_complete = 1;
            $dhl_label->is_return = (int)$is_return;
            $dhl_label->with_return = (int)$with_return;
            $dhl_label->id_order_return = (int)$id_order_return;
            $dhl_label->shipment_date = $options['shipment_date'];
            $dhl_label->api_version = $this->dhldp_api_rest->getApiVersion();

            if (!$dhl_label->save()) {
                return false;
            } else {
                if (isset($shipment_order['shipments']['details']['weight']['value'])) {
                    $dhl_package = new DHLDPPackage();
                    $dhl_package->id_dhldp_label = $dhl_label->id;
                    $dhl_package->weight = $this->dhldp_api_rest->convertKGToPSWeightUnit($shipment_order['shipments']['details']['weight']['value']);
                    $dhl_package->length = $this->dhldp_api_rest->convertMillimetersToPSDimensionUnit($shipment_order['shipments']['details']['dim']['length']);
                    $dhl_package->width = $this->dhldp_api_rest->convertMillimetersToPSDimensionUnit($shipment_order['shipments']['details']['dim']['width']);
                    $dhl_package->height = $this->dhldp_api_rest->convertMillimetersToPSDimensionUnit($shipment_order['shipments']['details']['dim']['height']);
                    $dhl_package->package_type = 'PK';
                    $dhl_package->shipment_number = $response['items'][0]['shipmentNo'];
                    $dhl_package->save();
                } else {
                    foreach ($shipment_order['Shipment']['ShipmentDetails']['ShipmentItem'] as $package) {
                        $dhl_package = new DHLDPPackage();
                        $dhl_package->id_dhldp_label = $dhl_label->id;
                        $dhl_package->weight = $this->dhldp_api_rest->convertKGToPSDimensionUnit($package['value']);
                        $dhl_package->length = $this->dhldp_api_rest->convertMillimetersToPSDimensionUnit($package['length']);
                        $dhl_package->width = $this->dhldp_api_rest->convertMillimetersToPSDimensionUnit($package['width']);
                        $dhl_package->height = $this->dhldp_api_rest->convertMillimetersToPSDimensionUnit($package['height']);
                        $dhl_package->package_type = $package['PackageType'];
                        $dhl_package->shipment_number = $response['shipmentNumber'];
                        $dhl_package->save();
                    }
                }

                if (self::getConfig('DHL_EPRINT_EMAIL', $order->id_shop)) {
                    $template = 'eprint';
                    $subject = $this->l('Label') . ' :' . $order->id;
                    $pdf_decoded = [];
                    if(isset($response['items']) && is_array($response['items'])) {
                        foreach($response['items'] as $_label) {
                            if (isset($_label['label']['url'])) {
                                $pdf_decoded = Tools::file_get_contents($_label['label']['url']);
                            }
                        }
                    }
                    if ($pdf_decoded) {
                        $file_attachment = array(
                            'label' => array(
                                'content' => $pdf_decoded,
                                'name' => 'label_' . $order->id . '.pdf',
                                'mime' => 'application/pdf'
                            )
                        );
                    } else {
                        $file_attachment = array();
                    }

                    if (!Mail::Send(
                        (int)$order->id_lang,
                        $template,
                        $subject,
                        array(),
                        self::getConfig('DHL_EPRINT_EMAIL', $order->id_shop),
                        'HP ePrint',
                        null,
                        null,
                        $file_attachment,
                        null,
                        dirname(__FILE__) . '/mails/',
                        false,
                        (int)$order->id_shop)
                    ) {

                    }

                    // mail for return label
                    if (isset($response['returnLabelUrl']) && $response['returnLabelUrl'] != '' && (($pdf_decoded_return = Tools::file_get_contents($response['returnLabelUrl'])) != '')) {
                        $file_attachment = array(
                            'return_label' => array(
                                'content' => $pdf_decoded_return,
                                'name' => 'return_label_' . $order->id . '.pdf',
                                'mime' => 'application/pdf'
                            )
                        );

                        $subject = $this->l('Return Label') . ' :' . $order->id;

                        if (!Mail::Send(
                            (int)$order->id_lang,
                            $template,
                            $subject,
                            array(),
                            self::getConfig('DHL_EPRINT_EMAIL', $order->id_shop),
                            'HP ePrint',
                            null,
                            null,
                            $file_attachment,
                            null,
                            dirname(__FILE__) . '/mails/',
                            false,
                            (int)$order->id_shop)
                        ) {

                        }
                    }
                }

                if ($is_return === false) {
                    $this->updateOrderCarrierWithTrackingNumber(
                        (int)$id_order_carrier,
                        $response['items'][0]['shipmentNo']
                    );
                    $this->updateOrderStatus((int)$id_order_carrier);
                } else {
                    $id_shop = $order->id_shop;
                    if (self::getConfig('DHL_RETURN_MAIL', $id_shop)) {
                        $customer = new Customer((int)$order->id_customer);
                        $data = array(
                            '{firstname}' => $customer->firstname,
                            '{lastname}' => $customer->lastname,
                            '{order_name}' => $order->reference,
                            '{id_order}' => $order->id
                        );
                        $template = 'dhl_return_label';
                        $subject = $this->l('Return label');

                        $pdf_file = $this->getLabelFilePathByLabelUrl($response['labelUrl']);

                        if ($pdf_file != '') {
                            $file_attachment = array(
                                'dhl_return_label' => array(
                                    'content' => Tools::file_get_contents($pdf_file),
                                    'name' => 'dhl_return_label_' . $order->id . '.pdf',
                                    'mime' => 'application/pdf'
                                )
                            );
                        } else {
                            $file_attachment = array();
                        }

                        if (!Mail::Send(
                            (int)$order->id_lang,
                            $template,
                            $subject,
                            $data,
                            $customer->email,
                            $customer->firstname . ' ' . $customer->lastname,
                            null,
                            null,
                            $file_attachment,
                            null,
                            dirname(__FILE__) . '/mails/',
                            false,
                            (int)$order->id_shop
                        )
                        ) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }

    public function hookActionOrderReturn($params)
    {
        /*
         * $params['orderReturn']->id_order
         * $params['orderReturn']->id_customer
         * $params['orderReturn']->state = 1
         */
        $order = new Order((int)$params['orderReturn']->id_order);
        if (Validate::isLoadedObject($order) && $this->dhldp_api_rest->setApiVersionByIdShop($order->id_shop)) {
            if (self::getConfig('DHL_RETURNS_EXTEND', $order->id_shop) &&
                (self::getConfig('DHL_RETURNS_IMMED', $order->id_shop))) {
                // restriction - only for germany
                $order_carriers = $this->filterShipping($order->getShipping(), (int)$order->id_shop);
                if (is_array($order_carriers) && count($order_carriers) > 0) {
                    // change state
                    $params['orderReturn']->state = 2;
                    $params['orderReturn']->save();
                    // mail will be send on hookActionObjectOrderReturnUpdateAfter
                }
            }
        }
    }

    public function getLastNonReturnLabelData($id_order_carrier)
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dhldp_label` l  WHERE l.`id_order_carrier`= ' .
            (int)$id_order_carrier . ' AND l.is_return != 1  ORDER BY l.`date_add` DESC LIMIT 1'
        );
    }

    public function getLastReturnLabelDataForIdOrderReturn($id_order_carrier, $id_order_return)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dhldp_label` l  WHERE l.`id_order_carrier`= ' .
            (int)$id_order_carrier . ' AND l.`id_order_return`=' . (int)$id_order_return . ' ORDER BY l.`date_add` DESC'
        );
    }

    public function hookActionObjectOrderReturnUpdateAfter($params)
    {
        /*
         * $params['object']
         */
        $order = new Order((int)$params['object']->id_order);
        if (Validate::isLoadedObject($order) && $this->dhldp_api_rest->setApiVersionByIdShop($order->id_shop)) {
            if (self::getConfig('DHL_RETURNS_EXTEND', $order->id_shop) && $params['object']->state == 2) {
                // restriction - only for germany
                //if ($this->isGermanyAddress($order->id_address_delivery)) {
                $order_carriers = $this->filterShipping($order->getShipping(), $order->id_shop);
                if (is_array($order_carriers) && count($order_carriers) > 0) {
                    foreach ($order_carriers as $order_carrier) {
                        $last_label = $this->getLastNonReturnLabelData($order_carrier['id_order_carrier']);

                        if (is_array($last_label) && isset($last_label[0]['id_dhldp_label'])) {
                            //send mail with button
                            $customer = new Customer((int)$order->id_customer);
                            $data = array(
                                '{firstname}' => $customer->firstname,
                                '{lastname}' => $customer->lastname,
                                '{order_name}' => $order->reference,
                                '{id_order}' => $order->id,
                                '{order_return_url}' => Context::getContext()->link->getPageLink(
                                    'order-follow',
                                    true,
                                    Context::getContext()->language->id,
                                    null,
                                    false,
                                    $order->id_shop
                                )
                            );
                            $template = 'dhl_return_approved';
                            $subject = $this->l('Return has been approved. Get DHL Return label');
                            $file_attachment = array();
                            Mail::Send(
                                (int)$order->id_lang,
                                $template,
                                $subject,
                                $data,
                                $customer->email,
                                $customer->firstname . ' ' . $customer->lastname,
                                null,
                                null,
                                $file_attachment,
                                null,
                                dirname(__FILE__) . '/mails/',
                                false,
                                (int)$order->id_shop
                            );
                        }
                    }
                }
                //}
            }
        }
    }

    public function getDHLAddressTypes()
    {
        return array(
            'RE' => array('name' => $this->l('Regular address'), 'prefix' => ''),
            'PF' => array('name' => $this->l('DHL Postfiliale'), 'prefix' => 'Postfiliale'),
            'PS' => array('name' => $this->l('DHL Packstation'), 'prefix' => 'Packstation'),
        );
    }

    public function getTranslationPFApiMessage($key)
    {
        $trans = array(
            'No result available.' => $this->l('No result available.'),
            'Zip or city required.' => $this->l('Zip or city required.'),
            'Missing street.' => $this->l('Missing street.'),
            'Invalid zip.' => $this->l('Invalid zip.'),
            'Invalid zip length.' => $this->l('Invalid zip length.'),
            'Invalid city length.' => $this->l('Invalid city length.'),
            'Invalid street length.' => $this->l('Invalid street length.'),
            'Invalid street number length.' => $this->l('Invalid street number length.'),
        );
        if (isset($trans[$key])) {
            return $trans[$key];
        }
        return $key;
    }

    public function getGoogleMapApiKey($id_shop = null)
    {
        return self::getConfig('DHL_GOOGLEMAPAPIKEY', $id_shop);
    }

    public function hookDisplayHeader($params)
    {
        // restriction - only for germany
        if (($this->context->controller instanceof \PrestaShopBundle\Controller\Admin\Sell\Address\AddressController) &&
            self::getConfig('DHL_PFPS', $this->context->shop->id)) {
            if (version_compare(_PS_VERSION_, '8.0', '<')) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addjqueryPlugin('fancybox');
            $this->context->controller->addjqueryPlugin('scrollTo');

            // connect google map script
            if (self::getConfig('DHL_PFPS_MAP', $this->context->shop->id)) {
                if (version_compare(_PS_VERSION_, '1.7', '<')) {
                    $this->context->controller->addJS(
                        '//maps.google.com/maps/api/js?region=' . $this->context->language->iso_code . '&key=' . $this->getGoogleMapApiKey($this->context->shop->id)
                    );
                } else {
                    $uri = '//maps.google.com/maps/api/js?region=' . $this->context->language->iso_code . '&key=' . $this->getGoogleMapApiKey($this->context->shop->id);
                    $this->context->controller->registerJavascript(
                        sha1($uri),
                        $uri,
                        array('position' => 'bottom', 'priority' => 80, 'server' => 'remote')
                    );
                }
                $this->context->smarty->assign('dhldp_pfps_map', '1');
            } else {
                $this->context->smarty->assign('dhldp_pfps_map', '0');
            }

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->context->controller->addJS($this->_path . 'views/js/address.js');
                $this->context->controller->addCSS($this->_path . 'views/css/map.css');
            } else {
                $this->context->controller->registerJavascript('dhldp_address', 'modules/' . $this->name . '/views/js/address.js', array('position' => 'bottom', 'priority' => 100));
                $this->context->controller->registerStylesheet('dhldp_map', 'modules/' . $this->name . '/views/css/map.css', array('media' => 'all', 'priority' => 150));
            }

            $dhldp_address_data = array(
                'address_types' => $this->getDHLAddressTypes(),
                'input_values' => array()
            );


            $this->context->smarty->assign('dhldp_address_data', $dhldp_address_data);
            $this->context->smarty->assign(
                'dhldp_ajax',
                $this->context->link->getModuleLink($this->name, 'address', array('ajax' => true), true)
            );
            $this->context->smarty->assign('dhldp_path', $this->getPathUri());

            if (Configuration::get('PS_RESTRICT_DELIVERED_COUNTRIES')) {
                $countries = Carrier::getDeliveredCountries($this->context->language->id, true, true);
            } else {
                $countries = Country::getCountries($this->context->language->id, true);
            }
            $this->context->smarty->assign('dhldp_country_data', $countries);
            return $this->display(__FILE__, '/views/templates/hook/address.tpl');

        } elseif (($this->context->controller instanceof OrderController)) {
            if (version_compare(_PS_VERSION_, '8.0', '<')) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addjqueryPlugin('fancybox');
            $this->context->controller->addjqueryPlugin('scrollTo');

            if (self::getConfig('DHL_CONFIRMATION_PRIVATE')) {
                if (version_compare(_PS_VERSION_, '1.7', '<')) {
                    $this->context->controller->addJS($this->_path . 'views/js/private.js');
                    $this->context->controller->addCSS($this->_path . 'views/css/private.css');
                } else {
                    $this->context->controller->registerJavascript('dhl_private', 'modules/' . $this->name . '/views/js/private.js', array('position' => 'bottom', 'priority' => 100));
                    $this->context->controller->registerStylesheet('dhl_private', 'modules/' . $this->name . '/views/css/private.css', array('media' => 'all', 'priority' => 150));
                }
            }

            // connect google map script
            if (self::getConfig('DHL_PFPS_MAP', $this->context->shop->id)) {
                if (version_compare(_PS_VERSION_, '1.7', '<')) {
                    $this->context->controller->addJS(
                        '//maps.google.com/maps/api/js?region=' . $this->context->language->iso_code . '&key=' . $this->getGoogleMapApiKey($this->context->shop->id)
                    );
                } else {
                    $uri = '//maps.google.com/maps/api/js?region=' . $this->context->language->iso_code . '&key=' . $this->getGoogleMapApiKey($this->context->shop->id);
                    $this->context->controller->registerJavascript(
                        sha1($uri),
                        $uri,
                        array('position' => 'bottom', 'priority' => 80, 'server' => 'remote')
                    );
                }
                $this->context->smarty->assign('dhldp_pfps_map', '1');
            } else {
                $this->context->smarty->assign('dhldp_pfps_map', '0');
            }

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->context->controller->addCSS($this->_path . 'views/css/map.css');
            } else {
                $this->context->controller->registerStylesheet('dhldp_map', 'modules/' . $this->name . '/views/css/map.css', array('media' => 'all', 'priority' => 150));
            }

            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->context->controller->addJS($this->_path . 'views/js/address.js');

            } else {
                $this->context->controller->registerJavascript('dhldp_address', 'modules/' . $this->name . '/views/js/address.js', array('position' => 'bottom', 'priority' => 100));
            }

            $dhldp_address_data = array(
                'address_types' => $this->getDHLAddressTypes(),
                'input_values' => array()
            );

            $this->context->smarty->assign('dhldp_address_data', $dhldp_address_data);
            $this->context->smarty->assign(
                'dhldp_ajax',
                $this->context->link->getModuleLink($this->name, 'address', array('ajax' => true))
            );
            $this->context->smarty->assign('dhldp_path', $this->getPathUri());

            if (Configuration::get('PS_RESTRICT_DELIVERED_COUNTRIES')) {
                $countries = Carrier::getDeliveredCountries($this->context->language->id, true, true);
            } else {
                $countries = Country::getCountries($this->context->language->id, true);
            }
            $this->context->smarty->assign('dhldp_country_data', $countries);
            return $this->display(__FILE__, '/views/templates/hook/address.tpl');
        } elseif (($this->context->controller instanceof OrderFollowController) && $this->dhldp_api_rest->setApiVersionByIdShop($this->context->shop->id)) {
            if (self::getConfig('DHL_RETURNS_EXTEND')) {
                if (version_compare(_PS_VERSION_, '8.0', '<')) {
                    $this->context->controller->addJquery();
                }
                if (version_compare(_PS_VERSION_, '1.7', '<')) {
                    $this->context->controller->addJS($this->_path . 'views/js/order_returns.js');
                } else {
                    $this->context->controller->registerJavascript('dhldp_order_returns', 'modules/' . $this->name . '/views/js/order_returns.js', array('position' => 'bottom', 'priority' => 100));
                }
                $dhl_order_returns = array();
                $ordersReturn = OrderReturn::getOrdersReturn($this->context->customer->id);
                if (is_array($ordersReturn)) {
                    foreach ($ordersReturn as $order_return_index => $order_return) {
                        $url = '';
                        if ($order_return['state'] == 2) {
                            $order = new Order((int)$order_return['id_order']);
                            if (Validate::isLoadedObject($order)) {
                                // restriction - only for germany
                                //if ($this->isGermanyAddress($order->id_address_delivery) && $this->isDomesticDelivery($order->id_shop, $order->id_address_delivery)) {
                                $order_carriers = $this->filterShipping($order->getShipping(), $order->id_shop);

                                if (is_array($order_carriers) && (count($order_carriers) > 0)) {
                                    foreach ($order_carriers as $order_carrier) {
                                        $last_label = $this->getLastNonReturnLabelData(
                                            $order_carrier['id_order_carrier']
                                        );
                                        if (is_array($last_label) && isset($last_label[0]['id_dhldp_label'])) {
                                            $url = $this->context->link->getModuleLink(
                                                $this->name,
                                                'return',
                                                array('id_order_return' => $order_return['id_order_return'])
                                            );
                                        }
                                    }
                                }
                                //}
                            }
                        }
                        $dhl_order_returns[$order_return_index] = array(
                            'id' => $order_return['id_order_return'],
                            'url' => $url
                        );
                    }
                }

                return '<script type="text/javascript">
                var dhldp_translation = {
				"Get_DHL_Return_Label": "' . $this->l('Get DHL Return Label') . '"
		        }
			    var dhldp_order_returns_items = ' . json_encode($dhl_order_returns) .
                    '</script>';
            }
        }
    }

    public function hookDisplayAfterCarrier($params)
    {
        return $this->hookExtraCarrier($params);
    }

    public function hookExtraCarrier($params)
    {
        if (self::getConfig('DHL_CONFIRMATION_PRIVATE')) {
            $ids_dhl = $this->getDHLCarriers(true, true, $params['cart']->id_shop);
            if (is_array($ids_dhl) && count($ids_dhl)) {
                $this->context->smarty->assign(
                    array(
                        'js_dhldp_path' => $this->getPathUri(),
                        'js_dhldp_carriers' => $ids_dhl,
                        'dhl_permission_private' => DHLDPOrder::hasPermissionForTransferring($params['cart']->id)
                    )
                );
                if (version_compare(_PS_VERSION_, '1.7', '>=') || version_compare(_PS_VERSION_, '1.6', '<')) {
                    return $this->display(__FILE__, 'views/templates/hook/private-17.tpl');
                } else {
                    return $this->display(__FILE__, 'views/templates/hook/private.tpl');
                }
            }
        }
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = (int)Tools::getValue('id_product');
        if (!$id_product && array_key_exists('id_product', $params)) {
            $id_product = $params['id_product'];
        }

        if (!$id_product || !Validate::isLoadedObject($product = new Product((int)$id_product, false, (int)$this->context->language->id))) {
            $this->context->smarty->assign(
                array(
                    'allow_to_use' => false,
                    'ctn' => ''
                )
            );
        } else {
            $combinations = array();
            $this->context->smarty->assign(
                array(
                    'product_link_rewrite' => $product->link_rewrite,
                    'product_name' => $product->name,
                    'allow_to_use' => true,
                    'ctn' => Db::getInstance()->getValue('select customs_tariff_number from ' . _DB_PREFIX_ . 'dhldp_product_customs WHERE id_product=' . (int)$id_product . ' AND id_product_attribute=0'),
                    'coo' => Db::getInstance()->getValue('select country_of_origin from ' . _DB_PREFIX_ . 'dhldp_product_customs WHERE id_product=' . (int)$id_product . ' AND id_product_attribute=0'),
                    'combinations' => $combinations
                )
            );
        }

        $this->context->smarty->assign('show_buttons', (version_compare(_PS_VERSION_, '1.7.0.0') >= 0 ? 0 : 1));
        return $this->display(__FILE__, 'admin-products-extra.tpl');
    }

    public function hookActionProductAdd($params)
    {
        if ($params['id_product'] > 0) {
            if (Db::getInstance()->getValue('select customs_tariff_number from ' . _DB_PREFIX_ . 'dhldp_product_customs WHERE id_product=' . (int)$params['id_product'] . ' AND id_product_attribute=0') !== false) {
                Db::getInstance()->update('dhldp_product_customs', array('customs_tariff_number' => pSQL(Tools::getValue('dhldp_ctn', '')), 'country_of_origin' => pSQL(Tools::getValue('dhldp_coo', '')), 'date_upd' => date('Y-m-d H:i:s')), 'id_product=' . (int)$params['id_product'] . ' AND id_product_attribute=0');
            } else {
                Db::getInstance()->insert('dhldp_product_customs', array('customs_tariff_number' => pSQL(Tools::getValue('dhldp_ctn', '')), 'country_of_origin' => pSQL(Tools::getValue('dhldp_coo', '')), 'date_upd' => date('Y-m-d H:i:s'), 'id_product' => (int)$params['id_product'], 'id_product_attribute' => '0', 'date_add' => date('Y-m-d H:i:s')));
            }
        }
    }

    public function hookActionProductUpdate($params)
    {
        if ($params['id_product'] > 0) {
            if (Db::getInstance()->getValue('select customs_tariff_number from ' . _DB_PREFIX_ . 'dhldp_product_customs WHERE id_product=' . (int)$params['id_product'] . ' AND id_product_attribute=0') !== false) {
                Db::getInstance()->update('dhldp_product_customs', array('customs_tariff_number' => pSQL(Tools::getValue('dhldp_ctn', '')), 'country_of_origin' => pSQL(Tools::getValue('dhldp_coo', '')), 'date_upd' => date('Y-m-d H:i:s')), 'id_product=' . (int)$params['id_product'] . ' AND id_product_attribute=0');
            } else {
                Db::getInstance()->insert('dhldp_product_customs', array('customs_tariff_number' => pSQL(Tools::getValue('dhldp_ctn', '')), 'country_of_origin' => pSQL(Tools::getValue('dhldp_coo', '')), 'date_upd' => date('Y-m-d H:i:s'), 'id_product' => (int)$params['id_product'], 'id_product_attribute' => '0', 'date_add' => date('Y-m-d H:i:s')));
            }
        }
    }

    public function hookActionProductDelete($params)
    {
        if ($params['id_product'] > 0) {
            Db::getInstance()->delete('dhldp_product_customs', 'id_product=' . (int)$params['id_product']);
        }
    }

    public function hookActionProductAttributeDelete($params)
    {
        if ($params['id_product'] > 0) {
            if ($params['id_product_attribute'] > 0) {
                Db::getInstance()->delete('dhldp_product_customs', 'id_product=' . (int)$params['id_product'] . ' and id_product_attribute=' . (int)$params['id_product_attribute']);
            } elseif ((int)$params['id_product_attribute'] == 0) {
                Db::getInstance()->delete('dhldp_product_customs', 'id_product=' . (int)$params['id_product'] . ' and id_product_attribute!=0');
            }
        }
    }

    public function filterShipping($shipping, $id_shop)
    {
        $dhl_carriers = $this->getDhlCarriers(true, false, $id_shop);
        $dhl_carriers_ids = array_keys($dhl_carriers);
        $return_shipping = array();
        if (is_array($shipping)) {
            foreach ($shipping as $shipping_item) {
                if (in_array($shipping_item['id_carrier'], $dhl_carriers_ids)) {
                    $shipping_item['default_dhl_product_code'] = $dhl_carriers[$shipping_item['id_carrier']]['product'];
                    $return_shipping[] = $shipping_item;
                }
            }
            return $return_shipping;
        }
        return array();
    }

    public function getLabelData($id_order_carrier)
    {
        if (!is_array($id_order_carrier)) {
            $id_order_carrier = array($id_order_carrier);
        }

        if (count($id_order_carrier) > 0) {
            $selected_values = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'dhldp_label` l
                 WHERE l.`id_order_carrier` IN (' . implode(',', array_map('intval', $id_order_carrier)) . ')' .
                ' ORDER BY `date_add`'
            );

            foreach ($selected_values as $selected_value_index => $selected_value) {
                $product_info = $this->getFormattedAddedDhlProducts(array($selected_value['product_code']));
                if (isset($product_info) && $product_info) {
                    $selected_values[$selected_value_index]['product_name'] = $product_info[0]['fullname'];
                } elseif ($selected_value['product_code'] == 'rp') {
                    $selected_values[$selected_value_index]['label_url'] = $this->getLabelFileURIByLabelUrl($selected_values[$selected_value_index]['label_url']);
                    $selected_values[$selected_value_index]['product_name'] = $this->l('Retoure portal');
                } elseif ($selected_value['product_code'] == 'ra') {
                    $selected_values[$selected_value_index]['label_url'] = $this->getLabelFileURIByLabelUrl($selected_values[$selected_value_index]['label_url']);
                    $selected_values[$selected_value_index]['product_name'] = $this->l('Retoure API');
                } else {
                    $selected_values[$selected_value_index]['product_name'] = '';
                }

                $selected_values[$selected_value_index]['packages'] = DHLDPLabel::getPackages(
                    $selected_value['id_dhldp_label']
                );
                $selected_values[$selected_value_index]['options_decoded'] = json_decode(
                    $selected_value['options'],
                    true
                );
                $selected_values[$selected_value_index]['tracking_url'] = str_replace(
                    '[tracking_number]',
                    $selected_value['shipment_number'],
                    \PrestaShop\Module\dhldp\classes\DHLDPApiRest::$tracking_url
                );
            }
            return $selected_values;
        }
        return false;
    }

    public function getCountryISOCodeByAddressID($id_address)
    {
        $country_and_state = Address::getCountryAndState((int)$id_address);
        $country_iso_code = '';
        if ($country_and_state) {
            $country = new Country((int)$country_and_state['id_country']);
            $country_iso_code = $country->iso_code;
        }
        return $country_iso_code;
    }

    public function isGermanyAddress($id_address)
    {
        if ($this->getCountryISOCodeByAddressID($id_address) == 'DE') {
            return true;
        }
        return false;
    }

    public function isEUAddress($id_address)
    {
        if (in_array($this->getCountryISOCodeByAddressID($id_address), $this->getEUCountriesCodes())) {
            return true;
        }
        return false;
    }

    public function getEUCountriesCodes()
    {
        return array('AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE',
            'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'/*, 'GB'*/);
    }

    public function getFormattedAddedDhlProductsByDeliveryAddress($id_address_delivery, $id_shop = null)
    {
        $to_country_iso_code = $this->getCountryISOCodeByAddressID($id_address_delivery);
        return $this->getFormattedAddedDhlProducts(
            explode(';', self::getConfig('DHL_PRODUCTS', $id_shop)),
            $to_country_iso_code,
            self::getConfig('DHL_COUNTRY', $id_shop),
            self::getConfig('DHL_API_VERSION', $id_shop)
        );
    }

    public function getShippedOrderStates($just_ids = false)
    {
        $states = array();
        foreach (OrderState::getOrderStates($this->context->language->id) as $state) {
            if ($just_ids) {
                $states[] = $state['id_order_state'];
            } else {
                $states[] = $state;
            }
        }
        return $states;
    }

    public function displayDPAdminOrder($params)
    {
        $order = new Order((int)$params['id_order']);
        $label_format = Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $order->id_shop);

        if (Tools::getIsset('submitDPLabelRequest')) {
            $id_address = (int)Tools::getValue('id_address');
            $product = Tools::getValue('product');
            $additional_info = Tools::getValue('additional_info');
            $form_errors = array();

            if (Tools::strlen($additional_info) > 80) {
                $form_errors[] = $this->l('Note is too long.');
            }

            if ($label_format == 'pdf') {
                $page_format = $this->dp_api->getPageFormats((int)Configuration::get('DHLDP_DP_PAGE_FORMAT', false, false, $order->id_shop));
                if ($page_format === false) {
                    $form_errors[] = $this->l('Pdf page format is invalid.');
                } else {
                    if ((int)Tools::getValue('label_position_page') < 1) {
                        $form_errors[] = $this->l('Label position page must be 1 and more');
                    }
                    if ((int)Tools::getValue('label_position_col') < 1) {
                        $form_errors[] = $this->l('Label position page must be 1 and more');
                    }
                    if ((int)Tools::getValue('label_position_col') > $page_format['col']) {
                        $form_errors[] = $this->l('Label position column must be equal or less than ') . ' ' . $page_format['col'];
                    }
                    if ((int)Tools::getValue('label_position_row') < 1) {
                        $form_errors[] = $this->l('Label position page must be 1 and more');
                    }
                    if ((int)Tools::getValue('label_position_row') > $page_format['row']) {
                        $form_errors[] = $this->l('Label position row must be equal or less than ') . ' ' . $page_format['row'];
                    }
                }
            }

            if (count($form_errors) == 0) {
                $id_order_carrier = (int)Tools::getValue('id_order_carrier');
                $label_position = array();
                if ($label_format == 'pdf') {
                    $label_position = array(
                        'page' => (int)Tools::getValue('label_position_page'),
                        'col' => (int)Tools::getValue('label_position_col'),
                        'row' => (int)Tools::getValue('label_position_row')
                    );
                }

                $result = $this->createDPDeliveryLabel(
                    $order->id_shop,
                    $id_address,
                    $product,
                    $additional_info,
                    $label_position,
                    $id_order_carrier
                );

                if (!$result) {
                    if (is_array($this->dp_api->errors) && count($this->dp_api->errors) > 0) {
                        $this->context->smarty->assign('deutschepost_errors', $this->dp_api->errors);
                    } else {
                        $this->context->smarty->assign('deutschepost_errors', array($this->l('Unable to generate label for this request')));
                    }
                } else {
                    //redirect
                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders', true, array(), array('vieworder' => '', 'id_order' => $params['id_order'], 'dpcm' => '1')));
                }
            } else {
                $this->context->smarty->assign('deutschepost_errors', $form_errors);
            }
        }

        $shipping = $this->filterDPShipping($order->getShipping(), (int)$order->id_shop);
        $html = '';
        if (is_array($shipping)) {
            foreach ($shipping as $shipping_item) {
                $labels = $this->getDPLabelData($shipping_item['id_order_carrier']);
                $last_label = array();
                if ($labels) {
                    $last_label = $labels[count($labels) - 1];
                }


                $default_page_format_desc = $this->dhldp_api_rest->getPageFormats(Configuration::get('DHLDP_DP_PAGE_FORMAT', false, false, $order->id_shop));

                $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
                $this->context->smarty->assign(
                    array(
                        'module_path' => __PS_BASE_URI__ . 'modules/' . $this->name . '/',
                        'id_address' => $order->id_address_delivery,
                        'is177' => $this->is177,
                        'carrier' => $shipping_item,
                        'labels' => $labels,
                        'last_label' => $last_label,
                        'def_label_format' => Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $order->id_shop),
                        'def_page_format' => Configuration::get('DHLDP_DP_PAGE_FORMAT', false, false, $order->id_shop),
                        'def_page_format_name' => (isset($default_page_format_desc) ? $default_page_format_desc['name'] : ''),
                        'def_label_position_page' => (int)Configuration::get('DHLDP_DP_POSITION_PAGE', false, false, $order->id_shop),
                        'def_label_position_col' => (int)Configuration::get('DHLDP_DP_POSITION_COL', false, false, $order->id_shop),
                        'def_label_position_row' => (int)Configuration::get('DHLDP_DP_POSITION_ROW', false, false, $order->id_shop),
                        'deutcshepost_products' => $this->dp_api->getProducts(),
                        'predef_deutschepost_product' => Configuration::get('DHLDP_DP_DEF_PRODUCT', null, null, $order->id_shop),
                        'form_action' => ($this->is177) ? $this->context->link->getAdminLink('AdminOrders', true, array(), array(
                            'vieworder' => 1,
                            'id_order' => (int)$order->id,
                        )) : $this->getTabLink('AdminOrders', array('id_order' => $order->id, 'vieworder' => true)),
                        'details_link' => $this->getModuleUrl(array('view' => 'labelDetails')),
                        'module_version' => $this->version,
                        'module_name' => $this->displayName,
                        'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax')
                    )
                );
                $html .= $this->display(__FILE__, 'dp-admin-carriers.tpl');
            }
        }
        return $html;
    }

    public function displayDHLAdminOrder($params)
    {
        $order = new Order((int)$params['id_order']);
        $this->dhldp_api_rest->setApiVersionByIdShop($order->id_shop);
        $feedback_key = 'dhldp_label_feedback_' . (int)$order->id;
        if (isset($this->context->cookie->{$feedback_key})) {
            $feedback = json_decode(base64_decode($this->context->cookie->{$feedback_key}), true);
            unset($this->context->cookie->{$feedback_key});
            $this->context->cookie->write();
            if (is_array($feedback)) {
                $this->context->smarty->assign('dhl_confirmations', $feedback['confirmations']);
                $this->context->smarty->assign('dhl_warnings', $feedback['warnings']);
                $this->context->smarty->assign('dhl_warnings_title', $this->l('THE SHIPPING LABEL WAS CREATED WITH WARNINGS'));
            }
        }
        if (Tools::getIsset('deleteDHLDPDhlLabel')) {
            $dhl_errors = array();
            $dhl_confirmations = array();

            $shipment_number = Tools::getValue('shipment_number');
            if ($shipment_number != '') {
                if (DHLDPLabel::getLabelIDByShipmentNumber($shipment_number) != false) {
                    $result = $this->deleteDeliveryLabel($shipment_number, $order->id_shop);
                    if (!$result) {
                        if ($this->hasRestErrors()) {
                            $this->appendRestErrors($dhl_errors);
                        } else {
                            $dhl_errors[] = $this->l('Unable to delete label for this shipment number');
                        }
                    } else {
                        $dhl_confirmations = $this->l('Shipment has been deleted');
                    }
                } else {
                    $dhl_errors[] = $this->l('No label for this shipment number');
                }
            }
            $this->context->smarty->assign('dhl_errors', $dhl_errors);
            $this->context->smarty->assign('dhl_confirmations', $dhl_confirmations);
        }

        if (Tools::getIsset('doDHLDPDhlManifest')) {
            $dhl_errors = array();
            $dhl_confirmations = array();
            $shipment_number = Tools::getValue('shipment_number');
            if ($shipment_number != '') {
                if (DHLDPLabel::getLabelIDByShipmentNumber($shipment_number) != false) {
                    $result = $this->doManifest($shipment_number, $order->id_shop);
                    if (!$result) {
                        if ($this->hasRestErrors()) {
                            $this->appendRestErrors($dhl_errors);
                        } else {
                            $dhl_errors[] = $this->l('Unable to do manifest for this shipment number');
                        }
                    } else {
                        $dhl_confirmations = $this->l('Manifest has been done');
                    }
                } else {
                    $dhl_errors[] = $this->l('No label for this shipment number');
                }
            }
            $this->context->smarty->assign('dhl_errors', $dhl_errors);
            $this->context->smarty->assign('dhl_confirmations', $dhl_confirmations);
        }

        if (Tools::getIsset('submitDHLDPDhlLabelRequest')
            || Tools::getIsset('submitDHLDPDhlLabelWithReturnRequest')
            || Tools::getIsset('submitDHLDPDhlLabelReturnRequest')
            || Tools::getIsset('submitDHLDPDhlCreateLabelNoValidation')
        ) {

            $id_address = (int)Tools::getValue('id_address');
            $product_code = Tools::getValue('dhl_product_code');
            $id_order_carrier = (int)Tools::getValue('id_order_carrier');
            $address_input = Tools::getValue('address');
            $addit_services_input = Tools::getValue('addit_services');
            $export_docs_input = Tools::getValue('export_docs');
            $with_warning = (bool)self::getConfig('DHL_LABEL_IGNORE_WARNING', $order->id_shop);
            $dhl_label_validation = $with_warning ? true : Tools::getValue('validation_of_dhl_label_creation');


            $receiver_address = $this->dhldp_api_rest->getDHLDeliveryAddress(
                $id_address,
                isset($address_input[$id_order_carrier]) ? $address_input[$id_order_carrier] : false,
                $order
            );

            $formatted_products = $this->getFormattedAddedDhlProducts(array($product_code));
            if (is_array($formatted_products[0])) {
                $aproduct_code = explode(':', $product_code);
                $product_def = $this->dhldp_api_rest->getDefinedProducts(
                    $aproduct_code[0],
                    isset($receiver_address['countryISOCode']) ? $receiver_address['countryISOCode'] : 'DE',
                    $this->dhldp_api_rest->getShipperCountry($order->id_shop),
                    $this->dhldp_api_rest->getApiVersion()
                );
                if (!is_array($product_def)) {
                    $formatted_product = false;
                    $product_params = false;
                } else {
                    $formatted_product = $formatted_products[0];
                    $product_params = $product_def['params'];
                }
            } else {
                $formatted_product = false;
                $product_params = false;
            }

            $dhl_errors = array();
            $dhl_warnings = array();
            $dhl_confirmations = array();

            $packages = array(
                array(
                    'weight' => (float)str_replace(',', '.', Tools::getValue('dhl_weight_package', 0)),
                    'length' => (int)Tools::getValue('dhl_length', 0),
                    'width' => (int)Tools::getValue('dhl_width', 0),
                    'height' => (int)Tools::getValue('dhl_height', 0),
                )
            );
            if ($this->dhldp_api_rest->getMajorApiVersion() == 1 && Tools::getIsset('submitDHLDPDhlLabelWithReturnRequest')) {
                $dhl_errors[] = $this->l('This operation is no available');
            } elseif ($this->dhldp_api_rest->getMajorApiVersion() == 2 && Tools::getIsset('submitDHLDPDhlLabelReturnRequest')) {
                $dhl_errors[] = $this->l('This operation is no available');
            } elseif (Tools::strlen($product_code) == 0) {
                $dhl_errors[] = $this->l('Please select product.');
            } elseif ($formatted_product == false) {
                $dhl_errors[] = $this->l('This product is not added in list.');
            } elseif (isset($product_def['export_documents']) && !isset($export_docs_input[$id_order_carrier])) {
                $dhl_errors[] = $this->l('No data of export document.');
            } elseif (isset($product_def['export_documents']) && (!isset($export_docs_input[$id_order_carrier]['exportType']) || ($export_docs_input[$id_order_carrier]['exportType'] == '') || ($this->getExportTypeOptions($export_docs_input[$id_order_carrier]['exportType']) === false))) {
                $dhl_errors[] = $this->l('Please select export type in export document.');
            } elseif (isset($product_def['export_documents']) && (!isset($export_docs_input[$id_order_carrier]['placeOfCommital']) || ($export_docs_input[$id_order_carrier]['placeOfCommital'] == ''))) {
                $dhl_errors[] = $this->l('Please fill Place of commital in export document.');
            } elseif (isset($product_def['export_documents']) && (!isset($export_docs_input[$id_order_carrier]['additionalFee']) || ($export_docs_input[$id_order_carrier]['additionalFee'] == ''))) {
                $dhl_errors[] = $this->l('Please enter Additional custom fees in export document.');
            } elseif (isset($product_def['export_documents']) && ($export_docs_errors = $this->isValidExportDocPositions($export_docs_input[$id_order_carrier])) != false) {
                foreach ($export_docs_errors as $errors) {
                    $dhl_errors[] = $errors;
                }
            } else {
                $options = array();
                if (isset($addit_services_input[$id_order_carrier])) {
                    $options['addit_services'] = $addit_services_input[$id_order_carrier];
                }
                if (isset($export_docs_input[$id_order_carrier])) {
                    $options['export_docs'] = $export_docs_input[$id_order_carrier];
                }
                $options['shipment_date'] = Tools::getValue('dhl_shipment_date', date('Y-m-d'));

                $with_return = (bool)Tools::getIsset('submitDHLDPDhlLabelWithReturnRequest') ||
                    self::getConfig('DHL_LABEL_WITH_RETURN', $order->id_shop);
                $is_return = false;

                $shipmentReference = (self::getConfig('DHL_REF_NUMBER', $order->id_shop) ? $order->id : $order->reference);
                if (strlen($shipmentReference) < 8) {
                    $shipmentReference = str_pad($shipmentReference, 8, '0', STR_PAD_LEFT);
                }

                $result = $this->createDhlDeliveryLabel(
                    $receiver_address,
                    $product_code,
                    $packages,
                    $options,
                    $id_order_carrier,
                    $shipmentReference,
                    $is_return,
                    $with_return,
                    0,
                    $order->id_shop,
                    $dhl_label_validation
                );
                $dhl_with_warning = (bool)self::getConfig('DHL_LABEL_IGNORE_WARNING', $order->id_shop);
                $this->context->smarty->assign('dhl_with_warning', $dhl_with_warning);
                if (!$result) {
                    if ($this->hasRestErrors()) {
                        $this->appendRestErrors($dhl_errors);
                    } else {
                        if (is_array($this->dhldp_api_rest->warnings) && count($this->dhldp_api_rest->warnings) > 0) {
                            $dhl_warnings_title = $this->l('THE SHIPPING LABEL WAS CREATED WITH WARNINGS');
                            $this->context->smarty->assign('dhl_warnings_title', $dhl_warnings_title);
                        } else {
                            $dhl_errors[] = $this->l('Unable to generate label for this request');
                        }
                    }
                } else {
                    // The order view was loaded before this display hook changed its state.
                    // Start a fresh GET so status, invoices and tracking are rebuilt together.
                    $this->context->cookie->{$feedback_key} = base64_encode(json_encode(array(
                        'confirmations' => $this->dhldp_api_rest->confirmations ?: array($this->l('Shipment order and shipping label have been created.')),
                        'warnings' => (array)$this->dhldp_api_rest->warnings,
                    )));
                    $this->context->cookie->write();
                    $order_url = $this->is177
                        ? $this->context->link->getAdminLink('AdminOrders', true, array(), array('vieworder' => 1, 'id_order' => (int)$order->id))
                        : $this->getTabLink('AdminOrders', array('id_order' => (int)$order->id, 'vieworder' => true));
                    Tools::redirectAdmin($order_url);
                    return '';
                }
            }

            if (is_array($this->dhldp_api_rest->warnings) && count($this->dhldp_api_rest->warnings) > 0) {
                $dhl_warnings = array_merge($dhl_warnings, $this->dhldp_api_rest->warnings);
            }
            $this->context->smarty->assign('dhl_errors', $dhl_errors);
            $this->context->smarty->assign('dhl_warnings', $dhl_warnings);
            $this->context->smarty->assign('dhl_confirmations', $dhl_confirmations);
        }

        $shipping = $this->filterShipping($order->getShipping(), $order->id_shop);
        $html = '';
        $dhl_products = $this->getFormattedAddedDhlProductsByDeliveryAddress(
            $order->id_address_delivery,
            $order->id_shop
        );

        if (is_array($shipping)) {
            foreach ($shipping as $shipping_item) {
                $labels = $this->getLabelData($shipping_item['id_order_carrier']);
                $car = new Carrier((int)$shipping_item['id_carrier']);
                $shipping_item['carrier_name'] = $car->name;
                $last_label = array();
                if (is_array($labels) && count($labels) > 0) {
                    $last_label = $labels[count($labels) - 1];
                }

                $perm_c = DHLDPOrder::getPermissionForTransferring($order->id_cart);
                $product_alias = str_replace(':', '_', $shipping_item["default_dhl_product_code"]);
                $package_length_key = 'DHL_' . $product_alias . '_LENGTH';
                $package_width_key = 'DHL_' . $product_alias . '_WIDTH';
                $package_height_key = 'DHL_' . $product_alias . '_HEIGHT';
                $package_length = self::getConfig($package_length_key, $order->id_shop);
                $package_width = self::getConfig($package_width_key, $order->id_shop);
                $package_height = self::getConfig($package_height_key, $order->id_shop);
                $this->context->smarty->assign(
                    array(
                        'module_path' => __PS_BASE_URI__ . 'modules/' . $this->name . '/',
                        'id_address' => $order->id_address_delivery,
                        'carrier' => $shipping_item,
                        'labels' => $labels,
                        'last_label' => $last_label,
                        'enable_return' => false,
                        'with_return' => false,
                        'dhl_visual_age_check' => self::getConfig('DHL_AGE_CHECK', $order->id_shop),
                        'dhl_products' => $dhl_products,
                        'form_action' => ($this->is177) ? $this->context->link->getAdminLink('AdminOrders', true, array(), array(
                            'vieworder' => 1,
                            'id_order' => (int)$order->id,
                        )) : $this->getTabLink('AdminOrders', array('id_order' => $order->id, 'vieworder' => true)),
                        'details_link' => $this->getModuleUrl(array('view' => 'labelDetails')),
                        'total_products' => $order->getTotalProductsWithTaxes(),
                        'total_weight' => $this->getOrderWeight($order, $product_alias),
                        'package_length' => $package_length,
                        'package_width' => $package_width,
                        'package_height' => $package_height,
                        'shipment_date' => date('Y-m-d'),
                        'dhldp_dhl_products_params' => $dhl_products,
                        'permission_confirmation' => $perm_c,
                        'self' => dirname(__FILE__),
                        'module_version' => $this->version,
                        'module_name' => $this->displayName,
                        'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax')
                    )
                );
                if ($this->is177) {
                    $this->context->smarty->assign('is177', true);
                } else {
                    $this->context->smarty->assign('is177', false);
                }
                if ($this->is16) {
                    $this->context->smarty->assign('is16', true);
                } else {
                    $this->context->smarty->assign('is16', false);
                }

                //update address
                $this->context->smarty->assign(
                    'address',
                    $this->getTemplateVarsForUpdateAddress(
                        $order,
                        $shipping_item['id_order_carrier'],
                        $order->id_address_delivery,
                        $perm_c
                    )
                );

                //addit services
                $this->context->smarty->assign(
                    'addit_services',
                    $this->getTemplateVarsForAdditServices(
                        $order,
                        $shipping_item['id_order_carrier'],
                        $order->id_address_delivery,
                        $perm_c
                    )
                );
                //export docs
                $this->context->smarty->assign(
                    'export_docs',
                    $this->getTemplateVarsForExportDocuments(
                        $order,
                        $shipping_item['id_order_carrier'],
                        $order->id_address_delivery
                    )
                );
                $this->context->smarty->assign('DHLDP_DHL_CREATE_MANIFEST_IN_ORDER', self::getConfig('DHL_CREATE_MANIFEST_IN_ORDER', $order->id_shop));
                $html .= $this->display(__FILE__, 'admin-carriers.tpl');
            }
        }
        return $html;
    }

    public function hookDisplayAdminOrder($params)
    {
        $html = $this->displayDPAdminOrder($params);
        $html .= $this->displayDHLAdminOrder($params);
        return $html;
    }

    public function getOrderWeight($order, $product_alias)
    {
        if (!$product_alias) {
            return 0;
        }
        $weight = 0;
        if (Validate::isLoadedObject($order)) {
            if (self::getConfig('DHL_ORDER_WEIGHT', $order->id_shop)) {
                if (self::getConfig('DHL_WEIGHT_RATE', $order->id_shop) != '') {
                    $weight = round($order->getTotalWeight() * (float)self::getConfig('DHL_WEIGHT_RATE', $order->id_shop), 1);
                } else {
                    $weight = $order->getTotalWeight();
                }
            }
        }
        if ($weight == 0) {
            $weight = (float)self::getConfig('DHL_' . $product_alias . '_WEIGHT', $order->id_shop);
        } else {
            $weight += (float)self::getConfig('DHL_PACK_WEIGHT', $order->id_shop);
        }
        return $weight;
    }

    public function getTemplateVarsForUpdateAddress($order, $id_order_carrier, $id_address_delivery, $perm_c)
    {
        $conf_private = self::getConfig('DHL_CONFIRMATION_PRIVATE', $order->id_shop);
        $oc = new OrderCarrier((int)$id_order_carrier);
        if (Validate::isLoadedObject($oc)) {
            $id_address = Hook::exec('actionGetIDDeliveryAddressByIDCarrier', array('id_carrier' => $oc->id_carrier));
            if ($id_address != false) {
                $id_address_delivery = $id_address;
            }
        }
        $delivery_address = new Address((int)$id_address_delivery);
        $norm_address = $this->dhldp_api_rest->normalizeAddress($delivery_address);
        $zip = '';
        if (isset($norm_address['Address']['Origin']['countryISOCode'])) {
            if ($norm_address['Address']['Origin']['countryISOCode'] == 'DE') {
                if (isset($norm_address['Address']['Zip']['germany'])) {
                    $zip = $norm_address['Address']['Zip']['germany'];
                }
            } elseif ($norm_address['Address']['Origin']['countryISOCode'] == 'GB') {
                if (isset($norm_address['Address']['Zip']['england'])) {
                    $zip = $norm_address['Address']['Zip']['england'];
                }
            } else {
                if (isset($norm_address['Address']['Zip']['other'])) {
                    $zip = $norm_address['Address']['Zip']['other'];
                }
            }
        }

        $addresses_input = Tools::getValue('address');
        $address_input = isset($addresses_input[$id_order_carrier]) ? $addresses_input[$id_order_carrier] : array();

        return array(
            'id_order_carrier' => $id_order_carrier,
            'delivery_address' => $delivery_address,
            'delivery_country' => Country::getNameById(
                $this->context->language->id,
                $delivery_address->id_country
            ),
            'delivery_state' => State::getNameById($delivery_address->id_state),
            'show_update_address' => isset($address_input['show_update_address']) ? $address_input['show_update_address'] : '',
            'name1' => isset($address_input['name1']) ? $address_input['name1'] : (isset($norm_address['name1']) ? $norm_address['name1'] : ''),
            'name2' => isset($address_input['name2']) ? $address_input['name2'] : (isset($norm_address['name2']) ? $norm_address['name2'] : ''),
            'name3' => isset($address_input['name3']) ? $address_input['name3'] : (isset($norm_address['name3']) ? $norm_address['name3'] : ''),
            'address_type' => isset($address_input['address_type']) ? $address_input['address_type'] : (isset($norm_address['Packstation']) ? 'ps' : (isset($norm_address['Postfiliale']) ? 'pf' : 're')),
            'ps_packstation_number' => isset($address_input['ps_packstation_number']) ? $address_input['ps_packstation_number'] : (isset($norm_address['Packstation']['PackstationNumber']) ? $norm_address['Packstation']['PackstationNumber'] : ''),
            'ps_post_number' => isset($address_input['ps_post_number']) ? $address_input['ps_post_number'] : (isset($norm_address['Packstation']['PostNumber']) ? $norm_address['Packstation']['PostNumber'] : ''),
            'ps_zip' => isset($address_input['ps_zip']) ? $address_input['ps_zip'] : (isset($norm_address['Packstation']['Zip']) ? $norm_address['Packstation']['Zip'] : ''),
            'ps_city' => isset($address_input['ps_city']) ? $address_input['ps_city'] : (isset($norm_address['Packstation']['City']) ? $norm_address['Packstation']['City'] : ''),
            'pf_postfiliale_number' => isset($address_input['pf_postfiliale_number']) ? $address_input['pf_postfiliale_number'] : (isset($norm_address['Postfiliale']['PostfilialeNumber']) ? $norm_address['Postfiliale']['PostfilialeNumber'] : ''),
            'pf_post_number' => isset($address_input['pf_post_number']) ? $address_input['pf_post_number'] : (isset($norm_address['Postfiliale']['PostNumber']) ? $norm_address['Postfiliale']['PostNumber'] : ''),
            'pf_zip' => isset($address_input['pf_zip']) ? $address_input['pf_zip'] : (isset($norm_address['Postfiliale']['Zip']) ? $norm_address['Postfiliale']['Zip'] : ''),
            'pf_city' => isset($address_input['pf_city']) ? $address_input['pf_city'] : (isset($norm_address['Postfiliale']['City']) ? $norm_address['Postfiliale']['City'] : ''),
            'street_name' => isset($address_input['street_name']) ? $address_input['street_name'] : (isset($norm_address['Address']['streetName']) ? $norm_address['Address']['streetName'] : ''),
            'address_addition' => isset($address_input['address_addition']) ? $address_input['address_addition'] : (isset($norm_address['Address']['addressAddition']) ? $norm_address['Address']['addressAddition'] : ''),
            'dispatching_information' => isset($address_input['dispatching_information']) ? $address_input['dispatching_information'] : (isset($norm_address['Address']['dispatchingInformation']) ? $norm_address['Address']['dispatchingInformation'] : ''),
            'zip' => isset($address_input['zip']) ? $address_input['zip'] : (isset($zip) ? $zip : ''),
            'country_iso_code' => isset($address_input['country_iso_code']) ? $address_input['country_iso_code'] : (isset($norm_address['Address']['Origin']['countryISOCode']) ? $norm_address['Address']['Origin']['countryISOCode'] : ''),
            'city' => isset($address_input['city']) ? $address_input['city'] : (isset($norm_address['Address']['city']) ? $norm_address['Address']['city'] : ''),
            'state' => isset($address_input['state']) ? $address_input['state'] : (isset($norm_address['Address']['Origin']['state']) ? $norm_address['Address']['Origin']['state'] : ''),
            'comm_email' => ((!is_array($perm_c) && $conf_private) || (is_array($perm_c) && $perm_c['permission_tpd'] == 0)) ? '' : (isset($address_input['comm_email']) ? $address_input['comm_email'] : (isset($norm_address['Communication']['email']) ? $norm_address['Communication']['email'] : '')),
            'comm_phone' => ((!is_array($perm_c) && $conf_private) || (is_array($perm_c) && $perm_c['permission_tpd'] == 0)) ? '' : (isset($address_input['comm_phone']) ? $address_input['comm_phone'] : (isset($norm_address['Communication']['phone']) ? $norm_address['Communication']['phone'] : '')),
            'comm_mobile' => ((!is_array($perm_c) && $conf_private) || (is_array($perm_c) && $perm_c['permission_tpd'] == 0)) ? '' : (isset($address_input['comm_mobile']) ? $address_input['comm_mobile'] : (isset($norm_address['Communication']['mobile']) ? $norm_address['Communication']['mobile'] : '')),
            'comm_person' => isset($address_input['comm_person']) ? $address_input['comm_person'] : (isset($norm_address['Communication']['contactPerson']) ? $norm_address['Communication']['contactPerson'] : ''),
            'permission_confirmation' => $perm_c
        );
    }

    public function isValidExportDocPositions($export_doc)
    {
        $errors = array();
        if (!isset($export_doc['ExportDocPosition']) || !count($export_doc['ExportDocPosition'])) {
            $errors[] = $this->l('No any position in export document');
        } else {
            $has_position = false;
            foreach ($export_doc['ExportDocPosition'] as $position_key => $position) {
                if ((int)$position['amount'] == 0) {
                    continue;
                }
                $has_position = true;
            }

            if ($has_position === false) {
                $errors[] = $this->l('No any position with \'Amount\' more than 0 in export document');
            }
        }
        if (count($errors)) {
            return $errors;
        }
        return false;
    }

    public function isValidCustomsTariffNumber($number)
    {
        if (preg_match('/^[0-9]{6}|[0-9]{8}|[0-9]{10}$/', $number)) {
            return true;
        }
        return false;
    }

    public function isDomesticDelivery($id_shop, $id_address_delivery)
    {
        if (self::getConfig('DHL_COUNTRY', $id_shop) == $this->getCountryISOCodeByAddressID($id_address_delivery)) {
            return true;
        }
        return false;
    }

    public function getTemplateVarsForAdditServices($order, $id_order_carrier, $id_address_delivery, $perm_c)
    {
        $services_input = Tools::getValue('addit_services');
        $service_input = isset($services_input[$id_order_carrier]) ? $services_input[$id_order_carrier] : array();

        return array(
            'id_order_carrier' => $id_order_carrier,
            'show_dhl_additional_services' => isset($service_input['show_dhl_additional_services']) ? $service_input['show_dhl_additional_services'] : '',
            'deliverytimeframe_options' => $this->getDeliveryTimeframeOptions(),
            'preferredtime_options' => $this->getPreferredTimeOptions(),
            'shipmenthandling_options' => $this->getShipmentHandlingOptions(),
            'endorsement_options' => $this->getEndorsementOptions('', $this->isDomesticDelivery($order->id_shop, $id_address_delivery)),
            'visualcheckofage_options' => $this->getVisualCheckOfAgeOptions(),
            'DayOfDelivery' => isset($service_input['DayOfDelivery']) ? $service_input['DayOfDelivery'] : '',
            'PreferredTime' => isset($service_input['PreferredTime']) ? $service_input['PreferredTime'] : '',
            'ReturnImmediately' => isset($service_input['ReturnImmediately']) ? $service_input['ReturnImmediately'] : '',
            'DeliveryTimeframe' => isset($service_input['DeliveryTimeframe']) ? $service_input['DeliveryTimeframe'] : '',
            'IndividualSenderRequirement' => isset($service_input['IndividualSenderRequirement']) ? $service_input['IndividualSenderRequirement'] : '',
            'PackagingReturn' => isset($service_input['PackagingReturn']) ? $service_input['PackagingReturn'] : '',
            'NoticeOfNonDeliverability' => isset($service_input['NoticeOfNonDeliverability']) ? $service_input['NoticeOfNonDeliverability'] : '',
            'ShipmentHandling' => isset($service_input['ShipmentHandling']) ? $service_input['ShipmentHandling'] : '',
            'Endorsement' => isset($service_input['Endorsement']) ? $service_input['Endorsement'] : '',
            'VisualCheckOfAge' => isset($service_input['VisualCheckOfAge']) ? $service_input['VisualCheckOfAge'] : self::getConfig('DHL_AGE_CHECK', $order->id_shop),
            'PreferredLocation' => isset($service_input['PreferredLocation']) ? $service_input['PreferredLocation'] : '',
            'PreferredNeighbour' => isset($service_input['PreferredNeighbour']) ? $service_input['PreferredNeighbour'] : '',
            'PreferredDay' => isset($service_input['PreferredDay']) ? $service_input['PreferredDay'] : '',
            'Perishables' => isset($service_input['Perishables']) ? $service_input['Perishables'] : '',
            'Personally' => isset($service_input['Personally']) ? $service_input['Personally'] : '',
            'NoNeighbourDelivery' => isset($service_input['NoNeighbourDelivery']) ? $service_input['NoNeighbourDelivery'] : '',
            'NamedPersonOnly' => isset($service_input['NamedPersonOnly']) ? $service_input['NamedPersonOnly'] : '',
            'NoticeOfNonDeliverability' => isset($service_input['NoticeOfNonDeliverability']) ? $service_input['NoticeOfNonDeliverability'] : '',
            'ReturnReceipt' => isset($service_input['ReturnReceipt']) ? $service_input['ReturnReceipt'] : '',
            'Premium' => isset($service_input['Premium']) ? $service_input['Premium'] : self::getConfig('DHL_PREMIUM', $order->id_shop),
            'CashOnDelivery' => isset($service_input['CashOnDelivery']) ? $service_input['CashOnDelivery'] : '',
            'CashOnDelivery_addFee' => isset($service_input['CashOnDelivery_addFee']) ? $service_input['CashOnDelivery_addFee'] : '',
            'CashOnDelivery_codAmount' => isset($service_input['CashOnDelivery_codAmount']) ? $service_input['CashOnDelivery_codAmount'] : '',
            'CashOnDelivery_bankdatanote' => isset($service_input['CashOnDelivery_bankdatanote']) ? $service_input['CashOnDelivery_bankdatanote'] : str_replace(
                '[order_reference_number]',
                (self::getConfig('DHL_REF_NUMBER', $order->id_shop) ? $order->id : $order->reference),
                self::getConfig('DHL_NOTE', $order->id_shop)
            ),
            'CashOnDelivery_bankdatanote2' => isset($service_input['CashOnDelivery_bankdatanote2']) ? $service_input['CashOnDelivery_bankdatanote2'] : str_replace(
                '[order_reference_number]',
                (self::getConfig('DHL_REF_NUMBER', $order->id_shop) ? $order->id : $order->reference),
                self::getConfig('DHL_NOTE2', $order->id_shop)
            ),
            'AdditionalInsurance' => isset($service_input['AdditionalInsurance']) ? $service_input['AdditionalInsurance'] : '',
            'AdditionalInsurance_insuranceAmount' => isset($service_input['AdditionalInsurance_insuranceAmount']) ? $service_input['AdditionalInsurance_insuranceAmount'] : '',
            'BulkyGoods' => isset($service_input['BulkyGoods']) ? $service_input['BulkyGoods'] : '',
            'SignedForByRecipient' => isset($service_input['SignedForByRecipient']) ? $service_input['SignedForByRecipient'] : '',
            'IdentCheck' => isset($service_input['IdentCheck']) ? $service_input['IdentCheck'] : '',
            'IdentCheck_Ident_surname' => isset($service_input['IdentCheck_Ident_surname']) ? $service_input['IdentCheck_Ident_surname'] : '',
            'IdentCheck_Ident_givenName' => isset($service_input['IdentCheck_Ident_givenName']) ? $service_input['IdentCheck_Ident_givenName'] : '',
            'IdentCheck_Ident_dateOfBirth' => isset($service_input['IdentCheck_Ident_dateOfBirth']) ? $service_input['IdentCheck_Ident_dateOfBirth'] : '',
            'IdentCheck_Ident_minimumAge' => isset($service_input['IdentCheck_Ident_minimumAge']) ? $service_input['IdentCheck_Ident_minimumAge'] : '',
            'minimum_age_options' => $this->getVisualCheckOfAgeOptions(),
            'ParcelOutletRouting' => isset($service_input['ParcelOutletRouting']) ? $service_input['ParcelOutletRouting'] : self::getConfig('DHL_DEF_PARCEL_ROUT_SERV', $order->id_shop),
            'ParcelOutletRouting_details' => isset($service_input['ParcelOutletRouting_details']) ? $service_input['ParcelOutletRouting_details'] : '',
            'permission_confirmation' => $perm_c
        );
    }


    public function getTemplateVarsForExportDocuments($order, $id_order_carrier, $id_address_delivery)
    {
        $docs_input = Tools::getValue('export_docs');
        $doc_input = isset($docs_input[$id_order_carrier]) ? $docs_input[$id_order_carrier] : array();

        if (isset($doc_input['ExportDocPosition'])) {
            $exportdoc_positions = $doc_input['ExportDocPosition'];
        } else {
            $order_positions = $order->getProducts();
            $exportdoc_positions = array();

            $i = 0;
            foreach ($order_positions as $order_position) {
                if ($i < 99) {
                    $product_customs = Db::getInstance()->getRow('select customs_tariff_number, country_of_origin from ' . _DB_PREFIX_ . 'dhldp_product_customs WHERE id_product=' . (int)$order_position['id_product'] . ' and id_product_attribute=0');
                    $exportdoc_positions[] = array(
                        'description' => $order_position['product_name'],
                        'countryCodeOrigin' => ($product_customs && $product_customs['country_of_origin'] != '') ? $product_customs['country_of_origin'] : self::getConfig('DHL_COUNTRY', $order->id_shop),
                        'customsTariffNumber' => ($product_customs) ? $product_customs['customs_tariff_number'] : self::getConfig('DHL_DEF_CUSTOMS_TARIFF_NUM', $order->id_shop),
                        'amount' => $order_position['product_quantity'],
                        'netWeightInKG' => number_format($order_position['product_weight'], 2, '.', ''),
                        'customsValue' => number_format($order_position['unit_price_tax_incl'], 2, '.', '')
                    );
                    $i++;
                }
            }
        }

        return array(
            'id_order_carrier' => $id_order_carrier,
            'show_dhl_export_documents' => isset($doc_input['show_dhl_export_documents']) ? $doc_input['show_dhl_export_documents'] : '',
            'exporttype_options' => $this->getExportTypeOptions(),
            'termsoftrade_options' => $this->getTermsOfTradeOptions(),
            'exportdoc_positions' => $exportdoc_positions,
            'exportdoc_positions_limit_exceed' => (isset($order_positions) && (count($order_positions) > count($exportdoc_positions))) ? true : false,
            'invoiceNumber' => isset($doc_input['invoiceNumber']) ? $doc_input['invoiceNumber'] : $this->getInvoiceNumberForExportDocument($order),
            'exportType' => isset($doc_input['exportType']) ? $doc_input['exportType'] : '',
            'exportTypeDescription' => isset($doc_input['exportTypeDescription']) ? $doc_input['exportTypeDescription'] : '',
            'termsOfTrade' => isset($doc_input['termsOfTrade']) ? $doc_input['termsOfTrade'] : '',
            'placeOfCommital' => isset($doc_input['placeOfCommital']) ? $doc_input['placeOfCommital'] : self::getConfig('DHL_DEF_PLACE_OF_COMMITAL', $order->id_shop),
            'additionalFee' => isset($doc_input['additionalFee']) ? $doc_input['additionalFee'] : self::getConfig('DHL_DEF_ADDITIONAL_CUSTOM_FEES'),
            'permitNumber' => isset($doc_input['permitNumber']) ? $doc_input['permitNumber'] : '',
            'attestationNumber' => isset($doc_input['attestationNumber']) ? $doc_input['attestationNumber'] : '',
            'WithElectronicExportNtfctn' => isset($doc_input['WithElectronicExportNtfctn']) ? $doc_input['WithElectronicExportNtfctn'] : '',
            'ExportDocPosition' => isset($doc_input['ExportDocPosition']) ? $doc_input['ExportDocPosition'] : '',
        );
    }

    public function getInvoiceNumberForExportDocument($order)
    {
        $type = self::getConfig('DHL_EXP_INV_NUM');
        if ($type == 1) {
            return $order->reference;
        } elseif ($type == 2) {
            $oi_one = Db::getInstance()->getValue('select id_order_invoice from ' . _DB_PREFIX_ . 'order_invoice where id_order=' . (int)$order->id . ' and number > 0 order by id_order_invoice');
            if ($oi_one) {
                $oi = new OrderInvoice((int)$oi_one);
                return $oi->getInvoiceNumberFormatted($order->id_lang, $order->id_shop);
            }
        }
        return '';
    }

    public function getTermsOfTradeOptions($option_key = '')
    {
        $res = array(
            'DDP' => $this->l('DDP (Delivery Duty Paid)'),
            'DXV' => $this->l('DXV (Delivery duty paid (excl. VAT))'),
            'DDU' => $this->l('DDU (DDU - Delivery Duty Paid)'),
            'DDX' => $this->l('DDX (Delivery duty paid (excl. Duties, taxes and VAT)'),
        );
        if ($option_key != '') {
            if (isset($res[$option_key])) {
                return $res[$option_key];
            } else {
                return false;
            }
        }
        return $res;
    }

    public function getExportTypeOptions($option_key = '')
    {
        $res = array(
            'COMMERCIAL_GOODS' => $this->l('COMMERCIAL_GOODS'),
            'OTHER' => $this->l('OTHER'),
            'PRESENT' => $this->l('PRESENT'),
            'COMMERCIAL_SAMPLE' => $this->l('COMMERCIAL_SAMPLE'),
            'DOCUMENT' => $this->l('DOCUMENT'),
            'RETURN_OF_GOODS' => $this->l('RETURN_OF_GOODS'),
        );
        if ($option_key != '') {
            if (isset($res[$option_key])) {
                return $res[$option_key];
            } else {
                return false;
            }
        }
        return $res;
    }

    public function getVisualCheckOfAgeOptions($option_key = '')
    {
        $res = array(
            'A16' => $this->l('16+ years'),
            'A18' => $this->l('18+ years'),
        );
        if ($option_key != '') {
            if (isset($res[$option_key])) {
                return $res[$option_key];
            } else {
                return false;
            }
        }
        return $res;
    }

    public function getEndorsementOptions($option_key = '', $is_domestic_delivery = null)
    {
        $res = array(
            'RETURN' => $this->l('Return immediately'),
            'ABANDON' => $this->l('Abandonment of parcel at the hands of sender (free of charge)'),
        );
        if ($option_key != '') {
            if (isset($res[$option_key])) {
                return $res[$option_key];
            } else {
                return false;
            }
        } else {
            if ($is_domestic_delivery !== null) {
                foreach ($res as $item_key => $item_value) {
                    if ((bool)$is_domestic_delivery === true) {
                        unset($res[$item_key]);
                    } else {
                        if (!in_array($item_key, array('RETURN', 'ABANDON'))) {
                            unset($res[$item_key]);
                        }
                    }
                }
            }
        }
        return $res;
    }

    public function getDeliveryTimeframeOptions($option_key = '')
    {
        $res = array(
            '10001200' => $this->l('10:00 until 12:00'),
            '12001400' => $this->l('12:00 until 14:00'),
            '14001600' => $this->l('14:00 until 16:00'),
            '16001800' => $this->l('16:00 until 18:00'),
            '18002000' => $this->l('18:00 until 20:00'),
            '19002100' => $this->l('19:00 until 21:00'),
        );
        if ($option_key != '') {
            if (isset($res[$option_key])) {
                return $res[$option_key];
            } else {
                return false;
            }
        }
        return $res;
    }

    public function getPreferredTimeOptions($option_key = '')
    {
        $res = array(
            '10001200' => $this->l('10:00 until 12:00'),
            '12001400' => $this->l('12:00 until 14:00'),
            '14001600' => $this->l('14:00 until 16:00'),
            '16001800' => $this->l('16:00 until 18:00'),
            '18002000' => $this->l('18:00 until 20:00'),
            '19002100' => $this->l('19:00 until 21:00'),
        );
        if ($option_key != '') {
            if (isset($res[$option_key])) {
                return $res[$option_key];
            } else {
                return false;
            }
        }
        return $res;
    }

    public function getShipmentHandlingOptions($option_key = '')
    {
        $res = array(
            'a' => $this->l('Remove content, return box'),
            'b' => $this->l('Remove content, pick up and dispose cardboard packaging'),
            'c' => $this->l('Handover parcel/box to customer; no disposal of cardboard/box'),
            'd' => $this->l('Remove bag from of cooling unit and handover to customer'),
            'e' => $this->l('Remove content, apply return label und seal box, return box'),
        );
        if ($option_key != '') {
            if (isset($res[$option_key])) {
                return $res[$option_key];
            } else {
                return false;
            }
        }
        return $res;
    }

    public function updateOrderStatus($id_order_carrier, $called_by = null)
    {
        $order_carrier = new OrderCarrier((int)$id_order_carrier);
        $order = new Order((int)$order_carrier->id_order);
        if ($called_by === 'createDPDeliveryLabel') {
            $id_os = self::getConfig('DP_CHANGE_OS', (int)$order->id_shop);
        } else {
            $id_os = self::getConfig('DHL_CHANGE_OS', (int)$order->id_shop);
        }
        $ret = Hook::exec('actionGetIDOrderStateByIDCarrier', array('id_carrier' => $order_carrier->id_carrier, 'id_shop' => (int)$order->id_shop), null, true);

        if (isset($ret['dhlcarrieraddress']['id_os'])) {
            $id_os_updated = $ret['dhlcarrieraddress']['id_os'];
            if ($id_os_updated === 0) {
                return true;
            } elseif ($id_os_updated != '' && $id_os_updated != -1) {
                $id_os = $id_os_updated;
            }
        }

        $order_state = new OrderState((int)$id_os);

        if (($id_os != '') && in_array((int)$id_os, $this->getShippedOrderStates(true))) {
            if (Validate::isLoadedObject($order_state)) {

                $current_order_state = $order->getCurrentOrderState();
                if ($current_order_state->id != $order_state->id) {
                    // Create new OrderHistory
                    $history = new OrderHistory();
                    $history->id_order = (int)$order->id;
                    $history->id_employee = (int)$this->context->employee->id;

                    $use_existings_payment = false;
                    if (!$order->hasInvoice()) {
                        $use_existings_payment = true;
                    }
                    $history->changeIdOrderState((int)$order_state->id, $order, $use_existings_payment);
                    $carrier = new Carrier($order->id_carrier, $order->id_lang);
                    $templateVars = array();
                    if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order_carrier->tracking_number) {
                        $templateVars = array('{followup}' => str_replace('@', $order_carrier->tracking_number, $carrier->url));
                    }
                    if (isset($ret['dhlcarrieraddress']['id_os']) && isset($ret['dhlcarrieraddress']['send_changeos']) && $ret['dhlcarrieraddress']['send_changeos'] == 1) {
                        if ($history->addWithemail(true, $templateVars)) {
                            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                                foreach ($order->getProducts() as $product) {
                                    if (StockAvailable::dependsOnStock($product['product_id'])) {
                                        StockAvailable::synchronize($product['product_id'], (int)$product['id_shop']);
                                    }
                                }
                            }
                            return true;
                        }
                    } else {
                        if ($history->add(true)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function updateOrderCarrierWithTrackingNumber($id_order_carrier, $tracking_number)
    {
        $order_carrier = new OrderCarrier((int)$id_order_carrier);

        if (Validate::isLoadedObject($order_carrier)) {
            $order = new Order((int)$order_carrier->id_order);
            $order->shipping_number = $tracking_number;
            $order->update();
            $order_carrier->tracking_number = $tracking_number;

            if ($order_carrier->update()) {
                $customer = new Customer((int)$order->id_customer);
                $carrier = new Carrier((int)$order->id_carrier, $order->id_lang);

                $ret = Hook::exec('actionGetIDOrderStateByIDCarrier', array('id_carrier' => $order_carrier->id_carrier, 'id_shop' => $order->id_shop), null, true);

                // don't send in_transit if 0
                if (isset($ret['dhlcarrieraddress']['id_os']) && isset($ret['dhlcarrieraddress']['send_intransit']) && $ret['dhlcarrieraddress']['send_intransit'] == 0) {
                    return true;
                }

                // Send mail to customer
                if (self::getConfig('DHL_INTRANSIT_MAIL', $order->id_shop)) {
                    $tracking_url = str_replace('[tracking_number]', $tracking_number, \PrestaShop\Module\dhldp\classes\DHLDPApiRest::$tracking_url);

                    $template_vars = array(
                        '{followup}' => $tracking_url,
                        '{firstname}' => $customer->firstname,
                        '{lastname}' => $customer->lastname,
                        '{id_order}' => $order->id,
                        '{shipping_number}' => $order->shipping_number,
                        '{order_name}' => $order->getUniqReference()
                    );

                    Mail::Send(
                        (int)$order->id_lang,
                        'in_transit',
                        $this->l('Package in transit'),
                        $template_vars,
                        $customer->email,
                        $customer->firstname . ' ' . $customer->lastname,
                        null,
                        null,
                        null,
                        null,
                        _PS_MAIL_DIR_,
                        true,
                        (int)$order->id_shop
                    );
                }

                Hook::exec(
                    'actionAdminOrdersTrackingNumberUpdate',
                    array('order' => $order, 'customer' => $customer, 'carrier' => $carrier),
                    null,
                    false,
                    true,
                    false,
                    $order->id_shop
                );
                return true;
            }
        }
        return false;
    }

    public function getTabLink($tab, $params = false)
    {
        $link = 'index.php?controller=' . $tab . '&token=' . Tools::getAdminTokenLite($tab, $this->context);

        if (is_array($params) && count($params)) {
            foreach ($params as $k => $v) {
                $link .= '&' . $k . '=' . $v;
            }
        }
        return $link;
    }

    public function hookDisplayBackOfficeHeader()
    {
        Media::addJsDef(['is177' => $this->is177]);

        if (($this->context->controller->controller_name == 'AdminOrders' || $this->context->controller instanceof AdminOrdersController) && $this->is177 && !Tools::getIsset('id_order')) {
            global $kernel;
            $id_order = $kernel->getContainer()->get('request_stack')->getCurrentRequest()->get('orderId');
        } else {
            $id_order = Tools::getValue('id_order');
        }
        if (version_compare(_PS_VERSION_, '8.0', '<')) {
            $this->context->controller->addJquery();
        }
        if (($this->context->controller->controller_name == 'AdminOrders' || $this->context->controller instanceof AdminOrdersController) && !$id_order) {
            $this->context->controller->addJS($this->_path . 'views/js/order-list.js');
            if ($this->is177) {
                $this->context->controller->addCSS($this->_path . 'views/css/order_list.css');
            }
            Media::addJsDef([
                'dhldp_request_path' => $this->getModuleUrl(array('view' => 'generateLabels')),
                'dhldp_translation' => json_encode(
                    [
                        'Generate DHL labels' => $this->l('Generate DHL labels'),
                        'Generate DP labels' => $this->l('Generate Deutschepost labels'),
                    ]
                ),
            ]);
        } elseif (($this->context->controller->controller_name == 'AdminOrders' || $this->context->controller instanceof AdminOrdersController) && $id_order) {
            if (!$this->is177) {
                $this->context->controller->addJS($this->_path . 'views/js/popper.min.js');
            }
            $this->context->controller->addJS($this->_path . 'views/js/admin_order.js');
            $this->context->controller->addJS($this->_path . 'views/js/dp-admin-order.js');
            $this->context->controller->addCSS($this->_path . 'views/css/admin_order.css');
            $this->context->controller->addJS($this->_path . 'views/js/jquery.maxlength.min.js');

            if ($this->is177) {
                $this->context->controller->addCSS($this->_path . 'views/css/admin_order_17.css');
            }
        } elseif (Tools::getValue('configure') == $this->name && Tools::getValue('view') == 'generateLabels') {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
            $this->context->controller->addJS($this->_path . 'views/js/jquery.maxlength.min.js');
            if (!$this->is177) {
                $this->context->controller->addJS($this->_path . 'views/js/popper.min.js');
            }
            $this->context->controller->addJS($this->_path . 'views/js/admin_orders.js');
            $this->context->controller->addJS($this->_path . 'views/js/dhl-product-dimensions.js');
        }
        Media::addJsDef([
            'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax')
        ]);
    }

    public function getModuleUrl($params = false)
    {
        $url = $this->context->link->getAdminLink('AdminModules', true);
        $url .= '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        if (is_array($params) && count($params)) {
            foreach ($params as $k => $v) {
                $url .= '&' . $k . '=' . $v;
            }
        }
        return $url;
    }

    public function installTab($tab_class, $tab_name, $parent = 'AdminModules', $active = false)
    {
        $tab = new Tab();
        $tab->active = (int)$active;
        $tab->class_name = $tab_class;
        $tab->name = array();

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $tab_name;
        }

        $tab->id_parent = (int)Tab::getIdFromClassName($parent);
        $tab->module = $this->name;
        return $tab->add();
    }

    public function uninstallTab($tab_class)
    {
        $id_tab = (int)Tab::getIdFromClassName($tab_class);

        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }

        return false;
    }

    public static function logToFile($service, $msg, $key = '')
    {
        if (in_array($service, array('DP', 'DHL'))) {
            if (self::getConfig($service . '_LOG')) {
                if ($service == 'DP') {
                    $key = 'dp_' . $key;
                }
                $filename = dirname(__FILE__) . '/logs/log_' . $key . '.txt';
                $fd = fopen($filename, 'a');
                fwrite($fd, "\n" . date('Y-m-d H:i:s') . ' ' . $msg);
                fclose($fd);
            }
        }
    }

    public function getContent()
    {
        $html = '';
        $view_mode = Tools::getValue('view');

        switch ($view_mode) {
            case 'generateLabels':
                if (Tools::isSubmit('generateMultipleLabels') || Tools::isSubmit('generateMultipleLabelsWithReturn')) {
                    $this->createDhlLabels(Tools::getValue('carrier'), Tools::isSubmit('generateMultipleLabelsWithReturn') ? true : false);
                }

                if (Tools::isSubmit('printMultipleLabels')) {
                    $this->printDhlLabels(Tools::getValue('printLabel'));
                }

                $html .= $this->displayMessages();
                $this->context->smarty->assign(
                    array(
                        'module' => $this,
                        'order_list' => $this->getBulkOrdersList(Tools::getValue('order_list')),
                        'self' => dirname(__FILE__),
                        'shipment_date' => date('Y-m-d'),
                        'is177' => $this->is177,
                        'is16' => $this->is16,
                        'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax')
                    )
                );

                $this->context->controller->addCSS($this->_path . 'views/css/admin_order.css');
                if ($this->is177) {
                    $this->context->controller->addCSS($this->_path . 'views/css/admin_order_17.css');
                }
                $dhl_with_warning = (bool)self::getConfig('DHL_LABEL_IGNORE_WARNING', (int)\Context::getContext()->shop->id);
                $this->context->smarty->assign('dhl_with_warning', $dhl_with_warning);
                $html .= $this->context->smarty->fetch(dirname(__FILE__) . '/views/templates/hook/order-list.tpl');
                break;
            case 'changelog':
                $changelog_file = dirname(__FILE__) . '/Readme.md';
                if (file_exists($changelog_file)) {
                    die($this->displayChangelog($changelog_file));
                }
                break;
            case 'init_dhl':
                $html .= $this->postInitDHLProcess();
                break;
            case 'settings_dp':
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminDhldpSettingsDp'));
                break;
            default:
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminDhldpSettingsDhl'));
                break;
        }
        return $html;
    }

    public function getDefinitionConfigurePages()
    {
        return array(
            'cparam' => 'view',
            'pages' => array(
                'settings_dhl' => array('name' => $this->l('DHL settings'), 'default' => true),
                'settings_dp' => array('name' => $this->l('Deutschepost settings')),
                'information' => array('name' => $this->l('Information'), 'icon' => ''),
                'manifest' => array('name' => $this->l('DHL Manifest'), 'icon' => ''),
            )
        );
    }

    public function displayMenu()
    {
        $currentController = Tools::getValue('controller', '');
        $menuItems = array(
            'settings_dhl' => array(
                'name' => $this->l('DHL settings'),
                'url' => $this->context->link->getAdminLink('AdminDhldpSettingsDhl'),
                'icon' => '',
                'active' => $currentController == 'AdminDhldpSettingsDhl',
            ),
            'settings_dp' => array(
                'name' => $this->l('DHL DP settings'),
                'url' => $this->context->link->getAdminLink('AdminDhldpSettingsDp'),
                'icon' => '',
                'active' => $currentController == 'AdminDhldpSettingsDp',
            ),
            'information' => array(
                'name' => $this->l('Information'),
                'url' => $this->context->link->getAdminLink('AdminDhldpInformation'),
                'icon' => '',
                'active' => $currentController == 'AdminDhldpInformation',
            ),
            'manifest' => array(
                'name' => $this->l('DHL Manifest'),
                'url' => $this->context->link->getAdminLink('AdminDhldpManifest'),
                'icon' => '',
                'active' => $currentController == 'AdminDhldpManifest',
            ),
        );
        $this->smarty->assign(array(
            'module_version' => $this->version,
            'module_name' => $this->displayName,
            'menu_items' => $menuItems,
            'changelog' => file_exists(dirname(__FILE__) . '/README.md'),
            '_path' => $this->getPathUri()
        ));
        return $this->display(__FILE__, 'views/templates/admin/menu.tpl');
    }

    public function renderQuickStartPanel()
    {
        $documents = array(
            'en' => array('user-guide-en.html', 'module-description-en.html'),
            'de' => array('benutzerhandbuch-de.html', 'modulbeschreibung-de.html'),
            'es' => array('manual-de-usuario-es.html', 'descripcion-del-modulo-es.html'),
            'pl' => array('podrecznik-uzytkownika-pl.html', 'opis-modulu-pl.html'),
            'it' => array('manuale-utente-it.html', 'descrizione-modulo-it.html'),
            'fr' => array('guide-utilisateur-fr.html', 'description-module-fr.html'),
        );

        $iso_code = Tools::strtolower((string)$this->context->language->iso_code);
        $iso_parts = preg_split('/[-_]/', $iso_code);
        $iso_code = isset($iso_parts[0]) ? $iso_parts[0] : 'en';
        if (!isset($documents[$iso_code])) {
            $iso_code = 'en';
        }

        $docs_url = $this->getPathUri() . 'docs/';
        $this->context->smarty->assign(array(
            'dhldp_quick_start_title' => $this->l('Quick start'),
            'dhldp_quick_start_intro' => $this->l('Complete these steps before creating production labels:'),
            'dhldp_quick_start_steps' => array(
                $this->l('Get personal GKP access and, for production API use, a dedicated system user; locate the EKP and 14-character billing numbers in the contract data.'),
                $this->l('Choose Sandbox for testing or Live for production, then enter the API user, password and 10-character EKP.'),
                $this->l('Add the contracted DHL products from billing-number characters 11-12, enter participation from characters 13-14, and map PrestaShop carriers.'),
                $this->l('Enter the shipper address or an exact GKP shipper reference; configure Portokasse separately if you use INTERNETMARKE.'),
                $this->l('Create a test label from an order and verify the file and tracking number before going live.'),
            ),
            'dhldp_user_guide_label' => $this->l('User guide'),
            'dhldp_module_description_label' => $this->l('Module description'),
            'dhldp_more_modules_label' => $this->l('More PrestaShop modules'),
            'dhldp_paid_support_label' => $this->l('Paid support'),
            'dhldp_support_email_label' => $this->l('Support email'),
            'dhldp_user_guide_url' => $docs_url . $documents[$iso_code][0],
            'dhldp_module_description_url' => $docs_url . $documents[$iso_code][1],
            'dhldp_more_modules_url' => 'https://www.silbersaiten.de/de/28-prestashop-module',
            'dhldp_paid_support_url' => 'https://www.silbersaiten.de/de/44-support',
            'dhldp_support_email_url' => 'mailto:support@silbersaiten.de',
        ));

        return $this->display(__FILE__, 'views/templates/admin/quick-start.tpl');
    }

    public function createDhlLabels($collection, $with_return = false)
    {
        $general_errors = array();
        $general_confirmations = array();
        $error_order_line = array();
        $success_order_line = array();
        $warning_order_line = array();
        $orders_errors = array();
        $orders_confirmations = array();
        $orders_warnings = array();

        if (!is_array($collection) || !count($collection)) {
            return false;
        }

        $address_input = Tools::getValue('address');
        $addit_services_input = Tools::getValue('addit_services');
        $export_docs_input = Tools::getValue('export_docs');

        foreach ($collection as $id_order_carrier => $c) {
            $order_errors = array();
            $order_confirmations = array();
            $order_warnings = array();

            if (!Validate::isUnsignedId($c['id_order_carrier']) ||
                !Validate::isLoadedObject($order_carrier = new OrderCarrier((int)$c['id_order_carrier'])) ||
                !Validate::isLoadedObject($order = new Order((int)$order_carrier->id_order))) {
                $order_errors[] = $this->l('Invalid order carrier');
            }
            if (!Validate::isUnsignedId($c['id_carrier'])) {
                $order_errors[] = $this->l('Invalid carrier id');
            }
            if (!Validate::isUnsignedId($c['id_address'])) {
                $order_errors[] = $this->l('Invalid address id');
            }
            if (!Validate::isFloat($c['weight'])) {
                $order_errors[] = $this->l('Invalid weight');
            }
            if (!Validate::isFloat($c['width'])) {
                $order_errors[] = $this->l('Invalid width');
            }
            if (!Validate::isFloat($c['height'])) {
                $order_errors[] = $this->l('Invalid height');
            }
            if (!Validate::isFloat($c['length'])) {
                $order_errors[] = $this->l('Invalid length');
            }

            $this->dhldp_api_rest->setApiVersionByIdShop($order->id_shop);
            $receiver_address = $this->dhldp_api_rest->getDHLDeliveryAddress(
                $c['id_address'],
                isset($address_input[$id_order_carrier]) ? $address_input[$id_order_carrier] : false,
                $order
            );

            $formatted_products = $this->getFormattedAddedDhlProducts(array($c['dhl_product_code']));
            if (is_array($formatted_products[0])) {
                $aproduct_code = explode(':', $c['dhl_product_code']);
                $product_def = $this->dhldp_api_rest->getDefinedProducts(
                    $aproduct_code[0],
                    isset($receiver_address['Address']['Origin']['countryISOCode']) ? $receiver_address['Address']['Origin']['countryISOCode'] : 'DE',
                    $this->dhldp_api_rest->getShipperCountry($order->id_shop),
                    $this->dhldp_api_rest->getApiVersion()
                );
                if (!is_array($product_def)) {
                    $formatted_product = false;
                    $product_params = false;
                } else {
                    $formatted_product = $formatted_products[0];
                    $product_params = $product_def['params'];
                }
            } else {
                $formatted_product = false;
                $product_params = false;
            }

            $packages = array(
                array(
                    'weight' => (float)str_replace(',', '.', $c['weight']),
                    'length' => (int)$c['length'],
                    'width' => (int)$c['width'],
                    'height' => (int)$c['height'],
                )
            );
            //echo '<pre>'.print_r($packages, true).'</pre>';
            if (Tools::strlen($c['dhl_product_code']) == 0) {
                $order_errors[] = $this->l('Please select product.');
            }
            if ($formatted_product == false) {
                $order_errors[] = $this->_errors[] = $this->l('This product is not added in list.');
            }
            if ((isset($product_params['weight_package']['min']) && $product_params['weight_package']['min'] > $packages[0]['weight']) ||
                (isset($product_params['weight_package']['max']) && $product_params['weight_package']['max'] < $packages[0]['weight'])
            ) {
                $order_errors[] = $this->l('Weight is invalid') . ' (min. ' . $product_params['weight_package']['min'] . ' kg, max. ' . $product_params['weight_package']['max'] . ' kg)';
            }
            if ((isset($product_params['length']['min']) && $product_params['length']['min'] > $packages[0]['length']) ||
                (isset($product_params['length']['max']) && $product_params['length']['max'] < $packages[0]['length'])
            ) {
                $order_errors[] = $this->l('Length is invalid') . ' (min. ' . $product_params['length']['min'] . ' cm, max. ' . $product_params['length']['max'] . ' cm)';
            }
            if ((isset($product_params['width']['min']) && $product_params['width']['min'] > $packages[0]['width']) ||
                (isset($product_params['width']['max']) && $product_params['width']['max'] < $packages[0]['width'])
            ) {
                $order_errors[] = $this->l('Width is invalid') . ' (min. ' . $product_params['width']['min'] . ' cm, max. ' . $product_params['width']['max'] . ' cm)';
            }
            if ((isset($product_params['height']['min']) && $product_params['height']['min'] > $packages[0]['height']) ||
                (isset($product_params['height']['max']) && $product_params['height']['max'] < $packages[0]['height'])
            ) {
                $order_errors[] = $this->l('Height is invalid') . ' (min. ' . $product_params['height']['min'] . ' cm, max. ' . $product_params['height']['max'] . ' cm)';
            }
            if (isset($product_def['export_documents']) && !isset($export_docs_input[$id_order_carrier])) {
                $order_errors[] = $this->l('No data of export document.');
            }
            if (isset($product_def['export_documents']) && (!isset($export_docs_input[$id_order_carrier]['exportType']) || ($export_docs_input[$id_order_carrier]['exportType'] == '') || ($this->getExportTypeOptions($export_docs_input[$id_order_carrier]['exportType']) === false))) {
                $order_errors[] = $this->l('Please select export type in export document.');
            }
            if (isset($product_def['export_documents']) && (!isset($export_docs_input[$id_order_carrier]['placeOfCommital']) || ($export_docs_input[$id_order_carrier]['placeOfCommital'] == ''))) {
                $order_errors[] = $this->l('Please fill Place of commital in export document.');
            }
            if (isset($product_def['export_documents']) && (!isset($export_docs_input[$id_order_carrier]['additionalFee']) || ($export_docs_input[$id_order_carrier]['additionalFee'] == ''))) {
                $order_errors[] = $this->l('Please enter Additional custom fees in export document.');
            }
            if (isset($product_def['export_documents']) && ($export_docs_errors = $this->isValidExportDocPositions($export_docs_input[$id_order_carrier])) != false) {
                foreach ($export_docs_errors as $errors) {
                    $order_errors[] = $errors;
                }
            }

            if (!count($order_errors)) {
                $options = array();
                if (isset($addit_services_input[$id_order_carrier])) {
                    $options['addit_services'] = $addit_services_input[$id_order_carrier];
                }
                if (isset($export_docs_input[$id_order_carrier])) {
                    $options['export_docs'] = $export_docs_input[$id_order_carrier];
                }
                $options['shipment_date'] = Tools::getValue($c['dhl_shipment_date'], date('Y-m-d'));

                $with_return = (bool)self::getConfig('DHL_LABEL_WITH_RETURN', $order->id_shop);
                $is_return = false;

                $with_warning = (bool)self::getConfig('DHL_LABEL_IGNORE_WARNING', $order->id_shop);
                if ($with_warning) {
                    $create_label_no_validation = isset($c['create_label_no_validation']) ? $c['create_label_no_validation'] : null;
                } else {
                    $create_label_no_validation = null;
                }

                $shipmentReference = (self::getConfig('DHL_REF_NUMBER', $order->id_shop) ? $order->id : $order->reference);
                if (strlen($shipmentReference) < 8) {
                    $shipmentReference = str_pad($shipmentReference, 8, '0', STR_PAD_LEFT);
                }

                $result = $this->createDhlDeliveryLabel(
                    $receiver_address,
                    $c['dhl_product_code'],
                    $packages,
                    $options,
                    $c['id_order_carrier'],
                    $shipmentReference,
                    $is_return,
                    $with_return,
                    0,
                    $order->id_shop,
                    $create_label_no_validation
                );

                if (!$result) {
                    $api_errors = $this->getRestErrors();
                    $has_api_errors = count($api_errors) > 0;
                    $has_warnings = is_array($this->dhldp_api_rest->warnings) && count($this->dhldp_api_rest->warnings) > 0;

                    if ($has_api_errors) {
                        $order_errors = array_merge($order_errors, $api_errors);
                    } elseif ($has_warnings) {
//                        $this->context->smarty->assign('warnings_title', $this->l('THE SHIPPING LABEL WAS CREATED WITH WARNINGS'));
                    } else {
                        $order_errors[] = $this->_errors[] = sprintf(
                                $this->l('Order #%s :'),
                                $c['order_id']
                            ) . ' ' . $this->l('Unable to generate label for this request');
                    }

                    $error_order_line[] = $c['order_id'];
                } else {
                    $order_confirmations[] = $this->l('Shipment order and shipping label have been created.');

                    if (is_array($this->dhldp_api_rest->warnings) && count($this->dhldp_api_rest->warnings) > 0) {
                        $order_warnings = array_merge($order_warnings, $this->dhldp_api_rest->warnings);
                        $warning_order_line[] = $c['order_id'];
                        $this->context->smarty->assign('warnings_title', $this->l('THE SHIPPING LABEL WAS CREATED WITH WARNINGS'));
                    } else {
                        $success_order_line[] = $c['order_id'];
                    }

                    $general_confirmations[] = $this->l('Label has been generated for #') . $c['order_id'];
                }
            } else {
                $error_order_line[] = $c['order_id'];
            }

            // Always assign relevant errors, warnings and confirmations from the API
            if (!isset($api_errors)) {
                $api_errors = $this->getRestErrors();
            }
            $orders_errors[$c['order_id']] = $api_errors;
            $orders_confirmations[$c['order_id']] = $this->dhldp_api_rest->confirmations;
            $orders_warnings[$c['order_id']] = $this->dhldp_api_rest->warnings;

            // Let's add a general error message if there were any.
            if (count($order_errors) > 0) {
                $general_errors[] = sprintf($this->l('Order #%s :'), $c['order_id']) . ' ' . $this->l('There are errors on the form');
            }
        }
        $this->context->smarty->assign('general_errors', $general_errors);
        $this->context->smarty->assign('general_confirmations', $general_confirmations);
        $this->context->smarty->assign('orders_errors', $orders_errors);
        $this->context->smarty->assign('orders_warnings', $orders_warnings);
        $this->context->smarty->assign('orders_confirmations', $orders_confirmations);
        $this->context->smarty->assign('success_order_line', $success_order_line);
        $this->context->smarty->assign('warning_order_line', $warning_order_line);
        $this->context->smarty->assign('error_order_line', $error_order_line);
    }

    public function printDhlLabels($collection)
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            require_once(_PS_TOOL_DIR_ . 'tcpdf/config/lang/eng.php');
            require_once(_PS_TOOL_DIR_ . 'tcpdf/tcpdf.php');
        }
        require_once(dirname(__FILE__) . '/classes/fpdi/fpdi.php');
        require_once(dirname(__FILE__) . '/classes/PDFMerger.php');

        if (!is_array($collection) || !count($collection)) {
            return false;
        }

        $pdf = new PDFMerger();
        $i = 0;
        foreach ($collection as $c) {
            $label_file = $this->getLabelFilePathByLabelUrl($c['label_url']);
            if ($label_file != '') {
                $pdf->addPDF($label_file, 'all');
                $i++;
            }
        }
        if ($i > 0) {
            try {
                $pdf->merge('download', 'labels_' . date('YmdHis') . '.pdf'); //download, browser
                exit;
            } catch (Exception $e) {
                $general_errors = array($e->getMessage());
                $this->context->smarty->assign('general_errors', $general_errors);
            }
        }
    }

    public function getLabelFilePathByLabelUrl($label_url)
    {
        if ($label_url != '') {
            $label_file = $this->getLabelFileNameByLabelUrl($label_url);
            if (!file_exists($label_file) || ((int)filesize($label_file) == 0)) {
                $content = Tools::file_get_contents($label_url);
                //if (strpos($content, '%PDF') !== false) {
                file_put_contents($label_file, $content);
                //}
            }
            if (file_exists($label_file) && ((int)filesize($label_file) != 0)) {
                return $label_file;
            }
        }
        return '';
    }

    protected function normalizeLabelBasename($label_url)
    {
        $basename = str_replace(array('printShipment?token', '?', '=', ' '), '', basename($label_url));
        if (substr($basename, -4) === '.pdf') {
            $basename = substr($basename, 0, -4);
        }

        $basename = preg_replace('#[^a-zA-Z0-9_-]#', '', $basename);
        // Leave room for .pdf and keep generated names stable when read as local URLs.
        if ($basename === '' || strlen($basename) > 32) {
            $basename = 'label_' . substr(hash('sha256', $label_url), 0, 24);
        }

        return $basename;
    }

    public function getLabelFileNameByLabelUrl($label_url)
    {
        $label_file = $this->getLocalPath() . 'pdfs/' . $this->normalizeLabelBasename($label_url) . '.pdf';

        // Reuse cached PDFs created before the filename limit was reduced.
        // Keep the old file too: its URL may still be stored in an order.
        if (!file_exists($label_file) || (int)filesize($label_file) === 0) {
            $legacy_basename = str_replace(array('printShipment?token', '?', '=', ' '), '', basename($label_url));
            if (substr($legacy_basename, -4) === '.pdf') {
                $legacy_basename = substr($legacy_basename, 0, -4);
            }
            $legacy_basename = preg_replace('#[^a-zA-Z0-9_-]#', '', $legacy_basename);
            if ($legacy_basename === '' || strlen($legacy_basename) > 100) {
                $legacy_basename = 'label_' . sha1($label_url);
            }
            $legacy_file = $this->getLocalPath() . 'pdfs/' . $legacy_basename . '.pdf';
            if ($legacy_file !== $label_file && is_file($legacy_file) && (int)filesize($legacy_file) > 0) {
                copy($legacy_file, $label_file);
            }
        }

        return $label_file;
    }

    public function getLabelFileURIByLabelUrl($label_url)
    {
        return $this->getPathUri() . 'pdfs/' . basename($this->getLabelFileNameByLabelUrl($label_url));
    }

    public function saveLabelFile($label_url, $data)
    {
        $label_file = $this->getLabelFileNameByLabelUrl($label_url);
        if (!file_exists($label_file) || ((int)filesize($label_file) == 0)) {
            file_put_contents($label_file, $data);
        }
        if (file_exists($label_file) && ((int)filesize($label_file) != 0)) {
            return $this->getLabelFileURIByLabelUrl($label_url);
        }
        return '';
    }

    public function displayMessages()
    {
        $messages = '';
        foreach ($this->_errors as $error) {
            $messages .= $this->displayError($error);
        }
        foreach ($this->_confirmations as $confirmation) {
            $messages .= $this->displayConfirmation($confirmation);
        }
        return $messages;
    }

    public function getBulkOrdersList($order_list)
    {
        if (!$order_list || !is_array($order_list) || !count($order_list)) {
            return false;
        }

        $orders = Db::getInstance()->ExecuteS(
            '
                        SELECT
                        o.`id_order`,
                        o.`reference`,
                        o.`id_address_delivery`,
                        o.`id_customer`,
                        a.`id_country`,
                        oc.*,
                        c.`name` as `carrier_name`
                        FROM
                        `' . _DB_PREFIX_ . 'order_carrier` oc
			LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = oc.`id_order`)
			LEFT JOIN `' . _DB_PREFIX_ . 'address` a ON (o.`id_address_delivery` = a.`id_address`)
			LEFT JOIN `' . _DB_PREFIX_ . 'carrier` c ON (c.`id_carrier` = oc.`id_carrier`)
			WHERE
			oc.`id_order` IN (' . (implode(',', array_map('intval', $order_list))) . ')'
        );

        if (!$orders) {
            return false;
        }

        $carrier_input = Tools::getValue('carrier');

        foreach ($orders as &$order) {
            $order_obj = new Order((int)$order['id_order']);
            $selected_carriers = $this->getDHLCarriers(true, false, $order_obj->id_shop);
            $ids_carriers = array_keys($selected_carriers);
            $perm_c = DHLDPOrder::getPermissionForTransferring($order_obj->id_cart);
            // Initialize default values for all orders
            $order['dhl_assigned'] = false;
            $order['address'] = array();
            $order['addit_services'] = array();
            $order['export_docs'] = array();
            $order['dhl_products'] = array();
            if (in_array($order['id_carrier'], $ids_carriers)) {
                $order['default_dhl_product_code'] = $selected_carriers[$order['id_carrier']]['product'];
                $order['dhl_assigned'] = true;
                $order['dhl_products'] = $this->getFormattedAddedDhlProductsByDeliveryAddress(
                    $order['id_address_delivery'],
                    $order_obj->id_shop
                );
                $order['show_minimum_age'] = $this->isGermanyAddress($order['id_address_delivery']);
                $order['labels'] = $this->getLabelData($order['id_order_carrier']);
                $car = new Carrier((int)$order['id_carrier']);
                $order['carrier_name'] = $car->name;
                $order['selected'] = array();
                if (is_array($order['labels']) && count($order['labels']) > 0) {
                    $order['selected'] = $order['labels'][count($order['labels']) - 1];
                }

                $product_alias = str_replace(':', '_', $order["default_dhl_product_code"]);
                $package_length_key = 'DHL_' . $product_alias . '_LENGTH';
                $package_width_key = 'DHL_' . $product_alias . '_WIDTH';
                $package_height_key = 'DHL_' . $product_alias . '_HEIGHT';
                $package_length = self::getConfig($package_length_key, $order_obj->id_shop);
                $package_width = self::getConfig($package_width_key, $order_obj->id_shop);
                $package_height = self::getConfig($package_height_key, $order_obj->id_shop);
                $order['input_default_values'] = array(
                    'weight' => (is_array($carrier_input) && isset($carrier_input[$order['id_order_carrier']]['weight'])) ? $carrier_input[$order['id_order_carrier']]['weight'] :
                        ((isset($order['selected']['packages'][0]['weight'])) ? $order['selected']['packages'][0]['weight'] : $this->getOrderWeight($order_obj, $product_alias)),
                    'width' => (is_array($carrier_input) && isset($carrier_input[$order['id_order_carrier']]['width'])) ? $carrier_input[$order['id_order_carrier']]['width'] :
                        ((isset($order['selected']['packages'][0]['width'])) ? $order['selected']['packages'][0]['width'] : $package_width),
                    'height' => (is_array($carrier_input) && isset($carrier_input[$order['id_order_carrier']]['height'])) ? $carrier_input[$order['id_order_carrier']]['height'] :
                        ((isset($order['selected']['packages'][0]['height'])) ? $order['selected']['packages'][0]['height'] : $package_height),
                    'length' => (is_array($carrier_input) && isset($carrier_input[$order['id_order_carrier']]['length'])) ? $carrier_input[$order['id_order_carrier']]['length'] :
                        ((isset($order['selected']['packages'][0]['length'])) ? $order['selected']['packages'][0]['length'] : $package_length),
                    'DeclaredValueOfGoods' => (is_array($carrier_input) && isset($carrier_input[$order['id_order_carrier']]['DeclaredValueOfGoods'])) ? $carrier_input[$order['id_order_carrier']]['DeclaredValueOfGoods'] :
                        ((isset($order['selected']['options_decoded']['DeclaredValueOfGoods'])) ? $order['selected']['options_decoded']['DeclaredValueOfGoods'] : $order_obj->getTotalProductsWithTaxes()),
                    'COD_CODAmount' => (is_array($carrier_input) && isset($carrier_input[$order['id_order_carrier']]['COD_CODAmount'])) ? $carrier_input[$order['id_order_carrier']]['COD_CODAmount'] :
                        ((isset($order['selected']['options_decoded']['COD']['CODAmount'])) ? $order['selected']['options_decoded']['COD']['CODAmount'] : 0),
                    'HigherInsurance_InsuranceAmount' => (is_array($carrier_input) && isset($carrier_input[$order['id_order_carrier']]['HigherInsurance_InsuranceAmount'])) ? $carrier_input[$order['id_order_carrier']]['HigherInsurance_InsuranceAmount'] :
                        ((isset($order['selected']['options_decoded']['HigherInsurance']['InsuranceAmount'])) ? $order['selected']['options_decoded']['HigherInsurance']['InsuranceAmount'] : 0),
                    'CheckMinimumAge_MinimumAge' => (is_array($carrier_input) && isset($carrier_input[$order['id_order_carrier']]['CheckMinimumAge_MinimumAge'])) ? $carrier_input[$order['id_order_carrier']]['CheckMinimumAge_MinimumAge'] :
                        ((isset($order['selected']['options_decoded']['CheckMinimumAge']['MinimumAge'])) ? $order['selected']['options_decoded']['CheckMinimumAge']['MinimumAge'] : self::getConfig('DHL_AGE_CHECK', $order_obj->id_shop)),
                );
                $order['address'] = $this->getTemplateVarsForUpdateAddress(
                    $order_obj,
                    $order['id_order_carrier'],
                    $order['id_address_delivery'],
                    $perm_c
                );
                $order['addit_services'] = $this->getTemplateVarsForAdditServices(
                    $order_obj,
                    $order['id_order_carrier'],
                    $order['id_address_delivery'],
                    $perm_c
                );
                $order['export_docs'] = $this->getTemplateVarsForExportDocuments(
                    $order_obj,
                    $order['id_order_carrier'],
                    $order['id_address_delivery']
                );
            } else {
                $order['dhl_assigned'] = false;
            }

            $customer = new Customer((int)$order['id_customer']);

            $order = array_merge(
                $order,
                array(
                    'reference' => $order['reference'],
                    'country' => Country::getNameById($this->context->language->id, $order['id_country']),
                    'customer' => $customer->firstname . ' ' . $customer->lastname,
                    'permission_confirmation' => $perm_c
                )
            );
        }
        return count($orders) ? $orders : false;
    }

    public function displayChangelog($file)
    {
        $this->smarty->assign(
            array(
                'changelog_content' => Tools::file_get_contents($file),
            )
        );

        return $this->display(__FILE__, 'views/templates/admin/changelog.tpl');
    }


    public function getFormattedAddedDhlProducts($added_dhl_products, $to_country = '', $from_country = '', $api_version = '')
    {
        $formatted = array();
        if (is_array($added_dhl_products)) {
            $dhl_products = $this->dhldp_api_rest->getDefinedProducts('', $to_country, $from_country, $api_version);
            $GoGreen = Configuration::get('DHLDP_DHL_DEF_GOGREEN', null, null, null);
            foreach ($added_dhl_products as $added_dhl_product) {
                $a = explode(':', $added_dhl_product);
                foreach ($dhl_products as $dhl_product_key => $dhl_product) {
                    if ($a[0] == $dhl_product_key || $a[0] == $dhl_product['alias_v2']) {
                        $formatted[] = array(
                            'fullcode' => $added_dhl_product,
                            'fullname' => ($GoGreen) ? $dhl_product['name'] . ' GoGreen' : $dhl_product['name'],
                            'code' => $a[0],
                            'name' => $dhl_product['name'],
                            'part' => $a[1],
                            'gogreen' => ($GoGreen) ? 'GoGreen' : '',
                            'definition' => $dhl_product
                        );
                        break;
                    }
                }
            }
        }
        return $formatted;
    }

    public function getDhlCarriers($with_referenced_carriers = false, $ids_only = true, $id_shop = null)
    {
        $carriers_data = explode(',', self::getConfig('DHL_CARRIERS', $id_shop));

        $result = array();
        foreach ($carriers_data as $carrier_data) {
            $adata = explode('|', $carrier_data);
            if (isset($adata[1])) {
                $result[(int)$adata[0]] = array('product' => $adata[1]);
            } else {
                $result[(int)$adata[0]] = array('product' => '');
            }
        }
        if ($with_referenced_carriers === false) {
            if ($ids_only == true) {
                return array_keys($result);
            } else {
                return $result;
            }
        } else {
            foreach ($result as $id_carrier => $data) {
                $carrier = new Carrier((int)$id_carrier);
                $ids_referenced_carrier = Db::getInstance()->executeS(
                    'SELECT `id_carrier` FROM `' . _DB_PREFIX_ . 'carrier` WHERE id_reference = ' . (int)$carrier->id_reference . ' ORDER BY id_carrier'
                );
                foreach ($ids_referenced_carrier as $id_referenced_carrier) {
                    $result[(int)$id_referenced_carrier['id_carrier']] = $data;
                }
            }
            if ($ids_only == true) {
                return array_keys($result);
            } else {
                return $result;
            }
        }
    }

    public function getDPCarriers($with_referenced_carriers = false, $id_shop = null)
    {
        $ids_carrier = explode(',', self::getConfig('DP_CARRIERS', $id_shop));
        if ($with_referenced_carriers === false) {
            return $ids_carrier;
        } else {
            $ids_ref_carriers = array();
            foreach ($ids_carrier as $id_carrier) {
                $carrier = new Carrier((int)$id_carrier);
                $ids_referenced_carrier = Db::getInstance()->executeS(
                    'SELECT `id_carrier` FROM `' . _DB_PREFIX_ . 'carrier` WHERE id_reference = ' . (int)$carrier->id_reference . ' ORDER BY id_carrier'
                );
                foreach ($ids_referenced_carrier as $id_referenced_carrier) {
                    $ids_ref_carriers[] = $id_referenced_carrier['id_carrier'];
                }
            }
            return $ids_ref_carriers;
        }
    }


    public function postInitDHLProcess()
    {
        if (Tools::isSubmit('submitSaveOptions')) {
            $form_errors = array();

            if (!in_array(Tools::getValue('DHLDP_DHL_COUNTRY'), array_keys(\PrestaShop\Module\dhldp\classes\DHLDPApiRest::$supported_shipper_countries))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select supported country');
            }
            if (!in_array(Tools::getValue('DHLDP_DHL_API_VERSION'), \PrestaShop\Module\dhldp\classes\DHLDPApiRest::$supported_shipper_countries[Tools::getValue('DHLDP_DHL_COUNTRY')]['api_versions'])) {
                $form_errors[] = $this->_errors[] = $this->l('Please select supported API version');
            }
            if (count($form_errors) == 0) {
                $result_save = Configuration::updateValue('DHLDP_DHL_API_VERSION', Tools::getValue('DHLDP_DHL_API_VERSION')) &&
                    Configuration::updateValue('DHLDP_DHL_COUNTRY', Tools::getValue('DHLDP_DHL_COUNTRY'));
                if ($result_save == true) {
                    $this->_confirmations[] = $this->l('Settings updated');
                    Tools::redirectAdmin($this->getModuleUrl() . '&m=4');
                }
            }
        }
        return $this->displayMessages();
    }

    public function postProcess()
    {
        switch (Tools::getValue('m')) {
            case 1:
                $this->_confirmations[] = $this->l('"Live" account data has been reset.');
                break;
            case 2:
                $this->_errors[] = $this->l('No any log data');
                break;
            case 3:
                $this->_confirmations[] = $this->l('Fixed');
                break;
            case 4:
                $this->_confirmations[] = $this->l('Country and Api Version have been saved successfully');
                break;
        }

        if (Tools::isSubmit('resetLiveAccount')) {
            if (Tools::getValue('DHLDP_DHL_MODE') == 1) {
                if (Configuration::updateValue('DHLDP_DHL_LIVE_USER', '') &&
                    Configuration::updateValue('DHLDP_DHL_LIVE_SIGN', '') &&
                    Configuration::updateValue('DHLDP_DHL_LIVE_EKP', '')
                ) {
                    Tools::redirectAdmin($this->getModuleUrl() . '&m=1');
                }
            }
        }
        return $this->displayMessages();
    }

    public function getLabelFormats()
    {
        return array(
            'A4' => array('name' => 'A4', 'desc' => $this->l('common label laser printing A4 plain paper')),
            '910-300-700' => array('name' => '910-300-700 (A5)', 'desc' => $this->l('common label laser printing 105 x 205 mm (A5 plain paper, 910-300-700)')),
            '910-300-700-oz' => array('name' => '910-300-700-oz (A5)', 'desc' => $this->l('common label laser printing 105 x 205 mm without additional barcode labels (A5 plain paper, 910-300-700)')),
            '910-300-300-oz' => array('name' => '910-300-300-oz (A5)', 'desc' => $this->l('common label laser printing 105 x 148 mm without additional barcode labels (A5 plain paper, 910-300-300)')),
            '910-300-710' => array('name' => '910-300-710 (105x208mm)', 'desc' => $this->l('common label laser printing 105 x 208 mm (910-300-710)')),
            '910-300-600' => array('name' => '910-300-600 (103x199mm)', 'desc' => $this->l('common label thermal printing 103 x 199 mm (910-300-600, 910-300-610)')),
            '910-300-400' => array('name' => '910-300-400 (103x150mm)', 'desc' => $this->l('common label thermal printing 103 x 150 mm (910-300-400, 910-300-410)')),
            '100x70mm' => array('name' => '100x70mm', 'desc' => $this->l('100 x 70 mm label (only for DHL Kleinpaket and Warenpost International)'))
        );
    }

    public function getRetoureLabelFormats()
    {
        return $this->getLabelFormats();
    }

    public function getArrayOptionsForSelect($array)
    {
        if (is_array($array)) {
            $arr = array();
            foreach ($array as $value) {
                $arr[] = array('id' => $value, 'name' => $value);
            }
            return $arr;
        }
        return array();
    }

    public function getAjaxTrackingData()
    {
        $shipment_number = Tools::getValue('shipment_number');
        $id_package = DHLDPPackage::getPackageIDByShipmentNumber($shipment_number);
        if ($id_package) {
            if ($this->getTrackData($shipment_number, $this->context->shop->id) === false) {
                $errors = $this->getRestErrors();
                $this->context->smarty->assign(
                    array(
                        'date_upd' => '',
                        'status' => '',
                        'descr' => implode(', ', $errors),
                        'delivery' => ''
                    )
                );
                return die($this->display(
                    __FILE__,
                    'tracking-data.tpl'
                ));
            } else {
                $package = new DHLDPPackage((int)$id_package);
                if ($package->last_track_status != '') {
                    $this->context->smarty->assign(
                        array(
                            'date_upd' => $package->last_track_date_upd,
                            'status' => $package->last_track_status,
                            'descr' => $package->last_track_descr,
                            'delivery' => $package->last_track_delivery
                        )
                    );
                    return die($this->display(
                        __FILE__,
                        'tracking-data.tpl'
                    ));
                }
            }
        }
        return die('');
    }

    public function getTrackData($shipment_number, $id_shop = null)
    {
        // check last status for package before sending request;
        $id_package = DHLDPPackage::getPackageIDByShipmentNumber($shipment_number);
        if ($id_package) {
            $dhldp_package = new DHLDPPackage((int)$id_package);
            if ($dhldp_package->last_track_delivery == 1) {
                //$this->setDeliveredStatusToOrder($id_package);
                return true;
            } else {
                $track_data = $this->dhldp_api_rest->callDhlTrackingApi($shipment_number, $id_shop);
                if ($track_data) {
                    if (isset($track_data['status-code'])) {
                        $dhldp_package->last_track_status = $track_data['status-code'];
                    }
                    if (isset($track_data['short-status'])) {
                        $dhldp_package->last_track_descr = $track_data['short-status'];
                    }
                    $delivered = false;
                    if (isset($track_data['delivery-event-flag'])) {
                        $dhldp_package->last_track_delivery = (int)$track_data['delivery-event-flag'];
                        if ((int)$track_data['delivery-event-flag'] > 0) {
                            $delivered = true;
                        }
                    }
                    $dhldp_package->last_track_date = date('Y-m-d H:i:s');
                    $dhldp_package->last_track_date_upd = date('Y-m-d H:i:s');
                    $dhldp_package->save();
                    if ($delivered == true) {
                        $this->setDeliveredStatusToOrder($id_package);
                    }
                } else {
                    return false;
                }
            }
        }
        return false;
    }

    public function getDPLabelData($id_order_carrier)
    {
        if (!is_array($id_order_carrier)) {
            $id_order_carrier = array($id_order_carrier);
        }

        if (count($id_order_carrier) > 0) {
            $selected_values = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'dhldp_dp_label` l
                 WHERE l.`id_order_carrier` IN (' . implode(',', array_map('intval', $id_order_carrier)) . ')' .
                ' ORDER BY `date_add`'
            );

            foreach ($selected_values as $selected_value_index => $selected_value) {
                $product_info = $this->dp_api->getProducts($selected_value['product']);
                if (isset($product_info['name'])) {
                    $selected_values[$selected_value_index]['product_name'] = $product_info['name'];
                } else {
                    $selected_values[$selected_value_index]['product_name'] = '';
                }


                if ($selected_values[$selected_value_index]['dp_track_id'] != '') {
                    $selected_values[$selected_value_index]['dp_track_link'] = 'https://www.deutschepost.de/sendung/simpleQuery.html?form.sendungsnummer=' .
                        $selected_values[$selected_value_index]['dp_track_id'] . '&form.einlieferungsdatum_tag=' . date('d', strtotime($selected_values[$selected_value_index]['date_add'])) .
                        '&form.einlieferungsdatum_monat=' . date('m', strtotime($selected_values[$selected_value_index]['date_add'])) . '&form.einlieferungsdatum_jahr=' .
                        date('Y', strtotime($selected_values[$selected_value_index]['date_add']));
                } else {
                    $selected_values[$selected_value_index]['dp_track_link'] = '';
                }


                if ($selected_value['label_format'] == '') {
                    $selected_values[$selected_value_index]['label_format'] = 'png';
                }
                if ($selected_value['label_format'] == 'pdf') {
                    $page_format = $this->dp_api->getPageFormats($selected_value['page_format_id']);
                    if ($page_format !== false) {
                        $selected_values[$selected_value_index]['page_format_name'] = $page_format['name'];
                    } else {
                        $selected_values[$selected_value_index]['page_format_name'] = '';
                    }
                    $label_position = explode(':', $selected_values[$selected_value_index]['label_position']);
                    $selected_values[$selected_value_index]['label_position_detail'] = array(
                        'page' => $label_position[0],
                        'col' => $label_position[1],
                        'row' => $label_position[2]
                    );
                    $selected_values[$selected_value_index]['label_position_name'] = $this->l('Page') . ': ' . $label_position[0] .
                        '; ' . $this->l('Column') . ': ' . $label_position[1] .
                        '; ' . $this->l('Row') . ': ' . $label_position[2];
                } else {
                    $selected_values[$selected_value_index]['page_format_name'] = '';
                }
            }
            return $selected_values;
        }
        return false;
    }

    public function filterDPShipping($shipping, $id_shop)
    {
        $return_shipping = [];
        if (is_array($shipping)) {
            foreach ($shipping as $shipping_item) {
                if (in_array($shipping_item['id_carrier'], $this->getDPCarriers(true, $id_shop))) {
                    $return_shipping[] = $shipping_item;
                }
            }
            return $return_shipping;
        }
        return [];
    }

    public function createDPDeliveryLabel($id_shop, $id_address, $product, $additional_info, $label_position, $id_order_carrier)
    {
        $sender = $this->dp_api->getSender($id_shop);
        $receiver = $this->dp_api->getReciver(new Address((int)$id_address));
        $position = new stdClass();
        $position->productCode = $product;
        $position->address = new stdClass();
        $position->address->sender = $sender;
        $position->address->receiver = $receiver;
        $position->additionalInfo = $additional_info;
        $position->voucherLayout = $this->dp_api->voucher_layout;
        if (Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $id_shop) == 'pdf') {
            $position->positionType = 'AppShoppingCartPDFPosition';
        }
        if (Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $id_shop) == 'png') {
            $position->positionType = 'AppShoppingCartPosition';
        }
        $position_page = 1;
        $position_col = 1;
        $position_row = 1;

        if (Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $id_shop) == 'pdf') {
            $position->position = new stdClass();
            $position->position->page = $position_page = $label_position['page'];
            $position->position->labelX = $position_col = $label_position['col'];
            $position->position->labelY = $position_row = $label_position['row'];
        }

        $positions = array($position);
        $product_info = $this->dp_api->getProducts($product);
        $total = $product_info['price'];
        $total_eurocent = Tools::ps_round($product_info['price'] * 100);
        $response = $this->dp_api->callApi(
            'createShopOrderId',
            array(),
            $id_shop,
            true
        );
        if (is_object($response) && isset($response->shopOrderId)) {
            $shop_order_id = (int)$response->shopOrderId;
            $page_format_id = 0;
            if (Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $id_shop) == 'pdf') {
                $page_format_id = (int)Configuration::get('DHLDP_DP_PAGE_FORMAT', false, false, $id_shop);
                $response = $this->dp_api->callApi(
                    'checkoutShoppingCartPDF',
                    array(
                        //'ppl' => $this->dp_api->ppl,
                        'type' => 'AppShoppingCartPDFRequest',
                        'shopOrderId' => $shop_order_id,
                        'total' => $total_eurocent,
                        'createManifest' => (bool)Configuration::get('DHLDP_DP_CREATE_MANIFEST', false, false, $id_shop),
                        'createShippingList' => (int)Configuration::get('DHLDP_DP_CREATE_SHIPLIST', false, false, $id_shop),
                        'pageFormatId' => ($page_format_id == 0) ? 1 : $page_format_id,
                        'positions' => $positions,
                    ),
                    $id_shop,
                    true
                );
            } else {
                $response = $this->dp_api->callApi(
                    'checkoutShoppingCartPNG',
                    array(
                        //'ppl' => $this->dp_api->ppl,
                        'type' => 'AppShoppingCartPNGRequest',
                        'shopOrderId' => $shop_order_id,
                        'total' => $total_eurocent,
                        'createManifest' => (bool)Configuration::get('DHLDP_DP_CREATE_MANIFEST', false, false, $id_shop),
                        'createShippingList' => (int)Configuration::get('DHLDP_DP_CREATE_SHIPLIST', false, false, $id_shop),
                        'positions' => $positions,
                    ),
                    $id_shop,
                    true
                );
            }

            //stdClass Object ( [link] => https://internetmarke.deutschepost.de/PcfExtensionWeb/document?keyphase=0&data=ihiNb0veRtkpv%2FxXrOE4q6SZ5r41RWPG [walletBallance] => 148709 [shoppingCart] => stdClass Object ( [orderId] => 938899224 [voucherList] => stdClass Object ( [voucherId] => A0011E78DF0000019569 ) ) )

            if (is_object($response) && isset($response->link)) {
                $dp_label = new DPLabel();
                $dp_label->id_order_carrier = (int)$id_order_carrier;
                $dp_label->product = $product;
                $dp_label->total = (float)$total;
                $dp_label->wallet_ballance = (float)Tools::ps_round($response->walletBallance / 100, 2);
                $dp_label->additional_info = $additional_info;
                $dp_label->dp_order_id = $response->shoppingCart->shopOrderId;
                $dp_label->dp_voucher_id = isset(reset($response->shoppingCart->voucherList)->voucherId) ? reset($response->shoppingCart->voucherList)->voucherId : '';
                $dp_label->dp_track_id = isset(reset($response->shoppingCart->voucherList)->trackId) ? reset($response->shoppingCart->voucherList)->trackId : '';
                $dp_label->is_complete = 1;
                $dp_label->dp_link = $response->link;
                $dp_label->manifest_link = isset($response->manifestLink) ? $response->manifestLink : '';
                $dp_label->label_format = (Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $id_shop) == 'pdf') ? 'pdf' : 'png';
                $dp_label->page_format_id = (Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $id_shop) == 'pdf') ? $page_format_id : 0;
                $dp_label->label_position = (Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $id_shop) == 'pdf') ?
                    ($position_page . ':' . $position_col . ':' . $position_row) : '';

                if (!$dp_label->add()) {
                    return false;
                } else {
                    if (isset(reset($response->shoppingCart->voucherList)->trackId)) {
                        $this->updateDPOrderCarrierWithTrackingNumber(
                            (int)$id_order_carrier,
                            reset($response->shoppingCart->voucherList)->trackId
                        );
                        $this->updateOrderStatus($id_order_carrier, 'createDPDeliveryLabel');
                    }
                }
                return true;
            }
        }
        return false;
    }

    public function updateDPOrderCarrierWithTrackingNumber($id_order_carrier, $tracking_number)
    {
        $order_carrier = new OrderCarrier((int)$id_order_carrier);

        if (Validate::isLoadedObject($order_carrier)) {
            $order = new Order((int)$order_carrier->id_order);
            $order->shipping_number = $tracking_number;
            $order->update();
            $order_carrier->tracking_number = $tracking_number;

            if ($order_carrier->update()) {
                // Send mail to customer
                $customer = new Customer((int)$order->id_customer);
                $carrier = new Carrier((int)$order->id_carrier, $order->id_lang);
                $tracking_url = str_replace('[tracking_number]', $tracking_number, DPRestApi::$tracking_url);
                $template_vars = array(
                    '{followup}' => $tracking_url,
                    '{firstname}' => $customer->firstname,
                    '{lastname}' => $customer->lastname,
                    '{id_order}' => $order->id,
                    '{shipping_number}' => $order->shipping_number,
                    '{order_name}' => $order->getUniqReference()
                );

                if (Mail::Send(
                    (int)$order->id_lang,
                    'in_transit',
                    $this->l('Package in transit'),
                    $template_vars,
                    $customer->email,
                    $customer->firstname . ' ' . $customer->lastname,
                    null,
                    null,
                    null,
                    null,
                    _PS_MAIL_DIR_,
                    true,
                    (int)$order->id_shop
                )
                ) {
                    Hook::exec(
                        'actionAdminOrdersTrackingNumberUpdate',
                        array('order' => $order, 'customer' => $customer, 'carrier' => $carrier),
                        null,
                        false,
                        true,
                        false,
                        $order->id_shop
                    );
                }
                return true;
            }
        }
        return false;
    }

    public function getCountriesIDsForRA($iso_code = null)
    {
//        https://gist.github.com/vxnick/380904
        $countries = [
            "AF" => "AFG",
            "AX" => "ALA",
            "AL" => "ALB",
            "DZ" => "DZA",
            "AS" => "ASM",
            "AD" => "AND",
            "AO" => "AGO",
            "AI" => "AIA",
            "AQ" => "ATA",
            "AG" => "ATG",
            "AR" => "ARG",
            "AM" => "ARM",
            "AW" => "ABW",
            "AU" => "AUS",
            "AT" => "AUT",
            "AZ" => "AZE",
            "BS" => "BHS",
            "BH" => "BHR",
            "BD" => "BGD",
            "BB" => "BRB",
            "BY" => "BLR",
            "BE" => "BEL",
            "BZ" => "BLZ",
            "BJ" => "BEN",
            "BM" => "BMU",
            "BT" => "BTN",
            "BO" => "BOL",
            "BA" => "BIH",
            "BW" => "BWA",
            "BV" => "BVT",
            "BR" => "BRA",
            "IO" => "IOT",
            "BN" => "BRN",
            "BG" => "BGR",
            "BF" => "BFA",
            "BI" => "BDI",
            "KH" => "KHM",
            "CM" => "CMR",
            "CA" => "CAN",
            "CV" => "CPV",
            "KY" => "CYM",
            "CF" => "CAF",
            "TD" => "TCD",
            "CL" => "CHL",
            "CN" => "CHN",
            "CX" => "CXR",
            "CC" => "CCK",
            "CO" => "COL",
            "KM" => "COM",
            "CG" => "COG",
            "CD" => "COD",
            "CK" => "COK",
            "CR" => "CRI",
            "CI" => "CIV",
            "HR" => "HRV",
            "CU" => "CUB",
            "CY" => "CYP",
            "CZ" => "CZE",
            "DK" => "DNK",
            "DJ" => "DJI",
            "DM" => "DMA",
            "DO" => "DOM",
            "EC" => "ECU",
            "EG" => "EGY",
            "SV" => "SLV",
            "GQ" => "GNQ",
            "ER" => "ERI",
            "EE" => "EST",
            "ET" => "ETH",
            "FK" => "FLK",
            "FO" => "FRO",
            "FJ" => "FJI",
            "FI" => "FIN",
            "FR" => "FRA",
            "GF" => "GUF",
            "PF" => "PYF",
            "TF" => "ATF",
            "GA" => "GAB",
            "GM" => "GMB",
            "GE" => "GEO",
            "DE" => "DEU",
            "GH" => "GHA",
            "GI" => "GIB",
            "GR" => "GRC",
            "GL" => "GRL",
            "GD" => "GRD",
            "GP" => "GLP",
            "GU" => "GUM",
            "GT" => "GTM",
            "GG" => "GGY",
            "GN" => "GIN",
            "GW" => "GNB",
            "GY" => "GUY",
            "HT" => "HTI",
            "HM" => "HMD",
            "VA" => "VAT",
            "HN" => "HND",
            "HK" => "HKG",
            "HU" => "HUN",
            "IS" => "ISL",
            "IN" => "IND",
            "ID" => "IDN",
            "IR" => "IRN",
            "IQ" => "IRQ",
            "IE" => "IRL",
            "IM" => "IMN",
            "IL" => "ISR",
            "IT" => "ITA",
            "JM" => "JAM",
            "JP" => "JPN",
            "JE" => "JEY",
            "JO" => "JOR",
            "KZ" => "KAZ",
            "KE" => "KEN",
            "KI" => "KIR",
            "KR" => "KOR",
            "KW" => "KWT",
            "KG" => "KGZ",
            "LA" => "LAO",
            "LV" => "LVA",
            "LB" => "LBN",
            "LS" => "LSO",
            "LR" => "LBR",
            "LY" => "LBY",
            "LI" => "LIE",
            "LT" => "LTU",
            "LU" => "LUX",
            "MO" => "MAC",
            "MK" => "MKD",
            "MG" => "MDG",
            "MW" => "MWI",
            "MY" => "MYS",
            "MV" => "MDV",
            "ML" => "MLI",
            "MT" => "MLT",
            "MH" => "MHL",
            "MQ" => "MTQ",
            "MR" => "MRT",
            "MU" => "MUS",
            "YT" => "MYT",
            "MX" => "MEX",
            "FM" => "FSM",
            "MD" => "MDA",
            "MC" => "MCO",
            "MN" => "MNG",
            "ME" => "MNE",
            "MS" => "MSR",
            "MA" => "MAR",
            "MZ" => "MOZ",
            "MM" => "MMR",
            "NA" => "NAM",
            "NR" => "NRU",
            "NP" => "NPL",
            "NL" => "NLD",
            "AN" => "ANT",
            "NC" => "NCL",
            "NZ" => "NZL",
            "NI" => "NIC",
            "NE" => "NER",
            "NG" => "NGA",
            "NU" => "NIU",
            "NF" => "NFK",
            "MP" => "MNP",
            "NO" => "NOR",
            "OM" => "OMN",
            "PK" => "PAK",
            "PW" => "PLW",
            "PS" => "PSE",
            "PA" => "PAN",
            "PG" => "PNG",
            "PY" => "PRY",
            "PE" => "PER",
            "PH" => "PHL",
            "PN" => "PCN",
            "PL" => "POL",
            "PT" => "PRT",
            "PR" => "PRI",
            "QA" => "QAT",
            "RE" => "REU",
            "RO" => "ROU",
            "RU" => "RUS",
            "RW" => "RWA",
            "BL" => "BLM",
            "SH" => "SHN",
            "KN" => "KNA",
            "LC" => "LCA",
            "MF" => "MAF",
            "PM" => "SPM",
            "VC" => "VCT",
            "WS" => "WSM",
            "SM" => "SMR",
            "ST" => "STP",
            "SA" => "SAU",
            "SN" => "SEN",
            "RS" => "SRB",
            "SC" => "SYC",
            "SL" => "SLE",
            "SG" => "SGP",
            "SK" => "SVK",
            "SI" => "SVN",
            "SB" => "SLB",
            "SO" => "SOM",
            "ZA" => "ZAF",
            "GS" => "SGS",
            "ES" => "ESP",
            "LK" => "LKA",
            "SD" => "SDN",
            "SR" => "SUR",
            "SJ" => "SJM",
            "SZ" => "SWZ",
            "SE" => "SWE",
            "CH" => "CHE",
            "SY" => "SYR",
            "TW" => "TWN",
            "TJ" => "TJK",
            "TZ" => "TZA",
            "TH" => "THA",
            "TL" => "TLS",
            "TG" => "TGO",
            "TK" => "TKL",
            "TO" => "TON",
            "TT" => "TTO",
            "TN" => "TUN",
            "TR" => "TUR",
            "TM" => "TKM",
            "TC" => "TCA",
            "TV" => "TUV",
            "UG" => "UGA",
            "UA" => "UKR",
            "AE" => "ARE",
            "GB" => "GBR",
            "US" => "USA",
            "UM" => "UMI",
            "UY" => "URY",
            "UZ" => "UZB",
            "VU" => "VUT",
            "VE" => "VEN",
            "VN" => "VNM",
            "VG" => "VGB",
            "VI" => "VIR",
            "WF" => "WLF",
            "EH" => "ESH",
            "YE" => "YEM",
            "ZM" => "ZMB",
            "ZW" => "ZWE",
            "UNKNOWN" => "UNKNOWN",
        ];

        if ($iso_code == null) {
            return $countries;
        } else {
            foreach ($countries as $iso => $alpha3) {
                if ($iso == $iso_code) {
                    return ['iso_code' => $iso, 'iso_code3' => $alpha3];
                }
            }
        }
        return false;
    }

    private function setDeliveredStatusToOrder($id_package)
    {
        $order_data = DHLDPPackage::getOrderByPackageID($id_package);
        if ($order_data && Configuration::get('DHLDP_DHL_CHANGE_OS_DELIVERED', null, null, $order_data['id_shop']) > 0) {
            $order = new Order((int)$order_data['id_order']);
            $order_state = new OrderState((int)Configuration::get('DHLDP_DHL_CHANGE_OS_DELIVERED'), null, null, (int)$order->id_shop);

            if (Validate::isLoadedObject($order_state)) {
                $current_order_state = $order->getCurrentOrderState();
                if ($current_order_state->id != $order_state->id) {
                    // Create new OrderHistory
                    $history = new OrderHistory();
                    $history->id_order = (int)$order->id;
                    $history->id_employee = (isset($this->context) && isset($this->context->employee)) ? (int)$this->context->employee->id : 0;

                    $use_existings_payment = false;
                    if (!$order->hasInvoice()) {
                        $use_existings_payment = true;
                    }
                    $history->changeIdOrderState((int)$order_state->id, $order, $use_existings_payment);
                    $carrier = new Carrier($order->id_carrier, $order->id_lang);
                    if ($history->addWithemail(true)) {

                    }
                }
            }
        }
    }

    public function hookActionGetAdminOrderButtons($params)
    {
        $bar = $params['actions_bar_buttons_collection'];

        if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
            $bar->add(
                new PrestaShop\PrestaShop\Core\Action\ActionsBarButton(
                    'btn-warning btn-scroll-to-dhldp-block',
                    [],
                    $this->l('Print DHL label')
                )
            );
        } else {
            $bar->add(
                new PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton(
                    'btn-warning btn-scroll-to-dhldp-block',
                    array(),
                    '<i class="material-icons" aria-hidden="true">print</i> ' . $this->l('Print DHL label')
                )
            );
        }
    }

    public function getDhlProductDimensions($product_code = null, $id_order = null)
    {
        if (!$product_code) {
            return false;
        }

        $order = null;
        if ($id_order) {
            $order = new Order($id_order);
        }

        $product_code = str_replace(':', '_', $product_code);
        $context = Context::getContext();
        $id_shop = (int)$context->shop->id;
        $id_shop_group = (int)$context->shop->id_shop_group;

        $weight = '';
        if ($order) {
            $weight = $this->getOrderWeight($order, $product_code);
        } else {
            $weight = Configuration::get('DHLDP_DHL_' . $product_code . '_WEIGHT', null, $id_shop_group, $id_shop) ?: '';
        }

        $dimensions = array(
            'LENGTH' => Configuration::get('DHLDP_DHL_' . $product_code . '_LENGTH', null, $id_shop_group, $id_shop) ?: '',
            'WIDTH' => Configuration::get('DHLDP_DHL_' . $product_code . '_WIDTH', null, $id_shop_group, $id_shop) ?: '',
            'HEIGHT' => Configuration::get('DHLDP_DHL_' . $product_code . '_HEIGHT', null, $id_shop_group, $id_shop) ?: '',
            'WEIGHT' => $weight
        );
        return $dimensions;
    }

    public function updateAjaxDhlProductDimensions()
    {
        $product_code = Tools::getValue('dhl_product_dimension');

        if (!$product_code) {
            die(json_encode([
                'success' => false,
                'message' => $this->l("Product code is required")
            ]));
        }

        $dimensions = [
            'LENGTH' => Tools::getValue('defaultLength'),
            'WIDTH' => Tools::getValue('defaultWidth'),
            'HEIGHT' => Tools::getValue('defaultHeight'),
            'WEIGHT' => Tools::getValue('defaultWeight')
        ];

        $errors = [];
        $pattern = '/^[0-9]{1,10}([,.]{1}[0-9]{1,9})?$/';
        $context = Context::getContext();
        $id_shop = (int)$context->shop->id;
        $id_shop_group = (int)$context->shop->id_shop_group;

        foreach ($dimensions as $key => $value) {
            $value = trim($value);
            $config_key = 'DHLDP_DHL_' . strtoupper(str_replace(':', '_', $product_code)) . '_' . $key;

            if ($value !== '' && preg_match($pattern, $value)) {
                Configuration::updateValue($config_key, $value, false, $id_shop_group, $id_shop);
            } else {
                Configuration::deleteByName($config_key, $id_shop_group, $id_shop);
                if ($value !== '') {
                    $errors[] = $this->l("Please enter a correct value for {$key}");
                }
            }
        }

        header('Content-Type: application/json');
        if (!empty($errors)) {
            die(json_encode([
                'success' => false,
                'message' => implode(', ', $errors)
            ]));
        }
        die(json_encode([
            'success' => true,
            'message' => $this->l('Product dimensions saved successfully')
        ]));
    }

    public function getAjaxDhlProductDimensions()
    {
        $product_code = Tools::getValue('dhl_product_dimension');
        $orderId = Tools::getValue('orderId');

        if (!$product_code) {
            die(json_encode([
                'success' => false,
                'message' => $this->l("Product code is required")
            ]));
        }

        $dimensions = $this->getDhlProductDimensions($product_code, $orderId);
        die(json_encode([
            'success' => true,
            'dimensions' => $dimensions
        ]));
    }

    public function getCountriesForRA($id_lang, $limited = array(), $with_keys = false)
    {
        $c = array();
        if (count($limited) == 0) {
            $res = $this->getCountriesIDsForRA();
            foreach ($res as $iso_code => $item) {
                $c[] = '\'' . $iso_code . '\'';
            }
        } else {
            $res = $limited;
            foreach ($res as $item) {
                $c[] = '\'' . $item . '\'';
            }
        }

        $countries = Db::getInstance(_PS_USE_SQL_SLAVE_)->executes(
            'SELECT cl.`name`, c.iso_code
							FROM `' . _DB_PREFIX_ . 'country_lang` as cl, `' . _DB_PREFIX_ . 'country` as c
							WHERE cl.id_country=c.id_country and cl.`id_lang` = ' . (int)$id_lang . '
							and c.iso_code in (' . implode(',', $c) . ')');
        if ($with_keys) {
            $c = array();
            foreach ($countries as $country) {
                $c[$country['iso_code']] = $country['name'];
            }
            return $c;
        }
        return $countries;
    }
}
