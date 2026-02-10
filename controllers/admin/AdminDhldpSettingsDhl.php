<?php
/**
 * DHL Deutschepost
 */

class AdminDhldpSettingsDhlController extends ModuleAdminController
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
            $this->displayInformation($this->l('You can only display the page in a shop context.'));
            return;
        }

        if (Tools::getValue('view') === 'init_dhl') {
            $this->content .= $this->module->postInitDHLProcess();
            $this->content .= $this->module->displayFormInitDHLSettings();
            return;
        }

        $this->content .= $this->module->postProcess();
        $this->content .= $this->module->displayFormDHLSettings();
        parent::initContent();
    }
}
