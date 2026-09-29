<?php
/** Run with: php scripts/test-filter-shipping.php */

define('_PS_VERSION_', '1.7.8.0');

class Module {}
class ObjectModel
{
    const TYPE_INT = 1;
    const TYPE_STRING = 2;
    const TYPE_DATE = 3;
    const TYPE_FLOAT = 4;
}

require_once dirname(__DIR__) . '/dhldp.php';

class FilterShippingTestModule extends DhlDp
{
    public function __construct() {}

    public function getDhlCarriers($with_referenced_carriers = false, $ids_only = true, $id_shop = null)
    {
        if (!$with_referenced_carriers || $ids_only || $id_shop !== 2) {
            throw new RuntimeException('Expected carrier mappings including historical carriers for shop 2');
        }

        return array(
            0 => array('product' => ''),
            12 => array('product' => 'V01PAK'),
            15 => array('product' => ''),
            16 => array(),
        );
    }
}

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$module = new FilterShippingTestModule();
$shipping = array(
    array('id_carrier' => ''),
    array('id_carrier' => null),
    array(),
    array('id_carrier' => 0),
    array('id_carrier' => '0'),
    array('id_carrier' => 99),
    array('id_carrier' => 16),
    array('id_carrier' => '12suffix'),
    array('id_carrier' => 12, 'id_order_carrier' => 101),
    array('id_carrier' => '12', 'id_order_carrier' => 102),
    array('id_carrier' => '15', 'id_order_carrier' => 103),
);
$expected = array(
    array('id_carrier' => 12, 'id_order_carrier' => 101, 'default_dhl_product_code' => 'V01PAK'),
    array('id_carrier' => '12', 'id_order_carrier' => 102, 'default_dhl_product_code' => 'V01PAK'),
    array('id_carrier' => '15', 'id_order_carrier' => 103, 'default_dhl_product_code' => ''),
);

if ($module->filterShipping($shipping, 2) !== $expected) {
    throw new RuntimeException('Invalid shipping rows must be skipped and valid DHL rows preserved');
}
foreach (array(false, null, array()) as $emptyShipping) {
    if ($module->filterShipping($emptyShipping, 2) !== array()) {
        throw new RuntimeException('Empty shipping must return an empty array');
    }
}

restore_error_handler();
echo "Shipping filter tests passed\n";
