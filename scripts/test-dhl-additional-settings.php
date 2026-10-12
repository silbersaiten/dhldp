<?php
/** Run with: php scripts/test-dhl-additional-settings.php */
define('_PS_MODULE_DIR_', dirname(__DIR__, 2) . '/');
class ModuleAdminController { public $module; }
class Shop { public static function isFeatureActive() { return false; } }
class Tools
{
    public static $request = array();
    public static function getValue($key, $default = false) { return isset(self::$request[$key]) ? self::$request[$key] : $default; }
    public static function getIsset($key) { return isset(self::$request[$key]); }
    public static function isSubmit($key) { return self::getIsset($key); }
    public static function strlen($value) { return strlen((string)$value); }
    public static function isEmpty($value) { return $value === '' || $value === false; }
}
class Configuration
{
    public static $values = array();
    public static $writes = array();
    public static function get($key) { return isset(self::$values[$key]) ? self::$values[$key] : ''; }
    public static function updateValue($key, $value) { self::$writes[$key] = self::$values[$key] = $value; return true; }
}
class Validate
{
    public static function isPrice($value) { return (bool)preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $value); }
}
class DhlDp
{
    public $dhldp_api_rest;
    public function __construct() { $this->dhldp_api_rest = new PrestaShop\Module\dhldp\classes\DHLDPApiRest(null); }
    public static function getConfig($key) { return Configuration::get('DHLDP_' . $key); }
    public function l($value) { return $value; }
    public function isValidCustomsTariffNumber($value) { return (bool)preg_match('/^(?:[0-9]{6}|[0-9]{8}|[0-9]{10})$/', $value); }
}
require_once dirname(__DIR__) . '/controllers/admin/AdminDhldpSettingsDhl.php';
class SettingsTestController extends AdminDhldpSettingsDhlController
{
    public function __construct() { $this->module = new DhlDp(); }
    public function errors() { return $this->dhldpErrors; }
    public function confirmations() { return $this->dhldpConfirmations; }
}
function checkSettings($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function saveSettings($values, $button = 'submitSaveAdditionalServicesOptions')
{
    Configuration::$values = array('DHLDP_DHL_API_VERSION' => '2.1', 'DHLDP_DHL_PACK_WEIGHT' => '0.15');
    Configuration::$writes = array();
    Tools::$request = array_merge(array('submitAddDHLDP_dhl_configure' => 1, $button => 1), $values);
    $controller = new SettingsTestController();
    $controller->postProcess();
    return $controller;
}
$values = array(
    'DHLDP_DHL_PREMIUM' => '1', 'DHLDP_DHL_EXP_INV_NUM' => '2',
    'DHLDP_DHL_DEF_CUSTOMS_TARIFF_NUM' => '12345678', 'DHLDP_DHL_DEF_PLACE_OF_COMMITAL' => 'Hannover',
    'DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES' => '2,50',
);
$controller = saveSettings($values);
checkSettings(!$controller->errors() && $controller->confirmations(), 'Valid block did not save');
foreach ($values as $key => $value) {
    $expected = $key === 'DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES' ? '2.50' : $value;
    checkSettings(isset(Configuration::$writes[$key]) && (string)Configuration::$writes[$key] === $expected, 'Setting not saved: ' . $key);
}
checkSettings(Configuration::$values['DHLDP_DHL_PACK_WEIGHT'] === '0.15', 'Other block was modified');
foreach (array('', '0') as $fee) {
    $controller = saveSettings(array_merge($values, array('DHLDP_DHL_PREMIUM' => '0', 'DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES' => $fee)));
    checkSettings(!$controller->errors() && Configuration::$writes['DHLDP_DHL_PREMIUM'] === 0, 'Premium cannot be disabled');
    checkSettings(Configuration::$writes['DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES'] === '0', 'Zero/empty fee not saved');
}
foreach (array('DHLDP_DHL_EXP_INV_NUM' => '9', 'DHLDP_DHL_DEF_CUSTOMS_TARIFF_NUM' => 'invalid',
    'DHLDP_DHL_DEF_PLACE_OF_COMMITAL' => str_repeat('A', 36), 'DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES' => 'invalid') as $key => $value) {
    $controller = saveSettings(array_merge($values, array($key => $value)));
    checkSettings($controller->errors() && !Configuration::$writes, 'Invalid setting accepted: ' . $key);
}
$controller = saveSettings($values, 'submitSaveMiscOptions');
checkSettings(!$controller->errors(), 'Misc settings failed to save');
foreach (array_keys($values) as $key) {
    checkSettings(!array_key_exists($key, Configuration::$writes), 'Misc block saved additional setting: ' . $key);
}
echo "DHL additional settings save checks passed.\n";
