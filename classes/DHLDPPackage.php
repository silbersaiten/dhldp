<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2022 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.17
 * @link      http://www.silbersaiten.de
 */

class DHLDPPackage extends ObjectModel
{
    public $id_dhldp_label;
    public $length;
    public $width;
    public $height;
    public $weight;
    public $package_type;
    public $shipment_number;
    public $date_add;
    public $date_upd;
    public $last_track_status;
    public $last_track_descr;
    public $last_track_date;
    public $last_track_date_upd;
    public $last_track_delivery;

    public static $definition = array(
        'table' => 'dhldp_package',
        'primary' => 'id_dhldp_package',
        'fields' => array(
            'id_dhldp_label' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'length' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'width' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'height' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'weight' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true),
            'package_type' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 30),
            'shipment_number' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 255),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'last_track_status' => array('type' => self::TYPE_STRING, 'size' => 10),
            'last_track_descr' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 400),
            'last_track_date' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'last_track_date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'last_track_delivery' => array('type' => self::TYPE_INT, 'size' => 1),
        ),
    );

    public static function getOrderByPackageID($id_dhldp_package)
    {
        return Db::getInstance()->getRow('select o.id_shop, o.id_order from '._DB_PREFIX_.'orders o, '._DB_PREFIX_.'order_carrier oc, '._DB_PREFIX_.'dhldp_label dhldpl, 
        '._DB_PREFIX_.'dhldp_package dhldpp WHERE o.id_order=oc.id_order AND oc.id_order_carrier=dhldpl.id_order_carrier 
        AND dhldpl.id_dhldp_label=dhldpl.id_dhldp_label AND dhldpp.id_dhldp_package='.(int)$id_dhldp_package);
    }

    public static function getPackageIDByShipmentNumber($shipment_number)
    {
        return Db::getInstance()->getValue('SELECT id_dhldp_label FROM `'._DB_PREFIX_.'dhldp_package` WHERE shipment_number=\''.pSql($shipment_number).'\'');
    }

    public static function getPackagesForTracking($id_shop) {
        return Db::getInstance()->executes('SELECT p.shipment_number FROM `'._DB_PREFIX_.'dhldp_package` p, '._DB_PREFIX_.'dhldp_label l, '._DB_PREFIX_.'order_carrier oc, 
        '._DB_PREFIX_.'orders o   WHERE oc.id_order=o.id_order AND l.id_order_carrier=oc.id_order_carrier AND p.id_dhldp_label=l.id_dhldp_label 
        AND p.last_track_delivery!=1 AND o.id_shop='.(int)$id_shop);
    }
}
