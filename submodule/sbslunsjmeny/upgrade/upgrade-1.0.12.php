<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function sbslunsjmeny_1_0_12_column_exists($table, $column)
{
    $rows = Db::getInstance()->executeS(
        'SHOW COLUMNS FROM `' . pSQL($table) . '` LIKE \'' . pSQL($column) . '\''
    );

    return !empty($rows);
}

function sbslunsjmeny_1_0_12_index_exists($table, $index)
{
    $rows = Db::getInstance()->executeS(
        'SHOW INDEX FROM `' . pSQL($table) . '` WHERE `Key_name` = \'' . pSQL($index) . '\''
    );

    return !empty($rows);
}

function upgrade_module_1_0_12($module)
{
    $table = _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue';

    if (!$module->unregisterHook('actionObjectOrderAddAfter')) {
        return false;
    }

    if (sbslunsjmeny_1_0_12_column_exists($table, 'id_sbslunsjmeny_order_export_queue')) {
        if (!Db::getInstance()->execute(
            'ALTER TABLE `' . $table . '`
                DROP PRIMARY KEY,
                DROP COLUMN `id_sbslunsjmeny_order_export_queue`,
                ADD PRIMARY KEY (`id_order`)'
        )) {
            return false;
        }
    } elseif (!sbslunsjmeny_1_0_12_index_exists($table, 'PRIMARY')) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` ADD PRIMARY KEY (`id_order`)')) {
            return false;
        }
    }

    if (sbslunsjmeny_1_0_12_index_exists($table, 'uniq_order')) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` DROP INDEX `uniq_order`')) {
            return false;
        }
    }

    return true;
}
