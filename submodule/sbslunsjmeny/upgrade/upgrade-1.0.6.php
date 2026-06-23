<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function sbslunsjmeny_1_0_6_column_exists($table, $column)
{
    $rows = Db::getInstance()->executeS(
        'SHOW COLUMNS FROM `' . pSQL($table) . '` LIKE \'' . pSQL($column) . '\''
    );

    return !empty($rows);
}

function sbslunsjmeny_1_0_6_index_exists($table, $index)
{
    $rows = Db::getInstance()->executeS(
        'SHOW INDEX FROM `' . pSQL($table) . '` WHERE `Key_name` = \'' . pSQL($index) . '\''
    );

    return !empty($rows);
}

function upgrade_module_1_0_6($module)
{
    $table = _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product';

    $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
        `id_sbslunsjmeny_order_exported_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_order` INT UNSIGNED NOT NULL,
        `id_order_detail` INT UNSIGNED NOT NULL,
        `id_cart_distribution_shipping` INT(10) UNSIGNED NOT NULL,
        `id_product` INT UNSIGNED NOT NULL,
        `ean13` VARCHAR(32) NOT NULL,
        `shipping_date` TIMESTAMP NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id_sbslunsjmeny_order_exported_product`),
        KEY `idx_order` (`id_order`),
        KEY `idx_order_shipping_date` (`id_order`, `shipping_date`),
        KEY `idx_cart_distribution_shipping` (`id_cart_distribution_shipping`),
        KEY `idx_product` (`id_product`),
        KEY `idx_shipping_date` (`shipping_date`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

    if (!Db::getInstance()->execute($sql)) {
        return false;
    }

    if (!sbslunsjmeny_1_0_6_column_exists($table, 'id_cart_distribution_shipping')) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` ADD `id_cart_distribution_shipping` INT(10) UNSIGNED NULL AFTER `id_order_detail`')) {
            return false;
        }
    }

    if (!sbslunsjmeny_1_0_6_column_exists($table, 'shipping_date')) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` ADD `shipping_date` TIMESTAMP NULL AFTER `ean13`')) {
            return false;
        }
    }

    Db::getInstance()->execute(
        'UPDATE `' . $table . '` eop
        INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od
            ON (od.`id_order_detail` = eop.`id_order_detail`)
        INNER JOIN `' . _DB_PREFIX_ . 'cart_distribution` cd
            ON (cd.`id_order` = eop.`id_order`)
        INNER JOIN `' . _DB_PREFIX_ . 'cart_distribution_shipping` cds
            ON (cds.`id_cart_distribution` = cd.`id_cart_distribution`
                AND cds.`id_product` = od.`product_id`
                AND cds.`id_product_attribute` = od.`product_attribute_id`)
        SET eop.`id_cart_distribution_shipping` = cds.`id_cart_distribution_shipping`,
            eop.`shipping_date` = cds.`shipping_date`
        WHERE eop.`id_cart_distribution_shipping` IS NULL
            OR eop.`id_cart_distribution_shipping` = 0
            OR eop.`shipping_date` IS NULL'
    );

    if (sbslunsjmeny_1_0_6_index_exists($table, 'uniq_order_detail')) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` DROP INDEX `uniq_order_detail`')) {
            return false;
        }
    }

    Db::getInstance()->execute(
        'UPDATE `' . $table . '`
        SET `id_cart_distribution_shipping` = IFNULL(`id_cart_distribution_shipping`, 0),
            `shipping_date` = IFNULL(`shipping_date`, \'1970-01-01 00:00:01\')'
    );

    if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` MODIFY `id_cart_distribution_shipping` INT(10) UNSIGNED NOT NULL')) {
        return false;
    }

    if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` MODIFY `shipping_date` TIMESTAMP NOT NULL')) {
        return false;
    }

    if (!sbslunsjmeny_1_0_6_index_exists($table, 'idx_order_shipping_date')) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` ADD KEY `idx_order_shipping_date` (`id_order`, `shipping_date`)')) {
            return false;
        }
    }

    if (!sbslunsjmeny_1_0_6_index_exists($table, 'idx_cart_distribution_shipping')) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` ADD KEY `idx_cart_distribution_shipping` (`id_cart_distribution_shipping`)')) {
            return false;
        }
    }

    if (!sbslunsjmeny_1_0_6_index_exists($table, 'idx_shipping_date')) {
        if (!Db::getInstance()->execute('ALTER TABLE `' . $table . '` ADD KEY `idx_shipping_date` (`shipping_date`)')) {
            return false;
        }
    }

    return true;
}
