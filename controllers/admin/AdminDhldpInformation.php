<?php
/**
 * DHL Deutschepost
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

    public function initContent()
    {
        parent::initContent();

        if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
            $this->displayInformation($this->l('You can only display the page in a shop context.'));
            return;
        }

        $this->content .= $this->module->displayInfo();
    }
}
