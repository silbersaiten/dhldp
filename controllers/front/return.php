<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2020 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.0
 * @link      http://www.silbersaiten.de
 */

class DHLDPReturnModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = true;
    public $dhldp_errors = array();
    public $dhldp_count_errors = 0;
    public $display_column_left = false;

    public function getTemplateVarPage()
    {
        $vars = parent::getTemplateVarPage();
        $vars['body_classes']['page-customer-account'] = true;
        return $vars;
    }

    public function getBreadcrumbLinks()
    {
        if (version_compare(_PS_VERSION_,  '1.7','<')) {
            $title = $this->module->l('Merchandise returns');
        } else {
            $title = $this->getTranslator()->trans('Merchandise returns', array(), 'Shop.Theme.Customeraccount');
        }
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();
        $breadcrumb['links'][] = array(
            'title' => $title,
            'url' => $this->context->link->getPageLink('order-follow', true),
        );
        return $breadcrumb;
    }

    public function init()
    {
        parent::init();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCreateLabel')) {
            $this->processSubmitCreateLabel();
        }

        if (Tools::isSubmit('submitGetLabel')) {
            $this->processSubmitGetLabel();
        }
    }

    protected function processSubmitGetLabel()
    {
        $order_return = new OrderReturn((int)Tools::getValue('id_order_return'));
        if (Validate::isLoadedObject($order_return)) {
            if ($order_return->id_customer == $this->context->customer->id) {
                $label = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'dhldp_label WHERE id_order_return='.(int)$order_return->id);
                if ($label) {
                    header("Content-type:application/pdf");
                    header("Content-Disposition:attachment;filename=return_label_".$order_return->id.".pdf");
                    readfile($this->module->getLabelFilePathByLabelUrl($label['label_url']));
                    exit;
                }
            }
        }
    }

    protected function processSubmitCreateLabel()
    {
        $order_return = new OrderReturn((int)Tools::getValue('id_order_return'));
        if (Validate::isLoadedObject($order_return)) {
            if ($order_return->id_customer == $this->context->customer->id) {
                $label = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'dhldp_label WHERE id_order_return='.(int)$order_return->id);
                if (!$label) {
                    $order = new Order((int)$order_return->id_order);
                    if (Validate::isLoadedObject($order)) {

                        $order_carriers = $this->module->filterShipping($order->getShipping(), $order->id_shop);
                        if (is_array($order_carriers) && (count($order_carriers) > 0)) {
                            $order_carrier = $order_carriers[0];
                            $last_label = $this->module->getLastNonReturnLabelData($order_carrier['id_order_carrier']);
                            if (is_array($last_label)) {
                                $dhldp_errors = array();

                                $this->module->dhldp_api_rest->setApiVersionByIdShop($order->id_shop);
                                $sender_address = $this->module->dhldp_api_rest->getDHLRASenderAddress($order->id_address_delivery, Tools::getValue('address'), $order->id_shop);
                                $result = $this->module->createDhlRetoureLabel(
                                    $sender_address,
                                    $order_carrier['id_order_carrier'],
                                    (Configuration::get('DHLDP_DHL_REF_NUMBER') ? $order->id : $order->reference),
                                    (int)$order_return->id,
                                    (int)$order->id_shop
                                );

                                if (!$result) {
                                    if (is_array($this->module->dhldp_api_rest->errors) && count($this->module->dhldp_api_rest->errors) > 0) {
                                        $dhldp_errors = array_merge($dhldp_errors, $this->module->dhldp_api_rest->errors);
                                    } else {
                                        $dhldp_errors[] = $this->l('Unable to generate label for this request');
                                    }
                                    $this->dhldp_errors = $dhldp_errors;
                                    $this->dhldp_count_errors = count($this->dhldp_errors);
                                } else {
                                    $this->dhldp_count_errors = 0;
                                }
                            } else {
                            }
                        }
                    }
                }
            }
        }
    }

    public function initContent()
    {
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            if (Configuration::isCatalogMode()) {
                Tools::redirect('index.php');
            }
        }

        $this->context->smarty->assign('form_errors_quantity', $this->dhldp_count_errors);
        $this->context->smarty->assign('form_errors', $this->dhldp_errors);
        $this->context->smarty->assign('show_form', false);
        $this->context->smarty->assign('show_label', false);
        $this->context->smarty->assign('id_order_return', (int)Tools::getValue('id_order_return'));
        $navigation_pipe = (Configuration::get('PS_NAVIGATION_PIPE') ? Configuration::get('PS_NAVIGATION_PIPE') : '>');
        $this->context->smarty->assign('navigationPipe', $navigation_pipe);


        $order_return = new OrderReturn((int)Tools::getValue('id_order_return'));
        if (Validate::isLoadedObject($order_return)) {
            if ($order_return->id_customer == $this->context->customer->id) {
                $order = new Order((int)$order_return->id_order);

                // available only for germany
                //if (!($this->module->isGermanyAddress($order->id_address_delivery) && $this->module->isDomesticDelivery($order->id_shop, $order->id_address_delivery))) {
                //    Tools::redirect('index.php');
                //}

                $label = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'dhldp_label WHERE id_order_return='.(int)$order_return->id);
                if (!$label) {
                    $sender_address = $this->module->dhldp_api_rest->getDHLRASenderAddress($order->id_address_delivery, Tools::getValue('address'), $order->id_shop);
                    $this->context->smarty->assign('address', $sender_address);
                    $this->context->smarty->assign('show_form', true);
                } else {
                    $this->context->smarty->assign('show_label', true);
                }
            } else {
                Tools::redirect('index.php');
            }
        } else {
            Tools::redirect('index.php');
        }

        $this->context->smarty->assign('countries', $this->module->getCountriesForRA($this->context->language->id, explode(',', Configuration::get('DHLDP_DHL_RA_COUNTRIES'))));

        parent::initContent();
        $this->setTemplate('module:dhldp/views/templates/front/return.tpl');
    }
}
