<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.0
 * @link      https://www.silbersaiten.de
 */

class AdminDhldpInformationController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = '';
        $this->bootstrap = true;
        $this->show_toolbar = false;
        $this->multishop_context = Shop::CONTEXT_SHOP;
        $this->context = Context::getContext();

        parent::__construct();
    }


    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        if ((_PS_VERSION_ < '1.6.0.0')) {
            $this->context->controller->addCSS($this->module->_path . 'views/css/admin-15.css');
        }

        $this->context->controller->addCSS($this->module->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->module->_path . 'views/js/dhl-product-dimensions.js');

        Media::addJsDef([
            'is177' => $this->module->is177,
            'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax', false, [], []) . '&token=' . Tools::getAdminTokenLite('AdminDhldpAjax')
        ]);
    }

    public function initContent()
    {
        if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
            $this->displayInformation($this->l('You can only display the page in a shop context.'));
            return;
        }
        $this->content .= $this->displayInfo();
        parent::initContent();
    }

    public function displayInfo()
    {
        $this->context->smarty->assign(
            array(
                '_path' => $this->module->getPathUri(),
                'displayName' => $this->module->displayName,
                'author' => $this->module->author,
                'description' => $this->module->description,
            )
        );
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/info.tpl');
    }
}
