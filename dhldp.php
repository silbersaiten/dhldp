<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2025 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.0.5
 * @link      https://www.silbersaiten.de
 */

use PrestaShop\Module\dhldp\classes\DHLDPApiRest;
use PrestaShop\Module\dhldp\classes\DHLTokenManager;
use PrestaShopBundle\Controller\Admin\Sell\Address\AddressController;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/classes/DHLDPApiRest.php');
require_once(dirname(__FILE__) . '/classes/DHLDPApi.php');
require_once(dirname(__FILE__) . '/classes/DHLDPLabel.php');
require_once(dirname(__FILE__) . '/classes/DHLDPPackage.php');
require_once(dirname(__FILE__) . '/classes/DHLDPOrder.php');

require_once(dirname(__FILE__) . '/classes/DPApi.php');
require_once(dirname(__FILE__) . '/classes/DPLabel.php');

require_once(dirname(__FILE__) . '/classes/DHLDPRestClient.php');
require_once(dirname(__FILE__) . '/classes/Env.php');

class DhlDp extends Module
{
    public $dhldp_api_rest;

    public function __construct()
    {
        $this->name = 'dhldp';
        $this->tab = 'shipping_logistics';
        $this->version = '3.0.5';
        $this->author = 'Silbersaiten';
        $this->module_key = '96d5521c4c1259e8e87786597735aa4e';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DHL Deutschepost');
        $this->description = $this->l('DHL and Deutschepost shipment service');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        new \PrestaShop\Module\dhldp\classes\Env($this->name);
//        $this->dhldp_api = new DHLDPApi($this);
        $this->dp_api = new DPApi();
        $this->dhldp_api_rest = new DHLDPApiRest($this);
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->is177 = version_compare(_PS_VERSION_, '1.7.7.0') >= 0 ? 1 : 0;
        $this->is16 = (version_compare(_PS_VERSION_, '1.6.0.0') >= 0 && version_compare(_PS_VERSION_, '1.7.0.0') < 0) ? 1 : 0;
    }

    public function install()
    {

        $return = true;

        $return &= parent::install();

        $return &= $this->createDbTables();
        $return &= $this->installTab('AdminDhldpManifest', 'DHL', 'AdminParentShipping', true);
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

        if (!Configuration::hasKey('DHLDP_INTRANSIT_MAIL')) {
            Configuration::updateValue('DHLDP_INTRANSIT_MAIL', 1);
        }

        $this->dp_api->retrievePageFormats();

        return (bool)$return;
    }

    public function uninstall()
    {
        $return = true;
        $return &= $this->uninstallTab('AdminDhldpManifest');
        $return &= $this->uninstallTab('AdminDhldpAjax');
        $return &= $this->removeHook('actionGetIDDeliveryAddressByIDCarrier');
        $return &= $this->removeHook('actionGetIDOrderStateByIDCarrier');
        $return &= parent::uninstall();

        return (bool)$return;
    }

    public function reset()
    {
        $return = true;
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
            $hook = new Hook();
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
        $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        if ($mode == 1) {
            $trackingEndpoint = DHLDPApiRest::$cig_endpoint_live;
        } else {
            $trackingEndpoint = DHLDPApiRest::$cig_endpoint_sandbox;
        }

        $trackingEndpoint = $trackingEndpoint . '/orders?shipment=' . $shipment_number . '&profile=STANDARD_GRUPPENPROFIL';

        $dhlManager = new DHLTokenManager();

        $rclient = new \DHLDPRestClient(array('savelog_callback' => 'DHLDP::logToFile'));

        $request = array(
            'profile' => 'STANDARD_GRUPPENPROFIL',
            'shipmentNumber' => $shipment_number,
        );

        $rclient->saveLogData('DHL', 'deleteDeliveryLabel', [
            'endpoint' => $trackingEndpoint,
            'data' => $request
        ]);

        $response = $dhlManager->makeApiRequest($trackingEndpoint, 'DELETE', $request);

        $rclient->saveLogData('DHL', 'deleteDeliveryLabel', null, $response);

        if (is_array($response) && isset($response['items'][0]['shipmentNo'])) {
            $id_dhldp_label = DHLDPLabel::getLabelIDByShipmentNumber($shipment_number);
            if ($id_dhldp_label > 0) {
                $dhldp_label = new DHLDPLabel($id_dhldp_label);
                if ($dhldp_label->delete()) {
                    return true;
                }
            }
        }
        return false;
    }

    public function doManifest($shipment_number, $id_shop = null)
    {
        $this->dhldp_api_rest->setApiVersionByIdShop($id_shop);

        $operation = 'doManifest';
        $request = array(
            'shipmentNumber' => $shipment_number
        );

        $response = $this->dhldp_api_rest->callDHLApi(
            $operation,
            $request,
            $id_shop
        );
        if (is_object($response) && isset($response->shipmentNumber)) {
            return true;
        }
        return false;
    }

