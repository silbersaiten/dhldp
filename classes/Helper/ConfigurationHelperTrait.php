<?php
/**
 * DHL Deutschepost
 *
 * Helper trait for accessing module configuration values.
 */

namespace PrestaShop\Module\dhldp\Helper;

use Configuration;

trait ConfigurationHelperTrait
{
    /**
     * Retrieve a configuration value with the module prefix applied.
     *
     * @param string $key
     * @param int|null $id_shop
     * @param int|null $id_lang
     * @param int|null $id_shop_group
     *
     * @return mixed
     */
    public static function getConfig($key, $id_shop = null, $id_lang = null, $id_shop_group = null)
    {
        return Configuration::get('DHLDP_' . $key, $id_lang, $id_shop_group, $id_shop);
    }

    /**
     * Update a configuration value with the module prefix applied.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $id_shop
     * @param int|null $id_lang
     * @param int|null $id_shop_group
     *
     * @return bool
     */
    public static function updateConfig($key, $value, $id_shop = null, $id_lang = null, $id_shop_group = null)
    {
        return Configuration::updateValue('DHLDP_' . $key, $value, $id_lang, $id_shop_group, $id_shop);
    }
}
