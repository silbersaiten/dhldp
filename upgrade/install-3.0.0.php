<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2023 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.1.4
 * @link      http://www.silbersaiten.de
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_0_0($object)
{
    $return = Configuration::updateValue('DHLDP_DHL_API_VERSION', '2.1');
    $rows = Db::getInstance()->executeS("SELECT id_configuration, value FROM "._DB_PREFIX_."configuration WHERE name = 'DHLDP_DHL_CARRIERS'");
    foreach ($rows as $row) {
        $original = $row['value'];
        $parts = explode(',', $original);
        $newParts = [];

        foreach ($parts as $part) {
            $segments = explode('|', $part);
            if (count($segments) != 2) continue;

            list($shopId, $code) = $segments;
            $codeParts = explode(':', $code);

            if (count($codeParts) >= 2) {
                if ($codeParts[0] == 'EPN') {
                    $codeParts[0] = 'V01PAK';
                } elseif ($codeParts[0] == 'BPI') {
                    $codeParts[0] = 'V53WPAK';
                } elseif ($codeParts[0] == 'EPI') {
                    $codeParts[0] = 'V54EPAK';
                }

                $newParts[] = $shopId.'|'.$codeParts[0].':'.$codeParts[1];
            }
        }

        $newValue = implode(',', $newParts);
        $return &= Db::getInstance()->update('configuration', ['value' => pSQL($newValue)], 'id_configuration = '.(int)$row['id_configuration']);
    }

    $rows = Db::getInstance()->executeS("SELECT id_configuration, value FROM "._DB_PREFIX_."configuration WHERE name = 'DHLDP_DHL_PRODUCTS'");
    foreach ($rows as $row) {
        $original = $row['value'];
        $parts = explode(';', $original);
        $newParts = [];

        foreach ($parts as $part) {
            $codeParts = explode(':', $part);
            if (count($codeParts) >= 2) {
                // Замена кода
                if ($codeParts[0] == 'EPN') {
                    $codeParts[0] = 'V01PAK';
                } elseif ($codeParts[0] == 'BPI') {
                    $codeParts[0] = 'V53WPAK';
                } elseif ($codeParts[0] == 'EPI') {
                    $codeParts[0] = 'V54EPAK';
                }

                $newParts[] = $codeParts[0].':'.$codeParts[1];
            }
        }

        $newValue = implode(';', $newParts);
        $return &= Db::getInstance()->update('configuration', ['value' => pSQL($newValue)], 'id_configuration = '.(int)$row['id_configuration']);
    }

    $rows = Db::getInstance()->executeS("SELECT id_dhldp_label, product_code FROM "._DB_PREFIX_."dhldp_label");
    foreach ($rows as $row) {
        $codeParts = explode(':', $row['product_code']);
        if (count($codeParts) >= 2) {
            if ($codeParts[0] == 'EPN') {
                $codeParts[0] = 'V01PAK';
            } elseif ($codeParts[0] == 'BPI') {
                $codeParts[0] = 'V53WPAK';
            } elseif ($codeParts[0] == 'EPI') {
                $codeParts[0] = 'V54EPAK';
            }

            $newCode = $codeParts[0].':'.$codeParts[1];

            if (isset($codeParts[2])) {
            }

            $return &= Db::getInstance()->update(
                'dhldp_label',
                ['product_code' => pSQL($newCode)],
                'id_dhldp_label = '.(int)$row['id_dhldp_label']
            );
        }
    }
    if (method_exists('Tools', 'clearSf2Cache')) {
        Tools::clearSf2Cache();
    }
    Cache::clean('*');
    Media::clearCache();
    return $return;
}