    public function createDhlRetoureLabel($sender_address, $id_order_carrier, $reference_number, $id_order_return = 0, $id_shop = null)
    {
        //$mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        $cr = $this->getCountriesIDsForRA($sender_address['country']['countryISOCode']);
        $receiverId = '';
        if ($cr !== false) {
            $receiverId = strtolower($cr['iso_code3']);
            $sender_address['country']['countryISOCode'] = $cr['iso_code3'];
        }
        $return_order = array(
            'receiverId' => $receiverId, //* max 30
            'customerReference' => 'Retoure ' . $reference_number, //max 30 - is displayed visibly on the returns label
            'shipmentReference' => 'Retoure ' . $reference_number, //max 30 - displayed exclusively in the returns overview
            'senderAddress' => $sender_address,
            'email' => '', //max 70
            'telephoneNumber' => '', //max 35
            //'weightInGrams' => 0,
            //'customsDocument' => array(),
            'returnDocumentType' => 'SHIPMENT_LABEL'
        );

        $response = $this->dhldp_api_rest->callDhlRetoureApi(
            $return_order,
            $id_shop
        );

        //$response = '{"shipmentNumber":"999990405288","labelData":"JVBERi0xLjQKJeLjz9MKNCAwIG9iago8PC9GaWx0ZXIvRmxhdGVEZWNvZGUvTGVuZ3RoIDUxPj5zdHJlYW0KeJwr5HIK4TJQsDAx1LOwVAhJ4XIN4QrkKlQwVDAAQgiZnKugH5FmqOCSrxDIBQD9uwpXCmVuZHN0cmVhbQplbmRvYmoKNiAwIG9iago8PC9Db250ZW50cyA0IDAgUi9UeXBlL1BhZ2UvUmVzb3VyY2VzPDwvUHJvY1NldCBbL1BERiAvVGV4dCAvSW1hZ2VCIC9JbWFnZUMgL0ltYWdlSV0vWE9iamVjdDw8L1hmMSAxIDAgUj4+Pj4vUGFyZW50IDUgMCBSL01lZGlhQm94WzAgMCA1OTUuMjggODQxLjg5XT4+CmVuZG9iagoyIDAgb2JqCjw8L1N1YnR5cGUvVHlwZTEvVHlwZS9Gb250L0Jhc2VGb250L0hlbHZldGljYS1Cb2xkL0VuY29kaW5nL1dpbkFuc2lFbmNvZGluZz4+CmVuZG9iagozIDAgb2JqCjw8L1N1YnR5cGUvVHlwZTEvVHlwZS9Gb250L0Jhc2VGb250L0hlbHZldGljYS9FbmNvZGluZy9XaW5BbnNpRW5jb2Rpbmc+PgplbmRvYmoKMSAwIG9iago8PC9TdWJ0eXBlL0Zvcm0vRmlsdGVyL0ZsYXRlRGVjb2RlL1R5cGUvWE9iamVjdC9NYXRyaXggWzEgMCAwIDEgMCAwXS9Gb3JtVHlwZSAxL1Jlc291cmNlczw8L1Byb2NTZXQgWy9QREYgL1RleHQgL0ltYWdlQiAvSW1hZ2VDIC9JbWFnZUldL0ZvbnQ8PC9GMSAyIDAgUi9GMiAzIDAgUj4+Pj4vQkJveFswIDAgNTk1LjI4IDg0MS44OV0vTGVuZ3RoIDI5MDc4Pj5zdHJlYW0KeJysvd2SJNlxpHlfTxFXI6QsGYgID48fiFBmCYIAOIPZwaB7d2S5XFmp7s7uLCIrq6d+iB08zT4cH2Mu1s9RM1U1yyJQEBlShGyLVjXPnwj/1Ox4Vf+3V4fd7Xzc3+67t9s/HnZPr9b7uj/d/B9D8PTq8dV/ffX86rj7/avT7j9s8n9+dTzs/tOr/+v/Puy+e/Xfpv+we//Dq599/eonvzjujqf9suy+/n77F8fdXx+3f7cs+2XdrYd1f7vuvt6uuD/fjuf77utvX/3Fz3/1691vHz6++/T+4S+//ufNs73491+/Oh339931cN1fxpd4Ou/XrVr319v29aA6XvbHu1fX02775yv++bg7rfsD/sX5svv21VaeZ7WpLvvDafvnrd1lFMt1FtsXtMku+/Uyy2X+u+0HMIrj0Yr7CcJRng774/yavNyudb/MYvt+t+K4XWD7hs7n4TuPZtfTaX/avpL5XW7/dz3GPx9v42v/tnz/T6++f7Ucxhc0fgSH4/iBbE23b+dw29/WcXWUx2V/OVq5XWoZ11+8uNyncPvK5jcxf0bTtxUXFNvXe7qOC1233+X8qVz3p1mMH9h1vy6zWE8slvErn7pZ3sc3sX0dt/3pMsvthxLfwfG6P68szuOH/2397sa3G422L+l+277d+Pfbz278NNJ835/nVUK7/RTnm/X7/GUs23c+3z3xu9l+pNehxw99+0mcWS3H/fGEN9N1XOg+3iEst397lnN7N69WbdJLXhdfyXIebxF+1ctpfzvry95Ey2pf9vZdLOm/ja/pcr+On9fbKMdP5TSvd9+fxqfgvL+dWIbYry8/rnDYfsl3/DaOy2yHb3yW1X8f3678sxwXPNvXf9A/d/P48tbLeO+/zfJ829/jrTC+l/M9vnmUEMMf7dMf5ebHF4+vZSvXRaX747tdD+Pty28+FfHNt3L7cq784c9f53n7EFz0njlv7/+7Pt7LdTTPX/35gE+f/+q3W48uPqvtUofb/Odl/PP4LG7/fJwf8vv4zY736fmUH4nt3x0WFrdxI+bn43yNHwbab189vhq72vjwbLfX621r/Pvtbvof4vb821++WuaPMu7Cb6PkT+SrvH2v28fIb97neQP1m/fhvmz32nHz/sVv//N/8rv2bHHa3ca9rfS4ju9j3X6K2ztp9ljXy/YjGD0+Pnz4+CU9ti90+2l7j/Nyu1zZY/f8+u3D6Us6XS7jxvEnOi1f0um6jvtT7bTeF3X68PH9w8PH3fFLmt22n/y5Njvf7/hBL4d1u5X96vXz87t/eXj/Jd3uE1rebbkfbxcw9+HTxw/fPj69fv7uRauXb4Djhrzb9vLxMPgwW11uywHf5df/+TNfzH2842uLDVRL+WpO19sGc/tqHna/ebf9xEYe+OX7d59+/KK+2ydo+7mUvsslksVX27f3zbv/d/e3v/mHkTA+vX/+8EU9tzv6vb7RTofjjWHlN69/t/1Gf/n2m199UbftfrS238NpOaDdb55ef/zD7ruH97v8GTzjh/C598vL1pfjeKl8qravFJ+qdTluN7ifvXt+/sxv+GWr63Hcj8tXedvuS3/s3XIcN/bfvzpu97T7uD9s96ZxWzke7vNXjfqJ9Wkkr3GbCcPldh43OhqipuFyAew3w7qOGKErXM/zl84rRK0rwKArQKArRM0r6IZ5vM6swpvk8XYcsI/6KWveNUNvIPrqS3us67K/qkWWAybxGz+PG3T5Nd2PI51eQNH5azre1gWfxP/68OaHx4+7n+y+evOHh8+8fy4DFt5te6+MnHJZt5C5xPvndrvjrbl1/pIex/sJ0X4mvbjNnPC++dtv3j98+/j86fmHD8/v9z/dnfg/h+vhi7rHV1i6r5e4iX318Pwde9/H/xzOh/V0u31R5+MW6W618+F8wRf+Hz89f/fw/P7h+4f3D89/+Gn9NJ5GgD3rtxu1cTReuRwWxMDTZcw7WbtiPY+cLgVqU4zstf14qYh6KvCKvo5QNJ5vP73Rs3zvty1yFqCf1uP9/JlvvXzf920yWed3cZvh9sTvcsZR1qf9zHspR/moFyblzD9r8583uniDqK3DzG7WYdbW4Tp+p9YhanUYs9BqHVCrw/EYCT/1UVuHywiO1uGCIMk6Jhnqo7YOtxGrrMOsrcP2qfQGKOWfEVT2WT55OXO6qWdt/nG3ND9uniy3ifDk/qjNfx9pwBrM2jpc9/fFO0StDttUtVytA2p1WCY51SFr67CMO6R1WJCPWW9Dtn8NWVuHTegNRvnk/e6r+6OW/3zc+7cwS/PfRzw0f9Tmv0QYzwYXMIL1eQRndcjaOmwTkf8MUFuHK96Z1F9z5MlX1iMmpnSgtg73MbFbh6iNVC/ur8s2A29vmMvpMOY0TA3L+Yz76z/9xf/5T3/5OQC8uFUt28/jeq/3qm2On11+/fDm47fvvnv4yW/fffr45vmH8c/ecxlA3dA0PnrjB3TG5ibqJ9ULPhrUL/lR4StX3KbpuOI2zXqOc9YhanUY257VOqC2DnfM0exwz7maHW7YZbDDrNXhtIHBGkQp/zKmCfOjNv99zKDWIGrrcB5TpnU4YxRnfYwbWOqjtg7jHmYNcEtjuY5sY/6o5Y8PWxriw8ZyG0uu5s/a/Nv/858BautwGjtE6xC1ddjGGP8WUFuHy5iUrEPU6rD9v7nRSgdqddg+fHf/RWZtHa5jZ2cdZm0dVrevxbtlhOPJvKjNe0Okpj1q67CMrat1WLCFZb3x3n+LWVuHddyErcOKmzLr7RbnP8Os1eEa4EjHNcDBDvexjbQOUVuHdSz0rMOKBR/r095vCVGa/z7em+a/473K+jK2ydYganW4nUboVgfU6nAbYdw6ZG0d5sbbOlzwbmW97v1XGaX57+PWav5Zm3+725cGUavDlv3LpwG1OmzTys1/jFlbh2v9NKC2DgtOIKhf8kQiXhn58mC/yaitQ7E371ru61E/qV729kbM0vy38c40/w3vVNZXfDaoj1odxlGJd0BtHe7709k7RG0dLnv/FmYp/zHGBaqjln8D9NneBVFbhzs+GewQtXVYymchanXYWLf695C1dbiUz0LU1mHFsQb1UavDRrOrd0BtHa5jq2sdorYO63hvWocV71XWxyFTh6ytwxXHaXRc8V5lndEu9VGrwwY0/zREbR1il84OUVuH7R3mDa57fzdtvDu4P2v5c3meDtTW4QZesEPU1mEZ71DrsOAdy/pYPxNZW4fruFtbhyvu3qw3XpQOUavD5TiOVNQBtXXYeHDzDlFbh3GmaA3OeyPsZWNe+Uxkbf7rGBitwRUDJOtL/UxkrQ5jIeefKtTqcD3gFIf6qK3DMg7hrMOCQznWJwzW1J9y0OYrN4z6dNww6rO+4DyW+qjV4balG383oFaH27Hc31lbhysOPOm44j3Let3fS4eo1WGjjjeYpfz3w9iFyp+1+S9jtW4NZm0dllgepD5qdrgejuPUhx2itg7XepfP2jos4x1qHRa8Y1mfxvnQk/RRW4fLGNqswwVDHOszDlOpj1odjuPQxjqgtg63/eoNbvv6UzjOFYL5Y6XAOpYs1HPpwleu5R0dtXVYy12etTqMcdR/jqitwy3WONnhlsMyO6z7uzdY93aPH0fM9mbK0ty3cQRt9huOpFlfsCKkPmp1WOaxjjqgVoflVKZZ1tbhiuUXHVe8X1lfYp2W+qjVYZus/BMRtXW4lWmW9R9bGJxPI12t203qvOYjIoflFPuC82H5p788XS/zVON/ud8Ph8N9WY6Hy+Gzi+QXe4TzMs/QfY9wvN9XLM7/4buHZ2wSfv3m24fnDw+7cS5Tdgnn89wcjWlmps11fDyyfMpy48i8g6Y660f5z1j6pf+METnLBYtKqpdcXOYr15jiw3CNIT79F5xH03/J82n6ryM5yX9FkMryNA5e5M9a/ttB5tvBnDfMuXTecu6l84xpI81nDBtZHuf2kWKU5o4nElIejx1kuUZOSvWaa7Z8Zbvp3+3njlL+W8yP6Y/a/FdwLP1XYCzLBTMu1UvOvPHK9jZFpoEhSvnv+ITRf89PHP3xqU9/fOizPMYmJtVRy7/dSw/66Ucp/yWmjvRHbf65hJc/dvJZnpDAqD5lIstXtvvsRT//KOWPgzH610zG9J+RKtN/BiCzPJb3PWvzX8c7Wf4r3thZrrELS3XU8m93UfvcREn/2HTZ25e1+Vczr+ZcyueGtZzbvfVuP3mU8seyl/6ozb+OqV3+FUN8lid8kKg+5Z06X1mD+mFYA/rpj8d56L/nfEn/BVvD9F/w1szyGBu4VEct/9hQ2U8epfw38JH+W/KS/mVv3/4FZ6xZnXCgQO0pDxj4ym2cqck+S/lxfkwxSrmvhRZRyl1pwdr8a/nUXNfyqbkGPKg+5741X7kVWkQp/y32humP2vyFFlHSn6fpVEct/8jS5kcpf2UGa/MvONpI/4I3d5aFGVn+sYyxjkiJg9hTPjBxXBYcJ/9bJ8jnkRB//2r8eO5XHbdGPR4Qu+BQ99+86jb0LLHnvEX2OFxOOGb/u3dv37573v369TcPT7vf/PqX/+t4wuRnnz68eX748GH3m3fvP75+8q9mPHF2WO7jSYXd+N/xWNd17Fu2NI3jxFmdx6kpzonrv56R4Rrl+NfxQhg+4y+Ot01vj40d51BTD2/GQhLG+GGvccKeZ/dvnr9/9/7t649vtp/B9//j/e4fHt8/7P73548P759e//BQHl0Z9+zbbtluvefxnhpPxJ238jDPEKI6H2IwZ7n9isax4YLiMorbZRbX8RDvMt40YRsbndssDpviNBYby3If78lNN4k2yu0jirv/KMaDrChGBA7hLM843ynluMBpFvfxddzHxdbRYz4IOMv5SHN8p9u/G08FRzFXq9/Wn8J8LngMIMvmxzHiYRxQLuMJ1SN+DrO84WlWletpfDXHM4rjDg+4juJ0xY/ldkzfdq1lfO/jtGb7520eGv+8rPhu8W9O42eF/3sZ83cW15H/Nx3K84LpaBlrq1FtreaXfz6Ooxr883bR63hO2r+x+Y2ixwag+Y3Cx3FtVsv4vp54tZXPA8evYaPDTDH5W1njdDd+xPEgbpTrGUvI8fbZmq+nWp7jOdIQX0orHXro677h1ANf6qbH+wPVEXfelC55I93c5/EbWU4n3MmjXIKCW3k7zx/SfEo8y1PGF16d/vi9HGLtsowUPkp8bFAW/zqSqvxR8oBl/o64ltx3N76g6z0e2IgSD2rH93I9xsgbJbNvvrfpRnXIM7r5lVyPWNln6W58r2Otdde3nor4Xnt5yuP17/m7vI9VCt802138dtMne8sjy9V+8YueCM7f5n18f29rucyTx1Gs4zM4PzLrvNC386d0nW+Y/Ehs/+Z0i38eu8wjPx7bF3e96p1zjfhUrvUkIs6Hpwub1nGzs1v0cjtf4+GtD9s9+uH9Tz/7rOln29zX8SnGnf56uf3xh3tbh8t1rBatw3k939Xijzzb2xqNc7Lrn2j0+Ud7W6P7/CMNpdHpeP7CJ3tbryMewfdml+1/vvTJ3t5tPEx7Lt0Ol/PyJ5/s/VyrccyxljfA5XTB1uXv3/74/b8+//CFb4HoZF/U6XK8Xf/MB3xftt1u8dvnzNueTsf1z3i+92XL7SO0lHfr8XZfL1/2fO/LblvsPpZ37nJY4gHkP+vx3pedb/OAyH/Nt1t8nX/k6d7PdbrfB7z+Z7xhTjj/8zfMMv5ESH8g88/oZV/W9XCP5xD/xPOcL3vh0S77us7HY7yR2+Ocf0Y7fml/MX9jM39fD7ftk/t7/bGK08h+ZwbkKM+HK2bFPxKQT+tVzvzTA/Hs7PPz68e3233m4elpe9f4e+iLPkX1Ott79Fo/5dfrih/0z958/Piw+93Tm29/t13nqzcPu9efvt+ut+Xxd08/DAQ8737133/cAvmb59/91e7T2913m+b5X799/PDxh4enh03ysCtfLYP8l3xl29B4X+tP4BbP2scfCtz94dPu4f3b8WU+PX/ul3eZw5w3vc8/HFS+3dsNTR8/fvzxpz/5yYeP2/t9G64e9t/FT/XH7Ye6FT/5Kv7Nh0/bi/8+dR//+49/8+Prb3+31WNWeXj+f34cd4nt7vPw/O9+fLv964e/Of+7b9+9/ebN38wbenkQ7ThPvTihofySN8hIZvf6Btlodvcfz/PTHB1ff/rw3ftP43f4mcf9+099OV/bT/10vx3jD01sv93otPvw8PTNv4Xv2nCdz474T/x4W+PPd0az2fjNw9iu//wXf/3z1x8f3sy32sOb593P/+F/2/3tefez7X75cXwru+0Tu70nH76RbzeeJd/eqb/616fvt//3w6cv+rq2fHSvX9dpDXp8eLP1++b1+/lFzLkzfqL7HT4TD88ftzvGc3wJf/j0L+827dP2L/6Ph/cftrcFfvDjK/3Z6/fj+ODD/kt+9vdDv4Gut+uR9Jk/+4/zM7Rd9jPfYzs2GavkY/lVXhJm//jw5of8AW7//7/8dvd321fJ7/ft7qu3r99//PFxez//f9svYftFbHeZX7x5evP66eGvZvkWMPzw+O7Hv9p9s/3Chu/97h8/zU/6796//v7jX+3ejZvT6/B/wRc8/4Crf8Hn20F/FPg3+pTttp/4fmc/lO9ef4hNSfx4xnvlf9Kv63yaf5T2GOvK81gh3KJ8Yjn3ZaGMXVlUt5GA5bvhD4X2Nn4Rd9+x6Er5Pba6rZlfytynYzzUB/kp/ihrb+aXcvcJi590n7DX6c38Uu4+42Am5efYJ7dmfil3r3GgG/I1znNbM7+Uu29Ybac8nk/LMg7RU8wz9XhhqGSe1dOLVn4h9x5xnJDmY5yb1V52HfcumCdTvOCkvrfyC7n7Mo4W5L7EyUOU53jkKMTnfJoiX7hjnZ3yOwiU5RVLjBRf82H3eOF8jEcxIEf59KKZX8rdJ/y5j3THU2C9mV/K3ed4zC/k53jKrzXzS7l7xSI75fH8WG/ml3J3Pv4d8nz6uzXzS7n7NjKk3Dc8ldyb+aXMvR729uue1dOLVn4h98aRa5rjyDXLPGwOsc6a44XL2FrLPcunF838Uu6+jv8r9zVO6Fozv5S77/G4S8jj+azezC9l7sth7KPoRvn0oplfyt2x6kp3rLN6M7+Uu9e9fdsXrCCzWuJhj5BGKe8Vf1wx5dc4X6m97DrmvR7iDzlAjFLeWzySGeZbPlmf7iUeCQl3PMGU5TEe1AnxMf8oUL5wxoYt5WdsI3szv5S78/GqkOfTVa2ZX8rdc8Ms9xVPZfZmfil3z8Wa3Hds/3ozv5S5b4exJacb5dOLZn4pd697e5vNit7bEgfiIV14Hh4vzFgr8x3PvGUZf5IsxTeeC+KF8YdI7SeO8ulFM7+Uu8/jDE3uM55Lz3KJQ8UQLzxTjBcu8fhSyONPyvRmfil33+Px1JDH33+Q5RWPQKb4mk9EZvQ54Owgg9Esn14080sV9yn+3ELa4w+T9XZ+seJfSlyJ+ullv3K90mHdly9g3ZfrLyW0sDb/pcSWqJ9eNPTLFf+tRJeon172K9ebHTwHjwbzD48rXaO0T27eIzwY0qeciLKFTuVEy2M0M+nB20Kjkp7HM5mZ1lC24Hds36/SGuRMaygzrdXwJvcMYHJnPOvNShKkW2ltypXW4G7RT2nNM5HczFsoW3hT3vJUIzcTE8oWv5SYLOXQzMwDb4tPyjzhPcYfBAjzMf4cQOvlaUreGYHkzYDUW9mF3M20BTnTVmtWwpfcTFuQM221ZiV8yc28BDnzEsoWvpSXPNPQrbzUmpX4JDfzEtzMS61ZiU9yL/FAbMgzIvVmJYvJnWkL6kxbrVUJX/Le4o+thDwjUpQtfF35bIsHLLqVt1qzEr/kZu6Am7kDZQsxyh1B6FM8Tg45SrpbDMlS7gWPCqQ8o0ZvZpdy92X8cUS5L/jTiVnWFLPui1fJYYqVHFBmcqhBQm4mB8iZHFqzEiTkZnKAnMkBZSaHGiTELZEPpBL5ou4kNfIZ6kaDZbyHRT6U9r4l+VBxMwQhN0MouWaCWJuhoBs3Q1OuzVBrZpdyNzdDcHMz1JrZpdxNZkNOZqMkswvC6Sazp5rMhpfMLgiXl8yGmcxGSWYXhMt9jj/GFXJCujUreUBuMhtyMhtlCwDGbIM03WI23GR2QTjd5OaCv6Bkb79rcbNgVF5yE2bCrvZyispLbkJM1LVWBaNyk5uQE3WtWcEo3eLHgr9xIW/6KBuMjB9226Zbd3G4GxLsLg73Mf5AS7hzTO7NCiHk5tQMOadmlBzBIdbUHC9waoacU3NrZpcytxgw5WIAygYUY4Dd9OUmA1AmA5wI8sbfepLiHFV7q0IXuTn3Qs65tzWzS5kbSKFbwEHJMRjiKOUmvSAncFqzAjO5SS/ISa/WrMBMbtILctILJelVYCaGaHLFfV+Ta9Q5CIeck2u+wMk19Jxcez+/Xumw7ssXsO7L9ZdyQ/fJtfDWGtzKPd34W3n8WIE7GpzHk5biL0p7H/BzZoyUj8hESf4WHNMt/k65kNmaFRzLTf7CTf62ZgXHcpO/kJO/KMnfgmO5ebYCOZGLMs9WQqyzFack3YImShK4AFluTs2QE5ooG5BtagbpeM4w5RpVUVak2jlDvMCTAsg5qqLkGAyxTgriBc69kHPubc3sUu7mSQHkRHZrZpcyN+k/1SR2a1XCgLwkOMyELsoWBozghmy5SfDWrACdbs2PUy5ko+T8CLHNj3iBO3PIObahzIkQWu7MndD0Ctjwkv4lDMjNzTPcHNpQNiBr8xwvcAKEnBNga2aXMrc2z1Ouka81s0u5m/yGm9BFmfx2mtMrhk6xsIeSDC1IlZsUhJwURNmQahQ07MlNCqIkBQsUdScXBXH3FbWiTgoWKJp/KbdEo1bvV6loHZKCYUgK9n6Viua/lduqUTDqS7mxFgoa9kaDy3jqXxREab9JUtBIJR/BhZIULFCkWxS8xN9zHuBqzQoU5SYF4SYFW7MCRblJQchJQZSkYIGi3KQg5KTgJf5oxsluVU5BIxXdAhfKBkWbJA17cpOCrVmBIt2aJKdc2IObkyTcNkkaqeQmuFCSggWKcpOCkBNcrVmBotykIOREV2tWoEg3KTjVpGBrVaAoL2dgmIm92sv5Ki8ZCDEZiLIB1RiIF7i/hZwrV5Tc30Js+1tjJN1CZmtml3I3+Qs3+duaFRzLzfkbcs7frVlhu9y5v4U6ad1a2YXcS/JDTvLXXp4D5OXuF2KivrUqmYJu5YYpV25ozUqMkJtzP9wc1VFy7ofY5n5LBnIzKKBk6ighhG7lhilXboC7hRDLDXBz7ww31wStWckkcnPnADl3Dq2ZXcrd3BtAzlEfZYswtjewmEK3UgtKppYSYuTm5A85Y0prVhKR3EwtkDO1oGRqKSFG5FVqAS2VMqLO1FJCjPmXgjBLGb1fTTHWIVNLGDK19H41xZifqSUcTC1RM7XUFPNIBVPLbfxdJkotKO03yc+KwZ4+sR8l2V+igNxkP+RkP0qyv0QBurm/nmrur1urkirkJflhJvlRthhh5McL3EBDzg10a1ZShdx8QhByBoXWzC5lbs3eU67ZG2UNIT57W66QmzGjNbNLmZvkn2rSGt4WIoz88PL0FWaevqJsMLfTVyOs3AQuStK7wJxuMnCqyS1UDafGQNyxuYGecm2gUVYw+Qba0CM3SYSSJCpgkpsTLOSEB8oGJptg7X5Pt27/KCuYnCV4IZ+dgjonVlQNLHp2ylkhM9FRe+k67uX+GWLun1E2KNn+2UhBt8CBkhQqUJKbHIGcHEFJjhSs6B6quzDumroLR827cL0rP9bb7mhwH3/CUndhlPad8H2Cik/GQ8hhsbUp93a5OXlCzmGxNbNLmVuT55RrWGzN7FLu5uQJNyfP1swu5W4+GQ85cdOa2aXcTXZBTna1ZgVlcpNdkBM4KMmugjK6Fz7dPuVa16IsLItKXs68EBNWKJfypvOZ1/giN3GDsoHQ2GV8oVu4gbuhzGZew43cpA/KhjKjD17gzAs5x1SUnHkhtplXtKKZ7IKX7Cook5e7W5g5aqLkEAux7W4NdXKTfChJvgJCuUk+yEk+lCRfASHdmjynXMMiSk6eENvkKVLKnNxERW4WjMrL+Q9yjmwoOf9BbPOfYZZuURclqVsgLDepCzmpi5LULRCWm/Mf5MQsSs5/ENv8Z1ylW5iFuyHc5j8jq9wELcqGcJvgDLRyk7soK8PFXQOtvOQuSnK3YJhucXfKxV2U5G7BsNzcWUPOnTVKDoMQ287aMC03qY2S1C4QF/34xDKAxWGvNSuJwNycHcPOWa+1s4sV/1IwYrNe7+fXKx1ydgxDzo69n1+v+C8FRZZSWkO7XPEz9YSeqaf3qynosaJza3Aef20sQYqKHC1YpTdnwClObNY+lc9y5vkprElNVA3Hx/YVk8BQJ4Brp4pjeXP6gzqJiarB2GY/A2Z6iU9UpGeBqby5b4Y6aVk7VS7Lm/tiqJOWqBqGbVtssEwv0YmK5CwglTc3vlAnKWunymR5k5tQJzZRkZoFovQm96Y4sYeC1CsQlDOpB3VCDxWZVxBIL3eeU03G1U6VpvImL+FNXNZOFZ7y5pQJdRIKVYOfzZhGpPSST6gq+nzCNLjRm6irnSr45I3pFOIAW+1TCSpncg7qxBwqUq5Aj15uKaeaXELVkKcd5fzXHO3O8y+54kwWpd5hR3dxPIKME02UOR5Bq+kIdY43Ic7xJsocb6DVdGO3XXnzLtxb6Trm5e0cYt7PWyu/vadXN9Yp1p0VZb1J686KOld6Ib7oLwwtrfyWTS9vyhDzrowyb8vlJp1ejkQQcyTqrfx2T28u88Kb9/DeStcxb45DIc77cJT1Bq9pyG7h8vKO3lr5DT69HGcg5jgTZb1Ja5qx27C8vCujzNtyuUmnl0dwEHN26a38dk9vjkHhzTGot9J1zJvHbyFOAvRWuo55EyTQJklaIwcLnXn0FuKr/iJQ72SASqcwMqXiCJwVSeKIgUNecqS1cqzQm3/ANcQ5ZfVWDqj0cmiCmOQIb0WSZiZDhbxkUGvlSKKXCIJ40V+SXFo5kujN47IQJzuizNkLWp2WGXbkJYVaK4dSevlHSyHmbNVbOd7ozTEtvIv+etrSStcxbw5pIc4hLcoKQ81oqOfUJW/OZL2VrmPeHO9CnBNZb6XrGHVzuAvM5jTWW+k67s3RLs05ivVmupK7l4JBDWIvutnF3J9jVepzrMo6x6qQa6ryhDH85/EXZSpwoLRfOxOHhQr6lDHgu+svwx7Wu/9d2EwocjOwtGYlv8id+9yQM6O0ZiUM0c1ZcKo5DMLbUoimwfDmPjbMOQC2XrqOexlaIGbSQLmU35unFsspcjO2tGYlxdCtqW7KRX2UnBAh1lznoJeb3G/NSqKgm8/yQM5hLsrD3mPBYV9+0/y7K0JM8qNsmUJ/d4UDm27xG+6WKiwLGLLlJsFbs5IN5CaYISeYUVbEi8wAWe4lIeZeMspGTO0l44X88yghz0Vkb2aXcnduNUOeg11vZpdyN2dEyEnk1swuZW7hfcrF5Nas0F5uAh5uUrk1K7yXm4iHnIhvzQrx5c45E+ocNFurEh7kZUCAnFRHyYBQ8gLdwvyUi81wt8RgnDeyy03Qt2aF+3JzzoWcbG/NSoiQm7CHnIRG2XKD0d6gLDcZ3ZoV+ot6gjZ4JcxGXRKAU9sxbX5iu/erGLcO6758Aeu+XL/HAO1jQ5FnueN+e9fxa5QN4zrLdT7LTVy3ZnYpc4v9Uy5ct2YlCshN9sNNXLdmJQrIzXUF5NwxoGxRwPYVBmy6xW+UJQsYv43Q8hLYrVVJBnKT/pAT2K1ZCQNyk/6Qk/6tWQkDcnNtATl3DSi5toDY9hbgOxcXU65tQ2tml3I3d9FwM2qg5CYDYm2jPWrIzeSBssUYSx7KFjQzacDLXQa8Osv1mCIzU0vt5RlGXqYWiJlaUDK1lBBDt1LLlCtowM3UUkKM3FxDwM3tAcoWYmwPAcZysJ9yTeMoOdhDbJO9pQO5GRZQthhjycPSgdwMC61ZCSJ0i6JTLoqiJEULVOkWRadc4IObFC1QlZsUhZvga80KVOUmRSEnRVuzAlW5OW5DTmy2ZoXQcpPBkBObrVlBstxkMORkcGtWkCwGiaHghpgXdWeyMdSgaQ2Sob1fZepjpeTwz/9shqCJ0n6HTNjGR/mIy9amoFhushdy4rI1KyimW/ScctETZUWx01OEo5m8g/dWfvw++xrhZCbwai8nKb1aYE+xbvsoK1J8g23QkJsMac0KUuTmEhty3vhRNqTYFttu/HSLAygLU4wDqHmmCTEPNVurQhi5SRHISRGUpEiBitxcoUNObLRmhVB0iyJTLoqgJEUKVOjWannKhQ24uVqG23bLBh25yaDWrCBJbs7OkBM6KDk7Q2yzs0FHbjIIZQOaMQgvcPqFnANra1b4Rrcm0CnX2IiSEyjENoEa4eQm8FDmRAotn+lxvslL3KFsKLU9s/GNbuGuNSsolZv8gpvIQdlgaPyKe3wubnHz5NgGdwOSbW6dMGYncVo7u1jxr/tiX/fF3Xn2gj98piYdl3JPfQE0PlPjpBn22/yPChA8KO2dy3e5IUc+Eghlw5kRyBhDt5DTmhW6yc3dLdwc2FA2nNnuFi9w+oOc019rZpdyN4+cISfwUPLIGWI7cxbhaCbvWquCUnnJTpjJztrLSSov506IOSq2VoXKcnNyhJyTI0pOjhDb5IgX8ingkHNUbM3sUu7m3Ak5R8XWzC5lbu2sp1zDHsoWAmxnbVyXm5hvzexS7mZmgJyZoTUrEYJuzp1TzYzQWpU0Ii/zBswMCbWXpw95mTcgZt5orUr8kJsH9pAzYKDkgT3EdmJvmYBuRQSUHGIhtm05XsgDcKhzO46qhQ87AccL3LRDzk177aXruJfPYUHMaNJa2YXMrZxzw39nPaNJa1Zij9zMOXAz57RmJfbIzZwDOXMOSuacEnvk5qQOOYNNa1YylNzMOZAz56BsoclyDhIBt/xTrsV8a1YylNzc8sPNWNSa2aXczYwFOTNWa1Yil9zMSZAz2qBkTiqxiW7lpClXTmrNSmySmzsGuLkWaM1KBpObOwbIGataM7uUu5nRIGdGa81KZJObWwLIGcpQcksAsW0JLIMpqzCStWYl8JmbCS/sTHitXQl85l/3xb7ui3sp2C8JL165FXjb8XzUPfHZ+bylsa3BuNnbX3MVZYt8tmuwPEU341WUNbr5tsDylNwZr1qzGt3kzrQV8oxIUTJtlfBFd6YtqDMhtVY1fMmbaSvMGZFqr5K96OWmAmIuF6LkpgJi21RYKpE7Q0pr5pdydyaekGdIac1qAJI7E0/IM6S0ZjUAyZ2JJ+QZU1qzGoDoZmqBnFEjypKALLVYTJE3U0trVUOM3Pnsd8gzprRmNRHJnZkn5Jl5WrMageTODU3Ic6nSmtU8RTcTE+RMTFEyMZUAJXckplBHyomKiakEKHkzMYU8Y07tVfITvUwtEDO1RFkjkKcWSxZyZ9CIkqmlhBi5M7WEPFNLa1ZDjNyZWkKeQSNKppYSYuhmaoGcQaM1qyFG7kwt4c6g0ZrVECN3ppaQZ2ppzWqIkTueTQh1bIJaq5qH5M3EE/IMKVEy8ZQARDczC+SMGVHWAOSZxXKF3BkzWrMaYeTOk42QZ8yIskUYO9mwXCF3xozWrEYYMZupIyjN1JF1CTGeOjwVmP9SCPYihnAv5Klg2Od/PJwhIUr7HeTny8OFfMwarU2JHnRzLwQ5w0W4cy8Ubu2FPE3IzaTSmpXgInfuhUKee6EoW3DRXsgDAt3KCyhL9lBeiDr3KyHOlUiUuV8JsfYrnifoZrwIdy5Mwq2zdc8TcjOrtGYlusjNrAI5s0prVqKL3EwbkDNtoGzRRWnD44XcTButWQkfdCsxTLkSA8oWPpQYnKx0E7ThzoOScOt83dkqd6I2ygZxnYw44egm8KJsMNXT7044uUnP1qzAVO482wh5Ai/KBlOdbTij6Bb/4G4wFf+cUXKTf61ZwaHc5B/k5F9rVnAodz4ZEPKEVpR5UBJiPRngyJObBGzNChDpFgGnXAREmQSsQJSbDIOcDEOZDKtI091cFMIdWBSKOihUoWT+PJ9PfU67L/pVylmHmJ7TENh60c+vB78BaPiX8TsVj1A2rBmPDCF0iygoK56cKLrr00wGwHsrPwibPx0gMpMntZfTRd58TizEBAjKXP6HWM+JOX7kJo1aswInuXNfH3LiB2Xu60Osfb3zhm7hpzUraKNb9/Qp1z0dJe/p5RYvN6dAyDkFtmaFF3JzCoScU2BrZpdyd560h5wAQcmhEGKdtAcxOItNuWYxlDnXQctZLOrcXoc4F869lV3I3NxeQ87RrTezS7mbJIOb8EFJkhWwyc1JDnLCB2UDmyY5p43chA/KBjZNcs4LuoUPlBVsziKDj9xkUWtW0CR3zmJQ5yzWWhXKyZsn/CEnuGovXce9pBjEpBjKhkSjmJGGboEHJQcziLX/dc7oLk7swM3RCm5tcAtlzE7qtHYFauYnxUJP6vR+lWrWYd2XL2Ddl+t3KhrFjFPDfx6/UWELpX1qmNcMd/KRfq1NgaHcecYfco5frVkhK92a5aZcsGzN7FLuJnnhJnlbswJiubl1hpzDW2tWqC43J0HIOQm2ZnYpd1/jD7SHPJfUvZldytzi/pSL+yhLhjDuG9nlJehbq5Io6NYkOOUa3lBWFPskiBc4y0HO8QslZzmIbZYD4Q57e6vkrje8HOzg1Wm3c11mYr720nXcy8QAMSHfWpUAITcTA+SEfGtWAoTcTAyQMzG0ZiVAyM29MeSMCK1ZSSN0awKdck2gKDmBQmwTqBKFzJkvWiu7kHm1+51yQR5lDRC2+3Wqy03Io2wBwqZX4zLdwjTcLQLY/GlclpuYbs1KBJCbzIecoG7NSgSQO5kPdXK6tSoRQF5yG3JyGyW5XTBON0+cIde42ZqVTCA3969wE/MoOcpCrP2rhwS5mRlasxIh5Ob+FXKGBJQchCHW/tUzgdjHiNCalQBibiaOsDMhtHYlgJifiSP0TAi9X00g1iETRxgycfR+NYGY/1JAZPvj1rAkGvPfCox0av2in18PHSydjAbbBGpZZVZ2x+Fn3FIJXcwotUeJP/QqNQy1OD+rFkAsMxjm6SX0Z9UChDFfVE8rET+dt/Jr9y2BMZ1WEr408uQgJ7PC1DIq1D4lONCrKX2olQymlzM6vDajW6yglyGjdiqRQ15um6eay+baqaQXeXkuPtXMI7WTXca8TCdDzEAxncwmJarISUpPKyE9qwZ8YzRIyh3zUGvFPL1cEMBrG2ajML1k8qxI9wJ7eZPuU5xwr30K6uXkHmKquTsojTwz0Kk9wtAqBUwntwiw2hbBQgC9jASzYiIoAUFebgGmmglgVtwBQGo7ACN+esX/2qlEC3mZBqaXBJ9VCxaWBUR/WjMK1D4lGMjJJDDVZPesmANKLKBXW+ihFrlnVUOB76CN1PSS27VTiQTycn891aT2rFogsO21UZpeMrt2KnFAHBH/JjqEK5QNp2u9uxr9IL/5/fUlS419wtZwX8eCRhRDWVFoFJvI4Ng9xZqU4eXYPb02dQtU8pJbKCsQbe4V8uQlAVsrByK9fMwLYlKvtXK0ppfwnFpCrzVymNJJeMJK6tVOxlI6uSKHlCtylOdckU/tWRtyAVJe8hIlF+RTa/txQY5eMQ9lAbHTU5CTl8xrrRym9BKeEJOerZXDlF4e00JMYqLknmBq7ZR2Yo6j/RRrHkfpJLbJXqiVk+RFSfQ6iNMr8E6xyAsv0esgppfghZfEREn0OojpJT4hJj9RFhKLn0KknCRma+RYTq+W8FMsYqLkfD61toMXbOUle1srRzG9RC/EJCZKwtdRnF4icGrJQFQVpsZAYU5WUg8l9+FTa+e4QhW9IhdKQtCRSC8RCDEZ2Fo5EunlGAwxqddaOVzpJT4hJvVaK8cpvcQnxORna+U4JcE0wII7Gjij5gALuc2vxq3hv49fkTA2S2GsUE1uwghuEgRl45rRSLd+mgkCeBubjClGEZkJldrLESMvZzKIOZS1VgVXdAsOU647OkrCobBCbuIBct7TW7NCC7kJCMgJiNas8EJu7o4hJxNaswIfurWFnXItTlFyCwuxbWHtdi437+4oGzfs5BW3Kc4tU67BZZb9PmyTi91c5ea9tjWzS5lb+8gp19iAkvMIxLaPtNuk3LxrtmZ2KXfzHBFy3jdR1huyzhHtnisvb8GtVbkjy80ZBnIOMa1Zub3TrW3mlOue3ZrZpdzNmyncvAOibPdlu5v6LY92uwVGnTvBkGsnGC+s+2Jf98Xdb7BLuzPZTBKOW7m/2FQSeptKoMiN3IhE9hdERblar7X4cpwJYY4zrY1fxNw8CYSci7goKxj8JNDu9XLnrT9KjjQQ20xj4JA7OdKaVazQzedwIOdcEGW7vdtzOHghh4yQ55DRmvmlzM07MuS8iUbZbu92R7ZbsNx5R27N6g2a7tyXQZ0xPbzM//Daxsxu3zLn3TxK3s3LzV3uPFMLeeb81qySQu5kQciTBVGSBQUNdHNsgJxjQ2tWOSN3RvhwJzqiZISH2DK8sYJuoqM1q1iSOzkU7uRQa1axJHcOASHPISBKDgEQ2xRgnKKb2Ao3pwK4bY9m2JI7KRZlQ6JRTJySOajVWlU+ypsUC3mCJ8oGRKOYkUbuBE9rVqFGNwcRyDk9RFmh5pMIXshRJOQ5P7Rmfil35zAS8oRea+aXchKQgXHvJwOzTgYWJD5WdAz7aXxXIgnKBiSRJF7I51lCnkNEb1YoRTdPhyAnOqJsWNL5ULyQK66Q5xgRZQOLdlxxt8+pAnKOAlHGVBFaThXOGXkTO72VXcjdpBDkpBDKhjRRKO72eVIEOYeQcOfuK9w6Kwp3TjThziGkN7NLuTsnmpAn8nozu5S7yU/Iyc/WrOBU7lx6hTyhF2XDqbZewYpce0HOtVeUhafaezkh5U1g9lZ2IXeTn5CTnygbjMVPJ6TcCczerLBZbtIXctK3NSswplv0nXLRtzUrMJY713bhzrVdlLm2C7H2dk5nuRPWUcbiLrTc3Dkf6SUuo2wo1v7NYSs32duaFRTLnU/QhjyfoO3NCtfpFrmnXOSGO8ldQS53shfmZC+qhnGx1/koM9mLMtlbUUy36DnloifKpGeFqdw5A4Y8Z8AoGw41A8YLuVILea7UejO7lPMrn0kJYCUsezO7VHGTvWEne1u7gmLzXwqENC9mve4Lynkm5nQe9vMgq2CN0n4HfJcapuUjtVG2CGDUBllzjwg5x8XerCQCucl8uMl8lGR+iQByc/aEnLNna1byhNycPSFnRGjN7FLuZt6AnHmjNSvxg27ljSlXSECZecPTh7zMGxAzb7RWJX7IzZkZcgaM1qxkGbmZViBnWmnNSniRO/+mqpDnWVxvVpKQ3HmwF3KGm9bMLmVuJaUpV7hpzUpwkptJCW6Gm9asBCe5mXUgZ9ZB2YKTZR1LM3Iz3LRmJUfJzaQEOZNSa1aCE91KO1OutIOyBSdLO5Zn5Ga8ac1KkpKbWQlyxpvWrEQnuZl2IGdAQdmik6Udizd0K+20ZiX8yJ1/a0S4GW9as5Kk5GZWgpxZqTUr0UluZiXImZVQZlby5ESvthxTrC0HvFyZwKwth6chuRmOUDJpleAlN5MW5AxHrVkJXnLnn5sNeZ5uRtmCl/7crGcpuRmtWjO7lLmV06ZcOa01K7FN7jxlCHeuVHqzkgHl5n4Gcga71swu5e7MiFBnRmytSmSUlxkRcmZElMyIJTLSzXMCyBUK4ea6Bm6dE3iklJsJszUrgVNublggZ6REyXUNxNqweAqUm6EQZYurljA90ylsKeNFve4/E/ke+e+Z8daRpJTxUNpPgZ8Pi2X0KaXB1yKfpTRLM3Iz3KBsscuSktIMzcw28LbYpNNeTzMyM9zUXp6a5GVOgpjRprUqsUlu5iTIGW1asxKb6FZemHLlBZQ1fHhesEQgNwNCa1ayiNxMG5AzILRmJXzIzbQBOQNCa1bCB93KC1MuxKNkXijxQW7mBciJ+NasxAe5uR2BnIhH2eKDbUeM6XIT8a1ZiQ90i/hTLkijrPHBiW+QlpvMRpnMdoLLy3MNiAnp1qqkAbp1MjHlgjTKGgDsZMIhLTeZjbIFAGO2cVVuYhYlmV0QLjeZDTkx25oVhNMt6k65QImyItypa2yUm6hszQqE5U7qQp3Uba0KhOXN5wJCTszWXs5zekXNKRY1UVYEOzWNi3ITk61ZIbLc3OpATkyibAi2rY5BVm4ytzUrCBYz81w/YEfERrkUBPm5fmG0NbgUCqlhQbj5bwVEei7gRb+aCR6pIPWvg9uiPkr7LfIThoqnORByldPalCxBt7YrUy7Mo+R2BWLbrlhIkJuZoTUrEUJublcgZ0xozUoeoVupYcqFepQlgVhqMLLLS9C3ViVEyM3UADlTQ2tWQoTc3K5AzoVIa1YSidzcrkDOhUhrZpcytzLHlCsmoGwBxjKH5QK5GRNasxJB5GbmgJyZozUrEURubjgg54ajNSt5hm6dBU25IgrKPAuCVmdBlkjkZUBprUr4kZv7DcgZUFC28GP7DUskcjOgtGYl/NCtvDLlyisomVdKfJE7/gRTqDOfoOK6AlL9GSZPJDIzoNRennzo1X5iihVP4G3Rx/YTFm7kZtZpzUr0kZtZB3JmHZTMOiX6yM2TIMgZblqzkqPo1oZhyhVP4G7RxzYMlinkZsRA2aKPpRWLGHIzcaCs6UWJAzV3BBBzR9BalSwjN5/hgJxLgdbMLmVupZ0pV0BBybRTwo/cTDuQM+20ZiX8yM0NA+SMN61ZSVJyMytBznjTmpXoJDezEuTMSq1ZiU5KDDrDQkbQGVbUeYYVcp1heZoy/1LIbf38eqVDprUwZLjq/Wp6M/+lwN/SVWtYwpv5byUAWFrr/Wp6e6SCae02zqsUs1Dau5D3CKQaPoc5hTr8QsmVDcR6DtMzkdyMSChbeLO8ZQFLbuat1qzEL7qVmaZcMQdli1/H9vNSZoKcMac1KxFKbmYmyBlzWrMSoejWqdCUazEDN0+F4LZTIcsWcjNqoGwhyHKLBRW5mVtasxJj6OYzoFPNnNJalUQkL3MLzMwtKFsEstxiyURuBpXWrGQiupU8plzJAyWTRwkicvNsBHKGjdaspBq5M7dAnVmjtSoxhl6dbky5gI+yhQk73TDCy03gt2YlTNAt/k+5+A83+V/igNzkKNzkKMoGZeOokZJugbM1K4yWmxSGm+BszQqU5SbLICfLUDYwGsuMVboPE12tWeGkuUnCsJNcrV0Bo/mXcks1cvV+lYzWIUkYhgRX71fJ+EhFTv9blj3af5UiyoYlm/5xz88nMyDnuB9lg5M9mWG8kTvx05r5pdydLAt54qc1q2ijO88boM5pP7wNbLf2M+PmIMyJrtrLruPe5FiIEz1RNigax/BCTu8hz+k9yoY1m96NVHInuFozv5S5OftDznE9Ss7+ENvsb7SRO+ETZYOicUzkojk51lpVrMmbJApzkijKhjUjkdGCbsIjSpKogEnuJFHIEx+tWQWT3EGiUAeJWqsKJnlz4x/yHHuj5EANsW38jVR0E1xRcgqG2KZgA5fcybEoGxSNY3ghp+CQ5xTcmlVGyp3nBSHPFX9r5pcyNxkKORkabjK0IFXuPC8Idw7NrVnls9w5gYc8J/DWzC/l7uR3yBO5UZLfBed0k9+QE7mtWcW53MnvcCe/W7OKc7lzDg55jq5Rcg6G2OZgI7TcCewoSf8SBh4r7ob7OPgg+qFM+lUY0s3zcsg5fIU7z8vDrfNyp6PcCcvezC7lbpIXcpK3NSsgljsnuZAn8KJsKNUk59CiWwxDmQyrSKObp96QExxR5nAVYp16xwu5Qw55jk9RNihphxx3+9zjQs4hJsrCFe1xo85daohzlxpl7lJDrF1qvJAnxyHPk+PezC5lbpJgqkkCVA0qIoHf62XOW3/r5YyRlxSBmBRprQpU6OYmFnJiozcrhJKbDIKbDGrNCpLkzkks5Amd3qzwTe48Nw553vijbFDRubHfuunWnRxlw4Lu5H63lZt3cpR5J683drp1J59y3clbs3Jjlzs3muHOm2+U7caujabffOXmvRhl3ovrrVn3Ys5Scf/k7JN1zFIp5yyVL8QklPKYhLLMSSjVmoTyldwJpiN3eL2hXQ5+g8ewL+N+KZagJEsKWugWS6Zct3+4yZKCFrnJErh5+2/NClrkJksgJ0tas4IWuTnFQc4prjUrnJKbUxzkRE9rZpdyNzkGOTnWmhWs0a05bso1x6E8+PtHc1zUuVEMcY5eUTYkaqMYL3COg5xzXGtmlzI3N4qQc3iKsiFRG8V4gZMY5JzEWjO7lLk5iU01idla2YXcS/rCTPrWXs5iefMcNMTELUoOdRDrHNRxKzfpi7Kh3OgLUuVz3pBz6OvNCtnl5gQJNyfI1swu5W5OkJAT9q2ZXcrdmRugTta3ViVGyMvcADlhX3t5ipCXuQFior61KjGCbuWGKRfqW7MSI+RmboCbqG/NSoyQm7Mr5JxdUbYYodnV2U63UI+S4yTEmgA9KMjN3NCalRghd56jhpxBAWWeo4ZY56ieDORmUGjNSgihW6ljypU6WrMSQuTm/Ag3Y0ZrVhKN3JwfIef8iLJFGM2PnkrkZkhpzexS7mbigZyJpzUrAUiJIXfPAXkGnNaspClzMy+FnXmptSvxyfxLgaclpN6v5jHrkIkrDBmQer+awMyfp6jpyFPUrHsC0ymqp6rR4Dx6K2ShtHcRP6cWzuRjVmttSnSTO/8sZMgZzlqzkgPpVtKbcoWz1qwEP7mZ9OBmOGvNSvCTm3kJcuYllC18WV6ygES38hLKw/5lepKXOwOIGZBQLuUX7zsDvMDNNeTcXKNs8Umb68gl3FxPuZYEKLmAgFiba081cjPkoGzhy/IS0kGeokLOdXOULQIdeYrq2UJuRg2UzC0lxtBN+k81iY2qBQmjvzFaZiK79vIsQK8YOsViKEoytCBVbu5/Ief+tzUrfJabkzvknNxRcnKH2CZ3IzTdAnZrZpdyN+kPN4HdmpUwIDfpDznp35qVMCB3PC8e6njiqrcquUJesh9y4hplCxLGfuMz3cJ1a1aigNxkP9xkf2tWooDcZD/kxDVKsr9EAd3HRVDce0W8qAuQnaDOJ/Nfyh3xBRDXek80/oWe/Ov9Kg8fK6lGg20eMm7Nyn6O/IQZoegir2qPgkJ6Ra+hFm+mt4HQ2GWwopfoqp0KyOTllmGquReYFXcMkNqOwTBHL6E3KzKvIJBenhMPMRFX+xSWyklaTithWRo5OeXMJ6WgJRtrn8JgeblZmGqScVbcK0BqewXDKr2EbO1UkEsvn5Gaam3xa6dCb3l5IjC95HHtZJdxL9k+1UR77VRALy/PEqaae5PaqWQGebmDmWqmgNrJLmNe7lCGmGuP6WzhwjYocPIse1q59ZhVixYLT7I9PNDLKDErJokSLOjVDmOotXWYFTcYkNoGQ1mB1gwOs2AGKZGETk3yQ61Bflac4yG1Od5SBr3MHLWTXca9zC9TzcRRO5UwIy/Pn6eaeWNWLcro9NnzBb1MG7VTCTL0KnsMtdLCrGqM8eRhhKeXvJ8V9waQ2t7ACJ1e8Xp6W5Aw9hvs6SX6a6cSBOQl+aea4J8VuV9igLyc2qeac/asOLNDajO7ZwTCU5EBZSaGEiDMnRMz1JkPUHFeDq3Ny0LkMF/HTV/EnKXg6yiml+iFl+xtrRzF9BKfEJN7KAlQx2l6ic+pJfZaI8cpncQnrORe7WQ0pZOjJqQcNVFWnNqkKUTKS2K2VrqOeYleiEnM1spRnF5hbIpFH5QVicYxEoRW8gROAsXxQicX8rByGq2dDFN0EkSQkkQoiSIHE71c5UPMVX5r5YijlxCDmBRDSYw51NJLEE0tSYSKKHIw0cl1NsTcQKPkbDy1ts0WbOgVe1ASYw41egkxiMme1sqhRi8hBjEp1lo51OglxCAme1ASYw619GqEnmKxB94KNZugpzeHWFhziEXFIXYqbYYVpmQltWonXsScHH4hJbNQVhja7CtI0StmtVYOQ3p51A4vmYWywtBO2oU7eUm/1sphmF4DGLghgkWdCHOgyZ0AC3ESLMrFb+kvCGYDc+g54LZ2jsdH/mtOu/dx19aoitLeonw3G+3oE/xQVo46/fACT8ch57DZmhWwys25FXIOrq2ZXcrdPB2HnKxtzexS7ia4ISdvW7PCcbo1hU65uImy8djmUEOl3CRna1aYLDcxDDnZ2ZoVKstNEENOELdmhct0a9875SIoSk56ENu+V9CkmQhtrQqb5SWOYSaOay+Hs7yEKsSEKsrGZ6OqgVNucrQ1K8CWm1CGnFBuzQqj6dbp+pRrnGzNCvDl5mQKN0fT1swu5W6erkPOFNCa2aXcnYEC6gwUrVXJF/IyUkDOSIGSkaIkDLp1Qj7lShGtWYkrcjOSwM0c0ZqVhCI3QwnkTBKtWckocjOWQM5Y0pqVlCI3zwYgZxJpzUrkoVvRZMoVTeBmNClJRW7u5+FmHkHJiRti289bIpGbAQVljTpKKBYk5GWuQMmIUhIL3Zqyp1zJAmXNLD5nW7aQm1EDZUstljXwAk/XIedw3ZqVGCM353TIOai3ZnYpTws8XUc8YK5pzexSxc2YFHbGpNaupCbzLwW+Fo16vxrDrENGrTBkNur9avQyP08XwsHThah7+LLTBSjynGB8Uuy/GBNli192UoAX8pQ85LmfaM38UuZmZoOcMStKZrYS4eTOzBbyjFmtWY1wdOfSAupcNYS3hSjbWlgukjljUu1l13FvZqYQZ8yJsgUwy0yWa+TOmNOa1QhFN3fwkHPjECW38BDbFh4v5CY95LkAj5LbDIhtl26ZSu6MWK2ZX8rdmddCniGrNavxjW5mLsiZuaIs+c0yF+o82w9xbj1aq5rm5M4VSsgzorVmfil3Z2ILeYasKFv8s8RmqUruDFmtWQ1wdDOxQc6Q1ZrVACd3PlkQ7ow6UdYUpScLLF3Qy7ARZY1BnlwsH8idcSHKFmQse1jYkDuzR2tWo4jcuVIJea5UWrOaa+hmcoGcySXcTC4lyMidZwPhzqjSmtVUJHfmnpBn7mnNagySO1Y6oY6c01rVREUvkwvkTC5RMrmUICN3Zo+QZ1yIsgUZyx6WD+TOuNCa1Sgi9jE9BO1I+6xLGLH04LAc9tO4i4udKBuIxU7HHd2iH8qGUtEvXsiNRchzydCbFbLSTXZONdkJb7KzolRe8g9m8g9l8q/iUO6c+kOeU3+UDYea+p1wdBN44c4T7HDrBNtxKTfp2ZoVmMpNekJOeqJMelaYyk16Qk56tmYFpnSLf1Mu/qEM/hUayksGQUwGoUwGVSTRLQ5MuTiAMjhQqECvODDF4gDK5EDFAt26m0657qZw59203lzl5v0Qbt4PUbabq+6HdgeUOe+HrVW5Pcqb57Qhz8EvyhwpQ6yTWr9f0s3bZ7hzqAy3ttV+85Wb9+LWrNya5c5ld8hzbOzNyn1e7pxBQ5637t7MLuX3Ut2LcffUvTjquBfXW7P5c5JLfU5eL/rVe711WPflC1j35fpLuS36JBeK3Htv74i7/V1RUdrvIROL3+np040fZc5QIdYM5Xd6uXnjb80KVOTOzXXIc9kcZYOKNtfGDZpJEXhv5cfvFIGXExjMnMBqL+eTvGQIxGQISjKkIIVuHuJCrhs3ygYFneL6nVpu3rhbswIFuUkByEmB1qxAQW7OUJBzhmrNCmHozp031GRGa2UXci/5AzP5U3s5jeTl/AUx56/WqpBNbs5fkHP+as3sUu4m+yAn+1CSfQWFdHPvDDmXxVHm3jnE2jvHC7k7Dnmue6NsINXu2GFJt9iJkuwsKJU7t78hz6GpNytcljufcAo5UYuSQxHEesbJ2Uq3UIuyYtzmGKer3IRta1YwLnfuYENOXKJsKNYO1vlIt3DZmhUUy032wk1ctmYFxXKTvZATl61ZQbHcnKIg5xSFsqFYU1S8wCkKck5RrZldyrmXG9wAHUHdmtmlipvcDzs53dqVGGB+cj/05HTvV3OAdVj35QtY9+X6PUe84D6Py9NxKSx7ESR4Xh7/nvvbdcwrGhtRthih/a3nA7oVF+DmSAq3njdzwstN4KNsYcKmSGO83EQ+yhYmdHIdnM6Ta8g1OKJsgUAn1/ECp1DIOYW2ZnYpd3MLCzkjQ2tml3I38wfkzB+tWYkjcufz2yHn0NqalWxDt2bYKVdcQckZFmKbYS2fyM240pqVKCQ3sw/kjCutWYlCcjP7QM640pqVKCQ3sw/kDCytWYlCdCtFTLlSBMoWSSxFWE6Qm7GhNSsJhW7tYadce1i4udSFW3tYTxlyM3S0ZnYpdzPBQM4E05qVQCN3nJyHOmf91qpkI3qVX6ZckQMl80uJM3Izv0DO/NKalTgjNzfIkHOD3JqVbCQ3N8iQc4Pcmtml3M3sBDmzE0pmpxKl6NbOY8oVllqzksvkZvKCm2GpNStBTG4mL8iZvFqzEsTkzpPzkHPNgTI3KNDy5NyTlbwMWiiZ2kqIEz+VAMA8ETvqnigsARihrUECu/ericD8TADhYAJoDUsgMP+toFBnwC/61YTxSAV3D9dxx1doQGmfHb7XLWzIx+zR2pQoIjf335AzbLRmJdfQrb3HlGtVgbIGGd97WDaRm1GlNbNLmVv8n3IhG2ULE8Z/Y7TcRHZrVuIA3SL4lAu6KEnwAnS5SXDICd3WrABdbhIcckK3NStAl5sMhpwMRtmAbgwG6XL/MNXcGbRWhe7ycv8AM5cGtZeu417uHyAm7Fsru5C7uUGAnBsElC0K2AbB6C43Yd+a2aXMreQw5YJ9a1aChNxJb5iT3qhaEDB64wVu/SHnsqL28lxAr8g/xYI1SpK/BAG5yW7IiVuULQgYu42QchOYKEnfAmO6Rd8pFzBbswJjuUlfuAnM1qzAWG7SF3LStzUrMJY7zyugTtq2VoXr8pK+kJO+KEnfAmO6tfWYci0qUHLrAbFtPfACNw+Qc1mAkpsHiG3zEFzMzQOAw1VBa2aXKm5uHsLOnNDa2cWKn7kj9MwJvV/NIdYhc0cYMib0fjWHPFY+D/9t3OeFa5T2e+CnxEAtH7mNsoUA47axlW6hFiW5XTAuN7kNObndmhWMy819BeTcV7RmJRPIzdMOyLmgaM3sUubWxmHKFRJQcuMAsW0cLCTIzcyAsgUQywwGarrFbZQtBBi3jcxyE9StWckEcpP6kBPVrVkJAXSL3FMu3KIsKcDIjZpTO8QctFE2kNvUbmyWm6huzexS7ib3ISeqW7MSA+Qm9yEnqluzEgPoFvenXNxvzUoMkDu5D3OyGhW5X2KAvOQ+5OR+7eUpQF6ed0DM847WqiQKupUaplypASVTQwkRcnNfADljQmtWEonczByQM3O0ZiWCyM19AeTcF7RmJc/IzSfOIGdEac3sUuZW5phyxQSUNcB45rBkIDeDQmtWIojcmTmgzszRWpUIQq9OWqZcMQHuFkHspMVChtzMHK1ZiSBy86QFcp60tGYlz4idmtVBS83qUXNWD73N6obprcH9MO7TpHaU9p3wfWZcli8x3drULEA3zwkgJ6bD3SKAnRMYl+VOTLdmNQLIndwNeXI3ygZx4y7YGE8ZQJ1PBoSXRw7w2lMGRmWZE9JR8owB4mP7TXFLEPKEdJQtANiWwBAvdxK/NasBgG4+6QY51wLh5qEB3PakmyUCuTMgRMktAcS2JbBEIHcGhNashg+5M22EPANCa1bDB93MC5AzL0SZecHTg7w56Yc4A0JrVZOI3JkXQp6Ij7KFD8sLxnS5E/GtWY0PdPNPikFO3KHsKNWfFAt3Tr3hzkE1yoYUm3rtXk03b91RNqQYBfBCPu0W8hwXo+QaGGJ72s3u9HLnjb8180uZmxSBnDf+1qxCRe48Mw93DptR8swcYjszj/t8zJ5xA81hMdycPeG22TPcOXumPWfF1s4vVvzrvtjXfXEv5bZYJkenlDVIarWGfrnpd/gM+5alDEWzakATiZwW6RWIpjc5VLEkb06P8Oa8h6pBSbOjc4bepE7rZJdxLwk21QRY7VRwRi+fdJtqMgdVoZmec3Nc0Ul41T4FZfLmtAp1sqp1KlSUl9ybamKvdioQlJfUm2pCr3YqCJQ3T8ahTsa1ToWm9IqXQy1c1k4FnvKSltNLWM4qWVnRKS9ZOdVEZe1UwCkvSTnVBGXtVLApb07lUOdQ3joVAtMrTg61MDmrBlxRMliUs+lUczSFNyfT8GoyDW+eZcObp8+tk13GvXmSDXXOsa2TXca9MRFDHENs62MXcWfOtFAnh1HlRBtSTbQO8fQK6bVTAby8OQ3Dm/Nr61Sygrw5zUKd8yeqnGVDqlk2mJkb8Knm/AhvixXaf3twoJcxYlaZImqokDdPj6HO1IAqJstQ8uzYMwKdjB+1Twkj8jJ9TDXDR+1Uogi9nEGnmlMjqhpEbAL1bEFvJg1UOY6GVM/Je0yhl6GldioRRt7ctkOdGaV1KmlIxOfcC1Bz7I2y5wdNvRYRhnsZNyMlBpSMDB4g6M11dYiZEVorjyLp1eA6xcoIKEv4sLkVdT7eFuKcNaOsIUBPtwnstBLzrZGuYk7GBVjJ+drJ0gOdjAuQkvKtkccHehkXICblWyuPD/QyLkBMyrdWHh/SK2xPsbgNb40Axm2hWV6SurXyQEAvsQ0xaYuyRgDjthBJr4iJMkZUSDmhosyFdkhzpoyyglj7bNQcTyHmeNpa6Trm5XAKMYfT1krXMW+uskNMxrdWuo68XCdDLGKiLCTmNtmQR6cIiLLA1AkoyMlL5rVWjlZ6CU+ISc/WymGaXsFzikVPeIlPhym9ucgNL3nZWjmW6SV4ISZ5WysHMb3EJ8SkHsoKYuOnoEevGIiSEHQk0ptL2BCTeq2Vw5VeIhBikgtlxakxUNCTlwxsrRyJ5BBHfoCHI39r5XCVlwN/mDnwt2a6kruXgiIN+C+62cXcfylQMVxHve7drmnfEDvc5zFmiLgoCe/CcrmJb8iJ79as0JxubQumXMRuzUo0kJv4h5v4b81KGpCbGwPIOeij5M4AYtsZGOblJvVRMj6UNEE3A8RUE/utVckT8jIIwEx6o2yJwpKAAVtu8rs1K8lAbs7/kHNsR9mygW0ADPhyk/+tmV3K3IL6lAvqKAn1wni68/k0qIlxeDlfw6vn0zwDyMxIUHt5QJCX0zjETAGtVQkbcjNSQM4c0JqVhCE3gwHkpDnKljEsGVgWkJvRoDUrSYFubQOmXEM8Su4DILZ9AF7gPgByDvKtmV3K3bkRgDqDSGtlFzKvosmUK0+gZDQpSUVuhhPImShas5JV5GY8gZzxpDUraYVuBZQpV6qAmwGl5BW547w3zDmVo2qZQ+e9nitkZsxAyZhRUgfdd+75p1zJAm7O7XBrz+9hQm5mi9aspBa5GVQgZ1BpzUpuEfsYGYArUh7ulj4sMzjWzU7Mt3YlQ5h/KQiy2ND71RhhHdZ9+QLWfbl+jyFLI5EFj3AweLSGJYc88t8zeVz29jeqoGLuKDGEXiWHoRbrp7eFEMsNFhToZWyonUqIkJdnBVNNzs+qRQg7KzC6plesndWx/Oic28ZWeknaWTWI2xRuoKWX2J1VQ7hR1zCbXkF3VmRuQbC83GJPNSk7K26xIbUttrEtvSLdrBqAjZqGSXoJzdqpIFReMnOqSblZkZgFoPKSmFNNYNZOBZ/0avM+1OJj7VRILC9pOb0k3KwaeI2VRrT0im/Tyz02vLbHNp7RS7rVTgWc8pJ1U03Uzaph00iHF/KZLKjzKarWqTBUXp6mTzWpOKs8Sw+pztIdqekVYGunglt5OcZPL4k6Kw7xkNoULxrTmmiufQqo5eQOfKrJ0lk1TNsO3OiXXrFwVhXSzlW8wB34VHNon1WDrO3AjZv0kqKz4ggPqc3wziwiRQhD2QlrRDRiyZ78as0qH81NHsJAHNZuBY6PBR7Dex23M7EEJcfRqbVpdN7Ec5ycWo6TcN78R+DTpLAjKylUO/Ei5iTMICXNWiOHG70cQiEmv1orxyS9BCHEJGFr5WCklyCEmCRESRQ6GNOrY+QpFvtQcj09tXaOLNjJS/ahrFC1s2BhU15StLVyqKZXg+sUi5soc3CdUs2tgqacZCjKimNjqDApL6nZWjmc0ysMTrE4CG9FqnFQqJOX5GutHLD0EqEQk6GtlSOVXh4jQ0xqouRafGrtHFmYpFfUbK0cx/QSv/CSv62V45he4hdiUhMlAew4ppcIhZjkQ0mIOlLTK4ROsRjaWjlS6SVC4SX7UBKijlR6OaJCTPah5Ig6tTahCpvykqKtlUM1vYLoFIuiKIlRhyq9hCjEpChKYtShSi+36BCTm62V4zm9GmunWHMovBXINtUKcuZdnUUveLxUGtkxdOhvhSoviGrn0BDw6ev7uCdpiERpP3q+owFJToRTqJEQJWdCiG0mxAucCSHnUIiykdymQsMd3aIfykbkY/uOhUPIiUOUjazGQ0Oe3CRga1ZQKzdxCjlx2poVutKtw+Ep14lua1ZQLTfnUrjJUJSNzjaZGjblJkVbs8JnuYlkyInk1qwQmm5NtlMulqJsjLbZ1hAoN4mIklMmxDZlGgTpFhNbs0JbuQlJuAlJlI23Rkm8kFtZqHMr21oV/MrLo2bIydTaS9dxL/EMMZnaWhVa062N7pSLjCgbc22ja1yVm5htzQp15eacCzkH3dasIJxuoXbKhVq4G7WNtYZTuUnX1qxgXG4iE3IiE2WjrzHTsEi3KNmaFRzLTeTCTeS2ZoXAcnP6hJy0Q9k4avOnsVL8ITpbs0JSc3MjHHbOq61dAbP5OX+GnriMutFYE6jj1vy3wiLrV3H8WIH79tU6vjT95ymytN8D3+mo4hQ5hbGxzZKbYYjtFNn4THfyuTUrl3J3sD/dwf7WrEUBuWNsT3nQPUuO7RDb3I4X4hQ55TGdZ9mChJ0iW1SgO5NDlsfyy/fkYNlA7sgGrVlLJXJH7kh5ZIPWrMUQuSN3pDyyQWvWYgjdSe+QJ2GzbFHA6G24ljvo3Zo1mNOd9A550jtL0rvAXO6gd8qDsFmS3gXmdCe9Q570bs0azOUOeqc7GJsl6V1gLjfonWowtrVqMJc36J3yYGztVVkub9A7xUHv1qrBnO4cr0OeU3Br1pKB3MH+dAefsyT7SxSQO9if8uBza9aigNzB/pQH+1uzFgXoTvaHPPmcbrK/RAG5Y1hOd/A5Sw7LENu0bISlOwmb7hYFjN6Ga7mD3q1Zg7ncMfamPPicJcdeiG3udd4KQ8lf1gXn4m/B3bCfxspL9ENp30f8rgtj6BO/4GswJL8KsOQmv1qzgjO5Y3ZNeTAmy4Yzzq4FWHKTX61ZwRndsbYOdeyXX7QqZJQ3FtdpDrr1XrqOe8kuiMkulA2EZFe+EFNvymMyzbKhjFNv8iWm3pDn1PuimV3K3WQX3GQXygZCsisJgWegQh0b3xetChXljVVymoNsvZeu495YJqc4dr5ZNoRym5wvxDo55bFOftHMLmVuUW/KRT2UgbkGQblJH8hJH5QNZKRPwQ3dok9rVmAkd6x30x18edGskE1usgtysqs1KyiTm+yCnOxqzQrK6M7ZMeQ5O2ZZYaTZMV+INW/KY837opldyt1kF+RkF8qAVUOZ3DG3pjzo9KJZ4SLdubANefIly1jRppgb23whJs+Ux3SYZUMZJ8/KKsFH7IoasGooM/+6L/Z1X9xLuR3b5pavXMoNmbPhi4Z2Ofjx7+NZoHXrfFs542XZUMqngQrl6Bb04CZBC1DlJkHhJkFbswJUueNPLac8ZrwXzQqd5eb8CDmB25rZpdxNekNO4LZmBeZ0k79TTWbC21Bu/DXgykz+1l5OY3k5/UFMZKJsOOb0VxgpN5HZmhUc0y3+Trn425oVHMsdh8Dpjr1ylnHsm2KeAhfGyk3koiS9C8zp1uQ55cImygZkTp4Fm3KToigbko2ieCGeikp5TKYvmhVCy82pF3JOva2ZXcrceawbciEbJYdgiHmuW4AvN/nfmpU4IHdOvVDn1NtalWRBr+g/5QI23C1KGP2N0HIT2K1ZCQNyk/6QE9itWQkDcpP+kJP+rVkJA3LH8XDKY7rMMg6EU8zz4ZIO6FZYaM3sUu5mdoCb2QFlCyKWHQz3cpP+KFuUMPqD0Jx7p1xzb2tWkoXcnHvh5tzbmtml3M3kATmTB0omjxJExN7YWicuGRXgbkGEW+uaDczOrBB1TyJ86qpkA2uwOomsX80i5r8UGFn2aA1LFHnkv2f2WMedSdkDJbNHiSJ0K3tMueIC3MweJYrITYLDTYKjbHHACG7YpVsURlmIbhRGzf0vxBx7UXKZDDH3v4XSchParZldytz5h3tDLnCirFDWn+4t2JWbFG7NCpTljpPjlMd++EWzQni5uXuGnNBuzexS7mYCgJwJoDUrgYBuzeBTLuSjzBkcWs3gFhfkZXporUqYkJsTPOSMC61ZSSZyMz1ATuCjbFHE0oPFBbmZHlqzEibo1s58yhUXWrOSTORm9oCbcaE1K1FEbu69Iee6AGVNE7n3LoSnV8CHl+mhhAm5mR7gJvBbsxIm5Cb/ISf/UbYwYfw3RstNZKMk/0scoFv8n3LxvzUrcUBu8h9u8h8l+V/igNx4zjrVeND6RauSLORldoCcuEfJ7FCiBN3KDlMu3LdmJUrIzewAN7NDa1aihNzcmUPORQPKFiW4My/pQG6GhdbMLuXkZfIALJk8WrMSRMzNnUfYmTRau5JrzM/kEnomjd6vJplHKnhufRmffcEeZQsSPLcuUYFuJYfWrAQJubm1gJuLhtaspBK5ubWAnIuG1swu5W5uLSBnTGnN7FLm5t5hqplS4L2VX4HvHSziyMzEU3t5/pGXiQdihhSUTDwlAMnNxAM5E09rVgKQ3NxaQM5FA0puLSC2rYUlIroVkFozu5S7mZfgZsRB2cKX5SVLJXIzpKBs8ckSjyULuhU0UGZm8QQjLzMLxIwZrVWJMHIzs0DOzNKalQgjN/clkDOktGYlD8nNxAM5Q0prVgKQ3Ew8kDOktGYlANGtjceUa02BskUY23hYNqBbUQFu5o4SQ+TmqTXcPLVG2YIET61LNpCbUaE1s0uZW6cOUy7Yo6xBwk8dRHuZk/2oWoww9uOF+PvNUs5DhtrLUwW9Iv8UC9bwthhh5DfUy03yt2YlCMjNrQHkRD1Kbg0gtq2BsV1uor41KzFC5BR7QTuxN+rO8hfsHf/PG6zOkZcsXxpJbGsQjkthiRra5eDHv+dp/218hrQmQGm/B+ZjSwXyMSS0NnYRdzNxQM6Q0JqVAEK3MsOUC/MoawDxzIAXuKuAnLsKlC0E2K7CyC43Qd+a2aXMzcww1eR8a1UihLx8Tg5mgh5lixB8Tq6QXW6CvjUrIUJunnVATtCjbCHCzjqM7HIT9K1ZCRF0a8sy5QI9yhoifMtiZJeboEfZQoTtSfACNx2Qc9OBsoUI23QoF9DMlNBa2YXcy8QBM2NC7eX5Q15mBoiJeZQtgFhmMDLLTVCjbBHCqA+2clMx5VouwN1CgG0qjMxyE9QouXqA2J5UAFu5L5hy7QtQ1hDg+wLjutzEPEruCyC2fYFxXW5ivjUrEUJuZgbImRlasxIh5OauAnKuF1qzkkfoFvenXNxHWTOEuG9kl5egR8l9AcS2LzC60i3YoiS5C8jl5swOOcdslA3kNrMHF3NmB3I4ZLdmdqniXv1GLshG2VOAUdv4OPz3kWuFS5T2u+e7zMgoH0HZ2hQIy03qQk7qtmYFwnRrzp9yYbY1K0SXm7M23MQsSs7aENusbWyUm6hE2RBu1BVnaSZ1W6sCYXnJTZjJTZQNwsZNY5vcRB1KcrNgVG5yE3JyszUrGKVb0/aUC5StWWGy3KQu3KRua1YgLDenbcgJSpQNwjZtG2PoFnJQNpzZ1GrAkpv8as0KzuTm1Ao5gdWaFTbSLfpNuejXmhUYys09PdwccluzQla5OTFDzom5NbNLuTufEYA6t/qtlV3IvTwhgJyYrr10HfNqUp9iQRreFgBsUjfEy03it2YlAMjN5wsgJ+Jbs5Im5GZegJyIb81KfJCbeQFyIr41K/FBbhIfchIfZYsPRnxwmecLU67FAEquHCC28wUlApkzH7RWdiH3Mi9AzryAknmhxAe6lRemXHkBJfNCiQ9yc9KHnAGhNStZRG6mDcgZEFqzEj5EXqYNwJJpozUr4cPcPCEIO7cKrV3JMuZfCj4tn/R+fr3SYd2XL2Ddl+svBaKfyTvaUoSDW4rWsOQp899y+A39LcHb+/n10ME4vzU4Hvf2XCAq5ocSJ+jN/DDFGR9qnxom5Mw9P6w5o5dGJZXImbkD2owdqJbyzXvqsFxBb6aM2qnmGXkzc0CdKQFViy+WOCwVpJcZoXaq8UPezBvwZtyonWr4kDfTBtQZNlAxa5ToQS/n7KnmmI0qp2woNWWjzr0+tDlV1z5+EffmhA51JprayS/j3kwoUGemQNXCjuUTsTitCWYULS4Y4w3qtCbiS6PCezpJ+Kklk+Ek3wvu5U2+w5tErp0q7OVNPkOdREXVUG90Bony/H6quXWHt8HWTu+Nn/QmTWsnv4x7k8xQJ01rp4ppeYPLEAeWa58KaTlv8acNoE4Ko+I6ANKL/qyBIZzeBHrtVPFOL/f+U821Pypu/SG1rb/xmt6kNyomgRIM6BVPp1z4i7LA2WnqrJH74nf1F2gkyfCvc/A/LuMTxIk9Sn3/+X5EkWN/yHJS7010BfPm0B/inNN7K11HXo78EHPk7610HfPmkj68uaTvrXQd8+axfohzO9Bb6TrmzUVDiHPR0FvpOuZlSICYKQFlxoQSGtLLkDC1TAmtkYcGOol6WMl6lAn7gn56iXqIyfrWytFPby4nQpx07608RNDLmAAxc0Jr5bEhvYoJU6yc0Fp5bKA3HwIIb+7vo6yxQc8ACOe0JtzDmet4OLWNNyTLStajrKFBrDdAy0teo0xgF3ynlwsBiDnDR5kLAWi1DxCUZU3Wo0rYF/SnU8CeYhEb3kR2ATi9hC68pC7KxG6BML25BA9xTrFR5oQMrXbgBlZ6RWx4E9kF4PQS2PCS2K2VA5zehC60SV1UFd+irgGOVvIuvBWkd56YGyrlJTlbKwcpvQQnxCQnykRnASm9OQSHOOfWKHMIhlYzsFNWIBJ1ow7sFgjLve6Led0X7+I3WJ8/HZHDfh7vVxETpf3w+I40etAnmKBsXDKa4FbG6W/KNf6hLIjQ/Oe3fXlJgdbKLuRuIgVycqA1K4SRm1CBnCRozQpj6Nbtfcp1e4e7kcLu73ZHl5s3eJS8wZf7vdx52hvyHP56swIPuTlHQs5BsjWzS7k7z4pDTqK0ZnYpc2sOnXIxBWWBlSbRqDlNQsxxEmWjleZJJ5LcBBRKAqrwim5iZqqJGVTETKGOvJwpIedQWXs5wOjVZDjFGg1RVmbZbOggkptcas3sUu7O7WvISReUjVvavjpf6BZuUFZyOW8MKnKTMa1ZQZncedYb8hzuoqz44lmvI0leEgolp0aINes5lOgWo1qzQj+5479wkW5SCmXj35L/hYvCOLmJvNasEFDsELhAC4Er6gRX4Zj5c/uaesKq96tgtA4JvzAk/Hq/CkPzXwqIbGRtDQtcHysuh/0yPkeiJ0r75JCeqDh6Qsh5ESVHT4ht9gRdOXxOuSbG1swu5W6On3BzZmzN7FLuJvchJ/dRkvslBsjNMRJyTn8oOUZCbHOkcgHNTAmtlV3IvUwcMDMm1F6eP+TlSAcxQY2SIx3ENtMZmekWqFHWEGAbXCez3AR1a1ZCgNzkNuRELcoWAozbYivNJC28DeI2FxpqZSZ5UZK8BcRy5zl3yPNoujcrVJeb3Iac3EZJbheM082Tasg1S7ZmJRPIzcEUbg6mrZldyt15Uh1yxoTWzC7l7kwcUGdKaK1KAKFXo+2UKybAzdEWbpttLRfIzZjQmpUIIjdHY8g5GqNsEcRmY8sFdCsmwN0iiE24FhPkZmpA2SKIpQajs9yENUqSvwQBujWsTrlgjbIGAZ9WDdZyk90oWxAwduOFPPMNOSfU1qzkArk57kLOcbc1s0s5/fLMN4DFmNCa2aWKm6kj7EwJrV0JIeZfCoQsJfR+NYVYh3VfvoB1X67fU8zSWKQz23TkmW3WPYXozNaDxmhwHZ805Q6U9i7iZ8WygXyMCiiZO0oMoVu5Y8oVFVqzEkPkZu6Am7mjNSsxRG7mDsiZO1Ayd5QYQjeTw1ST9vC2EGLJAV7uG2DmigBlCxLH9pvSvgFyrghaM7uUu7mIhpxRAyXXDxDbJtqCitzMLa1ZiTF0KztMubIDSmaHEiXk5swPOeNCa1ZyCd1aK0+54gJKrgAgtr2yEV5uAh9lCxN6zs0JLzeB35qVMEG3+D/lQjbKGiac/2K2zElwVC0MGMHxAncGkHNnUHt5NqBX9J9iARte0r+EAblJf7hJ/9ashAG5SX/ISX+UpH8JA3JzNQ45VwwouRqH2Hbjlg7kZlhozexS5lbymHIlj9asBBG5ue2Am1GjNSupRm7mFsiZW1qzEmPkzuU81LndaK1KIpKXmQdyZh6UzDwlAtGtzDPlyjwomXlKBJKbmQdyxhSUzDwlAsnNzAM5Y0prViKQ3Mw8kDOmtGYlAom8zDyAJUNKa1YikLmZWcLOjBF1z0CWWSykWIPVGfgyAy2NgpZZwnErHLTMUjPMIxXMLPfxGVZmQWnvAn7CLVfIx5iBkpmlRBi6lVmmXDGjNSsRRm5mFriZWVqzEmHkZmaBnJkFJTNLiTB0a2Mx5QoaKEuGsY2F5Qp5GTNaqxJh5GbqgJxBASVTRwkhdGvfMeVaUaDkvgNi23dYMpCbQQElU0cJIXIzdUDO1NGalRBCN/cdU819R2tV8oy8PKWAmRGl9tJ13Mu0AzHTTmtVwo/cTDuQM6CgZNop4YdubTumXAEF7hZ+bNth8UZupp3WrIQfuZlYIGdiQVnTjxKLZRJ5GVFaq5KF6FbemXJFlNasxB+5eUYCNyMKyhZ/7IzEAo7czDutWYk/cufT8SFnwGnNSpaSm2kJcgac1qyEJ7mZliBnwGnNSniiW4llyhUzULb4Y4lFGUXmTCytVQkw8vIpPsgZUVDyqAdiPcfnmYRuRRS4W/yx8xkLOHIz77RmJf7IzdMdyBlwWrOSpeRmWoKcAac1K+FJ3GViASqZWOBu8ccSiycKszNhRM3EUhOMdbgUAFrCiLpFGJ3NWMDY7KfD/mJ/zV+U9lvgJ8wwTR+pHSWpXSBOd24aoE5Kh/dWfgS+aTAuy5yYrr0K/+VN4oc4id9a1QAgd24pQp5bitaspgm586mIkGdAaM38UubmCQfkPOGIssUHO+EwLsudmI6yxQcjvrhMc1K6taoBQN4kfpgT07VX4b+8eToS4jzQiLIFADsdMcTLncSPksQvAUDu3G+EPBHfmtU0QTeJDzmJH2WND058YyPdRGWUFcO+KTDgyJ38ibLBzPhjhKGbwImS8zbENm+LODIHf6JqKDP+GGFkTuDUXoVs8ua0HeLETZQNZTZtG1/oJm5as4oyuZM+4U5gRNlQZvQx3Mid9GnNKozkzlk95Dlet2aVbLr357QbN/tERZadRUu7E4s96biUe7HYU1D0WGEz7BvVjsYelPZpTfZElY+zhzCH296mEE3unJRDnsNtb2aXMjcnZcg53PZmdil356Qc7pyUezO7lLvzsfaQJ2R7M7uUu0lsyEns1qwAXO58JiHkCeko85mEEOuZBCcr3WI2ysJ/MdtBKS+5ibJBWNyMF3JKD3lO6b1ZYbLceTYQ8jwb6M3sUubmjA85IR1lzvgh1ozviJebxG/NSgCQOzcEIU/ER5kbghBrQ+BUl5t5oTUr8YFuMX/KxXyUJT+I+U51eRPyvVVJE3IzMUDOxNCalQAhdz5PEfKMCL1ZSSNyM29AzrzRmpX4QbcSw5QrMaDMxFADhNxxIhLq2AlElSciIdWJiOcJmhkvwp1Tfrg15Xs4kZtZpTUr0UXunNNDnqN1lDmnh1hzukcbuZl0UGbSqcGHbp5LQM5o05uVFCV3zunhznATZYtNmtM9kcidASXKFn00a3sikZtppzUr4YdupZ0pV9ppzUr4kTsn9XBnvOnNSpKSm1kJcmal1qxEJ7mZlSBnVmrNSnSSm1kJcmal1qxEJ6WGnPMD9Dnnhzvn/HBrzk93Po2R9pzrezu7WPEvBb/KZi/6+fVKh8x6Ycis1/vV7Gf+S0G4Zb3WsEQ/8+fJSOrzZORFv5olH199/+pnX7/6yS+2n/L+ftp9/f2rw+64++vj7rDdwa/jY7dFvGX39dtX2yf4cr2ed19/++ovfvv3X9/H/xzOh/V0u/3l1/+82bZ/8fdfZ7fjcXwovd35ch6/t+N6Gl/abLicrofbbPhffrv7u3ffPeye33z7+HH39PDhm9fv//2LtqfddoOsTW9HJFhvet+i12j6szcfPz7sfnj45uF599Wbh933755+eHjervKPD9tlthffvn7+9PD0tHt48/zTL7nY/TQ+cX6x83K73b7wR9LbrRsslvvuuIxbCLqd7rfT7PbL//H08c0Pv3t48/ELvq51u3dfT95oua5bWBqNfv7m4cPD+13+fP/l4f3Tm4f3H3cftu/5YafL7F6/3c1Ts/3psL1H/KqHLeLft0/z71+dxpvmvCXn0/y03E/4u4hm/fTqq015PFy3u+mfVm5vkO1/3//w6njen8cTqCsAvZWXWV3ng83HEeS2t+r867Iu6yy2t+C3r/Lk5jJ/IbfRdrhWHMYN4cTZFN7GX4G0jDw+Wl1nMd+II8yOYpnC83j/j3ILa1sxO867XPkSv90+MNsLh/Gg6wXD/HG+r0d5GG+O+Z823PL/Ya7/lmUWWxSaXwuqbWyJr2szjYer4tDqOgIydbcBxWxx299m82WdxYqveP5dytAt6LdZ55evL3B8xYfd+N/tx71EGr6Ob/ZtLZ/+/9bOJTdvHAjC+zmFTxCIFB/iMeYM3mTh3H877K6qZsuDAAFmEMD5v5CSyGZ3sSQ78n/CfeVucV+4Mdq3OCOtuN12DIXFsb2xdsd7/gleCKP7ld+22va01e+2panwVXus7XTVElhXZg0idmDX9o4zcRvxj/PZwryn3l7LNm+uDjOtrh9XU6YBdr+Oewq1eSQEFeu7i8gX2LJwo6XkTszbL7YLQKARBu4biQOtG0ykWb1fHX3/Olju1PWqkU7drPBnZLzHnPWAMG54ikPpmFux45pFRnNrJgYKgkuVd5xAvzeyPFmeLhOE3Gl+nx55tL/24nDZxYSXjZX9LluD+Nx98NSRCZPJitp/+f1kcVdMLCP0hurg4MPtU/LDSW5oRQGw0yBQE3StJBzLVcRfst4tMWJtqXQBw6GEmn3+9cKvhPYinYA9hQOlpuOaPxwKcTOp4NTqjzVSeBwVjcpfQ9RuwSerh00PZnPjvQR9+ZXaRLkQ+5Pa5kqwUj+pdp2M4tH2YTm7i7c9LiwPC9yDP/HImOWvxaXOJGzAAfJFDHq3kfy5zTdx+6l9ClX1K2EfHwfGTDC5zo7S5QWdtxAipXZ1XzOpgqOK5bHnRJaU+OSLNXo0bEVacTpsGn6d2EImwnlLvfBx2F1H9IFOUK86XwhtSYBqjwSR4Gps0IIuIfd3UEN7XU586i4NLjYsDApsDuanRRfSiRT6Fbv7bTq44YpUY+3e2ACZrGzxLMbn04V1i10d8mraVjVY1EcMFiNQFVuiqnRUVjwMJfcNPDG7DdE72l7N9MbistpZf1ICzgIqwZKLn7BuAl+vcuSEEfOrnfDNdTYizAUeP/xWf1gwNFzGHPa0/4jy+I4A2TO61jFdfd/ll3BdfQ47P2do5FOk7zJe8l124DFedlp6shtdF2Lvi2QD8kp0/XgP16yM/YsXCt2XcQ/7ZedeXc7CqJVjwGwcrcmCcYioYc1Nm3l0dXcVJ3IbZgOm87LUsRHQllWM+ImKf4/2ZcX6wKYqL/bmr/+BmS90ZH3ulT6OzLr7JOjBjOt889nm/4znUYnfN/tThPk2ZhZtOmX03nwVdrd1mksxTOTeJ9gELsFdYoHTkvoSP/4yj2llROMVaUOLZitc5mnlegeNY9Ii/x5kb4F2uk0L0lgP750i0ZZBoxJy+eqLZw+Ht7qm3pb1tGDu1WxA89FKRHJ6ZI1oj131bC4uy+7XYp5u2CImdGzWeo1j2fq83fHTsxm2LteWqE6nux3jFnxBZtjXvVsid282DFczbunGPQwbeISfC20JAVv9uDibQg0bp8nDxyk0NHKhi/gRe0YR2hsSRQVXClBDD40RAiA79+avzPbS2UO1Zio1H9vwbfGVTF1Mla4uwuYcQar4BXZQMxk7aZ1aqev3D0Dtx9wl7k9unSvTyl1jf+gPw2sXrMniSQ7k8SgW8niGydRJWjLfTzg5CVnCb61EfIPum2r+jB1SVi9xHx+Jxsw0lQk17g+1scGghabQ8EU9OStx4ey0erJ92onVinttnZc7Fa1f4pobywEawMStxyYNHVGZyQQqg0LfNV6oDAtLxy6ZvSKJvOK2M9HdPt7RNi8o7ZcZDNfhbtBo9MhOJjLMXkiD2sadYN6pI5aCjmNTW/KFoQrtSKSXmQ8nVMJyPIyL6lOHonr/ReO4Q0mtigOpcEzY05K8aFoQn03hEA3KOhZRmaGujKRfNMV1qu+JM2zi3/vPPyJla6gKZW5kc3RyZWFtCmVuZG9iago1IDAgb2JqCjw8L0tpZHNbNiAwIFJdL1R5cGUvUGFnZXMvQ291bnQgMS9JVFhUKDIuMS43KT4+CmVuZG9iago3IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA1IDAgUj4+CmVuZG9iago4IDAgb2JqCjw8L01vZERhdGUoRDoyMDIwMDgxNzE1MzQ1NSswMicwMCcpL0NyZWF0aW9uRGF0ZShEOjIwMjAwODE3MTUzNDU1KzAyJzAwJykvUHJvZHVjZXIoaVRleHQgMi4xLjcgYnkgMVQzWFQpPj4KZW5kb2JqCnhyZWYKMCA5CjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDQ4MCAwMDAwMCBuIAowMDAwMDAwMjk5IDAwMDAwIG4gCjAwMDAwMDAzOTIgMDAwMDAgbiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDI5Nzk1IDAwMDAwIG4gCjAwMDAwMDAxMzIgMDAwMDAgbiAKMDAwMDAyOTg1OCAwMDAwMCBuIAowMDAwMDI5OTAzIDAwMDAwIG4gCnRyYWlsZXIKPDwvSW5mbyA4IDAgUi9JRCBbPDIyODVhY2QzN2U3N2JiYjVjMGZmMzE4YmM3MTBhYjU4PjwxNDYzYzRkZjQyZDRkYjc2MGRmMTY1OTFiOTM5NzMzND5dL1Jvb3QgNyAwIFIvU2l6ZSA5Pj4Kc3RhcnR4cmVmCjMwMDI1CiUlRU9GCg==","qrLabelData":"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","routingCode":"40327653113+99000933106001"}';

        if (is_array($response) && isset($response['shipmentNumber'])) {
            $dhldp_label = new DHLDPLabel();

            $dhldp_label->id_order_carrier = (int)$id_order_carrier;
            $dhldp_label->product_code = 'ra';
            $dhldp_label->options = '';
            $dhldp_label->shipment_number = isset($response['shipmentNumber']) ? $response['shipmentNumber'] : $response['shipmentNumber'];
            $dhldp_label->label_url = isset($response['shipmentNumber']) ? $response['shipmentNumber'] : $response['shipmentNumber'];
            $dhldp_label->export_label_url = isset($response['items']['customsDoc']['url']) ? $response['items']['customsDoc']['url'] : '';
            $dhldp_label->cod_label_url = '';
            $dhldp_label->return_label_url = '';
            $dhldp_label->is_complete = 1;
            $dhldp_label->is_return = 1;
            $dhldp_label->with_return = 0;
            $dhldp_label->id_order_return = (int)$id_order_return;
            $dhldp_label->shipment_date = '';
            $dhldp_label->api_version = $this->dhldp_api_rest->getApiVersion();
            $dhldp_label->routing_code = $response['routingCode'];
            $dhldp_label->idc = '';
            $dhldp_label->idc_type = '';
            $dhldp_label->int_idc = '';
            $dhldp_label->int_idc_type = '';

            $pdf_file = $this->saveLabelFile($dhldp_label->label_url, base64_decode($response['labelData']));

            if (!$dhldp_label->save()) {
                return false;
            } else {
                $dhldp_package = new DHLDPPackage();
                $dhldp_package->id_dhldp_label = $dhldp_label->id;
                $dhldp_package->weight = 1;
                $dhldp_package->length = 1;
                $dhldp_package->width = 1;
                $dhldp_package->height = 1;
                $dhldp_package->package_type = 'PK';
                $dhldp_package->shipment_number = $dhldp_label->shipment_number;
                $dhldp_package->save();

                $order_carrier = new OrderCarrier((int)$id_order_carrier);
                $id_order = $order_carrier->id_order;
                $order = new Order((int)$id_order);
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

                    $pdf_file = $this->getLabelFilePathByLabelUrl($dhldp_label->label_url);

                    if ($pdf_file != '') {
                        $file_attachment = array(
                            'dhl_return_label' => array(
                                'content' => Tools::file_get_contents($pdf_file),
                                'name' => 'dhldp_return_label_' . $order->id . '_' . $id_order_return . '.pdf',
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
            return true;
        }
        return false;
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
            $ekp = DHLDPApiRest::$dhl_sbx_ekp[$this->dhldp_api_rest->getApiVersion()];
        }

        $shipment_order = array(
            'profile' => "STANDARD_GRUPPENPROFIL",
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

        if (self::getConfig('DHL_REFERENCE', $id_shop) != '') {
            $shipment_order['Shipment']['ShipperReference'] = self::getConfig('DHL_REFERENCE', $id_shop);
            unset($shipment_order['Shipment']['Shipper']);
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
        if (Configuration::get('DHLDP_DHL_DEF_GOGREEN', null, null, $order->id_shop)) {
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
            $shipment_order['Shipment']['ShipmentDetails']['Service']['CashOnDelivery'] = array(
                'active' => '1',
                'addFee' => (isset($options['addit_services']['CashOnDelivery_addFee']) && $options['addit_services']['CashOnDelivery_addFee'] == 1) ? 1 : 0,
                'codAmount' => $options['addit_services']['CashOnDelivery_codAmount']
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
        //echo '<pre>'.print_r($shipment_order, true).'</pre>'; exit;

        $shipment_order_request = array('ShipmentOrder' => $shipment_order);

        //TODO проверить необходимость условия
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
                    $pdf_decoded = isset($response['labelUrl']) ? Tools::file_get_contents($response['labelUrl']) : '';
                    if ($pdf_decoded != '') {
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
                //if ($this->isGermanyAddress($order->id_address_delivery)) {
                $order_carriers = $this->filterShipping($order->getShipping(), (int)$order->id_shop);
                if (is_array($order_carriers) && count($order_carriers) > 0) {
                    // change state
                    $params['orderReturn']->state = 2;
                    $params['orderReturn']->save();

                    // mail will be send on hookActionObjectOrderReturnUpdateAfter
                }
                //}
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
        if (($this->context->controller instanceof AddressController) &&
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
                    DHLDPApi::$tracking_url
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
                    $id_order_carrier,
                    (Configuration::get('DHLDP_DP_REF_NUMBER', null, null, $order->id_shop) ? $order->id : $order->reference)
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
                        'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax', false, array(), array()) . '&token=' . Tools::getAdminTokenLite('AdminDhldpAjax')
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
        if (Tools::getIsset('deleteDHLDPDhlLabel')) {
            $dhl_errors = array();
            $dhl_confirmations = array();

            $shipment_number = Tools::getValue('shipment_number');
            if ($shipment_number != '') {
                if (DHLDPLabel::getLabelIDByShipmentNumber($shipment_number) != false) {
                    $result = $this->deleteDeliveryLabel($shipment_number, $order->id_shop);
                    if (!$result) {
                        if (is_array($this->dhldp_api_rest->errors) && count($this->dhldp_api_rest->errors) > 0) {
                            $dhl_errors = array_merge($dhl_errors, $this->dhldp_api_rest->errors);
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
                        if (is_array($this->dhldp_api_rest->errors) && count($this->dhldp_api_rest->errors) > 0) {
                            $dhl_errors = array_merge($dhl_errors, $this->dhldp_api_rest->errors);
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

            switch (Tools::getValue('dhlcm')) {
                case '1':
                    $dhl_confirmations[] = $this->l('Shipment order and shipping label have been created.');
                    break;
            }

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
            } elseif (isset($product_params['weight_package']['min']) && ($product_params['weight_package']['min'] > $packages[0]['weight'] || $product_params['weight_package']['max'] < $packages[0]['weight'])) {
                $dhl_errors[] = $this->l('Weight is invalid') . ' (min. ' . $product_params['weight_package']['min'] . ' kg, max. ' . $product_params['weight_package']['max'] . ' kg)';
            } elseif (isset($product_params['length']['min']) && ($product_params['length']['min'] > $packages[0]['length'] || $product_params['length']['max'] < $packages[0]['length'])) {
                $dhl_errors[] = $this->l('Length is invalid') . ' (min. ' . $product_params['length']['min'] . ' cm, max. ' . $product_params['length']['max'] . ' cm)';
            } elseif (isset($product_params['width']['min']) && ($product_params['width']['min'] > $packages[0]['width'] || $product_params['width']['max'] < $packages[0]['width'])) {
                $dhl_errors[] = $this->l('Width is invalid') . ' (min. ' . $product_params['width']['min'] . ' cm, max. ' . $product_params['width']['max'] . ' cm)';
            } elseif (isset($product_params['height']['min']) && ($product_params['height']['min'] > $packages[0]['height'] || $product_params['height']['max'] < $packages[0]['height'])) {
                $dhl_errors[] = $this->l('Height is invalid') . ' (min. ' . $product_params['height']['min'] . ' cm, max. ' . $product_params['height']['max'] . ' cm)';
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
                    if (is_array($this->dhldp_api_rest->errors) && count($this->dhldp_api_rest->errors) > 0) {
                        $dhl_errors = array_merge($dhl_errors, $this->dhldp_api_rest->errors);
                    } else {
                        if (is_array($this->dhldp_api_rest->warnings) && count($this->dhldp_api_rest->warnings) > 0) {
                            $dhl_warnings_title = $this->l('THE SHIPPING LABEL WAS CREATED WITH WARNINGS');
                            $this->context->smarty->assign('dhl_warnings_title', $dhl_warnings_title);
                        } else {
                            $dhl_errors[] = $this->l('Unable to generate label for this request');
                        }
                    }
                } else {
                    if (is_array($this->dhldp_api_rest->warnings) && count($this->dhldp_api_rest->warnings) > 0) {
                        $dhl_warnings_title = $this->l('THE SHIPPING LABEL WAS CREATED WITH WARNINGS');
                        $this->context->smarty->assign('dhl_warnings_title', $dhl_warnings_title);
                    }
                    $dhl_confirmations = $this->dhldp_api_rest->confirmations;
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
                        'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax', false, array(), array()) . '&token=' . Tools::getAdminTokenLite('AdminDhldpAjax')
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
            //'street_number'         => isset($address_input['street_number']) ? $address_input['street_number'] : (isset($norm_address['Address']['streetNumber']) ? $norm_address['Address']['streetNumber'] : ''),
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

        $customer = new Customer($order->id_customer);

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
//            'GoGreen' => isset($service_input['GoGreen']) ? $service_input['GoGreen'] : '',
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
//            'SOZU' => $this->l('Return immediately'),
//            'ZWZU' => $this->l('2nd attempt of Delivery'),
//            'IMMEDIATE' => $this->l('Sending back immediately to sender'),
//            'AFTER_DEADLINE' => $this->l('Sending back immediately to sender after expiration of time'),
//            'ABANDONMENT' => $this->l('Abandonment of parcel at the hands of sender (free of charge)'),
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

    public static function getConfig($key, $id_shop = null)
    {
        return Configuration::get('DHLDP_' . $key, null, null, $id_shop);
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
                    $tracking_url = str_replace('[tracking_number]', $tracking_number, DHLDPApi::$tracking_url);

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

    public function hookDisplayBackOfficeHeader($params)
    {
        Media::addJsDef(array('is177' => $this->is177));
        unset($params);
        $script = '';

        if (($this->context->controller->controller_name == 'AdminOrders' || $this->context->controller instanceof AdminOrdersController) && $this->is177 && !Tools::getIsset('id_order')) {
            global $kernel;
            $id_order = $kernel->getContainer()->get('request_stack')->getCurrentRequest()->get('orderId');
        } else {
            $id_order = Tools::getValue('id_order');
        }
        if (($this->context->controller->controller_name == 'AdminOrders' || $this->context->controller instanceof AdminOrdersController) && !$id_order) {
            if (version_compare(_PS_VERSION_, '8.0', '<')) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJS($this->_path . 'views/js/order-list.js');
            if ($this->is177) {
                $this->context->controller->addCSS($this->_path . 'views/css/order_list.css');
            }

            return '<script type="text/javascript">
                var dhldp_request_path = "' . $this->getModuleUrl(array('view' => 'generateLabels')) . '";
                var dhldp_translation = ' .
                json_encode(
                    array(
                        'Generate DHL labels' => $this->l('Generate DHL labels'),
                        'Generate DP labels' => $this->l('Generate Deutschepost labels'),
                    )
                ) . '</script>';
        } elseif (($this->context->controller->controller_name == 'AdminOrders' || $this->context->controller instanceof AdminOrdersController) && $id_order) {
            if (version_compare(_PS_VERSION_, '8.0', '<')) {
                $this->context->controller->addJquery();
            }
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
        } elseif (Tools::getValue('configure') == $this->name) {
            if (version_compare(_PS_VERSION_, '8.0', '<')) {
                $this->context->controller->addJquery();
            }
            if (Tools::getValue('view') == 'settings_dp') {
                $this->context->controller->addJS($this->_path . 'views/js/dp_admin_configure.js');
            } else {
                if (Tools::getValue('view') == 'generateLabels') {
                    $this->context->controller->addJS($this->_path . 'views/js/jquery.maxlength.min.js');
                    if (!$this->is177) {
                        $this->context->controller->addJS($this->_path . 'views/js/popper.min.js');
                    }
                    $this->context->controller->addJS($this->_path . 'views/js/admin_orders.js');
                } else {
                    if ((_PS_VERSION_ < '1.6.0.0')) {
                        $this->context->controller->addCSS($this->_path . 'views/css/admin-15.css');
                    }
                    $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
                    $this->context->controller->addJqueryPlugin(array('idTabs', 'select2'));


                    if (version_compare(_PS_VERSION_, '1.6', '<')) {
                        $this->context->controller->addJS($this->_path . 'views/js/jquery.validate.js');
                    } else {
                        $this->context->controller->addJqueryPlugin('validate');
                        $this->context->controller->addJS(
                            _PS_JS_DIR_ . 'jquery/plugins/validate/localization/messages_' . $this->context->language->iso_code . '.js'
                        );
                    }
                    $this->context->controller->addJS($this->_path . 'views/js/admin_configure.js');

                    $dhl_products = $this->dhldp_api_rest->getDefinedProducts('', '', self::getConfig('DHL_COUNTRY'), self::getConfig('DHL_API_VERSION'));
                    $dhl_products_js = array();
                    foreach ($dhl_products as $dhl_product_key => $dhl_product) {
                        if ($dhl_product['active'] == true) {
                            $dhl_product_js = new stdClass();
                            $dhl_product_js->name = $dhl_product['name'];
                            $dhl_product_js->code = $dhl_product_key;
                            $dhl_products_js[] = $dhl_product_js;
                        }
                    }
//                    $dhl_gogreen_options_js = array();
//                    $dhl_gogreen_option_js = new stdClass();
//                    $dhl_gogreen_option_js->name = '';
//                    $dhl_gogreen_option_js->code = '';
//                    $dhl_gogreen_options_js[] = $dhl_gogreen_option_js;
//
//                    $dhl_gogreen_option_js = new stdClass();
//                    $dhl_gogreen_option_js->name = 'GoGreen';
//                    $dhl_gogreen_option_js->code = 'gogreen';
//                    $dhl_gogreen_options_js[] = $dhl_gogreen_option_js;
                    $script .= '<script>
                    var defined_dhl_api_versions = ' . json_encode(DHLDPApi::$supported_shipper_countries) . ';
                    var defined_dhl_products = ' . json_encode($dhl_products_js) . ';
                    var dhl_translation = ' .
                        json_encode(
                            array(
                                'Remove' => $this->l('Remove'),
                                'ExistsParticipation' => $this->l('Such participation exists for this product'),
                                'Exists' => $this->l('This product already exists in the list')
                            )
                        ) .
                        '</script>';
                }
            }
        }
        $this->context->controller->addJS($this->_path . 'views/js/dhl-product-dimensions.js');
        Media::addJsDef([
            'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax', false, array(), array()) . '&token=' . Tools::getAdminTokenLite('AdminDhldpAjax')
        ]);
        return $script;
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

        if (Context::getContext()->shop->getContext() != Shop::CONTEXT_SHOP && Shop::isFeatureActive()) {
            return $this->displayError($this->l('You have to select a shop before configuring module.'));
        }
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
                        'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax', false, array(), array()) . '&token=' . Tools::getAdminTokenLite('AdminDhldpAjax')
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
            case 'information':
                $definition_pages = $this->getDefinitionConfigurePages();
                $html .= $this->displayMenu($definition_pages);
                $html .= $this->displayInfo();
                break;
            case 'changelog':
                $changelog_file = dirname(__FILE__) . '/Readme.md';
                if (file_exists($changelog_file)) {
                    die($this->displayChangelog($changelog_file));
                }
                break;
            case 'init_dhl':
                $definition_pages = $this->getDefinitionConfigurePages();
                $html .= $this->displayMenu($definition_pages);
                $html .= $this->postInitDHLProcess();
                $html .= $this->displayFormInitDHLSettings();
                break;
            case 'settings_dp':
                $definition_pages = $this->getDefinitionConfigurePages();
                $html .= $this->displayMenu($definition_pages);
                $html .= $this->postProcess();
                $html .= $this->displayFormDPSettings();
                break;
            default:
                $definition_pages = $this->getDefinitionConfigurePages();
                $html .= $this->displayMenu($definition_pages);
                $html .= $this->postProcess();
                $html .= $this->displayFormDHLSettings();

                break;
        }

        $rclient = new DHLDPRestClient(array('savelog_callback' => ''));
        //
        /*$res = $rclient->get('https://api-sandbox.dhl.com/location-finder/v1/find-by-address',
            array(
                'countryCode' => 'DE',
                'addressLocality' => 'Bonn',
                'postalCode' => '53113',
                'radius' => '2500',
                'limit' => '20'
            ),
            array(
                'DHL-API-Key' => 'prestashop'
            )
        );*/


        /*$res = $rclient->get('https://api.dhl.com/location-finder/v1/find-by-address',
            array(
                'countryCode' => 'DE',
                'addressLocality' => 'Hannover',
                'postalCode' => '30539',
                'radius' => '5000',
                'limit' => '100'
            ),
            array(
                'DHL-API-Key' => 'prestashop'
            )
        );*/

        //$out = print_r($res->decodeResponse(), true);
        /*
        $address = array(
            'streetAddress' => '', //Tools::getValue('street', '').' '.Tools::getValue('streetNo', ''),
            'zip' => '30539',
            'city' => 'Hannover',
        );

        $out = $this->dhldp_api->getRestPackstations($address);
        $html .= '<pre>'.print_r($out, true).'</pre>';

        $out = $this->dhldp_api->getRestPostfiliales($address);
        $html .= '<pre>'.print_r($out, true).'</pre>';
        */
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
            )
        );
    }

    public function displayMenu($def_pages)
    {
        $menu_items = array();
        foreach ($def_pages['pages'] as $page_key => $page_item) {
            $menu_items[$page_key] = array(
                'name' => $page_item['name'],
                'icon' => isset($page_item['icon']) ? $page_item['icon'] : '',
                'url' => $this->getModuleUrl() . '&' . $def_pages['cparam'] . '=' . $page_key,
                'active' => ((!in_array(Tools::getValue($def_pages['cparam']), array_keys($def_pages['pages'])) && isset($page_item['default']) && $page_item['default'] == true) || Tools::getValue($def_pages['cparam']) == $page_key) ? true : false
            );
        }

        $this->smarty->assign(array(
            'menu_items' => $menu_items,
            'module_version' => $this->version,
            'module_name' => $this->displayName,
            'changelog' => file_exists(dirname(__FILE__) . '/Readme.md'),
            'changelog_path' => $this->getModuleUrl() . '&' . $def_pages['cparam'] . '=changelog',
            '_path' => $this->_path
        ));

        return $this->display(__FILE__, 'views/templates/admin/menu.tpl');
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
                $create_label_no_validation = $with_warning ?: ($c['create_label_no_validation'] ?? null);

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
                    $has_api_errors = is_array($this->dhldp_api_rest->errors) && count($this->dhldp_api_rest->errors) > 0;
                    $has_warnings = is_array($this->dhldp_api_rest->warnings) && count($this->dhldp_api_rest->warnings) > 0;

                    if ($has_api_errors) {
                        $order_errors = array_merge($order_errors, $this->dhldp_api_rest->errors);
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
            $orders_errors[$c['order_id']] = $this->dhldp_api_rest->errors;
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

    public function getLabelFileNameByLabelUrl($label_url)
    {
        return $this->getLocalPath() . 'pdfs/' . str_replace(array('printShipment?token', '?', '=', ' '), '', basename($label_url)) . '.pdf';
    }

    public function getLabelFileURIByLabelUrl($label_url)
    {
        return $this->getPathUri() . 'pdfs/' . str_replace(array('printShipment?token', '?', '=', ' '), '', basename($label_url)) . '.pdf';
    }

    public function saveLabelFile($label_url, $data)
    {
        $label_file = $this->getLabelFileNameByLabelUrl($label_url);
        if (!file_exists($label_file) || ((int)filesize($label_file) == 0)) {
            file_put_contents($label_file, $data);
        }
        if (file_exists($label_file) && ((int)filesize($label_file) != 0)) {
            return $label_file;
        }
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

    public function displayInfo()
    {
        $this->smarty->assign(
            array(
                '_path' => $this->_path,
                'displayName' => $this->displayName,
                'author' => $this->author,
                'description' => $this->description,
            )
        );

        return $this->display(__FILE__, 'views/templates/admin/info.tpl');
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

    protected function setFormFieldsValue(&$helper, $keys)
    {
        if (is_array($keys)) {
            foreach ($keys as $key) {
                $helper->fields_value['DHLDP_' . $key] = Tools::getValue('DHLDP_' . $key, self::getConfig($key));
            }
        }
    }

    protected function displayFormDPSettings()
    {
        $helper = new HelperForm();

        // Helper Options
        $helper->required = false;
        $helper->id = null;// Tab::getCurrentTabId();

        // Helper
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . '&view=settings_dp';
        $helper->table = 'dp_configure';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->module = $this;
        $helper->identifier = null;
        $helper->toolbar_btn = null;
        $helper->ps_help_context = null;
        $helper->title = null;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = false;
        $helper->bootstrap = true;

        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        if (_PS_VERSION_ < '1.6.0.0') {
            $helper->show_toolbar = false;

            $helper->title = $this->displayName;
        }

        $carriers = Carrier::getCarriers($this->context->language->id, true);
        $option_carriers = array();
        foreach ($carriers as $carrier) {
            $option_carriers[] = array('id_carrier' => $carrier['id_carrier'], 'name' => $carrier['name']);
        }

        $this->context->smarty->assign(
            array(
                'page_format' => Tools::getValue('DHLDP_DP_PAGE_FORMAT', Configuration::get('DHLDP_DP_PAGE_FORMAT')),
                'position_row' => Tools::getValue('DHLDP_DP_POSITION_ROW', Configuration::get('DHLDP_DP_POSITION_ROW')),
                'position_col' => Tools::getValue('DHLDP_DP_POSITION_COL', Configuration::get('DHLDP_DP_POSITION_COL')),
                'position_page' => Tools::getValue('DHLDP_DP_POSITION_PAGE', Configuration::get('DHLDP_DP_POSITION_PAGE')),
                'page_formats' => $this->dp_api->getPageFormats(),
                'carriers' => $option_carriers,
                'dp_carriers' => $this->getDPCarriers(true),
                'link' => $this->context->link->getAdminLink('AdminCarrierWizard', false) . '&token=' . Tools::getAdminTokenLite('AdminCarrierWizard'),
                'ppl_version' => $this->dp_api->getPPLVersion()
            )
        );

        //$helper->fields_value['DHLDP_DP_MODE'] = Tools::getValue('DHLDP_DP_MODE', Configuration::get('DHLDP_DP_MODE'));

        $helper->fields_value['DHLDP_DP_LIVE_USERNAME'] = Tools::getValue('DHLDP_DP_LIVE_USERNAME', Configuration::get('DHLDP_DP_LIVE_USERNAME'));
//        $helper->fields_value['DHLDP_DP_LIVE_PASSWORD'] = Tools::getValue('DHLDP_DP_LIVE_PASSWORD', Configuration::get('DHLDP_DP_LIVE_PASSWORD'));
        $helper->fields_value['DHLDP_DP_LIVE_PASSWORD'] = str_repeat('*', mb_strlen(Configuration::get('DHLDP_DP_LIVE_PASSWORD')));

        //$helper->fields_value['DHLDP_DP_SBX_USERNAME'] = Tools::getValue('DHLDP_DP_SBX_USERNAME', Configuration::get('DHLDP_DP_SBX_USERNAME'));
        //$helper->fields_value['DHLDP_DP_SBX_PASSWORD'] = Tools::getValue('DHLDP_DP_SBX_PASSWORD', Configuration::get('DHLDP_'.'DP_SBX_PASSWORD'));

        $helper->fields_value['DHLDP_DP_LOG'] = Tools::getValue('DHLDP_DP_LOG', Configuration::get('DHLDP_DP_LOG'));
        $helper->fields_value['log_information'] = $this->displayDPLogInformation();

        $helper->fields_value['DHLDP_DP_DEF_PRODUCT'] = Tools::getValue('DHLDP_DP_DEF_PRODUCT', Configuration::get('DHLDP_DP_DEF_PRODUCT'));
        $helper->fields_value['DHLDP_DP_CHANGE_OS'] = Tools::getValue('DHLDP_DP_CHANGE_OS', Configuration::get('DHLDP_DP_CHANGE_OS'));
        $helper->fields_value['DHLDP_DP_REF_NUMBER'] = Tools::getValue('DHLDP_DP_REF_NUMBER', Configuration::get('DHLDP_DP_REF_NUMBER'));

        $helper->fields_value['DHLDP_DP_CREATE_MANIFEST'] = (int)Tools::getValue('DHLDP_DP_CREATE_MANIFEST', Configuration::get('DHLDP_DP_CREATE_MANIFEST'));
        $helper->fields_value['DHLDP_DP_CREATE_SHIPLIST'] = (int)Tools::getValue('DHLDP_DP_CREATE_SHIPLIST', Configuration::get('DHLDP_DP_CREATE_SHIPLIST'));
        $helper->fields_value['DHLDP_DP_LABEL_FORMAT'] = Tools::getValue('DHLDP_DP_LABEL_FORMAT', Configuration::get('DHLDP_DP_LABEL_FORMAT'));

        $helper->fields_value['retrieve_page_formats'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dp-retrieve-pageformats.tpl');
        $helper->fields_value['label_position'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dp-label-position.tpl');

        $helper->fields_value['update_ppl'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dp-update-ppl.tpl');
        $helper->fields_value['add_carrier'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dp-add-carrier.tpl');
        $helper->fields_value['carrier_list'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dp-carrier-list.tpl');

        $helper->fields_value['DHLDP_DP_NAME'] = Tools::getValue('DHLDP_DP_NAME', Configuration::get('DHLDP_DP_NAME'));
        $helper->fields_value['DHLDP_DP_COMPANY'] = Tools::getValue('DHLDP_DP_COMPANY', Configuration::get('DHLDP_DP_COMPANY'));
        $helper->fields_value['DHLDP_DP_SALUTATION'] = Tools::getValue('DHLDP_DP_SALUTATION', Configuration::get('DHLDP_DP_SALUTATION'));
        $helper->fields_value['DHLDP_DP_TITLE'] = Tools::getValue('DHLDP_DP_TITLE', Configuration::get('DHLDP_DP_TITLE'));
        $helper->fields_value['DHLDP_DP_FIRSTNAME'] = Tools::getValue('DHLDP_DP_FIRSTNAME', Configuration::get('DHLDP_DP_FIRSTNAME'));
        $helper->fields_value['DHLDP_DP_LASTNAME'] = Tools::getValue('DHLDP_DP_LASTNAME', Configuration::get('DHLDP_DP_LASTNAME'));
        $helper->fields_value['DHLDP_DP_STREET'] = Tools::getValue('DHLDP_DP_STREET', Configuration::get('DHLDP_DP_STREET'));
        $helper->fields_value['DHLDP_DP_HOUSENO'] = Tools::getValue('DHLDP_DP_HOUSENO', Configuration::get('DHLDP_DP_HOUSENO'));
        $helper->fields_value['DHLDP_DP_ZIP'] = Tools::getValue('DHLDP_DP_ZIP', Configuration::get('DHLDP_DP_ZIP'));
        $helper->fields_value['DHLDP_DP_STREET'] = Tools::getValue('DHLDP_DP_STREET', Configuration::get('DHLDP_DP_STREET'));
        $helper->fields_value['DHLDP_DP_CITY'] = Tools::getValue('DHLDP_DP_CITY', Configuration::get('DHLDP_DP_CITY'));
        $helper->fields_value['DHLDP_DP_COUNTRY'] = Tools::getValue('DHLDP_DP_COUNTRY', Configuration::get('DHLDP_DP_COUNTRY'));
        $helper->fields_value['DHLDP_DP_ADDITIONAL'] = Tools::getValue('DHLDP_DP_ADDITIONAL', Configuration::get('DHLDP_DP_ADDITIONAL'));

        return $helper->generateForm($this->getFormFieldsDPSettings());
    }

    protected function displayFormDHLSettings()
    {
        $helper = new HelperForm();
        $helper->required = false;
        $helper->id = null;
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->table = 'DHLDP_dhl_configure';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->module = $this;
        $helper->identifier = null;
        $helper->toolbar_btn = null;
        $helper->ps_help_context = null;
        $helper->title = null;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = false;
        $helper->bootstrap = true;

        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        if (_PS_VERSION_ < '1.6.0.0') {
            $helper->show_toolbar = false;
            $helper->title = $this->displayName;
        }

        $fields_value_keys = array('DHL_MODE', 'DHL_RETURN_PARTICIPATION', 'DHL_LIVE_USER', 'DHL_LIVE_SIGN',
            'DHL_LIVE_EKP', 'DHL_LOG', 'DHL_REF_NUMBER', 'DHL_ORDER_WEIGHT', 'DHL_WEIGHT_RATE', 'DHL_PACK_WEIGHT', 'DHL_AGE_CHECK', 'DHL_PREMIUM',
            'DHL_PFPS', 'DHL_PFPS_MAP', 'DHL_GOOGLEMAPAPIKEY', 'DHL_CHANGE_OS', 'DHL_RETURN_MAIL', 'DHL_INTRANSIT_MAIL', 'DHL_LABEL_WITH_RETURN', 'DHL_LABEL_IGNORE_WARNING',
            'DHL_CONFIRMATION_PRIVATE', 'DHL_CREATE_MANIFEST_IN_ORDER', 'DHL_RETURNS_EXTEND', 'DHL_RETURNS_RP', 'DHL_RETURNS_IMMED', 'DHL_RETOUREPORTAL_ID', 'DHL_RETOUREPORTAL_DNAME',
            'DHL_RETOUREPORTAL_USER', 'DHL_RETOUREPORTAL_PASS', 'DHL_SHIPPER_TYPE', 'DHL_COMPANY_NAME_1', 'DHL_COMPANY_NAME_2', 'DHL_CONTACT_PERSON',
            'DHL_STREET_NAME', 'DHL_STREET_NUMBER', 'DHL_ZIP', 'DHL_CITY', 'DHL_STATE', 'DHL_PHONE', 'DHL_EMAIL', 'DHL_REFERENCE', 'DHL_ACCOUNT_OWNER',
            'DHL_ACCOUNT_NUMBER', 'DHL_BANK_CODE', 'DHL_BANK_NAME', 'DHL_IBAN', 'DHL_BIC', 'DHL_NOTE', 'DHL_NOTE2', 'DHL_DEFAULT_LENGTH', 'DHL_DEFAULT_WIDTH',
            'DHL_DEFAULT_HEIGHT', 'DHL_LABEL_FORMAT', 'DHL_RETOURE_LABEL_FORMAT', 'DHL_EPRINT_EMAIL', 'DHL_LIVE_TUSER', 'DHL_LIVE_TSIGN', 'DHL_SECURE_KEY',
            'DHL_CHANGE_OS_DELIVERED', 'DHL_EXP_INV_NUM', 'DHL_DEF_CUSTOMS_TARIFF_NUM', 'DHL_DEF_PLACE_OF_COMMITAL', 'DHL_DEF_ADDITIONAL_CUSTOM_FEES', 'DHL_DEF_PARCEL_ROUT_SERV', 'DHL_DEF_GOGREEN');

        $this->setFormFieldsValue($helper, $fields_value_keys);

        $helper->fields_value['DHLDP_DHL_RA_COUNTRIES[]'] = Tools::getValue('DHLDP_DHL_RA_COUNTRIES',
            explode(',', self::getConfig('DHL_RA_COUNTRIES')));

        $helper->fields_value['DHLDP_DHL_LIVE_RESET'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dhl-reset-live-account.tpl'
        );
        $this->context->smarty->assign(
            array(
                'initdhl_link' => $helper->currentIndex . '&view=init_dhl&token=' . $helper->token,
                'dhldp_dhl_api_version' => Tools::getValue('DHLDP_DHL_API_VERSION', self::getConfig('DHL_API_VERSION') ? self::getConfig('DHL_API_VERSION') : '2.1'),
            )
        );
        $helper->fields_value['DHLDP_DHL_API_VERSION'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dhl-api-version.tpl'
        );
        $helper->fields_value['log_information'] = $this->displayDHLLogInformation();

        $carriers = Carrier::getCarriers($this->context->language->id, true);
        $option_carriers = array();
        foreach ($carriers as $carrier) {
            $option_carriers[] = array('id_carrier' => $carrier['id_carrier'], 'name' => $carrier['name']);
        }

        $added_dhl_products = $this->getFormattedAddedDhlProducts(
            Tools::getValue('added_dhl_products', explode(';', self::getConfig('DHL_PRODUCTS')))
        );

        $this->context->smarty->assign(
            array(
                'carriers' => $option_carriers,
                'dhl_carriers' => $this->getDhlCarriers(true, false),
                'link' => $this->context->link->getAdminLink(
                        'AdminCarrierWizard',
                        false
                    ) . '&token=' . Tools::getAdminTokenLite('AdminCarrierWizard'),
                'added_dhl_products' => $added_dhl_products,
                'dhl_product_dimensions' => $added_dhl_products
            )
        );

        $helper->fields_value['add_carrier'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dhl-add-carrier.tpl'
        );
        $helper->fields_value['carrier_list'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dhl-carrier-list.tpl'
        );
        $helper->fields_value['dhl_products'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dhl-products.tpl'
        );

        $helper->fields_value['dhl-product-dimensions'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dhl-product-dimensions.tpl'
        );

        $helper->fields_value['DHLDP_DHL_IND_NOTIF'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dhl-ind-notif.tpl'
        );

        $this->context->smarty->assign(
            array(
                'dhl_country' => Tools::getValue('DHLDP_DHL_COUNTRY', self::getConfig('DHL_COUNTRY')),
            )
        );
        $helper->fields_value['DHLDP_DHL_COUNTRY'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/dhl-shipper-country.tpl'
        );
        $helper->fields_value['DHLDP_DHL_LIVE_SIGN'] =  str_repeat('*', mb_strlen(Configuration::get('DHLDP_DHL_LIVE_SIGN')));
        return $helper->generateForm($this->getFormFieldsDHLSettings());
    }

    protected function displayFormInitDHLSettings()
    {
        $helper = new HelperForm();

        // Helper Options
        $helper->required = false;
        $helper->id = null; // Tab::getCurrentTabId();

        // Helper
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . '&view=init_dhl';
        $helper->table = 'dhldp_ini_configure';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->module = $this;
        $helper->identifier = null;
        $helper->toolbar_btn = null;
        $helper->ps_help_context = null;
        $helper->title = null;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = false;
        $helper->bootstrap = true;

        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        if (_PS_VERSION_ < '1.6.0.0') {
            $helper->show_toolbar = false;

            $helper->title = $this->displayName;
        }

        $helper->fields_value['DHLDP_DHL_API_VERSION'] = Tools::getValue('DHLDP_DHL_API_VERSION', self::getConfig('DHL_API_VERSION') ? self::getConfig('DHL_API_VERSION') : '2.1');
        $helper->fields_value['DHLDP_DHL_COUNTRY'] = Tools::getValue('DHLDP_DHL_COUNTRY', self::getConfig('DHL_COUNTRY'));
        return $helper->generateForm($this->getFormFieldsInitDHLSettings());
    }

    protected function getFormattedAddedDhlProducts($added_dhl_products, $to_country = '', $from_country = '', $api_version = '')
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

    private function displayDHLLogInformation()
    {
        $this->smarty->assign(array(
            'general_log_file_path' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dhl&log_file=dhl_general',
            'api_log_file_path' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dhl&log_file=dhl_api',
            'api_log_file_path_clear' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dhl&log_file=dhl_api_clear',
        ));

        return $this->display(__FILE__, 'views/templates/admin/log_information.tpl');
    }

    private function displayDPLogInformation()
    {
        $this->smarty->assign(array(
            'general_log_file_path' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dp&log_file=dp_general',
            'api_log_file_path' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dp&log_file=dp_api',
            'api_log_file_path_clear' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dp&log_file=dp_api_clear',
        ));

        return $this->display(__FILE__, 'views/templates/admin/log_information.tpl');
    }

    protected function getFormFieldsInitDHLSettings()
    {
        $form_fields = array();

        $api_versions = DHLDPApi::getSupportedApiVersions();
        $api_version_options = array(
            'id' => 'value',
            'name' => 'label'
        );
        foreach ($api_versions as $apiv) {
            $api_version_options['query'][] = array(
                'value' => $apiv,
                'label' => $apiv
            );
        }

        $shipper_country_options = array(
            'id' => 'value',
            'name' => 'label'
        );
        foreach (array_keys(DHLDPApi::$supported_shipper_countries) as $iso_code) {
            $shipper_country_options['query'][] = array(
                'value' => $iso_code,
                'label' => Country::getNameById($this->context->language->id, Country::getByIso($iso_code))
            );
        }

        $form_fields = array_merge(
            $form_fields,
            array(
                'form' => array(
                    'form' => array(
                        'id_form' => 'dhldp_init_settings',
                        'legend' => array(
                            'title' => $this->l('DHL init settings'),
                            'icon' => 'icon-circle',
                        ),
                        'description' => $this->l('Please select shipper country and version of DHL API.'),
                        'input' => array(
                            array(
                                'name' => 'DHLDP_DHL_COUNTRY',
                                'type' => 'select',
                                'label' => $this->l('Country'),
                                'required' => true,
                                'options' => $shipper_country_options
                            ),
                            /*array(
                                'name'     => 'DHLDP_DHL_API_VERSION',
                                'type'     => 'select',
                                'label'    => $this->l('DHL API version'),
                                'class'    => 't',
                                'required' => true,
                                'options'   => $api_version_options
                            ),*/
                        ),
                        'submit' => array(
                            'title' => $this->l('Save'),
                            'name' => 'submitSaveOptions',
                        )
                    )
                ),
            )
        );

        return $form_fields;
    }

    protected function getFormFieldsDPSettings()
    {
        $form_fields = array(
            'form1' => array(
                'form' => array(
                    'id_form' => 'dp_global_settings',
                    'legend' => array(
                        'title' => $this->l('Global settings'),
                        'icon' => 'icon-circle',
                    ),
                    'description' => $this->l('Please select mode and fill form with all relevant information regarding authentication in modes.'),
                    'input' => array(
                        array(
                            'name' => 'DHLDP_DP_LIVE_USERNAME',
                            'type' => 'text',
                            'label' => $this->l('Live username'),
                            'desc' => $this->l('"Live" username for Authentication'),
                            'required' => true,
                            //'form_group_class' => 'deutschepost_authdata_live'
                        ),
                        array(
                            'name' => 'DHLDP_DP_LIVE_PASSWORD',
                            'type' => 'text',
                            'label' => $this->l('Live password'),
                            'desc' => $this->l('"Live" password for Authentication'),
                            'required' => true,
                            //'form_group_class' => 'deutschepost_authdata_live'
                        ),
                        array(
                            'name' => 'DHLDP_DP_LOG',
                            'type' => 'radio',
                            'label' => $this->l('Enable Log'),
                            'desc' => $this->l('Logs of actions in') . ' ' . DIRECTORY_SEPARATOR . 'logs ' .
                                $this->l('directory. Please notice: logs information can take a lot of disk space after a time.'),
                            'class' => 't',
                            'is_bool' => true,
                            'disabled' => false,
                            'values' => array(
                                array(
                                    'id' => 'log_yes',
                                    'value' => 1,
                                    'label' => $this->l('Yes')
                                ),
                                array(
                                    'id' => 'log_no',
                                    'value' => 0,
                                    'label' => $this->l('No')
                                ),
                            ),
                        ),
                        array(
                            'type' => 'free',
                            'name' => 'log_information',
                        ),
                        array(
                            'type' => 'free',
                            'label' => $this->l('PPL'),
                            'name' => 'update_ppl',
                            //'desc' => $this->l('Please update PPL file and version, if you see "The PPL is invalid!" error message'),
                        ),
                        array(
                            'type' => 'free',
                            'label' => $this->l('Carriers'),
                            'name' => 'carrier_list',
                        ),
                        array(
                            'type' => 'free',
                            'label' => $this->l('New carrier'),
                            'name' => 'add_carrier',
                            'desc' => $this->l('If you do not have a carrier'),
                        ),
                        array(
                            'type' => 'radio',
                            'label' => $this->l('Reference number in label is '),
                            'name' => 'DHLDP_DP_REF_NUMBER',
                            'required' => true,
                            'class' => 't',
                            'br' => true,
                            'values' => array(
                                array(
                                    'id' => 'order_ref',
                                    'value' => 0,
                                    'label' => $this->l('Order reference')
                                ),
                                array(
                                    'id' => 'order_number',
                                    'value' => 1,
                                    'label' => $this->l('Order ID')
                                )
                            )
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->l('Default product'),
                            'desc' => $this->l('Please select product which will be preselected for creating label'),
                            'name' => 'DHLDP_DP_DEF_PRODUCT',
                            'options' => array(
                                'query' => array_merge(array(array('code' => '0', 'name' => $this->l('---- Select product ----'))), $this->dp_api->getProducts()),
                                'id' => 'code',
                                'name' => 'name'
                            )
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->l('Enable updating order status'),
                            'desc' => $this->l('Order status will be changed "Shipped" automatically after creating DP label'),
                            'name' => 'DHLDP_DP_CHANGE_OS',
                            'options' => array(
                                'query' => array_merge(
                                    array(
                                        array(
                                            'id_order_state' => '',
                                            'name' => $this->l('-- Do not change --')
                                        )
                                    ),
                                    $this->getShippedOrderStates()
                                ),
                                'id' => 'id_order_state',
                                'name' => 'name'
                            )
                        ),
                        array(
                            'type' => 'radio',
                            'label' => $this->l('Enable creating manifest'),
                            'name' => 'DHLDP_DP_CREATE_MANIFEST',
                            'required' => true,
                            'class' => 't',
                            'br' => true,
                            'values' => array(
                                array(
                                    'id' => 'create_manifest_yes',
                                    'value' => 1,
                                    'label' => $this->l('Yes')
                                ),
                                array(
                                    'id' => 'create_manifest_no',
                                    'value' => 0,
                                    'label' => $this->l('No')
                                )
                            )
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->l('Enable creating shipping list'),
                            'desc' => $this->l('Please select product which will be preselected for creating label'),
                            'name' => 'DHLDP_DP_CREATE_SHIPLIST',
                            'options' => array(
                                'query' => array(
                                    array('code' => '0', 'name' => $this->l('No')),
                                    array('code' => '1', 'name' => $this->l('Yes, shipping list without addresses')),
                                    array('code' => '2', 'name' => $this->l('Yes, shipping list with addresses')),
                                ),
                                'id' => 'code',
                                'name' => 'name'
                            )
                        ),
                        array(
                            'type' => 'radio',
                            'label' => $this->l('Label file format'),
                            'name' => 'DHLDP_DP_LABEL_FORMAT',
                            'required' => true,
                            'class' => 't',
                            'br' => true,
                            'values' => array(
                                array(
                                    'id' => 'label_format_png',
                                    'value' => 'png',
                                    'label' => $this->l('PNG picture')
                                ),
                                array(
                                    'id' => 'label_format_pdf',
                                    'value' => 'pdf',
                                    'label' => $this->l('PDF document')
                                )
                            )
                        ),
                        array(
                            'type' => 'free',
                            'label' => $this->l('Page format (only for PDF label file format)'),
                            'name' => 'retrieve_page_formats',
                            'desc' => $this->l('Please retrieve page formats, if you see empty list of page formats'),
                        ),
                        array(
                            'type' => 'free',
                            'label' => $this->l('Label position on PDF document(only for PDF label file format)'),
                            'name' => 'label_position',
                            'desc' => $this->l('The values must be greater than 0 if the position is specified'),
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save options'),
                        'name' => 'submitSaveDPOptions',
                    )
                ),

            ),
            'form2' => array(
                'form' => array(
                    'id_form' => 'deutschepost_address',
                    'legend' => array(
                        'title' => $this->l('Address'),
                        'icon' => 'icon-circle',
                    ),
                    'description' => $this->l('Please enter adress of sender(shop)'),
                    'input' => array(
                        array(
                            'type' => 'radio',
                            'label' => $this->l('Name'),
                            'name' => 'DHLDP_DP_NAME',
                            'required' => true,
                            'class' => 't',
                            'br' => true,
                            'values' => array(
                                array(
                                    'id' => 'dp_sender_person',
                                    'value' => 0,
                                    'label' => $this->l('Person')
                                ),
                                array(
                                    'id' => 'dp_sender_company',
                                    'value' => 1,
                                    'label' => $this->l('Company')
                                )
                            ),
                            'desc' => $this->l('Please select type of sender'),
                        ),
                        array(
                            'name' => 'DHLDP_DP_COMPANY',
                            'type' => 'text',
                            'label' => $this->l('Company'),
                            'desc' => $this->l('Name of company. Max. 50 characters.'),
                            'size' => 50,
                            'required' => true,
                            'form_group_class' => 'dp_company deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_SALUTATION',
                            'type' => 'text',
                            'label' => $this->l('Salutation'),
                            'desc' => $this->l('Max. 10 characters'),
                            'size' => 10,
                            'required' => false,
                            'form_group_class' => 'deutschepost_salutation deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_TITLE',
                            'type' => 'text',
                            'label' => $this->l('Title'),
                            'desc' => $this->l('Max. 10 characters'),
                            'size' => 10,
                            'required' => false,
                            'form_group_class' => 'deutschepost_title deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_FIRSTNAME',
                            'type' => 'text',
                            'label' => $this->l('Firstname'),
                            'desc' => $this->l('Max. 35 characters'),
                            'size' => 35,
                            'required' => true,
                            'form_group_class' => 'deutschepost_firstname deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_LASTNAME',
                            'type' => 'text',
                            'label' => $this->l('Lastname'),
                            'desc' => $this->l('Max. 35 characters'),
                            'size' => 35,
                            'required' => true,
                            'form_group_class' => 'deutschepost_lastname deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_STREET',
                            'type' => 'text',
                            'label' => $this->l('Street'),
                            'desc' => $this->l('Max. 50 characters'),
                            'size' => 50,
                            'required' => true
                        ),
                        array(
                            'name' => 'DHLDP_DP_HOUSENO',
                            'type' => 'text',
                            'label' => $this->l('House number'),
                            'desc' => $this->l('Max. 10 characters'),
                            'size' => 10,
                            'required' => true
                        ),
                        array(
                            'name' => 'DHLDP_DP_ZIP',
                            'type' => 'text',
                            'label' => $this->l('Postcode'),
                            'desc' => $this->l('Max. 10 characters'),
                            'size' => 10,
                            'required' => true
                        ),
                        array(
                            'name' => 'DHLDP_DP_CITY',
                            'type' => 'text',
                            'label' => $this->l('City'),
                            'desc' => $this->l('Max. 35 characters'),
                            'size' => 35,
                            'required' => true
                        ),
                        array(
                            'name' => 'DHLDP_DP_COUNTRY',
                            'type' => 'select',
                            'label' => $this->l('Country'),
                            'required' => true,
                            'options' => array(
                                'query' => array_merge(
                                    array(
                                        array('id_country' => '0', 'name' => $this->l('---- Select country ----'))
                                    ),
                                    Country::getCountries($this->context->language->id)
                                ),
                                'id' => 'id_country',
                                'name' => 'name'
                            )
                        ),
                        array(
                            'name' => 'DHLDP_DP_ADDITIONAL',
                            'type' => 'text',
                            'label' => $this->l('Additional to address'),
                            'desc' => $this->l('Max. 50 characters'),
                            'size' => 50,
                            'required' => false
                        ),
                    ),

                    'submit' => array(
                        'title' => $this->l('Save options'),
                        'name' => 'submitSaveDPOptions',
                    )
                )
            )
        );

        return $form_fields;
    }

    protected function getFormFieldsDHLSettings()
    {
        $form_fields = array();

        $vcoa_options = array();
        foreach ($this->getVisualCheckOfAgeOptions() as $option_key => $option_value) {
            $vcoa_options[] = array(
                'value' => $option_key,
                'name' => $option_value,
            );
        }

        $this->dhldp_api_rest->setApiVersion(self::getConfig('DHL_API_VERSION'));

        $added_dhl_products = $this->getFormattedAddedDhlProducts(
            Tools::getValue('added_dhl_products', explode(';', self::getConfig('DHL_PRODUCTS')))
        );

        $dimensions = [];
        foreach ($added_dhl_products as $product) {
            if (!empty($product['fullcode'])) {
                $code = str_replace(':', '_', $product['fullcode']);
                $name = $product['name'];
                $dimensions[$code] = [
                    'title' => $name,
                    'weight' => Configuration::get('DHLDP_DHL_' . $code . '_WEIGHT'),
                    'length' => Configuration::get('DHLDP_DHL_' . $code . '_LENGTH'),
                    'width' => Configuration::get('DHLDP_DHL_' . $code . '_WIDTH'),
                    'height' => Configuration::get('DHLDP_DHL_' . $code . '_HEIGHT')
                ];
            }
        }

        $fields = [
            'weight' => ['label' => 'Weight', 'suffix' => 'kg'],
            'length' => ['label' => 'Length', 'suffix' => 'cm'],
            'width' => ['label' => 'Width', 'suffix' => 'cm'],
            'height' => ['label' => 'Height', 'suffix' => 'cm'],
        ];

        $inputs = [];

        foreach ($dimensions as $alias => $product) {
            foreach ($fields as $param => $options) {
                $inputs[] = [
                    'type' => 'text',
                    'name' => 'DHLDP_DHL_' . strtoupper($alias) . '_' . strtoupper($param),
                    'label' => $this->l("Default {$options['label']} for {$alias}"),
                    //'desc'     => $this->l("Used when no {$options['label']} is defined for the order items."),
                    'maxlength' => 8,
                    'suffix' => $this->l($options['suffix']),
                    'class' => 'fixed-width-sm',
                ];
            }
        }

        $form_fields = array_merge(
            $form_fields,
            array(
                'form' => array(
                    'form' => array(
                        'id_form' => 'dhl_global_settings',
                        'legend' => array(
                            'title' => $this->l('Global settings'),
                            'icon' => 'icon-circle',
                        ),
                        'description' => $this->l('Please select mode and fill form with all relevant information regarding authentication in modes.'),
                        'input' => array(
                            array(
                                'name' => 'DHLDP_DHL_MODE',
                                'type' => 'radio',
                                'label' => $this->l('Mode'),
                                'desc' => $this->l('Select "Sandbox" for testing'),
                                'class' => 't',
                                'values' => array(
                                    array(
                                        'id' => 'dhl_mode_live',
                                        'value' => 1,
                                        'label' => $this->l('Live')
                                    ),
                                    array(
                                        'id' => 'dhl_mode_sbx',
                                        'value' => 0,
                                        'label' => $this->l('Sandbox')
                                    ),
                                ),
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LIVE_USER',
                                'type' => 'text',
                                'label' => $this->l('Username'),
                                'desc' => $this->l('"Live" username for user authentication for business customer shipping API'),
                                'required' => true,
                                'form_group_class' => 'dhl_authdata_live'
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LIVE_SIGN',
                                'type' => 'text',
                                'label' => $this->l('Signature'),
                                'desc' => $this->l('"Live" signature for user authentication for business customer shipping API'),
                                'required' => true,
                                'form_group_class' => 'dhl_authdata_live'
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LIVE_EKP',
                                'type' => 'text',
                                'label' => $this->l('EKP'),
                                'desc' => $this->l('"Live" DHL customer number'),
                                'required' => true,
                                'form_group_class' => 'dhl_authdata_live'
                            ),
                            array(
                                'type' => 'free',
                                'label' => '',
                                'name' => 'DHLDP_DHL_LIVE_RESET',
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LOG',
                                'type' => 'radio',
                                'label' => $this->l('Enable Log'),
                                'desc' => $this->l('Logs of actions in') . ' ' . DIRECTORY_SEPARATOR . 'logs ' .
                                    $this->l('directory. Please notice: logs information can take a lot of disk space after a time.'),
                                'class' => 't',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'log_yes',
                                        'value' => 1,
                                        'label' => $this->l('Yes')
                                    ),
                                    array(
                                        'id' => 'log_no',
                                        'value' => 0,
                                        'label' => $this->l('No')
                                    ),
                                ),
                            ),
                            array(
                                'type' => 'free',
                                'name' => 'log_information',
                            ),
                            array(
                                'type' => 'free',
                                'label' => $this->l('Carriers'),
                                'name' => 'carrier_list',
                            ),
                            array(
                                'type' => 'free',
                                'label' => $this->l('New carrier'),
                                'name' => 'add_carrier',
                                'desc' => $this->l('If you do not have a carrier'),
                            ),
                        ),
                        'submit' => array(
                            'title' => $this->l('Save'),
                            'name' => 'submitSaveAuthOptions',
                        )
                    )
                ),
                'form2' => array(
                    'form' => array(
                        'id_form' => 'dhl_products',
                        'legend' => array(
                            'title' => $this->l('DHL products'),
                            'icon' => 'icon-circle',
                        ),
                        'description' => $this->l('Please enter the last two digits (e.g. 01, 02 or similar) of the settlement numbers. You can find them in your account under Contract data overview.'),
                        'input' => array(
                            array(
                                'name' => 'dhl_products',
                                'type' => 'free',
                                'label' => $this->l('DHL Products'),
                                'class' => 't',
                            ),
                            array(
                                'class' => 'fixed-width-xs',
                                'name' => 'DHLDP_DHL_RETURN_PARTICIPATION',
                                'type' => 'text',
                                'label' => $this->l('Participation number for return shipment account number'),
                                'desc' => $this->l('Max. 2 digits. 01 by default.'),
                                'maxlength' => 2
                            ),
                        ),
                        'submit' => array(
                            'title' => $this->l('Save'),
                            'name' => 'submitSaveOptions',
                        )
                    )
                ),
                'form3' => array(
                    'form' => array(
                        'legend' => array(
                            'title' => $this->l('Miscellaneous settings'),
                            'icon' => 'icon-truck'
                        ),
                        'input' => array(
                            array(
                                'type' => 'radio',
                                'label' => $this->l('Reference number in label is '),
                                'name' => 'DHLDP_DHL_REF_NUMBER',
                                'required' => true,
                                'class' => 't',
                                'br' => true,
                                'values' => array(
                                    array(
                                        'id' => 'order_ref',
                                        'value' => 0,
                                        'label' => $this->l('Order reference')
                                    ),
                                    array(
                                        'id' => 'order_number',
                                        'value' => 1,
                                        'label' => $this->l('Order ID')
                                    )
                                )
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_ORDER_WEIGHT',
                                'label' => $this->l('Enable calculating weight of package'),
                                'desc' => $this->l('Enable calculating weight of package in according with weight of products in order'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_ORDER_WEIGHT_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_ORDER_WEIGHT_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'type' => 'text',
                                'name' => 'DHLDP_DHL_WEIGHT_RATE',
                                'label' => sprintf($this->l('Rate of converting shop weight unit in kg. Current shop weight unit is %s'), Configuration::get('PS_WEIGHT_UNIT')),
                                'desc' => $this->l('Rate of converting shop weight unit in kg. If shop weight unit is gramm(g), then rate have to be 0,001 . If shop weight unit is kilogramm(kg), then rate have to be 1(or empty). If rate is empty then weigth will not be recalculated.'),
                            ),
//                            array(
//                                'type'     => 'text',
//                                'name'     => 'DHLDP_DHL_DEFAULT_WEIGHT',
//                                'label'    => $this->l('Default weight of package'),
//                                'desc'     => $this->l('If weight of products in order is not filled(sum of product weights is zero), then this default weight will be used'),
//                                'maxlength' => 8,
//                                'suffix' => $this->l('kg'),
//                                'class' => 'fixed-width-sm'
//                            ),
                            array(
                                'type' => 'text',
                                'name' => 'DHLDP_DHL_PACK_WEIGHT',
                                'label' => $this->l('Weight of pack'),
                                'desc' => $this->l('If weight of products in order is filled(sum of product weights is not zero), then weight of pack will be also applied'),
                                'maxlength' => 8,
                                'suffix' => $this->l('kg'),
                                'class' => 'fixed-width-sm'
                            ),
                            array(
                                'type' => 'free',
                                'label' => $this->l('DHL Product dimensions'),
                                'name' => 'dhl-product-dimensions',
                            ),
//                            array(
//                                'type'     => 'text',
//                                'name'     => 'DHLDP_DHL_DEFAULT_LENGTH',
//                                'label'    => $this->l('Default length of package'),
//                                'maxlength' => 8,
//                                'suffix' => $this->l('cm'),
//                                'class' => 'fixed-width-sm'
//                            ),
//                            array(
//                                'type'     => 'text',
//                                'name'     => 'DHLDP_DHL_DEFAULT_WIDTH',
//                                'label'    => $this->l('Default width of package'),
//                                'maxlength' => 8,
//                                'suffix' => $this->l('cm'),
//                                'class' => 'fixed-width-sm'
//                            ),
//                            array(
//                                'type'     => 'text',
//                                'name'     => 'DHLDP_DHL_DEFAULT_HEIGHT',
//                                'label'    => $this->l('Default height of package'),
//                                'maxlength' => 8,
//                                'suffix' => $this->l('cm'),
//                                'class' => 'fixed-width-sm'
//                            ),
                            array(
                                'type' => 'select',
                                'name' => 'DHLDP_DHL_AGE_CHECK',
                                'label' => $this->l('Select default age for age checking '),
                                'desc' => $this->l('The visual check of age service ensures in an uncomplicated and convenient way that your parcels are not delivered to minors. The service takes care of particular aspects of the protection of minors, e.g., when sending alcoholic drinks, CDs/DVDs with an age limit, PC and console games, or medicines requiring a doctor prescription'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'disabled' => false,
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'value' => '',
                                                'name' => $this->l('-- Do not check --')
                                            ),
                                        ),
                                        $vcoa_options
                                    ),
                                    'id' => 'value',
                                    'name' => 'name'
                                )
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_PFPS',
                                'label' => $this->l('Enable DHL Postfiliales and DHL Packstations'),
                                'desc' => $this->l('Including extension for addresses of customer'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_PFPS_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_PFPS_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_PFPS_MAP',
                                'label' => $this->l('Show google map'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_PFPS_MAP_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_PFPS_MAP_off',
                                        'value' => 0,
                                    )
                                ),
                                'form_group_class' => 'dhl_pfps_map dhlp_new_release'
                            ),
                            array(
                                'type' => 'text',
                                'name' => 'DHLDP_DHL_GOOGLEMAPAPIKEY',
                                'label' => $this->l('Google Map API key'),
                                'desc' => $this->l('Google API key is used for showing map with locations of DHL Postfiliales and DHL Packstations. It is required if you set enabled DHL Postfiliales and DHL Packstations.'),
                                'form_group_class' => 'dhl_googlemapapikey'
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_INTRANSIT_MAIL',
                                'label' => $this->l('Enable sending "Package in transit" mail after generating label'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_INTRANSIT_MAIL_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_INTRANSIT_MAIL_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'type' => 'select',
                                'label' => $this->l('Enable updating order status'),
                                'name' => 'DHLDP_DHL_CHANGE_OS',
                                'desc' => $this->l('Order status will be changed "Shipped" automatically after creating DHL label'),
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'id_order_state' => '',
                                                'name' => $this->l('-- Do not change --')
                                            )
                                        ),
                                        $this->getShippedOrderStates()
                                    ),
                                    'id' => 'id_order_state',
                                    'name' => 'name'
                                )
                            ),
                            /*array(
                                'type'    => 'select',
                                'label'   => $this->l('Enable updating order status, if shipment has been delivered'),
                                'name'    => 'DHLDP_DHL_CHANGE_OS_DELIVERED',
                                'desc'    => sprintf($this->l('Status of shipment will be checked if you get tracking data on DHL by clicking on "Update tracking data" button of package or you can use cron job. For example, this cron job will get tracking data at 1:00am: 0 1 * * * php -f %s'), $this->getLocalPath() . 'cron_track.php secure_key=' . Configuration::get('DHLDP_DHL_SECURE_KEY').' id_shop='.$this->context->shop->id),
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'id_order_state' => '',
                                                'name'           => $this->l('-- Do not change --')
                                            )
                                        ),
                                        $this->getShippedOrderStates()
                                    ),
                                    'id'    => 'id_order_state',
                                    'name'  => 'name'
                                ),
                                'form_group_class' => 'dhlp_new_release',
                                'disabled' => true
                            ),
                            array(
                                'name' => 'DHLDP_DHL_SECURE_KEY',
                                'type' => 'text',
                                'label' => $this->l('Secure key for running cron job task'),
                                'form_group_class' => 'dhlp_new_release'
                            ),*/
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_CREATE_MANIFEST_IN_ORDER',
                                'label' => $this->l('Enable manifest creation in order.'),
                                'desc' => $this->l('If you enable it, the order will have a button to create a manifest in the order.'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_CREATE_MANIFEST_IN_ORDER_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_CREATE_MANIFEST_IN_ORDER_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_CONFIRMATION_PRIVATE',
                                'label' => $this->l('Enable customer confirmation for permission transferring private information to DHL service'),
                                'desc' => $this->l('If you are enable it, then shop will ask customer permission for sending e-mail address and phone number to DHL service in frontend. If you disabled it, then e-mail address and phone number will be sent to DHL service by default.'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_CONFIRMATION_PRIVATE_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_CONFIRMATION_PRIVATE_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'name' => 'DHLDP_DHL_IND_NOTIF',
                                'type' => 'free'
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LABEL_WITH_RETURN',
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'label' => $this->l('Enable generate label with return label'),
                                'desc' => $this->l('This option adds enclosed return label to generated label. Your customers receive a fully prepared return label with their delivery. If they choose to send an item back, all they have to do is pack it and affix the label. Supported products: DHL Paket, DHL Paket Austria, DHL Paket Taggleich, DHL Kurier Taggleich, DHL Karier Wunschzeit'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_LABEL_WITH_RETURN_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_LABEL_WITH_RETURN_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LABEL_IGNORE_WARNING',
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'label' => $this->l('Auto generate label with Warning'),
                                'desc' => $this->l('Enable jobs creation even if a warning appears, if the future is active, create the label immediately without a confirmation step. However, the warning will still be displayed after creation'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_LABEL_IGNORE_WARNING_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_LABEL_IGNORE_WARNING_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LABEL_FORMAT',
                                'type' => 'select',
                                'label' => $this->l('Label format'),
                                'disabled' => false,
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'id' => '',
                                                'name' => $this->l('-- By default --')
                                            )
                                        ),
                                        $this->getAssocArrayOptionsForSelect($this->getLabelFormats(), 'name,desc')
                                    ),
                                    'id' => 'id',
                                    'name' => 'name'
                                )
                            ),
                            array(
                                'name' => 'DHLDP_DHL_RETOURE_LABEL_FORMAT',
                                'type' => 'select',
                                'label' => $this->l('Retoure label format'),
                                'disabled' => false,
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'id' => '',
                                                'name' => $this->l('-- By default --')
                                            )
                                        ),
                                        $this->getAssocArrayOptionsForSelect($this->getRetoureLabelFormats(), 'name,desc')
                                    ),
                                    'id' => 'id',
                                    'name' => 'name'
                                )
                            ),
                            array(
                                'type' => 'text',
                                'label' => $this->l('E-mail address of HP ePrint printer'),
                                'name' => 'DHLDP_DHL_EPRINT_EMAIL',
                                'required' => false,
                                'size' => 35,
                                'desc' => $this->l('Enable sending mail with PDF after generating to HP ePrint printer'),
                                'form_group_class' => 'dhlp_new_release'
                            ),
                        ),
                        'submit' => array(
                            'title' => $this->l('Save'),
                            'name' => 'submitSaveOptions',
                        )
                    )
                )
            )
        );

        $form_fields['form35'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Default settings for additional services'),
                    'icon' => 'icon-truck'
                ),
                'input' => array(
                    array(
                        'name' => 'DHLDP_DHL_DEF_PARCEL_ROUT_SERV',
                        'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                        'label' => $this->l('Enable \'Parcel outlet routing\' service by default'),
                        'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                        'is_bool' => true,
                        'disabled' => false,
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_DEF_PARCEL_ROUT_SERV_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_DEF_PARCEL_ROUT_SERV_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'dhlp_new_release'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_DEF_GOGREEN',
                        'type' => 'switch',
                        'label' => $this->l('GoGreen'),
                        'is_bool' => true,
                        'disabled' => false,
                        'desc' => $this->l('You will make a sustainable contribution towards climate protection by offsetting the CO2 e-emissions generated during the transportation of your items. Extra charge in addition to the price of a parcel.'),
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_DEF_GOGREEN_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_DEF_GOGREEN_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'dhlp_new_release'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_PREMIUM',
                        'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                        'label' => $this->l('choosing premium service for \'Warenpost international\''),
                        'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                        'is_bool' => true,
                        'disabled' => false,
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_PREMIUM_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_PREMIUM_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'dhlp_new_release'
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Invoice number of export document is'),
                        'name' => 'DHLDP_DHL_EXP_INV_NUM',
                        'required' => true,
                        'class' => 't',
                        'br' => true,
                        'values' => array(
                            array(
                                'id' => 'expinvnum_nothing',
                                'value' => 0,
                                'label' => $this->l('Nothing')
                            ),
                            array(
                                'id' => 'expinvnum_orderref',
                                'value' => 1,
                                'label' => $this->l('Order reference')
                            ),
                            array(
                                'id' => 'expinvnum_invoicenumber',
                                'value' => 2,
                                'label' => $this->l('Invoice number')
                            ),
                        ),
                        'form_group_class' => 'dhlp_new_release'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_DEF_CUSTOMS_TARIFF_NUM',
                        'type' => 'text',
                        'label' => $this->l('Default customs tariff number of products'),
                        'form_group_class' => 'dhlp_new_release',
                        'maxlength' => 11
                    ),
                    array(
                        'name' => 'DHLDP_DHL_DEF_PLACE_OF_COMMITAL',
                        'type' => 'text',
                        'label' => $this->l('Default place of committal'),
                        'form_group_class' => 'dhlp_new_release',
                        'maxlength' => 35
                    ),
                    array(
                        'name' => 'DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES',
                        'type' => 'text',
                        'label' => $this->l('Default additional custom fees'),
                        'form_group_class' => 'dhlp_new_release',
                        'desc' => $this->l('Enter the default amount for additional custom fees.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'name' => 'submitSaveOptions',
                )
            )
        );

        $form_fields['form4'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('DHL Retoure settings and additional settings for Merchandise return (RMA)'),
                    'icon' => 'icon-truck'
                ),
                'description' => $this->l('If you enable returns in shop on "Sell/Customer service/Merchandise returns/Merchandise return (RMA) options/Enable returns", then you will possibility to pass Return Labels automatically.'),
                'input' => array(
                    array(
                        'name' => 'DHLDP_DHL_RETURNS_EXTEND',
                        'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                        'label' => $this->l('Enable extending management of returns in shop'),
                        'desc' => $this->l('Enable sending Return label on return request of customer'),
                        'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                        'is_bool' => true,
                        'disabled' => !Configuration::get('PS_ORDER_RETURN'),
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_RETURNS_EXTEND_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_RETURNS_EXTEND_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'dhl_returns_extend',
                    ),
                    array(
                        'name' => 'DHLDP_DHL_RETURNS_IMMED',
                        'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                        'label' => $this->l('Enable sending Return Label immediately'),
                        'desc' => $this->l('Enable sending Return label on return request of customer immediately without approving by shop administrator'),
                        'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                        'is_bool' => true,
                        'disabled' => !Configuration::get('PS_ORDER_RETURN'),
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_RETURNS_IMMED_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_RETURNS_IMMED_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'hide dhldp_dhl_ra',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Countries'),
                        'name' => 'DHLDP_DHL_RA_COUNTRIES',
                        'desc' => $this->l('Enable creating Return label for countries of sender address'),
                        'required' => true,
                        'multiple' => true,
                        'form_group_class' => 'hide dhldp_dhl_ra',
                        'class' => 'select2',
                        'options' => array(
                            'query' => $this->getCountriesForRA($this->context->language->id),
                            'id' => 'iso_code',
                            'name' => 'name'
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'name' => 'submitSaveOptions',
                )
            )
        );


        $form_fields['form5'] = array(
            'form' => array(
                'id_form' => 'dhl_address',
                'legend' => array(
                    'title' => $this->l('Address'),
                    'icon' => 'icon-circle',
                ),
                'description' => $this->l('Please enter address of shop'),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Shipper address'),
                        'name' => 'DHLDP_DHL_SHIPPER_TYPE',
                        'desc' => $this->l('You have possibility enter shipper address or use shipper reference (valid shipper reference from your GKP) to use address from GKP. 
                        If you will use shipper reference, then you will get possibility to set company logo from GKP on shipment label.'),
                        'required' => true,
                        'options' => array(
                            'query' => array(
                                array('code' => 0, 'name' => $this->l('Fill shipper address')),
                                array('code' => 1, 'name' => $this->l('Get shipper address from GKP by reference'))
                            ),
                            'id' => 'code',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'name' => 'DHLDP_DHL_COMPANY_NAME_1',
                        'type' => 'text',
                        'label' => $this->l('Company'),
                        'desc' => $this->l('Max. 35 characters'),
                        'required' => true,
                        'maxlength' => 35,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_COMPANY_NAME_2',
                        'type' => 'text',
                        'label' => $this->l('Company 2'),
                        'desc' => $this->l('Max. 35 characters'),
                        'required' => true,
                        'maxlength' => 35,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_CONTACT_PERSON',
                        'type' => 'text',
                        'label' => $this->l('Contact person'),
                        'desc' => $this->l('Max. 50 characters'),
                        'required' => false,
                        'maxlength' => 50,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_STREET_NAME',
                        'type' => 'text',
                        'label' => $this->l('Street'),
                        'desc' => $this->l('Max. 35 characters'),
                        'required' => true,
                        'maxlength' => 35,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_STREET_NUMBER',
                        'type' => 'text',
                        'label' => $this->l('House number'),
                        'desc' => $this->l('Max. 5 characters'),
                        'required' => true,
                        'maxlength' => 5,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_ZIP',
                        'type' => 'text',
                        'label' => $this->l('Postcode'),
                        'desc' => $this->l('Max. 10 characters'),
                        'required' => true,
                        'maxlength' => 10,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_CITY',
                        'type' => 'text',
                        'label' => $this->l('City'),
                        'desc' => $this->l('Max. 35 characters'),
                        'required' => true,
                        'maxlength' => 35,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_COUNTRY',
                        'type' => 'free',
                        'label' => $this->l('Country'),
                        'disabled' => true,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_STATE',
                        'type' => 'text',
                        'label' => $this->l('State'),
                        'desc' => $this->l('Max. 30 characters'),
                        'required' => false,
                        'maxlength' => 30,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_PHONE',
                        'type' => 'text',
                        'label' => $this->l('Phone'),
                        'desc' => $this->l('Max. 20 characters'),
                        'required' => true,
                        'maxlength' => 20,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_EMAIL',
                        'type' => 'text',
                        'label' => $this->l('E-mail'),
                        'desc' => $this->l('Max. 70 characters'),
                        'required' => true,
                        'maxlength' => 70,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_REFERENCE',
                        'type' => 'text',
                        'label' => $this->l('Shipper reference'),
                        'desc' => $this->l('Max. 50 characters. Contains a reference to the Shipper data configured in GKP.'),
                        'required' => true,
                        'maxlength' => 50,
                        'form_group_class' => 'dhl_shipper_by_reference'
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'name' => 'submitSaveOptions',
                )
            )
        );

        $form_fields['form6'] = array(
            'form' => array(
                'id_form' => 'dhl_bankdata',
                'legend' => array(
                    'title' => $this->l('Bank data'),
                    'icon' => 'icon-circle',
                ),
                'description' => $this->l('Bank data can be provided here for different purposes. E.g. if COD is booked as service, bank data must be provided by DHL customer (mandatory server logic). The collected money will be transferred to specified bank account.'),
                'input' => array(
                    array(
                        'name' => 'DHLDP_DHL_ACCOUNT_OWNER',
                        'type' => 'text',
                        'label' => $this->l('Account owner'),
                        'desc' => $this->l('Max. 30 characters'),
                        'required' => false,
                        'maxlength' => 30,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_BANK_NAME',
                        'type' => 'text',
                        'label' => $this->l('Bank name'),
                        'desc' => $this->l('Max. 30 characters'),
                        'required' => false,
                        'maxlength' => 30,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_IBAN',
                        'type' => 'text',
                        'label' => $this->l('IBAN'),
                        'desc' => $this->l('Max. 34 characters'),
                        'required' => false,
                        'maxlength' => 34,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_BIC',
                        'type' => 'text',
                        'label' => $this->l('BIC'),
                        'desc' => $this->l('Max. 11 characters'),
                        'required' => false,
                        'maxlength' => 11,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_NOTE',
                        'type' => 'text',
                        'label' => $this->l('Note'),
                        'desc' => $this->l('Max. 35 characters. Use [order_reference_number] in note for adding Order ID or Order Reference in according with "Reference number in label is" setting. Example, "Bestellnummer [order_reference_number]" will add "Bestellnummer KHWLILZLL" in note.'),
                        'required' => false,
                        'maxlength' => 35,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_NOTE2',
                        'type' => 'text',
                        'label' => $this->l('Note 2'),
                        'desc' => $this->l('Max. 35 characters. Use [order_reference_number] in note for adding Order ID or Order Reference in according with "Reference number in label is" setting. Example, "Bestellnummer [order_reference_number]" will add "Bestellnummer KHWLILZLL" in note.'),
                        'required' => false,
                        'maxlength' => 35,
                        'form_group_class' => 'dhlp_new_release'
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'name' => 'submitSaveOptions',
                )
            )
        );

        return $form_fields;
    }

    public function postInitDHLProcess()
    {
        if (Tools::isSubmit('submitSaveOptions')) {
            $form_errors = array();

            if (!in_array(Tools::getValue('DHLDP_DHL_COUNTRY'), array_keys(DHLDPApi::$supported_shipper_countries))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select supported country');
            }
            if (!in_array(Tools::getValue('DHLDP_DHL_API_VERSION'), DHLDPApi::$supported_shipper_countries[Tools::getValue('DHLDP_DHL_COUNTRY')]['api_versions'])) {
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

        if (Tools::getIsset('log_file')) {
            if (in_array(Tools::getValue('log_file'), array('dhl_general', 'dhl_api', 'dhl_api_clear', 'dp_general', 'dp_api', 'dp_api_clear'))) {
                $key = Tools::getValue('log_file');
                $file_key = strpos($key, '_clear') !== false ? str_replace('_clear', '', $key) : $key;
                $file_path = dirname(__FILE__) . '/logs/log_' . $file_key . '.txt';
                if (strpos($key, '_clear') !== false && file_exists($file_path)) {
                    file_put_contents($file_path, '');
                    Tools::redirectAdmin($this->getModuleUrl() . '&view=' . Tools::getValue('view') . '&m=3');
                }
                if (file_exists($file_path)) {
                    header('Content-type: text/plain');
                    header('Content-Disposition: attachment; filename=' . $key . '.txt');
                    echo Tools::file_get_contents($file_path);
                    exit;
                }
            }
            Tools::redirectAdmin($this->getModuleUrl() . '&view=' . Tools::getValue('view') . '&m=2');
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

        /*
        if (Tools::isSubmit('submitDPUpdatePPL')) {
            if ($this->dp_api->updatePPL()) {
                $this->_confirmations[] = $this->l('PPL has been updated successfully');
            } else {
                $this->_errors[] = $this->l('PPL update is failed');
            }
        }
        */

        if (Tools::isSubmit('submitDPGetProductList')) {
            if ($this->dp_api->getProductList()) {
                $this->_confirmations[] = $this->l('Product list has been updated successfully');
            } else {
                $this->_errors[] = $this->l('Product list updating has been failed');
            }

            if ($this->dp_api->retrieveContractProducts()) {
                $this->_confirmations[] = $this->l('Contract product list has been updated successfully');
            } else {
                $this->_errors[] = $this->l('Contract product list updating has been failed');
            }
        }

        if (Tools::isSubmit('submitDPRetrievePageFormats')) {
            if ($this->dp_api->retrievePageFormats()) {
                $this->_confirmations[] = $this->l('Page formats has been retrieved successfully');
            } else {
                $this->_errors[] = $this->l('Page formats retrieving is failed');
            }
        }

        if (Tools::isSubmit('submitSaveDPOptions')) {
            $form_errors = array();

            //$deutschepost_mode = Tools::getValue('DHLDP_DP_MODE');
            $deutschepost_mode = 1;
            $deutschepost_live_username = Tools::getValue('DHLDP_DP_LIVE_USERNAME');
            $deutschepost_live_password = Tools::getValue('DHLDP_DP_LIVE_PASSWORD');
            $deutschepost_sbx_username = Tools::getValue('DHLDP_DP_SBX_USERNAME');
            $deutschepost_sbx_password = Tools::getValue('DHLDP_DP_SBX_PASSWORD');

            $deutschepost_carriers = Tools::getValue('deutschepost_carriers', array());

            $deutschepost_log = Tools::getValue('DHLDP_DP_LOG');

            if (!in_array($deutschepost_mode, array('0', '1'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select mode');
            }

            if (($deutschepost_mode == '0' && $deutschepost_sbx_username == '') ||
                ($deutschepost_mode == '1' && $deutschepost_live_username == '')
            ) {
                $form_errors[] = $this->_errors[] = $this->l('Please fill user name');
            }

            if (($deutschepost_mode == '0' && $deutschepost_sbx_password == '') ||
                ($deutschepost_mode == '1' && $deutschepost_live_password == '')
            ) {
                $form_errors[] = $this->_errors[] = $this->l('Please fill password');
            }

            if (count($form_errors) == 0) {

                $check_client = $this->dp_api->authenticateUser(
                    $deutschepost_mode,
                    DPApi::$partnerid,
                    DPApi::$keyphase,
                    DPApi::$apikey,
                    ($deutschepost_mode == 1) ? $deutschepost_live_username : $deutschepost_sbx_username,
                    ($deutschepost_mode == 1) ? $deutschepost_live_password : $deutschepost_sbx_password
                );

                if (Tools::getValue('DHLDP_DP_LIVE_PASSWORD') != str_repeat('*', mb_strlen(Configuration::get('DHLDP_DP_LIVE_PASSWORD')))) {
                    if (!is_object($check_client) || (!isset($check_client->userToken))) {
                        $form_errors[] = $this->_errors[] = $this->l('Authentication data is incorrect(' . implode(', ', $this->dp_api->errors) . ').');
                        self::logToFile(implode(', ', $this->dp_api->errors), 'general');
                    } else {
                        Configuration::updateValue('DHLDP_DP_LIVE_PASSWORD', Tools::getValue('DHLDP_DP_LIVE_PASSWORD'));
                    }
                }
            }

            if (!in_array($deutschepost_log, array('0', '1'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select log mode');
            }

            if (!in_array(Tools::getValue('DHLDP_DP_CREATE_MANIFEST'), array('0', '1'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select value for Enable creating manifest');
            }
            if (!in_array(Tools::getValue('DHLDP_DP_CREATE_SHIPLIST'), array('0', '1', '2'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select value for Enable creating shipping list');
            }
            if (!in_array(Tools::getValue('DHLDP_DP_LABEL_FORMAT'), array('png', 'pdf'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select format of label file');
            }
            $page_formats = json_decode(Configuration::getGlobalValue('DHLDP_DP_PAGE_FORMATS'), true);
            if (is_array($page_formats)) {
                $page_formats_keys = array_keys($page_formats, true);
            } else {
                $page_formats_keys = array();
            }
            if (count($page_formats_keys) == 0) {
                $page_formats_keys = array(1);
            }
            $page_format_id = (Tools::getValue('DHLDP_DP_PAGE_FORMAT', 1)) == '' ? 1 : Tools::getValue('DHLDP_DP_PAGE_FORMAT', 1);

            if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && !in_array($page_format_id, $page_formats_keys)) {
                $form_errors[] = $this->_errors[] = $this->l('Please select valid page format for PDF document');
            }
            if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_PAGE', 1) < 1)) {
                $form_errors[] = $this->_errors[] = $this->l('Please specify page by positive integer value');
            }
            if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_ROW', 1) < 1)) {
                $form_errors[] = $this->_errors[] = $this->l('Please specify row on page by positive integer value');
            }
            if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_COL', 1) < 1)) {
                $form_errors[] = $this->_errors[] = $this->l('Please specify column on page by positive integer value');
            }
            if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf') {
                $page_format = $page_formats[$page_format_id];
                if ((int)Tools::getValue('DHLDP_DP_POSITION_COL', 1) > $page_format['col']) {
                    $form_errors[] = $this->_errors[] = $this->l('Column of Label position must be maximum') . ' ' . $page_format['col'];
                }
                if ((int)Tools::getValue('DHLDP_DP_POSITION_ROW', 1) > $page_format['row']) {
                    $form_errors[] = $this->_errors[] = $this->l('Row of Label position must be maximum') . ' ' . $page_format['row'];
                }
            }

            if (!in_array((int)Tools::getValue('DHLDP_DP_NAME', 0), array('0', '1'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select company or person in address');
            }

            if (Tools::getValue('DHLDP_DP_NAME') == 1 && Tools::strlen(Tools::getValue('DHLDP_DP_COMPANY')) > 50) {
                $form_errors[] = $this->_errors[] = $this->l('The company name is too long');
            }
            if (Tools::getValue('DHLDP_DP_NAME') == 1 && Tools::strlen(Tools::getValue('DHLDP_DP_COMPANY')) == 0) {
                $form_errors[] = $this->_errors[] = $this->l('The company name is required');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_SALUTATION')) > 10) {
                $form_errors[] = $this->_errors[] = $this->l('The salutation is too long');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_TITLE')) > 10) {
                $form_errors[] = $this->_errors[] = $this->l('The title is too long');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_FIRSTNAME')) > 30) {
                $form_errors[] = $this->_errors[] = $this->l('The firstname is too long');
            }
            if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_FIRSTNAME')) == 0) {
                $form_errors[] = $this->_errors[] = $this->l('The firstname name is required');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_LASTNAME')) > 30) {
                $form_errors[] = $this->_errors[] = $this->l('The lastname is too long');
            }
            if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_LASTNAME')) == 0) {
                $form_errors[] = $this->_errors[] = $this->l('The lastname name is required');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_STREET')) > 50) {
                $form_errors[] = $this->_errors[] = $this->l('The street is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DP_STREET')) == 0) {
                $form_errors[] = $this->_errors[] = $this->l('The street is required');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_HOUSENO')) > 10) {
                $form_errors[] = $this->_errors[] = $this->l('The house number is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DP_HOUSENO')) == 0) {
                $form_errors[] = $this->_errors[] = $this->l('The house number is required');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_ZIP')) > 10) {
                $form_errors[] = $this->_errors[] = $this->l('The postcode is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DP_ZIP')) == 0) {
                $form_errors[] = $this->_errors[] = $this->l('The postcode is required');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_CITY')) > 35) {
                $form_errors[] = $this->_errors[] = $this->l('The city is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DP_CITY')) == 0) {
                $form_errors[] = $this->_errors[] = $this->l('The city is required');
            }

            if ((int)Tools::getValue('DHLDP_DP_COUNTRY') == 0) {
                $form_errors[] = $this->_errors[] = $this->l('The country is required');
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DP_ADDITIONAL')) > 50) {
                $form_errors[] = $this->_errors[] = $this->l('The additional address is too long');
            }

            if (count($form_errors) == 0) {
                $result_save = Configuration::updateValue('DHLDP_DP_MODE', (int)$deutschepost_mode) &&
                    Configuration::updateValue('DHLDP_DP_LIVE_USERNAME', Tools::getValue('DHLDP_DP_LIVE_USERNAME')) &&
//                    Configuration::updateValue('DHLDP_DP_LIVE_PASSWORD', Tools::getValue('DHLDP_DP_LIVE_PASSWORD')) &&
                    /*Configuration::updateValue('DHLDP_DP_SBX_USERNAME', Tools::getValue('DHLDP_DP_SBX_USERNAME')) &&
                    Configuration::updateValue('DHLDP_DP_SBX_PASSWORD', Tools::getValue('DHLDP_DP_SBX_PASSWORD')) &&*/
                    Configuration::updateValue('DHLDP_DP_LOG', (int)Tools::getValue('DHLDP_DP_LOG', 0)) &&
                    Configuration::updateValue('DHLDP_DP_CARRIERS', implode(',', $deutschepost_carriers)) &&
                    Configuration::updateValue('DHLDP_DP_REF_NUMBER', (int)Tools::getValue('DHLDP_DP_REF_NUMBER')) &&
                    Configuration::updateValue('DHLDP_DP_DEF_PRODUCT', (int)Tools::getValue('DHLDP_DP_DEF_PRODUCT', 0)) &&
                    Configuration::updateValue('DHLDP_DP_CHANGE_OS', (int)Tools::getValue('DHLDP_DP_CHANGE_OS', 0)) &&
                    Configuration::updateValue('DHLDP_DP_CREATE_MANIFEST', (int)Tools::getValue('DHLDP_DP_CREATE_MANIFEST')) &&
                    Configuration::updateValue('DHLDP_DP_CREATE_SHIPLIST', (int)Tools::getValue('DHLDP_DP_CREATE_SHIPLIST')) &&
                    Configuration::updateValue('DHLDP_DP_LABEL_FORMAT', Tools::getValue('DHLDP_DP_LABEL_FORMAT')) &&
                    Configuration::updateValue('DHLDP_DP_PAGE_FORMAT', (int)$page_format_id) &&
                    Configuration::updateValue('DHLDP_DP_POSITION_PAGE', (int)Tools::getValue('DHLDP_DP_POSITION_PAGE', 1)) &&
                    Configuration::updateValue('DHLDP_DP_POSITION_ROW', (int)Tools::getValue('DHLDP_DP_POSITION_ROW', 1)) &&
                    Configuration::updateValue('DHLDP_DP_POSITION_COL', (int)Tools::getValue('DHLDP_DP_POSITION_COL', 1)) &&

                    Configuration::updateValue('DHLDP_DP_NAME', (int)Tools::getValue('DHLDP_DP_NAME', 0)) &&
                    Configuration::updateValue('DHLDP_DP_COMPANY', Tools::getValue('DHLDP_DP_COMPANY', 0)) &&
                    Configuration::updateValue('DHLDP_DP_SALUTATION', Tools::getValue('DHLDP_DP_SALUTATION', 0)) &&
                    Configuration::updateValue('DHLDP_DP_TITLE', Tools::getValue('DHLDP_DP_TITLE', 0)) &&
                    Configuration::updateValue('DHLDP_DP_FIRSTNAME', Tools::getValue('DHLDP_DP_FIRSTNAME', 0)) &&
                    Configuration::updateValue('DHLDP_DP_LASTNAME', Tools::getValue('DHLDP_DP_LASTNAME', 0)) &&
                    Configuration::updateValue('DHLDP_DP_STREET', Tools::getValue('DHLDP_DP_STREET', 0)) &&
                    Configuration::updateValue('DHLDP_DP_HOUSENO', Tools::getValue('DHLDP_DP_HOUSENO', 0)) &&
                    Configuration::updateValue('DHLDP_DP_ZIP', Tools::getValue('DHLDP_DP_ZIP', 0)) &&
                    Configuration::updateValue('DHLDP_DP_CITY', Tools::getValue('DHLDP_DP_CITY', 0)) &&
                    Configuration::updateValue('DHLDP_DP_COUNTRY', (int)Tools::getValue('DHLDP_DP_COUNTRY', 0)) &&
                    Configuration::updateValue('DHLDP_DP_ADDITIONAL', Tools::getValue('DHLDP_DP_ADDITIONAL', 0));
                if ($result_save == true) {
                    $this->_confirmations[] = $this->l('Settings updated');
                }
            }
        }

        if (Tools::isSubmit('submitAddDHLDP_dhl_configure')) {
            Configuration::updateValue('DHLDP_DHL_API_VERSION', '2.1');
            Configuration::updateValue('DHLDP_DHL_COUNTRY', 'DE');

            $form_errors = array();

            if (Tools::isSubmit('submitSaveAuthOptions')) {
                $dhl_mode = Tools::getValue('DHLDP_DHL_MODE');
                $dhl_live_user = Tools::getValue('DHLDP_DHL_LIVE_USER');
                $dhl_live_sign = Tools::getValue('DHLDP_DHL_LIVE_SIGN');
                $dhl_live_ekp = Tools::getValue('DHLDP_DHL_LIVE_EKP');
                $dhl_log = Tools::getValue('DHLDP_DHL_LOG');
                $dhl_carriers = array();
                foreach (Tools::getValue('dhl_carriers', array()) as $value) {
                    if (isset($value['carrier']) && isset($value['product'])) {
                        $dhl_carriers[] = $value['carrier'] . '|' . $value['product'];
                    }
                }
                if (!in_array($dhl_mode, array('0', '1'))) {
                    $form_errors[] = $this->_errors[] = $this->l('Please select mode of DHL');
                }

                if ($dhl_mode == '1' && $dhl_live_user == '') {
                    $form_errors[] = $this->_errors[] = $this->l('Please fill username');
                }

                if ($dhl_mode == '1' && $dhl_live_sign == '') {
                    $form_errors[] = $this->_errors[] = $this->l('Please fill signature');
                }

                if (($dhl_mode == '1' && Tools::strlen($dhl_live_ekp) > 10)) {
                    $form_errors[] = $this->_errors[] = $this->l('EKP is too long');
                }

                if ($dhl_mode == '1' && $dhl_live_ekp == '') {
                    $form_errors[] = $this->_errors[] = $this->l('Please fill EKP');
                }
                if (!in_array($dhl_log, array('0', '1'))) {
                    $form_errors[] = $this->_errors[] = $this->l('Please select log mode');
                }
                if (count($form_errors) == 0) {
                    $check_client = $this->dhldp_api_rest->checkDHLAccount($dhl_mode);
                    if ($check_client) {
                        Configuration::updateValue('DHLDP_DHL_LIVE_USER', $dhl_live_user);
                        if (substr_count($dhl_live_sign, '*') != strlen($dhl_live_sign)) {
                            Configuration::updateValue('DHLDP_DHL_LIVE_SIGN', $dhl_live_sign);
                        }
                        Configuration::updateValue('DHLDP_DHL_MODE', (int)$dhl_mode);
                        Configuration::updateValue('DHLDP_DHL_CARRIERS', implode(',', $dhl_carriers));
                        Configuration::updateValue('DHLDP_DHL_LIVE_EKP', $dhl_live_ekp);
                    } else {
                        $form_errors[] = $this->_errors[] = implode(', ', $this->dhldp_api_rest->errors);
                        self::logToFile('DHL', implode(', ', $this->dhldp_api_rest->errors), 'general');
                    }
                }
            }

            $added_dhl_products = Tools::getValue('added_dhl_products', array());

            if (count($form_errors) == 0) {
                if (in_array(Configuration::get('DHLDP_DHL_API_VERSION'), DHLDPApiRest::getSupportedApiVersions())) {
                    $this->dhldp_api_rest->setApiVersion(Configuration::get('DHLDP_DHL_API_VERSION'));
                }
            }

            if (!in_array((int)Tools::getValue('DHLDP_DHL_REF_NUMBER'), array('0', '1'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select Reference number');
            }

            if (Tools::getValue('DHLDP_DHL_RETURN_PARTICIPATION') != '' && !preg_match('/^[0-9]{2}$/', Tools::getValue('DHLDP_DHL_RETURN_PARTICIPATION'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please enter 2 digits participation number for return shipment account number');
            }

            if (!in_array((int)Tools::getValue('DHLDP_DHL_ORDER_WEIGHT'), array('0', '1'))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select Enable calculating weigth of order');
            }

            if (trim(Tools::getValue('DHLDP_DHL_WEIGHT_RATE')) != '' && !preg_match('/^[0-9]{1,10}([,.]{1}[0-9]{1,9})?$/', trim(Tools::getValue('DHLDP_DHL_WEIGHT_RATE')))) {
                $form_errors[] = $this->_errors[] = $this->l('Please enter correct weight rate for converting into kg');
            }

            if (Tools::getValue('DHLDP_DHL_PFPS') == 1 && Tools::getValue('DHLDP_DHL_PFPS_MAP') == 1 && Tools::getValue('DHLDP_DHL_GOOGLEMAPAPIKEY') == '') {
                $form_errors[] = $this->_errors[] = $this->l('Please enter Google Map API key');
            }

            if ((int)Tools::getValue('DHLDP_DHL_RETURNS_EXTEND') == 1 && !count(Tools::getValue('DHLDP_DHL_RA_COUNTRIES', array()))) {
                $form_errors[] = $this->_errors[] = $this->l('Please select countries for Return label');
            }
            if (!in_array((int)Tools::getValue('DHLDP_DHL_EXP_INV_NUM'), array(0, 1, 2))) {
                $form_errors[] = $this->_errors[] = $this->l('Invalid value of "Invoice number of export document is"');
            }
            if ((Tools::getValue('DHLDP_DHL_DEF_CUSTOMS_TARIFF_NUM') != '') && !$this->isValidCustomsTariffNumber(Tools::getValue('DHLDP_DHL_DEF_CUSTOMS_TARIFF_NUM'))) {
                $form_errors[] = $this->_errors[] = $this->l('Invalid value of Customs tariff number');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DHL_DEF_PLACE_OF_COMMITAL')) > 35) {
                $form_errors[] = $this->_errors[] = $this->l('The company name is too long');
            }
            $fee = Tools::getValue('DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES');
            if (!Validate::isPrice($fee)) {
                $form_errors[] = $this->_errors[] = $this->l('Invalid amount for additional custom fees.');
            }
            if (Tools::getValue('DHLDP_DHL_LABEL_FORMAT') != '' && !in_array(Tools::getValue('DHLDP_DHL_LABEL_FORMAT'), array_keys($this->getLabelFormats()))) {
                $form_errors[] = $this->_errors[] = $this->l('Invalid label format');
            }

            if (Tools::getValue('DHLDP_DHL_RETOURE_LABEL_FORMAT') != '' && !in_array(Tools::getValue('DHLDP_DHL_RETOURE_LABEL_FORMAT'), array_keys($this->getRetoureLabelFormats()))) {
                $form_errors[] = $this->_errors[] = $this->l('Invalid retoure label format');
            }

            if (!Tools::isEmpty(Tools::getValue('DHLDP_DHL_EPRINT_EMAIL')) && !Validate::isEmail(Tools::getValue('DHLDP_DHL_EPRINT_EMAIL'))) {
                $form_errors[] = $this->_errors[] = $this->l('"E-mail address of HP ePrint printer" is invalid');
            }

            if (Tools::getValue('DHLDP_DHL_SHIPPER_TYPE') == 0) {
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_COMPANY_NAME_1')) == 0) {
                    $form_errors[] = $this->_errors[] = $this->l('The company name is required');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_COMPANY_NAME_1')) > 35) {
                    $form_errors[] = $this->_errors[] = $this->l('The company name is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_COMPANY_NAME_2')) == 0) {
                    $form_errors[] = $this->_errors[] = $this->l('The company name 2 is required');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_COMPANY_NAME_2')) > 35) {
                    $form_errors[] = $this->_errors[] = $this->l('The company name 2 is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_CONTACT_PERSON')) > 50) {
                    $form_errors[] = $this->_errors[] = $this->l('The Contact person is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_STREET_NAME')) == 0) {
                    $form_errors[] = $this->_errors[] = $this->l('The Street is required');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_STREET_NAME')) > 35) {
                    $form_errors[] = $this->_errors[] = $this->l('The Street is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_STREET_NUMBER')) == 0) {
                    $form_errors[] = $this->_errors[] = $this->l('The House number is required');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_STREET_NUMBER')) > 5) {
                    $form_errors[] = $this->_errors[] = $this->l('The House number is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_ZIP')) == 0) {
                    $form_errors[] = $this->_errors[] = $this->l('The Postcode is required');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_ZIP')) > 10) {
                    $form_errors[] = $this->_errors[] = $this->l('The Postcode is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_CITY')) == 0) {
                    $form_errors[] = $this->_errors[] = $this->l('The City is required');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_CITY')) > 35) {
                    $form_errors[] = $this->_errors[] = $this->l('The City is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_STATE')) > 30) {
                    $form_errors[] = $this->_errors[] = $this->l('The State is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_PHONE')) == 0) {
                    $form_errors[] = $this->_errors[] = $this->l('The Phone is required');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_PHONE')) > 20) {
                    $form_errors[] = $this->_errors[] = $this->l('The Phone is too long');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_EMAIL')) == 0) {
                    $form_errors[] = $this->_errors[] = $this->l('The E-mail is required');
                }
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_EMAIL')) > 70) {
                    $form_errors[] = $this->_errors[] = $this->l('The E-mail is too long');
                }
            } else {
                if (Tools::strlen(Tools::getValue('DHLDP_DHL_REFERENCE')) > 50) {
                    $form_errors[] = $this->_errors[] = $this->l('The Shipper reference is too long');
                }
            }

            if (Tools::strlen(Tools::getValue('DHLDP_DHL_ACCOUNT_OWNER')) > 30) {
                $form_errors[] = $this->_errors[] = $this->l('The Account owner is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DHL_BANK_NAME')) > 30) {
                $form_errors[] = $this->_errors[] = $this->l('The Account number is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DHL_IBAN')) > 34) {
                $form_errors[] = $this->_errors[] = $this->l('The IBAN is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DHL_BIC')) > 34) {
                $form_errors[] = $this->_errors[] = $this->l('The BIC is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DHL_NOTE')) > 35) {
                $form_errors[] = $this->_errors[] = $this->l('The Note is too long');
            }
            if (Tools::strlen(Tools::getValue('DHLDP_DHL_NOTE2')) > 35) {
                $form_errors[] = $this->_errors[] = $this->l('The Note 2 is too long');
            }

            if (count($form_errors) == 0) {
                $result_save =
                    Configuration::updateValue('DHLDP_DHL_RETURN_PARTICIPATION', Tools::getValue('DHLDP_DHL_RETURN_PARTICIPATION')) &&
                    Configuration::updateValue('DHLDP_DHL_PRODUCTS', implode(';', $added_dhl_products)) &&
                    Configuration::updateValue('DHLDP_DHL_REF_NUMBER', (int)Tools::getValue('DHLDP_DHL_REF_NUMBER')) &&
                    Configuration::updateValue('DHLDP_DHL_ORDER_WEIGHT', (int)Tools::getValue('DHLDP_DHL_ORDER_WEIGHT', 0)) &&
                    Configuration::updateValue('DHLDP_DHL_WEIGHT_RATE', str_replace(',', '.', Tools::getValue('DHLDP_DHL_WEIGHT_RATE', ''))) &&
//                    Configuration::updateValue('DHLDP_DHL_DEFAULT_WEIGHT', str_replace(',', '.', Tools::getValue('DHLDP_DHL_DEFAULT_WEIGHT', ''))) &&
                    Configuration::updateValue('DHLDP_DHL_PACK_WEIGHT', str_replace(',', '.', Tools::getValue('DHLDP_DHL_PACK_WEIGHT', ''))) &&
                    Configuration::updateValue('DHLDP_DHL_DEFAULT_LENGTH', (int)Tools::getValue('DHLDP_DHL_DEFAULT_LENGTH', '')) &&
                    Configuration::updateValue('DHLDP_DHL_DEFAULT_WIDTH', (int)Tools::getValue('DHLDP_DHL_DEFAULT_WIDTH', '')) &&
                    Configuration::updateValue('DHLDP_DHL_DEFAULT_HEIGHT', (int)Tools::getValue('DHLDP_DHL_DEFAULT_HEIGHT', '')) &&
                    Configuration::updateValue('DHLDP_DHL_AGE_CHECK', Tools::getValue('DHLDP_DHL_AGE_CHECK', 0)) &&
                    Configuration::updateValue('DHLDP_DHL_PREMIUM', Tools::getValue('DHLDP_DHL_PREMIUM', 0)) &&
                    Configuration::updateValue('DHLDP_DHL_PFPS', (int)Tools::getValue('DHLDP_DHL_PFPS')) &&
                    Configuration::updateValue('DHLDP_DHL_PFPS_MAP', (Tools::getValue('DHLDP_DHL_PFPS_MAP') == 1) ? 1 : 0) &&
                    Configuration::updateValue('DHLDP_DHL_GOOGLEMAPAPIKEY', Tools::getValue('DHLDP_DHL_GOOGLEMAPAPIKEY', '')) &&
                    Configuration::updateValue('DHLDP_DHL_CHANGE_OS', (int)Tools::getValue('DHLDP_DHL_CHANGE_OS', 0)) &&
                    Configuration::updateValue('DHLDP_DHL_CREATE_MANIFEST_IN_ORDER', (int)Tools::getValue('DHLDP_DHL_CREATE_MANIFEST_IN_ORDER', (int)self::getConfig('DHL_CREATE_MANIFEST_IN_ORDER'))) &&
                    Configuration::updateValue('DHLDP_DHL_INTRANSIT_MAIL', (int)Tools::getValue('DHLDP_DHL_INTRANSIT_MAIL', (int)self::getConfig('DHL_INTRANSIT_MAIL'))) &&
                    Configuration::updateValue('DHLDP_DHL_CONFIRMATION_PRIVATE', (int)Tools::getValue('DHLDP_DHL_CONFIRMATION_PRIVATE', (int)self::getConfig('DHL_CONFIRMATION_PRIVATE'))) &&
                    Configuration::updateValue('DHLDP_DHL_RETURN_MAIL', (int)Tools::getValue('DHLDP_DHL_RETURN_MAIL', (int)self::getConfig('DHL_RETURN_MAIL'))) &&
                    Configuration::updateValue('DHLDP_DHL_LABEL_WITH_RETURN', (int)Tools::getValue('DHLDP_DHL_LABEL_WITH_RETURN', (int)self::getConfig('DHL_LABEL_WITH_RETURN'))) &&
                    Configuration::updateValue('DHLDP_DHL_LABEL_IGNORE_WARNING', (int)Tools::getValue('DHLDP_DHL_LABEL_IGNORE_WARNING', (int)self::getConfig('DHLDP_DHL_LABEL_IGNORE_WARNING'))) &&
                    Configuration::updateValue('DHLDP_DHL_EXP_INV_NUM', (int)Tools::getValue('DHLDP_DHL_EXP_INV_NUM', self::getConfig('DHL_EXP_INV_NUM'))) &&
                    Configuration::updateValue('DHLDP_DHL_DEF_CUSTOMS_TARIFF_NUM', Tools::getValue('DHLDP_DHL_DEF_CUSTOMS_TARIFF_NUM', self::getConfig('DHL_DEF_CUSTOMS_TARIFF_NUM'))) &&
                    Configuration::updateValue('DHLDP_DHL_DEF_PLACE_OF_COMMITAL', Tools::getValue('DHLDP_DHL_DEF_PLACE_OF_COMMITAL', self::getConfig('DHLDP_DHL_DEF_PLACE_OF_COMMITAL'))) &&
                    Configuration::updateValue('DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES', Tools::getValue('DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES', self::getConfig('DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES'))) &&
                    Configuration::updateValue('DHLDP_DHL_LABEL_FORMAT', Tools::getValue('DHLDP_DHL_LABEL_FORMAT', self::getConfig('DHL_LABEL_FORMAT'))) &&
                    Configuration::updateValue('DHLDP_DHL_RETOURE_LABEL_FORMAT', Tools::getValue('DHLDP_DHL_RETOURE_LABEL_FORMAT', self::getConfig('DHL_RETOURE_LABEL_FORMAT'))) &&
                    Configuration::updateValue('DHLDP_DHL_EPRINT_EMAIL', Tools::getValue('DHLDP_DHL_EPRINT_EMAIL', self::getConfig('DHL_EPRINT_EMAIL'))) &&
                    Configuration::updateValue('DHLDP_DHL_DEF_PARCEL_ROUT_SERV', (int)Tools::getValue('DHLDP_DHL_DEF_PARCEL_ROUT_SERV', self::getConfig('DHL_DEF_PARCEL_ROUT_SERV'))) &&
                    Configuration::updateValue('DHLDP_DHL_DEF_GOGREEN', (int)Tools::getValue('DHLDP_DHL_DEF_GOGREEN', self::getConfig('DHL_DEF_GOGREEN'))) &&
                    Configuration::updateValue('DHLDP_DHL_RETURNS_EXTEND', (int)Tools::getValue('DHLDP_DHL_RETURNS_EXTEND', (int)self::getConfig('DHL_RETURNS_EXTEND'))) &&
                    Configuration::updateValue('DHLDP_DHL_RETURNS_IMMED', (int)Tools::getValue('DHLDP_DHL_RETURNS_IMMED', (int)self::getConfig('DHL_RETURNS_IMMED'))) &&
                    Configuration::updateValue('DHLDP_DHL_RA_COUNTRIES', implode(',', Tools::getValue('DHLDP_DHL_RA_COUNTRIES', explode(',', self::getConfig('DHLDP_DHL_RA_COUNTRIES'))))) &&
                    Configuration::updateValue('DHLDP_DHL_SHIPPER_TYPE', Tools::getValue('DHLDP_DHL_SHIPPER_TYPE')) &&
                    Configuration::updateValue('DHLDP_DHL_COMPANY_NAME_1', Tools::getValue('DHLDP_DHL_COMPANY_NAME_1')) &&
                    Configuration::updateValue('DHLDP_DHL_COMPANY_NAME_2', Tools::getValue('DHLDP_DHL_COMPANY_NAME_2')) &&
                    Configuration::updateValue('DHLDP_DHL_CONTACT_PERSON', Tools::getValue('DHLDP_DHL_CONTACT_PERSON')) &&
                    Configuration::updateValue('DHLDP_DHL_STREET_NAME', Tools::getValue('DHLDP_DHL_STREET_NAME')) &&
                    Configuration::updateValue('DHLDP_DHL_STREET_NUMBER', Tools::getValue('DHLDP_DHL_STREET_NUMBER')) &&
                    Configuration::updateValue('DHLDP_DHL_ZIP', Tools::getValue('DHLDP_DHL_ZIP')) &&
                    Configuration::updateValue('DHLDP_DHL_CITY', Tools::getValue('DHLDP_DHL_CITY')) &&
                    Configuration::updateValue('DHLDP_DHL_STATE', Tools::getValue('DHLDP_DHL_STATE')) &&
                    Configuration::updateValue('DHLDP_DHL_PHONE', Tools::getValue('DHLDP_DHL_PHONE')) &&
                    Configuration::updateValue('DHLDP_DHL_EMAIL', Tools::getValue('DHLDP_DHL_EMAIL')) &&
                    Configuration::updateValue('DHLDP_DHL_REFERENCE', (Tools::getValue('DHLDP_DHL_SHIPPER_TYPE') == 0) ? '' : Tools::getValue('DHLDP_DHL_REFERENCE')) &&
                    Configuration::updateValue('DHLDP_DHL_ACCOUNT_OWNER', Tools::getValue('DHLDP_DHL_ACCOUNT_OWNER')) &&
                    Configuration::updateValue('DHLDP_DHL_BANK_NAME', Tools::getValue('DHLDP_DHL_BANK_NAME')) &&
                    Configuration::updateValue('DHLDP_DHL_IBAN', Tools::getValue('DHLDP_DHL_IBAN')) &&
                    Configuration::updateValue('DHLDP_DHL_BIC', Tools::getValue('DHLDP_DHL_BIC')) &&
                    Configuration::updateValue('DHLDP_DHL_NOTE', Tools::getValue('DHLDP_DHL_NOTE')) &&
                    Configuration::updateValue('DHLDP_DHL_NOTE2', Tools::getValue('DHLDP_DHL_NOTE2'));

                if ($result_save == true) {
                    $this->_confirmations[] = $this->l('Settings updated');
                }
            }
        }
        return $this->displayMessages();
    }

    private function getLabelFormats()
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

    private function getRetoureLabelFormats()
    {
        return $this->getLabelFormats();
    }

    private function getArrayOptionsForSelect($array)
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
                $this->context->smarty->assign(
                    array(
                        'date_upd' => '',
                        'status' => '',
                        'descr' => implode(', ', $this->dhldp_api_rest->errors),
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

    private function getAssocArrayOptionsForSelect($array, $name_assoc = '')
    {
        if ($name_assoc != '') {
            $name_assoc = explode(',', $name_assoc);
        }
        if (is_array($array)) {
            $arr = array();
            foreach ($array as $key => $value) {
                $name_str = '';
                if (is_array($name_assoc)) {
                    foreach ($name_assoc as $name_assoc_val) {
                        if ($name_assoc != '' && isset($value[$name_assoc_val])) {
                            $name_str .= (($name_str != '') ? ' - ' : '') . $value[$name_assoc_val];
                        }
                    }
                }
                if ($name_str != '') {
                    $arr[] = array('id' => $key, 'name' => $name_str);
                } else {
                    $arr[] = array('id' => $key, 'name' => $value);
                }
            }
            return $arr;
        }
        return array();
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
        $return_shipping = array();
        if (is_array($shipping)) {
            foreach ($shipping as $shipping_item) {
                if (in_array($shipping_item['id_carrier'], $this->getDPCarriers(true, $id_shop))) {
                    $return_shipping[] = $shipping_item;
                }
            }
            return $return_shipping;
        }
        return array();
    }

    public function createDPDeliveryLabel($id_shop, $id_address, $product, $additional_info, $label_position, $id_order_carrier, $reference_number)
    {
        unset($reference_number);
        $receiver = $this->dp_api->prepareAddress(new Address((int)$id_address));
        $sender = $this->dp_api->getSender($id_shop);

        $positions = new stdClass();
        $positions->productCode = $product;
        $positions->address = new stdClass();
        $positions->address->receiver = $receiver;
        $positions->address->sender = $sender;
        $positions->additionalInfo = $additional_info;
        $positions->voucherLayout = $this->dp_api->voucher_layout;
        $position_page = 1;
        $position_col = 1;
        $position_row = 1;

        if (Configuration::get('DHLDP_DP_LABEL_FORMAT', false, false, $id_shop) == 'pdf') {
            $positions->position = new stdClass();
            $positions->position->page = $position_page = $label_position['page'];
            $positions->position->labelX = $position_col = $label_position['col'];
            $positions->position->labelY = $position_row = $label_position['row'];
        }

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
                        'shopOrderId' => $shop_order_id,
                        'positions' => $positions,
                        'total' => $total_eurocent,
                        'createManifest' => (bool)Configuration::get('DHLDP_DP_CREATE_MANIFEST', false, false, $id_shop),
                        'createShippingList' => (int)Configuration::get('DHLDP_DP_CREATE_SHIPLIST', false, false, $id_shop),
                        'pageFormatId' => ($page_format_id == 0) ? 1 : $page_format_id,
                    ),
                    $id_shop,
                    true
                );
            } else {
                $response = $this->dp_api->callApi(
                    'checkoutShoppingCartPNG',
                    array(
                        //'ppl' => $this->dp_api->ppl,
                        'shopOrderId' => $shop_order_id,
                        'positions' => $positions,
                        'total' => $total_eurocent,
                        'createManifest' => (bool)Configuration::get('DHLDP_DP_CREATE_MANIFEST', false, false, $id_shop),
                        'createShippingList' => (int)Configuration::get('DHLDP_DP_CREATE_SHIPLIST', false, false, $id_shop),
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
                $dp_label->dp_voucher_id = $response->shoppingCart->voucherList->voucher->voucherId;

                $dp_label->dp_track_id = isset($response->shoppingCart->voucherList->voucher->trackId) ? $response->shoppingCart->voucherList->voucher->trackId : '';
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
                    if (isset($response->shoppingCart->voucherList->voucher->trackId)) {
                        $this->updateDPOrderCarrierWithTrackingNumber(
                            (int)$id_order_carrier,
                            $response->shoppingCart->voucherList->voucher->trackId
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

                $tracking_url = str_replace('[tracking_number]', $tracking_number, DPApi::$tracking_url);

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
}