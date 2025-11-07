<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2025 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.0.0
 * @link      http://www.silbersaiten.de
 */

class AdminDhldpAjaxController extends ModuleAdminController
{
    public function displayAjaxGettrackingdata()
    {
        $this->module->getAjaxTrackingData();
    }

    public function displayAjaxGetdhlproductdimensions()
    {
        $this->module->getAjaxDhlProductDimensions();
    }

    public function displayAjaxUpdatedhlproductdimensions()
    {
        $this->module->updateAjaxDhlProductDimensions();
    }
}