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

use PrestaShop\Module\dhldp\classes\DHLDPApiRest;

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
            $this->displayInformation($this->module->l('You can only display the page in a shop context.'));
            return;
        }

        if (Tools::getValue('view') === 'init_dhl') {
            $this->content .= $this->module->postInitDHLProcess();
            $this->content .= $this->displayFormInitDHLSettings();
            return;
        }

        $this->content .= $this->module->postProcess();
        $this->content .= $this->displayFormDHLSettings();
        parent::initContent();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->context->controller->addJqueryPlugin(['idTabs', 'select2', 'validate']);
        $this->context->controller->addJqueryUI('ui.accordion');
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->context->controller->addJS(
            _PS_JS_DIR_ . 'jquery/plugins/validate/localization/messages_' . $this->context->language->iso_code . '.js'
        );
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/admin_configure.js');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/dhl-product-dimensions.js');

        $dhl_products = $this->module->dhldp_api_rest->getDefinedProducts('', '', Configuration::get('DHLDP_DHL_COUNTRY'), Configuration::get('DHLDP_DHL_API_VERSION'));
        $dhl_products_js = [];
        foreach ($dhl_products as $dhl_product_key => $dhl_product) {
            if ($dhl_product['active'] == true) {
                $dhl_product_js = new stdClass();
                $dhl_product_js->name = $dhl_product['name'];
                $dhl_product_js->code = $dhl_product_key;
                $dhl_products_js[] = $dhl_product_js;
            }
        }

        Media::addJsDef([
            'is177' => $this->module->is177,
            'defined_dhl_api_versions' => json_encode(DHLDPApiRest::$supported_shipper_countries),
            'defined_dhl_products' => json_encode($dhl_products_js),
            'dhl_translation' => json_encode(
                [
                    'Remove' => $this->module->l('Remove'),
                    'ExistsParticipation' => $this->module->l('Such participation exists for this product'),
                    'Exists' => $this->module->l('This product already exists in the list')
                ]
            ),
            'dhldp_ajax_path' => $this->context->link->getAdminLink('AdminDhldpAjax')
        ]);
    }

    public function displayFormDHLSettings()
    {
        $helper = new HelperForm();
        $helper->required = false;
        $helper->id = null;
        $helper->currentIndex = AdminController::$currentIndex;
        $helper->table = 'DHLDP_dhl_configure';
        $helper->token = Tools::getValue('token');
        $helper->module = $this->module;
        $helper->identifier = null;
        $helper->toolbar_btn = null;
        $helper->ps_help_context = null;
        $helper->title = null;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = false;
        $helper->bootstrap = true;

        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_value_keys = array('DHL_MODE', 'DHL_RETURN_PARTICIPATION', 'DHL_LIVE_USER', 'DHL_LIVE_SIGN',
            'DHL_LIVE_EKP', 'DHL_LOG', 'DHL_REF_NUMBER', 'DHL_ORDER_WEIGHT', 'DHL_WEIGHT_RATE', 'DHL_PACK_WEIGHT', 'DHL_AGE_CHECK', 'DHL_PREMIUM',
            'DHL_PFPS', 'DHL_PFPS_MAP', 'DHL_GOOGLEMAPAPIKEY', 'DHL_CHANGE_OS', 'DHL_RETURN_MAIL', 'DHL_INTRANSIT_MAIL', 'DHL_LABEL_WITH_RETURN', 'DHL_LABEL_IGNORE_WARNING',
            'DHL_CONFIRMATION_PRIVATE', 'DHL_CREATE_MANIFEST_IN_ORDER', 'DHL_RETURNS_EXTEND', 'DHL_RETURNS_RP', 'DHL_RETURNS_IMMED', 'DHL_RETOUREPORTAL_ID', 'DHL_RETOUREPORTAL_DNAME',
            'DHL_RETOUREPORTAL_USER', 'DHL_RETOUREPORTAL_PASS', 'DHL_SHIPPER_TYPE', 'DHL_COMPANY_NAME_1', 'DHL_COMPANY_NAME_2', 'DHL_CONTACT_PERSON',
            'DHL_STREET_NAME', 'DHL_STREET_NUMBER', 'DHL_ZIP', 'DHL_CITY', 'DHL_STATE', 'DHL_PHONE', 'DHL_EMAIL', 'DHL_REFERENCE', 'DHL_ACCOUNT_OWNER',
            'DHL_ACCOUNT_NUMBER', 'DHL_BANK_CODE', 'DHL_BANK_NAME', 'DHL_IBAN', 'DHL_BIC', 'DHL_NOTE', 'DHL_NOTE2', 'DHL_DEFAULT_LENGTH', 'DHL_DEFAULT_WIDTH',
            'DHL_DEFAULT_HEIGHT', 'DHL_LABEL_FORMAT', 'DHL_RETOURE_LABEL_FORMAT', 'DHL_EPRINT_EMAIL', 'DHL_LIVE_TUSER', 'DHL_LIVE_TSIGN', 'DHL_SECURE_KEY',
            'DHL_CHANGE_OS_DELIVERED', 'DHL_EXP_INV_NUM', 'DHL_DEF_CUSTOMS_TARIFF_NUM', 'DHL_DEF_PLACE_OF_COMMITAL', 'DHL_DEF_ADDITIONAL_CUSTOM_FEES', 'DHL_DEF_PARCEL_ROUT_SERV', 'DHL_DEF_GOGREEN');

        $this->setFormFieldsValue($helper, $fields_value_keys);

        $helper->fields_value['DHLDP_DHL_RA_COUNTRIES[]'] = Tools::getValue('DHLDP_DHL_RA_COUNTRIES',
            explode(',', Configuration::get('DHLDP_DHL_RA_COUNTRIES')));

        $helper->fields_value['DHLDP_DHL_LIVE_RESET'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dhl-reset-live-account.tpl'
        );
        $this->context->smarty->assign(
            array(
                'initdhl_link' => $helper->currentIndex . '&view=init_dhl&token=' . $helper->token,
                'dhldp_dhl_api_version' => Tools::getValue('DHLDP_DHL_API_VERSION', Configuration::get('DHLDP_DHL_API_VERSION') ? Configuration::get('DHLDP_DHL_API_VERSION') : '2.1'),
            )
        );
        $helper->fields_value['DHLDP_DHL_API_VERSION'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dhl-api-version.tpl'
        );
        $helper->fields_value['log_information'] = $this->displayDHLLogInformation();

        $carriers = Carrier::getCarriers($this->context->language->id, true);
        $option_carriers = array();
        foreach ($carriers as $carrier) {
            $option_carriers[] = array('id_carrier' => $carrier['id_carrier'], 'name' => $carrier['name']);
        }

        $added_dhl_products = $this->module->getFormattedAddedDhlProducts(
            Tools::getValue('added_dhl_products', explode(';', Configuration::get('DHLDP_DHL_PRODUCTS')))
        );

        $this->context->smarty->assign(
            array(
                'carriers' => $option_carriers,
                'dhl_carriers' => $this->module->getDhlCarriers(true, false),
                'link' => $this->context->link->getAdminLink(
                        'AdminCarrierWizard',
                        false
                    ) . '&token=' . Tools::getAdminTokenLite('AdminCarrierWizard'),
                'added_dhl_products' => $added_dhl_products,
                'dhl_product_dimensions' => $added_dhl_products
            )
        );

        $helper->fields_value['add_carrier'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dhl-add-carrier.tpl'
        );
        $helper->fields_value['carrier_list'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dhl-carrier-list.tpl'
        );
        $helper->fields_value['dhl_products'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dhl-products.tpl'
        );

        $helper->fields_value['dhl-product-dimensions'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dhl-product-dimensions.tpl'
        );

        $helper->fields_value['DHLDP_DHL_IND_NOTIF'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dhl-ind-notif.tpl'
        );

        $this->context->smarty->assign(
            array(
                'dhl_country' => Tools::getValue('DHLDP_DHL_COUNTRY', Configuration::get('DHLDP_DHL_COUNTRY')),
            )
        );
        $helper->fields_value['DHLDP_DHL_COUNTRY'] = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dhl-shipper-country.tpl'
        );
        $helper->fields_value['DHLDP_DHL_LIVE_SIGN'] = str_repeat('*', mb_strlen(Configuration::get('DHLDP_DHL_LIVE_SIGN')));
        $forms = array();
        foreach ($this->getFormFieldsDHLSettings() as $form_field) {
            $forms[] = $helper->generateForm(array($form_field));
        }

        return implode('', $forms);
    }
    protected function getFormFieldsDHLSettings()
    {
        $form_fields = array();

        $vcoa_options = array();
        foreach ($this->module->getVisualCheckOfAgeOptions() as $option_key => $option_value) {
            $vcoa_options[] = array(
                'value' => $option_key,
                'name' => $option_value,
            );
        }

        $this->module->dhldp_api_rest->setApiVersion(Configuration::get('DHLDP_DHL_API_VERSION'));

        $added_dhl_products = $this->module->getFormattedAddedDhlProducts(
            Tools::getValue('added_dhl_products', explode(';', Configuration::get('DHLDP_DHL_PRODUCTS')))
        );

        $dimensions = [];
        foreach ($added_dhl_products as $product) {
            if (!empty($product['fullcode'])) {
                $code = str_replace(':', '_', $product['fullcode']);
                $name = $product['name'];
                $dimensions[$code] = [
                    'title' => $name,
                    'weight' => Configuration::get('DHLDP_DHL_' . $code . '_WEIGHT'),
                    'length' => Configuration::get('DHLDP_DHL_' . $code . '_LENGTH'),
                    'width' => Configuration::get('DHLDP_DHL_' . $code . '_WIDTH'),
                    'height' => Configuration::get('DHLDP_DHL_' . $code . '_HEIGHT')
                ];
            }
        }

        $fields = [
            'weight' => ['label' => 'Weight', 'suffix' => 'kg'],
            'length' => ['label' => 'Length', 'suffix' => 'cm'],
            'width' => ['label' => 'Width', 'suffix' => 'cm'],
            'height' => ['label' => 'Height', 'suffix' => 'cm'],
        ];

        $inputs = [];

        foreach ($dimensions as $alias => $product) {
            foreach ($fields as $param => $options) {
                $inputs[] = [
                    'type' => 'text',
                    'name' => 'DHLDP_DHL_' . strtoupper($alias) . '_' . strtoupper($param),
                    'label' => $this->module->l("Default {$options['label']} for {$alias}"),
                    //'desc'     => $this->module->l("Used when no {$options['label']} is defined for the order items."),
                    'maxlength' => 8,
                    'suffix' => $this->module->l($options['suffix']),
                    'class' => 'fixed-width-sm',
                ];
            }
        }

        $form_fields = array_merge(
            $form_fields,
            array(
                'form' => array(
                    'form' => array(
                        'id_form' => 'dhl_global_settings',
                        'legend' => array(
                            'title' => $this->module->l('Global settings'),
                            'icon' => 'icon-circle',
                        ),
                        'description' => $this->module->l('Please select mode and fill form with all relevant information regarding authentication in modes.'),
                        'input' => array(
                            array(
                                'name' => 'DHLDP_DHL_MODE',
                                'type' => 'radio',
                                'label' => $this->module->l('Mode'),
                                'desc' => $this->module->l('Select "Sandbox" for testing'),
                                'class' => 't',
                                'values' => array(
                                    array(
                                        'id' => 'dhl_mode_live',
                                        'value' => 1,
                                        'label' => $this->module->l('Live')
                                    ),
                                    array(
                                        'id' => 'dhl_mode_sbx',
                                        'value' => 0,
                                        'label' => $this->module->l('Sandbox')
                                    ),
                                ),
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LIVE_USER',
                                'type' => 'text',
                                'label' => $this->module->l('Username'),
                                'desc' => $this->module->l('"Live" username for user authentication for business customer shipping API'),
                                'required' => true,
                                'form_group_class' => 'dhl_authdata_live'
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LIVE_SIGN',
                                'type' => 'text',
                                'label' => $this->module->l('Signature'),
                                'desc' => $this->module->l('"Live" signature for user authentication for business customer shipping API'),
                                'required' => true,
                                'form_group_class' => 'dhl_authdata_live'
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LIVE_EKP',
                                'type' => 'text',
                                'label' => $this->module->l('EKP'),
                                'desc' => $this->module->l('"Live" DHL customer number'),
                                'required' => true,
                                'form_group_class' => 'dhl_authdata_live'
                            ),
                            array(
                                'type' => 'free',
                                'label' => '',
                                'name' => 'DHLDP_DHL_LIVE_RESET',
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LOG',
                                'type' => 'radio',
                                'label' => $this->module->l('Enable Log'),
                                'desc' => $this->module->l('Logs of actions in') . ' ' . DIRECTORY_SEPARATOR . 'logs ' .
                                    $this->module->l('directory. Please notice: logs information can take a lot of disk space after a time.'),
                                'class' => 't',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'log_yes',
                                        'value' => 1,
                                        'label' => $this->module->l('Yes')
                                    ),
                                    array(
                                        'id' => 'log_no',
                                        'value' => 0,
                                        'label' => $this->module->l('No')
                                    ),
                                ),
                            ),
                            array(
                                'type' => 'free',
                                'name' => 'log_information',
                            ),
                            array(
                                'type' => 'free',
                                'label' => $this->module->l('Carriers'),
                                'name' => 'carrier_list',
                            ),
                            array(
                                'type' => 'free',
                                'label' => $this->module->l('New carrier'),
                                'name' => 'add_carrier',
                                'desc' => $this->module->l('If you do not have a carrier'),
                            ),
                        ),
                        'submit' => array(
                            'title' => $this->module->l('Save'),
                            'name' => 'submitSaveAuthOptions',
                        )
                    )
                ),
                'form2' => array(
                    'form' => array(
                        'id_form' => 'dhl_products',
                        'legend' => array(
                            'title' => $this->module->l('DHL products'),
                            'icon' => 'icon-circle',
                        ),
                        'description' => $this->module->l('Please enter the last two digits (e.g. 01, 02 or similar) of the settlement numbers. You can find them in your account under Contract data overview.'),
                        'input' => array(
                            array(
                                'name' => 'dhl_products',
                                'type' => 'free',
                                'label' => $this->module->l('DHL Products'),
                                'class' => 't',
                            ),
                            array(
                                'class' => 'fixed-width-xs',
                                'name' => 'DHLDP_DHL_RETURN_PARTICIPATION',
                                'type' => 'text',
                                'label' => $this->module->l('Participation number for return shipment account number'),
                                'desc' => $this->module->l('Max. 2 digits. 01 by default.'),
                                'maxlength' => 2
                            ),
                        ),
                        'submit' => array(
                            'title' => $this->module->l('Save'),
                            'name' => 'submitSaveProductsOptions',
                        )
                    )
                ),
                'form3' => array(
                    'form' => array(
                        'id_form' => 'dhl_misc_settings',
                        'legend' => array(
                            'title' => $this->module->l('Miscellaneous settings'),
                            'icon' => 'icon-truck'
                        ),
                        'input' => array(
                            array(
                                'type' => 'radio',
                                'label' => $this->module->l('Reference number in label is '),
                                'name' => 'DHLDP_DHL_REF_NUMBER',
                                'required' => true,
                                'class' => 't',
                                'br' => true,
                                'values' => array(
                                    array(
                                        'id' => 'order_ref',
                                        'value' => 0,
                                        'label' => $this->module->l('Order reference')
                                    ),
                                    array(
                                        'id' => 'order_number',
                                        'value' => 1,
                                        'label' => $this->module->l('Order ID')
                                    )
                                )
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_ORDER_WEIGHT',
                                'label' => $this->module->l('Enable calculating weight of package'),
                                'desc' => $this->module->l('Enable calculating weight of package in according with weight of products in order'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_ORDER_WEIGHT_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_ORDER_WEIGHT_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'type' => 'text',
                                'name' => 'DHLDP_DHL_WEIGHT_RATE',
                                'label' => sprintf($this->module->l('Rate of converting shop weight unit in kg. Current shop weight unit is %s'), Configuration::get('PS_WEIGHT_UNIT')),
                                'desc' => $this->module->l('Rate of converting shop weight unit in kg. If shop weight unit is gramm(g), then rate have to be 0,001 . If shop weight unit is kilogramm(kg), then rate have to be 1(or empty). If rate is empty then weigth will not be recalculated.'),
                            ),
//                            array(
//                                'type'     => 'text',
//                                'name'     => 'DHLDP_DHL_DEFAULT_WEIGHT',
//                                'label'    => $this->module->l('Default weight of package'),
//                                'desc'     => $this->module->l('If weight of products in order is not filled(sum of product weights is zero), then this default weight will be used'),
//                                'maxlength' => 8,
//                                'suffix' => $this->module->l('kg'),
//                                'class' => 'fixed-width-sm'
//                            ),
                            array(
                                'type' => 'text',
                                'name' => 'DHLDP_DHL_PACK_WEIGHT',
                                'label' => $this->module->l('Weight of pack'),
                                'desc' => $this->module->l('If weight of products in order is filled(sum of product weights is not zero), then weight of pack will be also applied'),
                                'maxlength' => 8,
                                'suffix' => $this->module->l('kg'),
                                'class' => 'fixed-width-sm'
                            ),
                            array(
                                'type' => 'free',
                                'label' => $this->module->l('DHL Product dimensions'),
                                'name' => 'dhl-product-dimensions',
                            ),
//                            array(
//                                'type'     => 'text',
//                                'name'     => 'DHLDP_DHL_DEFAULT_LENGTH',
//                                'label'    => $this->module->l('Default length of package'),
//                                'maxlength' => 8,
//                                'suffix' => $this->module->l('cm'),
//                                'class' => 'fixed-width-sm'
//                            ),
//                            array(
//                                'type'     => 'text',
//                                'name'     => 'DHLDP_DHL_DEFAULT_WIDTH',
//                                'label'    => $this->module->l('Default width of package'),
//                                'maxlength' => 8,
//                                'suffix' => $this->module->l('cm'),
//                                'class' => 'fixed-width-sm'
//                            ),
//                            array(
//                                'type'     => 'text',
//                                'name'     => 'DHLDP_DHL_DEFAULT_HEIGHT',
//                                'label'    => $this->module->l('Default height of package'),
//                                'maxlength' => 8,
//                                'suffix' => $this->module->l('cm'),
//                                'class' => 'fixed-width-sm'
//                            ),
                            array(
                                'type' => 'select',
                                'name' => 'DHLDP_DHL_AGE_CHECK',
                                'label' => $this->module->l('Select default age for age checking '),
                                'desc' => $this->module->l('The visual check of age service ensures in an uncomplicated and convenient way that your parcels are not delivered to minors. The service takes care of particular aspects of the protection of minors, e.g., when sending alcoholic drinks, CDs/DVDs with an age limit, PC and console games, or medicines requiring a doctor prescription'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'disabled' => false,
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'value' => '',
                                                'name' => $this->module->l('-- Do not check --')
                                            ),
                                        ),
                                        $vcoa_options
                                    ),
                                    'id' => 'value',
                                    'name' => 'name'
                                )
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_PFPS',
                                'label' => $this->module->l('Enable DHL Postfiliales and DHL Packstations'),
                                'desc' => $this->module->l('Including extension for addresses of customer'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_PFPS_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_PFPS_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_PFPS_MAP',
                                'label' => $this->module->l('Show google map'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_PFPS_MAP_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_PFPS_MAP_off',
                                        'value' => 0,
                                    )
                                ),
                                'form_group_class' => 'dhl_pfps_map dhlp_new_release'
                            ),
                            array(
                                'type' => 'text',
                                'name' => 'DHLDP_DHL_GOOGLEMAPAPIKEY',
                                'label' => $this->module->l('Google Map API key'),
                                'desc' => $this->module->l('Google API key is used for showing map with locations of DHL Postfiliales and DHL Packstations. It is required if you set enabled DHL Postfiliales and DHL Packstations.'),
                                'form_group_class' => 'dhl_googlemapapikey'
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_INTRANSIT_MAIL',
                                'label' => $this->module->l('Enable sending "Package in transit" mail after generating label'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_INTRANSIT_MAIL_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_INTRANSIT_MAIL_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'type' => 'select',
                                'label' => $this->module->l('Enable updating order status'),
                                'name' => 'DHLDP_DHL_CHANGE_OS',
                                'desc' => $this->module->l('Order status will be changed "Shipped" automatically after creating DHL label'),
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'id_order_state' => '',
                                                'name' => $this->module->l('-- Do not change --')
                                            )
                                        ),
                                        $this->module->getShippedOrderStates()
                                    ),
                                    'id' => 'id_order_state',
                                    'name' => 'name'
                                )
                            ),
                            /*array(
                                'type'    => 'select',
                                'label'   => $this->module->l('Enable updating order status, if shipment has been delivered'),
                                'name'    => 'DHLDP_DHL_CHANGE_OS_DELIVERED',
                                'desc'    => sprintf($this->module->l('Status of shipment will be checked if you get tracking data on DHL by clicking on "Update tracking data" button of package or you can use cron job. For example, this cron job will get tracking data at 1:00am: 0 1 * * * php -f %s'), $this->getLocalPath() . 'cron_track.php secure_key=' . Configuration::get('DHLDP_DHL_SECURE_KEY').' id_shop='.$this->context->shop->id),
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'id_order_state' => '',
                                                'name'           => $this->module->l('-- Do not change --')
                                            )
                                        ),
                                        $this->getShippedOrderStates()
                                    ),
                                    'id'    => 'id_order_state',
                                    'name'  => 'name'
                                ),
                                'form_group_class' => 'dhlp_new_release',
                                'disabled' => true
                            ),
                            array(
                                'name' => 'DHLDP_DHL_SECURE_KEY',
                                'type' => 'text',
                                'label' => $this->module->l('Secure key for running cron job task'),
                                'form_group_class' => 'dhlp_new_release'
                            ),*/
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_CREATE_MANIFEST_IN_ORDER',
                                'label' => $this->module->l('Enable manifest creation in order.'),
                                'desc' => $this->module->l('If you enable it, the order will have a button to create a manifest in the order.'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_CREATE_MANIFEST_IN_ORDER_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_CREATE_MANIFEST_IN_ORDER_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'name' => 'DHLDP_DHL_CONFIRMATION_PRIVATE',
                                'label' => $this->module->l('Enable customer confirmation for permission transferring private information to DHL service'),
                                'desc' => $this->module->l('If you are enable it, then shop will ask customer permission for sending e-mail address and phone number to DHL service in frontend. If you disabled it, then e-mail address and phone number will be sent to DHL service by default.'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_CONFIRMATION_PRIVATE_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_CONFIRMATION_PRIVATE_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'name' => 'DHLDP_DHL_IND_NOTIF',
                                'type' => 'free'
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LABEL_WITH_RETURN',
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'label' => $this->module->l('Enable generate label with return label'),
                                'desc' => $this->module->l('This option adds enclosed return label to generated label. Your customers receive a fully prepared return label with their delivery. If they choose to send an item back, all they have to do is pack it and affix the label. Supported products: DHL Paket, DHL Paket Austria, DHL Paket Taggleich, DHL Kurier Taggleich, DHL Karier Wunschzeit'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_LABEL_WITH_RETURN_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_LABEL_WITH_RETURN_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LABEL_IGNORE_WARNING',
                                'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                                'label' => $this->module->l('Auto generate label with Warning'),
                                'desc' => $this->module->l('Enable jobs creation even if a warning appears, if the future is active, create the label immediately without a confirmation step. However, the warning will still be displayed after creation'),
                                'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                                'is_bool' => true,
                                'disabled' => false,
                                'values' => array(
                                    array(
                                        'id' => 'DHLDP_DHL_LABEL_IGNORE_WARNING_on',
                                        'value' => 1,
                                    ),
                                    array(
                                        'id' => 'DHLDP_DHL_LABEL_IGNORE_WARNING_off',
                                        'value' => 0,
                                    )
                                ),
                            ),
                            array(
                                'name' => 'DHLDP_DHL_LABEL_FORMAT',
                                'type' => 'select',
                                'label' => $this->module->l('Label format'),
                                'disabled' => false,
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'id' => '',
                                                'name' => $this->module->l('-- By default --')
                                            )
                                        ),
                                        $this->getAssocArrayOptionsForSelect($this->module->getLabelFormats(), 'name,desc')
                                    ),
                                    'id' => 'id',
                                    'name' => 'name'
                                )
                            ),
                            array(
                                'name' => 'DHLDP_DHL_RETOURE_LABEL_FORMAT',
                                'type' => 'select',
                                'label' => $this->module->l('Retoure label format'),
                                'disabled' => false,
                                'options' => array(
                                    'query' => array_merge(
                                        array(
                                            array(
                                                'id' => '',
                                                'name' => $this->module->l('-- By default --')
                                            )
                                        ),
                                        $this->getAssocArrayOptionsForSelect($this->module->getRetoureLabelFormats(), 'name,desc')
                                    ),
                                    'id' => 'id',
                                    'name' => 'name'
                                )
                            ),
                            array(
                                'type' => 'text',
                                'label' => $this->module->l('E-mail address of HP ePrint printer'),
                                'name' => 'DHLDP_DHL_EPRINT_EMAIL',
                                'required' => false,
                                'size' => 35,
                                'desc' => $this->module->l('Enable sending mail with PDF after generating to HP ePrint printer'),
                                'form_group_class' => 'dhlp_new_release'
                            ),
                        ),
                        'submit' => array(
                            'title' => $this->module->l('Save'),
                            'name' => 'submitSaveMiscOptions',
                        )
                    )
                )
            )
        );

        $form_fields['form35'] = array(
            'form' => array(
                'id_form' => 'dhl_additional_services_defaults',
                'legend' => array(
                    'title' => $this->module->l('Default settings for additional services'),
                    'icon' => 'icon-truck'
                ),
                'input' => array(
                    array(
                        'name' => 'DHLDP_DHL_DEF_PARCEL_ROUT_SERV',
                        'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                        'label' => $this->module->l('Enable \'Parcel outlet routing\' service by default'),
                        'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                        'is_bool' => true,
                        'disabled' => false,
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_DEF_PARCEL_ROUT_SERV_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_DEF_PARCEL_ROUT_SERV_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'dhlp_new_release'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_DEF_GOGREEN',
                        'type' => 'switch',
                        'label' => $this->module->l('GoGreen'),
                        'is_bool' => true,
                        'disabled' => false,
                        'desc' => $this->module->l('You will make a sustainable contribution towards climate protection by offsetting the CO2 e-emissions generated during the transportation of your items. Extra charge in addition to the price of a parcel.'),
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_DEF_GOGREEN_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_DEF_GOGREEN_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'dhlp_new_release'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_PREMIUM',
                        'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                        'label' => $this->module->l('choosing premium service for \'Warenpost international\''),
                        'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                        'is_bool' => true,
                        'disabled' => false,
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_PREMIUM_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_PREMIUM_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'dhlp_new_release'
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->module->l('Invoice number of export document is'),
                        'name' => 'DHLDP_DHL_EXP_INV_NUM',
                        'required' => true,
                        'class' => 't',
                        'br' => true,
                        'values' => array(
                            array(
                                'id' => 'expinvnum_nothing',
                                'value' => 0,
                                'label' => $this->module->l('Nothing')
                            ),
                            array(
                                'id' => 'expinvnum_orderref',
                                'value' => 1,
                                'label' => $this->module->l('Order reference')
                            ),
                            array(
                                'id' => 'expinvnum_invoicenumber',
                                'value' => 2,
                                'label' => $this->module->l('Invoice number')
                            ),
                        ),
                        'form_group_class' => 'dhlp_new_release'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_DEF_CUSTOMS_TARIFF_NUM',
                        'type' => 'text',
                        'label' => $this->module->l('Default customs tariff number of products'),
                        'form_group_class' => 'dhlp_new_release',
                        'maxlength' => 11
                    ),
                    array(
                        'name' => 'DHLDP_DHL_DEF_PLACE_OF_COMMITAL',
                        'type' => 'text',
                        'label' => $this->module->l('Default place of committal'),
                        'form_group_class' => 'dhlp_new_release',
                        'maxlength' => 35
                    ),
                    array(
                        'name' => 'DHLDP_DHL_DEF_ADDITIONAL_CUSTOM_FEES',
                        'type' => 'text',
                        'label' => $this->module->l('Default additional custom fees'),
                        'form_group_class' => 'dhlp_new_release',
                        'desc' => $this->module->l('Enter the default amount for additional custom fees.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->module->l('Save'),
                    'name' => 'submitSaveAdditionalServicesOptions',
                )
            )
        );

        $form_fields['form4'] = array(
            'form' => array(
                'id_form' => 'dhl_retoure_settings',
                'legend' => array(
                    'title' => $this->module->l('DHL Retoure settings and additional settings for Merchandise return (RMA)'),
                    'icon' => 'icon-truck'
                ),
                'description' => $this->module->l('If you enable returns in shop on "Sell/Customer service/Merchandise returns/Merchandise return (RMA) options/Enable returns", then you will possibility to pass Return Labels automatically.'),
                'input' => array(
                    array(
                        'name' => 'DHLDP_DHL_RETURNS_EXTEND',
                        'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                        'label' => $this->module->l('Enable extending management of returns in shop'),
                        'desc' => $this->module->l('Enable sending Return label on return request of customer'),
                        'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                        'is_bool' => true,
                        'disabled' => !Configuration::get('PS_ORDER_RETURN'),
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_RETURNS_EXTEND_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_RETURNS_EXTEND_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'dhl_returns_extend',
                    ),
                    array(
                        'name' => 'DHLDP_DHL_RETURNS_IMMED',
                        'type' => (_PS_VERSION_ < '1.6.0.0') ? 'radio' : 'switch',
                        'label' => $this->module->l('Enable sending Return Label immediately'),
                        'desc' => $this->module->l('Enable sending Return label on return request of customer immediately without approving by shop administrator'),
                        'class' => (_PS_VERSION_ < '1.6.0.0') ? 't' : '',
                        'is_bool' => true,
                        'disabled' => !Configuration::get('PS_ORDER_RETURN'),
                        'values' => array(
                            array(
                                'id' => 'DHLDP_DHL_RETURNS_IMMED_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'DHLDP_DHL_RETURNS_IMMED_off',
                                'value' => 0,
                            )
                        ),
                        'form_group_class' => 'hide dhldp_dhl_ra',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->module->l('Countries'),
                        'name' => 'DHLDP_DHL_RA_COUNTRIES',
                        'desc' => $this->module->l('Enable creating Return label for countries of sender address'),
                        'required' => true,
                        'multiple' => true,
                        'form_group_class' => 'hide dhldp_dhl_ra',
                        'class' => 'select2',
                        'options' => array(
                            'query' => $this->getCountriesForRA($this->context->language->id),
                            'id' => 'iso_code',
                            'name' => 'name'
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->module->l('Save'),
                    'name' => 'submitSaveRetoureOptions',
                )
            )
        );


        $form_fields['form5'] = array(
            'form' => array(
                'id_form' => 'dhl_address',
                'legend' => array(
                    'title' => $this->module->l('Address'),
                    'icon' => 'icon-circle',
                ),
                'description' => $this->module->l('Please enter address of shop'),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->module->l('Shipper address'),
                        'name' => 'DHLDP_DHL_SHIPPER_TYPE',
                        'desc' => $this->module->l('You have possibility enter shipper address or use shipper reference (valid shipper reference from your GKP) to use address from GKP. 
                        If you will use shipper reference, then you will get possibility to set company logo from GKP on shipment label.'),
                        'required' => true,
                        'options' => array(
                            'query' => array(
                                array('code' => 0, 'name' => $this->module->l('Fill shipper address')),
                                array('code' => 1, 'name' => $this->module->l('Get shipper address from GKP by reference'))
                            ),
                            'id' => 'code',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'name' => 'DHLDP_DHL_COMPANY_NAME_1',
                        'type' => 'text',
                        'label' => $this->module->l('Company'),
                        'desc' => $this->module->l('Max. 35 characters'),
                        'required' => true,
                        'maxlength' => 35,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_COMPANY_NAME_2',
                        'type' => 'text',
                        'label' => $this->module->l('Company 2'),
                        'desc' => $this->module->l('Max. 35 characters'),
                        'required' => true,
                        'maxlength' => 35,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_CONTACT_PERSON',
                        'type' => 'text',
                        'label' => $this->module->l('Contact person'),
                        'desc' => $this->module->l('Max. 50 characters'),
                        'required' => false,
                        'maxlength' => 50,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_STREET_NAME',
                        'type' => 'text',
                        'label' => $this->module->l('Street'),
                        'desc' => $this->module->l('Max. 35 characters'),
                        'required' => true,
                        'maxlength' => 35,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_STREET_NUMBER',
                        'type' => 'text',
                        'label' => $this->module->l('House number'),
                        'desc' => $this->module->l('Max. 5 characters'),
                        'required' => true,
                        'maxlength' => 5,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_ZIP',
                        'type' => 'text',
                        'label' => $this->module->l('Postcode'),
                        'desc' => $this->module->l('Max. 10 characters'),
                        'required' => true,
                        'maxlength' => 10,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_CITY',
                        'type' => 'text',
                        'label' => $this->module->l('City'),
                        'desc' => $this->module->l('Max. 35 characters'),
                        'required' => true,
                        'maxlength' => 35,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_COUNTRY',
                        'type' => 'free',
                        'label' => $this->module->l('Country'),
                        'disabled' => true,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_STATE',
                        'type' => 'text',
                        'label' => $this->module->l('State'),
                        'desc' => $this->module->l('Max. 30 characters'),
                        'required' => false,
                        'maxlength' => 30,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_PHONE',
                        'type' => 'text',
                        'label' => $this->module->l('Phone'),
                        'desc' => $this->module->l('Max. 20 characters'),
                        'required' => true,
                        'maxlength' => 20,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_EMAIL',
                        'type' => 'text',
                        'label' => $this->module->l('E-mail'),
                        'desc' => $this->module->l('Max. 70 characters'),
                        'required' => true,
                        'maxlength' => 70,
                        'form_group_class' => 'dhl_shipper_by_address'
                    ),
                    array(
                        'name' => 'DHLDP_DHL_REFERENCE',
                        'type' => 'text',
                        'label' => $this->module->l('Shipper reference'),
                        'desc' => $this->module->l('Max. 50 characters. Contains a reference to the Shipper data configured in GKP.'),
                        'required' => true,
                        'maxlength' => 50,
                        'form_group_class' => 'dhl_shipper_by_reference'
                    ),
                ),
                'submit' => array(
                    'title' => $this->module->l('Save'),
                    'name' => 'submitSaveAddressOptions',
                )
            )
        );

        $form_fields['form6'] = array(
            'form' => array(
                'id_form' => 'dhl_bankdata',
                'legend' => array(
                    'title' => $this->module->l('Bank data'),
                    'icon' => 'icon-circle',
                ),
                'description' => $this->module->l('Bank data can be provided here for different purposes. E.g. if COD is booked as service, bank data must be provided by DHL customer (mandatory server logic). The collected money will be transferred to specified bank account.'),
                'input' => array(
                    array(
                        'name' => 'DHLDP_DHL_ACCOUNT_OWNER',
                        'type' => 'text',
                        'label' => $this->module->l('Account owner'),
                        'desc' => $this->module->l('Max. 30 characters'),
                        'required' => false,
                        'maxlength' => 30,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_BANK_NAME',
                        'type' => 'text',
                        'label' => $this->module->l('Bank name'),
                        'desc' => $this->module->l('Max. 30 characters'),
                        'required' => false,
                        'maxlength' => 30,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_IBAN',
                        'type' => 'text',
                        'label' => $this->module->l('IBAN'),
                        'desc' => $this->module->l('Max. 34 characters'),
                        'required' => false,
                        'maxlength' => 34,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_BIC',
                        'type' => 'text',
                        'label' => $this->module->l('BIC'),
                        'desc' => $this->module->l('Max. 11 characters'),
                        'required' => false,
                        'maxlength' => 11,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_NOTE',
                        'type' => 'text',
                        'label' => $this->module->l('Note'),
                        'desc' => $this->module->l('Max. 35 characters. Use [order_reference_number] in note for adding Order ID or Order Reference in according with "Reference number in label is" setting. Example, "Bestellnummer [order_reference_number]" will add "Bestellnummer KHWLILZLL" in note.'),
                        'required' => false,
                        'maxlength' => 35,
                    ),
                    array(
                        'name' => 'DHLDP_DHL_NOTE2',
                        'type' => 'text',
                        'label' => $this->module->l('Note 2'),
                        'desc' => $this->module->l('Max. 35 characters. Use [order_reference_number] in note for adding Order ID or Order Reference in according with "Reference number in label is" setting. Example, "Bestellnummer [order_reference_number]" will add "Bestellnummer KHWLILZLL" in note.'),
                        'required' => false,
                        'maxlength' => 35,
                        'form_group_class' => 'dhlp_new_release'
                    ),
                ),
                'submit' => array(
                    'title' => $this->module->l('Save'),
                    'name' => 'submitSaveBankOptions',
                )
            )
        );

        return $form_fields;
    }
    private function getAssocArrayOptionsForSelect($array, $name_assoc = '')
    {
        if ($name_assoc != '') {
            $name_assoc = explode(',', $name_assoc);
        }
        if (is_array($array)) {
            $arr = array();
            foreach ($array as $key => $value) {
                $name_str = '';
                if (is_array($name_assoc)) {
                    foreach ($name_assoc as $name_assoc_val) {
                        if ($name_assoc != '' && isset($value[$name_assoc_val])) {
                            $name_str .= (($name_str != '') ? ' - ' : '') . $value[$name_assoc_val];
                        }
                    }
                }
                if ($name_str != '') {
                    $arr[] = array('id' => $key, 'name' => $name_str);
                } else {
                    $arr[] = array('id' => $key, 'name' => $value);
                }
            }
            return $arr;
        }
        return [];
    }
    public function getCountriesForRA($id_lang, $limited = array(), $with_keys = false)
    {
        $c = array();
        if (count($limited) == 0) {
            $res = $this->module->getCountriesIDsForRA();
            foreach ($res as $iso_code => $item) {
                $c[] = '\'' . $iso_code . '\'';
            }
        } else {
            $res = $limited;
            foreach ($res as $item) {
                $c[] = '\'' . $item . '\'';
            }
        }

        $countries = Db::getInstance(_PS_USE_SQL_SLAVE_)->executes(
            'SELECT cl.`name`, c.iso_code
							FROM `' . _DB_PREFIX_ . 'country_lang` as cl, `' . _DB_PREFIX_ . 'country` as c
							WHERE cl.id_country=c.id_country and cl.`id_lang` = ' . (int)$id_lang . '
							and c.iso_code in (' . implode(',', $c) . ')');
        if ($with_keys) {
            $c = array();
            foreach ($countries as $country) {
                $c[$country['iso_code']] = $country['name'];
            }
            return $c;
        }
        return $countries;
    }
    protected function setFormFieldsValue(&$helper, $keys)
    {
        if (is_array($keys)) {
            foreach ($keys as $key) {
                $helper->fields_value['DHLDP_' . $key] = Tools::getValue('DHLDP_' . $key, Configuration::get('DHLDP_' . $key));
            }
        }
    }
    private function displayDHLLogInformation()
{
    $this->context->smarty->assign(array(
        'general_log_file_path' => AdminController::$currentIndex . '&configure=' . $this->module->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dhl&log_file=dhl_general',
        'api_log_file_path' => AdminController::$currentIndex . '&configure=' . $this->module->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dhl&log_file=dhl_api',
        'api_log_file_path_clear' => AdminController::$currentIndex . '&configure=' . $this->module->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&view=settings_dhl&log_file=dhl_api_clear',
    ));

    return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/log_information.tpl');
}

    public function displayFormInitDHLSettings()
    {
        $helper = new HelperForm();
        $helper->required = false;
        $helper->id = Tab::getCurrentTabId(); // Tab::getCurrentTabId();
//        $helper->id = null; // Tab::getCurrentTabId();
        $helper->currentIndex = AdminController::$currentIndex . '&view=init_dhl';
        $helper->table = 'dhldp_ini_configure';
        $helper->token = Tools::getValue('token');
        $helper->module = $this;
        $helper->identifier = null;
        $helper->toolbar_btn = null;
        $helper->ps_help_context = null;
        $helper->title = null;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = false;
        $helper->bootstrap = true;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->fields_value['DHLDP_DHL_API_VERSION'] = Tools::getValue('DHLDP_DHL_API_VERSION', Configuration::get('DHLDP_DHL_API_VERSION') ? Configuration::get('DHLDP_DHL_API_VERSION') : '2.1');
        $helper->fields_value['DHLDP_DHL_COUNTRY'] = Tools::getValue('DHLDP_DHL_COUNTRY', Configuration::get('DHLDP_DHL_COUNTRY'));
        return $helper->generateForm($this->getFormFieldsInitDHLSettings());
    }

    protected function getFormFieldsInitDHLSettings()
    {
        $form_fields = [];

        $api_versions = DHLDPApiRest::getSupportedApiVersions();
        $api_version_options = array(
            'id' => 'value',
            'name' => 'label'
        );
        foreach ($api_versions as $apiv) {
            $api_version_options['query'][] = array(
                'value' => $apiv,
                'label' => $apiv
            );
        }

        $shipper_country_options = array(
            'id' => 'value',
            'name' => 'label'
        );
        foreach (array_keys(DHLDPApiRest::$supported_shipper_countries) as $iso_code) {
            $shipper_country_options['query'][] = array(
                'value' => $iso_code,
                'label' => Country::getNameById($this->context->language->id, Country::getByIso($iso_code))
            );
        }

        $form_fields = array_merge(
            $form_fields,
            array(
                'form' => array(
                    'form' => array(
                        'id_form' => 'dhldp_init_settings',
                        'legend' => array(
                            'title' => $this->module->l('DHL init settings'),
                            'icon' => 'icon-circle',
                        ),
                        'description' => $this->module->l('Please select shipper country and version of DHL API.'),
                        'input' => array(
                            array(
                                'name' => 'DHLDP_DHL_COUNTRY',
                                'type' => 'select',
                                'label' => $this->module->l('Country'),
                                'required' => true,
                                'options' => $shipper_country_options
                            ),
                        ),
                        'submit' => array(
                            'title' => $this->module->l('Save'),
                            'name' => 'submitSaveOptions',
                        )
                    )
                ),
            )
        );
        return $form_fields;
    }
    protected function l($str, $class = null, $addslashes = false, $htmlentities = true)
    {
        return $this->trans($str, [], 'Module.Orderedit.Admin');
    }
}
