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

class DHLDPOrder extends ObjectModel
{
    public $id_order;
    public $id_cart;
    public $permission_tpd;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'dhldp_order',
        'primary' => 'id_dhldp_order',
        'fields' => array(
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => false),
            'permission_tpd' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
        ),
    );

    public static function hasPermissionForTransferring($id_cart)
    {
        return Db::getInstance()->getValue('SELECT permission_tpd FROM '._DB_PREFIX_.'dhldp_order WHERE id_cart='.(int)$id_cart);
    }

    public static function getPermissionForTransferring($id_cart)
    {
        return Db::getInstance()->getRow('SELECT permission_tpd, date_add FROM '._DB_PREFIX_.'dhldp_order WHERE id_cart='.(int)$id_cart);
    }

    public static function updatePermission($id_cart, $permission)
    {
        $id_dhldp_order = Db::getInstance()->getValue('SELECT id_dhldp_order FROM '._DB_PREFIX_.'dhldp_order WHERE id_cart='.(int)$id_cart);
        $do = new DhlDPOrder((int)$id_dhldp_order);
        $do->id_cart = (int)$id_cart;
        $do->permission_tpd = (int)$permission;
        if ($do->save()) {
            return true;
        }
        return false;
    }
}
