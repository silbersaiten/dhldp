<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2020 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.0
 * @link      http://www.silbersaiten.de
 */

class DHLDPLabel extends ObjectModel
{
    public $id_order_carrier;
    public $product_code;
    public $options;
    public $shipment_number;
    public $label_url;
    public $export_label_url;
    public $cod_label_url;
    public $return_label_url;
    public $date_add;
    public $date_upd;
    public $is_complete;
    public $is_return;
    public $id_order_return;
    public $api_version;
    public $shipment_date;
    public $with_return;

    public $routing_code;
    public $idc;
    public $idc_type;
    public $int_idc;
    public $int_idc_type;

    public static $definition = array(
        'table' => 'dhldp_label',
        'primary' => 'id_dhldp_label',
        'fields' => array(
            'id_order_carrier' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'product_code' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'required' => true, 'size' => 30),
            'options' => array('type' => self::TYPE_STRING),
            'shipment_number' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 255),
            'label_url' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 500),
            'is_complete' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'size' => 1),
            'is_return' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'size' => 1),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'id_order_return' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'api_version' => array('type' => self::TYPE_STRING, 'size' => 10),
            'shipment_date' => array('type' => self::TYPE_DATE),
            'with_return' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'size' => 1),
            'export_label_url' => array('type' => self::TYPE_STRING, 'size' => 500),
            'cod_label_url' => array('type' => self::TYPE_STRING, 'size' => 500),
            'return_label_url' => array('type' => self::TYPE_STRING, 'size' => 500),
            'routing_code' => array('type' => self::TYPE_STRING, 'size' => 50),
            'idc' => array('type' => self::TYPE_STRING, 'size' => 50),
            'idc_type' => array('type' => self::TYPE_STRING, 'size' => 20),
            'int_idc' => array('type' => self::TYPE_STRING, 'size' => 50),
            'int_idc_type' => array('type' => self::TYPE_STRING, 'size' => 20),
        ),
    );

    public static function getPackages($id_dhldp_label)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT * FROM `'._DB_PREFIX_.'dhldp_package` dp
        WHERE dp.`id_dhldp_label` = '.(int)$id_dhldp_label);
    }

    public static function getLabelIDByShipmentNumber($shipment_number)
    {
        return Db::getInstance()->getValue('SELECT id_dhldp_label FROM `'._DB_PREFIX_.'dhldp_label` WHERE shipment_number=\''.pSql($shipment_number).'\'');
    }

    public function delete()
    {
        $result = parent::delete();

        if (!$result || !$this->deletePackages()) {
            return false;
        }

        return true;
    }

    public function deletePackages()
    {
        return Db::getInstance()->execute(
            'DELETE FROM `'._DB_PREFIX_.'dhldp_package`
			WHERE `id_dhldp_label` = '.(int)$this->id
        );
    }
}
