<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2022 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.17
 * @link      http://www.silbersaiten.de
 */

require(dirname(__FILE__).'/../../config/config.inc.php');

$controller = new FrontController();
if (Tools::isPHPCLI()) {
    $controller->ssl = Tools::usingSecureMode();
    $_SERVER['REQUEST_METHOD'] = 'POST';
}
$controller->init();

require_once(dirname(__FILE__).'/dhldp.php');

if (Tools::isPHPCLI() && isset($argc) && isset($argv)) {
    Tools::argvToGET($argc, $argv);
}

if (Tools::getValue('secure_key') != Configuration::getGlobalValue('DHLDP_DHL_SECURE_KEY')) {
    die('Secure key is wrong');
} else {
    $module = new DhlDp();

    $shops = Shop::getShops(true, null, true);
    $id_shop_selected = (int)Tools::getValue('id_shop');
    foreach ($shops as $id_shop) {
        if (($id_shop_selected > 0 && $id_shop == $id_shop_selected) || ($id_shop_selected == 0)) {
            $packages = DHLDPPackage::getPackagesForTracking((int)$id_shop);
            if ($packages) {
                foreach ($packages as $package) {
                    $response = $module->getTrackData($package['shipment_number'], $id_shop);
                    // send request every 2 second
                    sleep(2);
                }
            }
        }
    }
}
