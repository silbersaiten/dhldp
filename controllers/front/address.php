<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2025 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.0.4
 * @link      https://www.silbersaiten.de
 */

class DHLDPAddressModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();
        if (Tools::getIsset('ajax')) {
            if ((int)Configuration::get('DHLDP_DHL_PFPS') && Tools::getIsset('getAddressAdditions')) {
                $this->context->smarty->assign(
                    array(
                        'self' => dirname(__FILE__) . '/../..',
                        'display_search_button' => $this->module->getConfig('DHL_PFPS_MAP', $this->context->shop->id)
                    )
                );

                $this->setTemplate('module:dhldp/views/templates/front/address_additions.tpl');

                die($this->context->smarty->fetch($this->template));
            } elseif (Tools::getIsset('getPackstations') || Tools::getIsset('getPostfiliales')) {
                $address = array(
                    'street' => Tools::getValue('street', ''),
                    'streetNo' => Tools::getValue('streetNo', ''),
                    'zip' => Tools::getValue('zip', ''),
                    'city' => Tools::getValue('city', ''),
                );
                if (Tools::getIsset('getPackstations')) {
                    die(json_encode($this->module->dhldp_api_rest->getRestPackstations($address)));
                } else {
                    die(json_encode($this->module->dhldp_api_rest->getRestPostfiliales($address)));
                }
            } elseif (Tools::getValue('action') == 'setprivate') {
                die(DHLDPOrder::updatePermission((int)Context::getContext()->cart->id, (Tools::getValue('permission', 0)) == '1' ? 1 : 0));
            } elseif (Tools::getValue('action') == 'getprivate') {
                if (DHLDPOrder::hasPermissionForTransferring((int)Context::getContext()->cart->id) === false) {
                    DHLDPOrder::updatePermission((int)Context::getContext()->cart->id, 0);
                }
                $permission = DHLDPOrder::hasPermissionForTransferring((int)Context::getContext()->cart->id);
                die(json_encode(array('permission' => ($permission) ? 1 : 0)));
            }
        }
    }
}
