<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.1
 * @link      https://www.silbersaiten.de
 */

class AdminDhldpController extends ModuleAdminController
{
    public function init()
    {
        parent::init();

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminDhldpSettingsDhl'));
    }
}
