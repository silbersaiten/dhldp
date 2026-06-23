<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_13($module)
{
    $queueTable = _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue';
    $cartDistributionTable = _DB_PREFIX_ . 'cart_distribution';
    $cartDistributionShippingTable = _DB_PREFIX_ . 'cart_distribution_shipping';
    $exportedProductTable = _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product';
    $orderTable = _DB_PREFIX_ . 'orders';

    return Db::getInstance()->execute(
        'INSERT INTO `' . $queueTable . '` (`id_order`, `source`, `created_at`, `updated_at`)
        SELECT DISTINCT cd.`id_order`, \'upgrade_1_0_13_backfill\', NOW(), NOW()
        FROM `' . $cartDistributionTable . '` cd
        INNER JOIN `' . $cartDistributionShippingTable . '` cds
            ON (cds.`id_cart_distribution` = cd.`id_cart_distribution`)
        INNER JOIN `' . $orderTable . '` o
            ON (o.`id_order` = cd.`id_order`)
        LEFT JOIN `' . $exportedProductTable . '` eop
            ON (eop.`id_order` = cd.`id_order`
                AND eop.`id_cart_distribution_shipping` = cds.`id_cart_distribution_shipping`)
        WHERE cd.`id_order` IS NOT NULL
            AND cd.`id_order` > 0
            AND DATE(cds.`shipping_date`) >= CURDATE()
            AND eop.`id_sbslunsjmeny_order_exported_product` IS NULL
        ON DUPLICATE KEY UPDATE
            `source` = VALUES(`source`),
            `exported_at` = NULL,
            `updated_at` = NOW()'
    );
}
