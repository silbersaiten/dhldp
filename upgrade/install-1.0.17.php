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

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_17($object)
{
    $return = (bool)(Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dhldp_package` ADD last_track_status varchar(3);')) &&
        (bool)(Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dhldp_package` ADD last_track_descr varchar(400);')) &&
        (bool)(Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dhldp_package` ADD last_track_date datetime;')) &&
        (bool)(Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dhldp_package` ADD last_track_date_upd datetime;'));
    return $return;
}