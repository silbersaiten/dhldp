<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2020 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.2
 * @link      http://www.silbersaiten.de
 */

class DPLabel extends ObjectModel
{
    public $id_order_carrier;
    public $product;
    public $total;
    public $wallet_ballance;
    public $additional_info;
    public $dp_order_id;
    public $dp_voucher_id;
    public $dp_link;
    public $date_add;
    public $date_upd;
    public $is_complete;
    public $dp_track_id;
    public $manifest_link;
    public $label_format;
    public $page_format_id;
    public $label_position;

    public static $definition = array(
        'table' => 'dhldp_dp_label',
        'primary' => 'id_dhldp_dp_label',
        'fields' => array(
            'id_order_carrier' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'total' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'wallet_ballance' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'additional_info' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 80),
            'dp_order_id' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 255),
            'dp_voucher_id' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 64),
            'dp_link' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 255),
            'is_complete' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'size' => 1),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'manifest_link' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 255),
            'dp_track_id' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 64),
            'label_format' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 3),
            'page_format_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'label_position' => array('type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 255),
        ),
    );
}
