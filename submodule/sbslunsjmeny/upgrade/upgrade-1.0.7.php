<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_7($module)
{
    return Configuration::updateValue(Sbslunsjmeny::CONF_INTEGRATION_TIMEZONE, Sbslunsjmeny::DEFAULT_INTEGRATION_TIMEZONE);
}
