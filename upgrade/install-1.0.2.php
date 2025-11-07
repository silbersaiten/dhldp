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

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_2($object)
{
    $return = (bool)(Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'dhldp_dp_label` (
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
                ) ENGINE='._MYSQL_ENGINE_.'  DEFAULT CHARSET=utf8')) &&
        (bool)(Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'dhldp_dp_productlist` (
                `id_dhldp_dp_productlist` int(11) NOT NULL AUTO_INCREMENT,
                `id` int(11) NOT NULL,
                `name` varchar(256) NOT NULL,
                `price` decimal(20,2) NOT NULL DEFAULT \'0\',
                `price_contract` decimal(20,2) NOT NULL DEFAULT \'0\',
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_dhldp_dp_productlist`)
                ) ENGINE='._MYSQL_ENGINE_.'  DEFAULT CHARSET=utf8'));

    return $return;
}