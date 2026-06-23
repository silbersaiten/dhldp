<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_2($module)
{
    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product` (
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

    return Db::getInstance()->execute($sql);
}
