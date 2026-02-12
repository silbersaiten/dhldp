<?php
/**
 * DHL Deutschepost
 */

class AdminDhldpSettingsDpController extends ModuleAdminController
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

    public function initContent()
    {
        if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
            $this->displayInformation($this->module->l('You can only display the page in a shop context.'));
            return;
        }

        $this->content .= $this->module->postProcess();
        $this->content .= $this->module->displayMenu();
        $this->content .= $this->module->displayFormDPSettings();
        parent::initContent();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->context->controller->addJqueryUI('ui.tabs');
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/dp_admin_configure.js');

        Media::addJsDef([
            'is177' => $this->module->is177,
            'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax')
        ]);
    }
}
