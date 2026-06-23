<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_9($module)
{
    return Configuration::updateValue(
        Sbslunsjmeny::CONF_INTEGRATION_BASE_URL,
        'https://api-dev.test.ngdata.no/sylinder/netthandel/lunsjintegration/v1'
    );
}
