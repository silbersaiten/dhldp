<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.0
 * @link      http://www.silbersaiten.de
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_2_0($object)
{
    $tabs = array(
        array('class' => 'AdminDhldpManifest', 'name' => 'DHL', 'parent' => 'AdminParentShipping', 'active' => true),
        array('class' => 'AdminDhldpSettingsDhl', 'name' => 'DHL settings', 'parent' => 'AdminDhldpManifest', 'active' => true),
        array('class' => 'AdminDhldpSettingsDp', 'name' => 'Deutschepost settings', 'parent' => 'AdminDhldpManifest', 'active' => true),
        array('class' => 'AdminDhldpInformation', 'name' => 'Information', 'parent' => 'AdminDhldpManifest', 'active' => true),
        array('class' => 'AdminDhldpAjax', 'name' => 'DHL Ajax', 'parent' => 'AdminParentShipping', 'active' => false),
    );

    $return = true;

    foreach ($tabs as $tab) {
        if (!Tab::getIdFromClassName($tab['class'])) {
            $return &= $object->installTab($tab['class'], $tab['name'], $tab['parent'], $tab['active']);
        }
    }

    return (bool)$return;
}
