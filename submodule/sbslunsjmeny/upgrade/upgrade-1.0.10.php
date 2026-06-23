<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_10($module)
{
    return Configuration::updateValue(
        Sbslunsjmeny::CONF_TOKEN_URL,
        'https://systest.id.trumf.no/connect/token'
    );
}
