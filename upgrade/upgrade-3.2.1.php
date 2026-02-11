<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.1
 * @link      http://www.silbersaiten.de
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_2_1($object)
{
    $tabs = array(
        array('class' => 'AdminDhldp', 'name' => 'DHL', 'parent' => 'AdminParentShipping', 'active' => true),
    );

    $return = true;

    foreach ($tabs as $tab) {
        if (!Tab::getIdFromClassName($tab['class'])) {
            $return &= $object->installTab($tab['class'], $tab['name'], $tab['parent'], $tab['active']);
        }
    }

    return (bool)$return;
}
