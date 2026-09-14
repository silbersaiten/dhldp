<?php
/** Run with: php scripts/test-order-label-redirect.php */
define('_PS_VERSION_', '1.7.8.0');

class Module {}
class ObjectModel
{
    const TYPE_INT = 1;
    const TYPE_STRING = 2;
    const TYPE_DATE = 3;
    const TYPE_FLOAT = 4;
}
class Tools
{
    public static $request = array();
    public static $redirect;
    public static function getIsset($key) { return isset(self::$request[$key]); }
    public static function getValue($key, $default = false) { return isset(self::$request[$key]) ? self::$request[$key] : $default; }
    public static function strlen($value) { return strlen($value); }
    public static function getAdminTokenLite($tab, $context) { return 'test-token'; }
    public static function redirectAdmin($url) { self::$redirect = $url; }
}
class Configuration
{
    public static function get($key, $lang = null, $group = null, $shop = null) { return false; }
}
class Order
{
    public $id;
    public $id_shop = 1;
    public $id_address_delivery = 2;
    public $reference = 'ORDER123';
    public function __construct($id) { $this->id = $id; }
    public function getShipping() { return array(); }
}
class TestCookie
{
    public $writes = 0;
    public function write() { ++$this->writes; }
}
class TestSmarty
{
    public $values = array();
    public function assign($key, $value) { $this->values[$key] = $value; }
}
class TestLink
{
    public function getAdminLink($controller, $token, $route, $params) { return '/orders/view?' . http_build_query($params); }
}
class TestDhlApi
{
    public $warnings = array();
    public $errors = array();
    public $confirmations = array('Shipment created');
    public function setApiVersionByIdShop($shop) {}
    public function getDHLDeliveryAddress($id, $address, $order) { return array('countryISOCode' => 'DE'); }
    public function getDefinedProducts($code, $country, $shipper, $version) { return array('params' => array()); }
    public function getShipperCountry($shop) { return 'DE'; }
    public function getApiVersion() { return '2'; }
    public function getMajorApiVersion() { return 2; }
}

require_once dirname(__DIR__) . '/dhldp.php';

class OrderRedirectTestModule extends DhlDp
{
    public $context;
    public $result = true;
    public $creations = 0;
    public function __construct()
    {
        $this->context = (object)array('cookie' => new TestCookie(), 'smarty' => new TestSmarty(), 'link' => new TestLink());
        $this->dhldp_api_rest = new TestDhlApi();
    }
    public function l($message) { return $message; }
    public function getFormattedAddedDhlProducts($products, $to = '', $from = '', $version = '') { return array(array('code' => 'V01PAK')); }
    public function getFormattedAddedDhlProductsByDeliveryAddress($address, $shop = null) { return array(); }
    public function filterShipping($shipping, $shop) { return array(); }
    public function createDhlDeliveryLabel($address, $code, $packages, $options, $carrier, $reference, $isReturn = false, $withReturn = false, $returnId = 0, $shop = null, $validation = false)
    {
        ++$this->creations;
        return $this->result;
    }
}

function checkRedirect($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
}

$buttons = array('submitDHLDPDhlLabelRequest', 'submitDHLDPDhlLabelWithReturnRequest', 'submitDHLDPDhlCreateLabelNoValidation');
foreach (array(false, true) as $modern) {
    foreach ($buttons as $button) {
        $module = new OrderRedirectTestModule();
        $module->is177 = $modern;
        $module->dhldp_api_rest->warnings = array('Address corrected');
        Tools::$request = array($button => 1, 'id_address' => 2, 'id_order_carrier' => 3, 'dhl_product_code' => 'V01PAK');
        Tools::$redirect = null;
        $module->displayDHLAdminOrder(array('id_order' => 42));
        checkRedirect(Tools::$redirect !== null, 'Successful creation did not redirect');
        parse_str(parse_url(Tools::$redirect, PHP_URL_QUERY), $query);
        checkRedirect($query['id_order'] == 42 && $query['vieworder'] == 1, 'Wrong redirect destination');
        checkRedirect(!isset($query[$button]), 'Redirect repeats label submission');
        checkRedirect($module->context->cookie->writes === 1, 'Feedback not written before redirect');

        // Simulate subsequent requests, including another order in another tab.
        Tools::$request = array();
        $module->context->smarty = new TestSmarty();
        $module->displayDHLAdminOrder(array('id_order' => 43));
        checkRedirect(!isset($module->context->smarty->values['dhl_confirmations']), 'Feedback leaked to another order');
        $module->displayDHLAdminOrder(array('id_order' => 42));
        checkRedirect($module->context->smarty->values['dhl_warnings'] === array('Address corrected'), 'Warnings lost after redirect');
        checkRedirect($module->context->smarty->values['dhl_confirmations'] === array('Shipment created'), 'Confirmation lost after redirect');
        $module->context->smarty = new TestSmarty();
        $module->displayDHLAdminOrder(array('id_order' => 42));
        checkRedirect(!isset($module->context->smarty->values['dhl_confirmations']), 'Feedback shown twice');
        checkRedirect($module->creations === 1, 'GET created another label');
    }
}

foreach (array('error', 'warning', 'validation') as $failure) {
    $module = new OrderRedirectTestModule();
    $module->result = false;
    Tools::$request = array('submitDHLDPDhlLabelRequest' => 1, 'dhl_product_code' => $failure === 'validation' ? '' : 'V01PAK');
    Tools::$redirect = null;
    if ($failure === 'error') { $module->dhldp_api_rest->errors = array('DHL unavailable'); }
    if ($failure === 'warning') { $module->dhldp_api_rest->warnings = array('Please check address'); }
    $module->displayDHLAdminOrder(array('id_order' => 42));
    checkRedirect(Tools::$redirect === null, 'Failed request redirected');
    $messageKey = $failure === 'warning' ? 'dhl_warnings' : 'dhl_errors';
    checkRedirect(count($module->context->smarty->values[$messageKey]) > 0, 'Failure message lost');
}
echo "Order label redirect checks passed.\n";
