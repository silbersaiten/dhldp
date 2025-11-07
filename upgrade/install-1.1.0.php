<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2022 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.1.0
 * @link      http://www.silbersaiten.de
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($object)
{
    $return = Configuration::updateValue('DHLDP_DHL_API_VERSION', '3.4') &&
        (bool)(Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dhldp_package` ADD last_track_delivery tinyint(1);')) &&
        (bool)(Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dhldp_package` MODIFY last_track_status varchar(10);'));
    return $return;
}