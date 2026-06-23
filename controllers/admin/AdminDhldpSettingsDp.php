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

require_once _PS_MODULE_DIR_ . 'dhldp/classes/DPRestApi.php';

class AdminDhldpSettingsDpController extends ModuleAdminController
{
    /** @var DPRestApi */
    protected $dp_api;
    protected $dpErrors = [];
    protected $dpConfirmations = [];

    public function __construct()
    {
        $this->table = '';
        $this->bootstrap = true;
        $this->show_toolbar = false;
        $this->multishop_context = Shop::CONTEXT_SHOP;
        $this->context = Context::getContext();

        parent::__construct();

        $this->dp_api = new DPRestApi();
    }

    public function initContent()
    {
        if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
            $this->displayInformation($this->module->l('You can only display the page in a shop context.'));
            return;
        }

        $this->postProcess();
        $this->content .= $this->module->postProcess();// #FIXME
        $this->content .= $this->renderMessages();
        $this->content .= $this->module->displayMenu();
        $this->content .= $this->displayFormDPSettings();
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

    protected function getAdminControllerLink($controller, array $params = array(), $with_token = false)
    {
        $url = $this->context->link->getAdminLink($controller, $with_token);

        foreach ($params as $key => $value) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . urlencode($key) . '=' . urlencode($value);
        }

        return $url;
    }

    protected function getCurrentToken()
    {
        return Tools::getValue('token', Tools::getAdminTokenLite($this->controller_name));
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitDPGetProductList')) {
            if ($this->dp_api->getProductList()) {
                $this->dpConfirmations[] = $this->module->l('Product list has been updated successfully');
            } else {
                $this->dpErrors[] = $this->module->l('Product list updating has been failed');
            }
            if ($this->dp_api->retrieveContractProducts()) {
                $this->dpConfirmations[] = $this->module->l('Contract product list has been updated successfully');
            } else {
                $this->dpErrors[] = $this->module->l('Contract product list updating has been failed');
            }
        }

        if (Tools::isSubmit('submitDPRetrievePageFormats')) {
            if ($this->dp_api->retrievePageFormats()) {
                $this->dpConfirmations[] = $this->module->l('Page formats has been retrieved successfully');
            } else {
                $this->dpErrors[] = $this->module->l('Page formats retrieving is failed');
            }
        }

        if (Tools::isSubmit('submitSaveDPOptionsGlobal')) {
            $this->processGlobalSettings();
        }

        if (Tools::isSubmit('submitSaveDPAddressOptions')) {
            $this->processAddressSettings();
        }

        if (count($this->dpErrors) == 0) {
            $this->dpConfirmations[] = $this->module->l('Settings updated');
        }
    }

    protected function processGlobalSettings()
    {
        $deutschepost_mode = 1;
        $deutschepost_live_username = Tools::getValue('DHLDP_DP_LIVE_USERNAME');
        $deutschepost_live_password = Tools::getValue('DHLDP_DP_LIVE_PASSWORD');
        $deutschepost_sbx_username = Tools::getValue('DHLDP_DP_SBX_USERNAME');
        $deutschepost_sbx_password = Tools::getValue('DHLDP_DP_SBX_PASSWORD');
        $deutschepost_carriers = Tools::getValue('deutschepost_carriers', array());
        $deutschepost_log = Tools::getValue('DHLDP_DP_LOG');

        if (!in_array($deutschepost_mode, array('0', '1'))) {
            $this->dpErrors[] = $this->module->l('Please select mode');
        }

        if (($deutschepost_mode == '0' && $deutschepost_sbx_username == '') ||
            ($deutschepost_mode == '1' && $deutschepost_live_username == '')
        ) {
            $this->dpErrors[] = $this->module->l('Please fill user name');
        }

        if (($deutschepost_mode == '0' && $deutschepost_sbx_password == '') ||
            ($deutschepost_mode == '1' && $deutschepost_live_password == '')
        ) {
            $this->dpErrors[] = $this->module->l('Please fill password');
        }

        if (count($this->dpErrors) == 0) {
            $check_client = $this->dp_api->authenticateUser(
                $deutschepost_mode,
                DPRestApi::$partnerid,
                DPRestApi::$keyphase,
                DPRestApi::$apikey,
                ($deutschepost_mode == 1) ? $deutschepost_live_username : $deutschepost_sbx_username,
                ($deutschepost_mode == 1) ? $deutschepost_live_password : $deutschepost_sbx_password
            );

            if (Tools::getValue('DHLDP_DP_LIVE_PASSWORD') != str_repeat('*', mb_strlen(Configuration::get('DHLDP_DP_LIVE_PASSWORD')))) {
                if (!is_object($check_client) || (!isset($check_client->userToken))) {
                    $this->dpErrors[] = $this->module->l('Authentication data is incorrect(' . implode(', ', $this->dp_api->errors) . ').');
                    DhlDp::logToFile(implode(', ', $this->dp_api->errors), 'general');
                } else {
                    Configuration::updateValue('DHLDP_DP_LIVE_PASSWORD', Tools::getValue('DHLDP_DP_LIVE_PASSWORD'));
                }
            }
        }

        if (!in_array($deutschepost_log, array('0', '1'))) {
            $this->dpErrors[] = $this->module->l('Please select log mode');
        }

        if (!in_array(Tools::getValue('DHLDP_DP_CREATE_MANIFEST'), array('0', '1'))) {
            $this->dpErrors[] = $this->module->l('Please select value for Enable creating manifest');
        }
        if (!in_array(Tools::getValue('DHLDP_DP_CREATE_SHIPLIST'), array('0', '1', '2'))) {
            $this->dpErrors[] = $this->module->l('Please select value for Enable creating shipping list');
        }
        if (!in_array(Tools::getValue('DHLDP_DP_LABEL_FORMAT'), array('png', 'pdf'))) {
            $this->dpErrors[] = $this->module->l('Please select format of label file');
        }
        $page_formats = json_decode(Configuration::getGlobalValue('DHLDP_DP_PAGE_FORMATS'), true);
        $page_formats_keys = is_array($page_formats) ? array_keys($page_formats, true) : array();
        if (count($page_formats_keys) == 0) {
            $page_formats_keys = array(1);
        }
        $page_format_id = (Tools::getValue('DHLDP_DP_PAGE_FORMAT', 1)) == '' ? 1 : Tools::getValue('DHLDP_DP_PAGE_FORMAT', 1);

        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && !in_array($page_format_id, $page_formats_keys)) {
            $this->dpErrors[] = $this->module->l('Please select valid page format for PDF document');
        }
        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_PAGE', 1) < 1)) {
            $this->dpErrors[] = $this->module->l('Please specify page by positive integer value');
        }
        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_ROW', 1) < 1)) {
            $this->dpErrors[] = $this->module->l('Please specify row on page by positive integer value');
        }
        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_COL', 1) < 1)) {
            $this->dpErrors[] = $this->module->l('Please specify column on page by positive integer value');
        }
        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && isset($page_formats[$page_format_id])) {
            $page_format = $page_formats[$page_format_id];
            if ((int)Tools::getValue('DHLDP_DP_POSITION_COL', 1) > $page_format['col']) {
                $this->dpErrors[] = $this->module->l('Column of Label position must be maximum') . ' ' . $page_format['col'];
            }
            if ((int)Tools::getValue('DHLDP_DP_POSITION_ROW', 1) > $page_format['row']) {
                $this->dpErrors[] = $this->module->l('Row of Label position must be maximum') . ' ' . $page_format['row'];
            }
        }

        if (count($this->dpErrors) == 0) {
            Configuration::updateValue('DHLDP_DP_MODE', (int)$deutschepost_mode);
            Configuration::updateValue('DHLDP_DP_LIVE_USERNAME', Tools::getValue('DHLDP_DP_LIVE_USERNAME'));
            Configuration::updateValue('DHLDP_DP_LOG', (int)Tools::getValue('DHLDP_DP_LOG', 0));
            Configuration::updateValue('DHLDP_DP_CARRIERS', implode(',', $deutschepost_carriers));
            Configuration::updateValue('DHLDP_DP_REF_NUMBER', (int)Tools::getValue('DHLDP_DP_REF_NUMBER'));
            Configuration::updateValue('DHLDP_DP_DEF_PRODUCT', (int)Tools::getValue('DHLDP_DP_DEF_PRODUCT', 0));
            Configuration::updateValue('DHLDP_DP_CHANGE_OS', (int)Tools::getValue('DHLDP_DP_CHANGE_OS', 0));
            Configuration::updateValue('DHLDP_DP_CREATE_MANIFEST', (int)Tools::getValue('DHLDP_DP_CREATE_MANIFEST'));
            Configuration::updateValue('DHLDP_DP_CREATE_SHIPLIST', (int)Tools::getValue('DHLDP_DP_CREATE_SHIPLIST'));
            Configuration::updateValue('DHLDP_DP_LABEL_FORMAT', Tools::getValue('DHLDP_DP_LABEL_FORMAT'));
            Configuration::updateValue('DHLDP_DP_PAGE_FORMAT', (int)$page_format_id);
            Configuration::updateValue('DHLDP_DP_POSITION_PAGE', (int)Tools::getValue('DHLDP_DP_POSITION_PAGE', 1));
            Configuration::updateValue('DHLDP_DP_POSITION_ROW', (int)Tools::getValue('DHLDP_DP_POSITION_ROW', 1));
            Configuration::updateValue('DHLDP_DP_POSITION_COL', (int)Tools::getValue('DHLDP_DP_POSITION_COL', 1));
        }
    }

    protected function processAddressSettings()
    {
        if (!in_array((int)Tools::getValue('DHLDP_DP_NAME', 0), array('0', '1'))) {
            $this->dpErrors[] = $this->module->l('Please select company or person in address');
        }

        if (Tools::getValue('DHLDP_DP_NAME') == 1 && Tools::strlen(Tools::getValue('DHLDP_DP_COMPANY')) > 50) {
            $this->dpErrors[] = $this->module->l('The company name is too long');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 1 && Tools::strlen(Tools::getValue('DHLDP_DP_COMPANY')) == 0) {
            $this->dpErrors[] = $this->module->l('The company name is required');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_FIRSTNAME')) > 30) {
            $this->dpErrors[] = $this->module->l('The first name is too long');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_FIRSTNAME')) == 0) {
            $this->dpErrors[] = $this->module->l('The first name is required');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_LASTNAME')) > 30) {
            $this->dpErrors[] = $this->module->l('The last name is too long');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_LASTNAME')) == 0) {
            $this->dpErrors[] = $this->module->l('The last name is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_STREET')) > 50) {
            $this->dpErrors[] = $this->module->l('The street is too long');
        }
        if (Tools::strlen(Tools::getValue('DHLDP_DP_STREET')) == 0) {
            $this->dpErrors[] = $this->module->l('The street is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_HOUSENO')) > 10) {
            $this->dpErrors[] = $this->module->l('The house number is too long');
        }
        if (Tools::strlen(Tools::getValue('DHLDP_DP_HOUSENO')) == 0) {
            $this->dpErrors[] = $this->module->l('The house number is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_ZIP')) > 10) {
            $this->dpErrors[] = $this->module->l('The postcode is too long');
        }
        if (Tools::strlen(Tools::getValue('DHLDP_DP_ZIP')) == 0) {
            $this->dpErrors[] = $this->module->l('The postcode is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_CITY')) > 35) {
            $this->dpErrors[] = $this->module->l('The city is too long');
        }
        if (Tools::strlen(Tools::getValue('DHLDP_DP_CITY')) == 0) {
            $this->dpErrors[] = $this->module->l('The city is required');
        }

        if ((int)Tools::getValue('DHLDP_DP_COUNTRY') == 0) {
            $this->dpErrors[] = $this->module->l('The country is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_ADDITIONAL')) > 50) {
            $this->dpErrors[] = $this->module->l('The additional address is too long');
        }

        if (count($this->dpErrors) == 0) {
            Configuration::updateValue('DHLDP_DP_NAME', (int)Tools::getValue('DHLDP_DP_NAME', 0));
            Configuration::updateValue('DHLDP_DP_COMPANY', Tools::getValue('DHLDP_DP_COMPANY', 0));
            Configuration::updateValue('DHLDP_DP_SALUTATION', Tools::getValue('DHLDP_DP_SALUTATION', 0));
            Configuration::updateValue('DHLDP_DP_TITLE', Tools::getValue('DHLDP_DP_TITLE', 0));
            Configuration::updateValue('DHLDP_DP_FIRSTNAME', Tools::getValue('DHLDP_DP_FIRSTNAME', 0));
            Configuration::updateValue('DHLDP_DP_LASTNAME', Tools::getValue('DHLDP_DP_LASTNAME', 0));
            Configuration::updateValue('DHLDP_DP_STREET', Tools::getValue('DHLDP_DP_STREET', 0));
            Configuration::updateValue('DHLDP_DP_HOUSENO', Tools::getValue('DHLDP_DP_HOUSENO', 0));
            Configuration::updateValue('DHLDP_DP_ZIP', Tools::getValue('DHLDP_DP_ZIP', 0));
            Configuration::updateValue('DHLDP_DP_CITY', Tools::getValue('DHLDP_DP_CITY', 0));
            Configuration::updateValue('DHLDP_DP_COUNTRY', (int)Tools::getValue('DHLDP_DP_COUNTRY', 0));
            Configuration::updateValue('DHLDP_DP_ADDITIONAL', Tools::getValue('DHLDP_DP_ADDITIONAL', 0));
        }
    }

    public function displayFormDPSettings()
    {
        $helper = new HelperForm();
        $helper->required = false;
        $helper->id = null;// Tab::getCurrentTabId();
        $helper->currentIndex = $this->getAdminControllerLink($this->controller_name);
        $helper->table = 'dp_configure';
        $helper->token = $this->getCurrentToken();
        $helper->module = $this->module;
        $helper->identifier = null;
        $helper->toolbar_btn = null;
        $helper->ps_help_context = null;
        $helper->title = null;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = false;
        $helper->bootstrap = true;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $carriers = Carrier::getCarriers($this->context->language->id, true);
        $option_carriers = array();
        foreach ($carriers as $carrier) {
            $option_carriers[] = array('id_carrier' => $carrier['id_carrier'], 'name' => $carrier['name']);
        }

        $this->context->smarty->assign(
            array(
                'page_format' => Tools::getValue('DHLDP_DP_PAGE_FORMAT', Configuration::get('DHLDP_DP_PAGE_FORMAT')),
                'position_row' => Tools::getValue('DHLDP_DP_POSITION_ROW', Configuration::get('DHLDP_DP_POSITION_ROW')),
                'position_col' => Tools::getValue('DHLDP_DP_POSITION_COL', Configuration::get('DHLDP_DP_POSITION_COL')),
                'position_page' => Tools::getValue('DHLDP_DP_POSITION_PAGE', Configuration::get('DHLDP_DP_POSITION_PAGE')),
                'page_formats' => $this->dp_api->getPageFormats(),
                'carriers' => $option_carriers,
                'dp_carriers' => $this->module->getDPCarriers(true),
                'dp_wizard_link' => $this->context->link->getAdminLink('AdminCarrierWizard', false) . '&token=' . Tools::getAdminTokenLite('AdminCarrierWizard'),
                'ppl_version' => $this->dp_api->getPPLVersion()
            )
        );
        $helper->fields_value['DHLDP_DP_LIVE_USERNAME'] = Tools::getValue('DHLDP_DP_LIVE_USERNAME', Configuration::get('DHLDP_DP_LIVE_USERNAME'));
        $helper->fields_value['DHLDP_DP_LIVE_PASSWORD'] = str_repeat('*', mb_strlen(Configuration::get('DHLDP_DP_LIVE_PASSWORD')));
        $helper->fields_value['DHLDP_DP_LOG'] = Tools::getValue('DHLDP_DP_LOG', Configuration::get('DHLDP_DP_LOG'));
        $helper->fields_value['log_information'] = $this->renderDPLogInformation();
        $helper->fields_value['DHLDP_DP_DEF_PRODUCT'] = Tools::getValue('DHLDP_DP_DEF_PRODUCT', Configuration::get('DHLDP_DP_DEF_PRODUCT'));
        $helper->fields_value['DHLDP_DP_CHANGE_OS'] = Tools::getValue('DHLDP_DP_CHANGE_OS', Configuration::get('DHLDP_DP_CHANGE_OS'));
        $helper->fields_value['DHLDP_DP_REF_NUMBER'] = Tools::getValue('DHLDP_DP_REF_NUMBER', Configuration::get('DHLDP_DP_REF_NUMBER'));
        $helper->fields_value['DHLDP_DP_CREATE_MANIFEST'] = (int)Tools::getValue('DHLDP_DP_CREATE_MANIFEST', Configuration::get('DHLDP_DP_CREATE_MANIFEST'));
        $helper->fields_value['DHLDP_DP_CREATE_SHIPLIST'] = (int)Tools::getValue('DHLDP_DP_CREATE_SHIPLIST', Configuration::get('DHLDP_DP_CREATE_SHIPLIST'));
        $helper->fields_value['DHLDP_DP_LABEL_FORMAT'] = Tools::getValue('DHLDP_DP_LABEL_FORMAT', Configuration::get('DHLDP_DP_LABEL_FORMAT'));
        $helper->fields_value['retrieve_page_formats'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dp-retrieve-pageformats.tpl');
        $helper->fields_value['label_position'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dp-label-position.tpl');
        $helper->fields_value['update_ppl'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dp-update-ppl.tpl');
        $helper->fields_value['add_carrier'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dp-add-carrier.tpl');
        $helper->fields_value['carrier_list'] = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/dp-carrier-list.tpl');
        $helper->fields_value['DHLDP_DP_NAME'] = Tools::getValue('DHLDP_DP_NAME', Configuration::get('DHLDP_DP_NAME'));
        $helper->fields_value['DHLDP_DP_COMPANY'] = Tools::getValue('DHLDP_DP_COMPANY', Configuration::get('DHLDP_DP_COMPANY'));
        $helper->fields_value['DHLDP_DP_SALUTATION'] = Tools::getValue('DHLDP_DP_SALUTATION', Configuration::get('DHLDP_DP_SALUTATION'));
        $helper->fields_value['DHLDP_DP_TITLE'] = Tools::getValue('DHLDP_DP_TITLE', Configuration::get('DHLDP_DP_TITLE'));
        $helper->fields_value['DHLDP_DP_FIRSTNAME'] = Tools::getValue('DHLDP_DP_FIRSTNAME', Configuration::get('DHLDP_DP_FIRSTNAME'));
        $helper->fields_value['DHLDP_DP_LASTNAME'] = Tools::getValue('DHLDP_DP_LASTNAME', Configuration::get('DHLDP_DP_LASTNAME'));
        $helper->fields_value['DHLDP_DP_STREET'] = Tools::getValue('DHLDP_DP_STREET', Configuration::get('DHLDP_DP_STREET'));
        $helper->fields_value['DHLDP_DP_HOUSENO'] = Tools::getValue('DHLDP_DP_HOUSENO', Configuration::get('DHLDP_DP_HOUSENO'));
        $helper->fields_value['DHLDP_DP_ZIP'] = Tools::getValue('DHLDP_DP_ZIP', Configuration::get('DHLDP_DP_ZIP'));
        $helper->fields_value['DHLDP_DP_STREET'] = Tools::getValue('DHLDP_DP_STREET', Configuration::get('DHLDP_DP_STREET'));
        $helper->fields_value['DHLDP_DP_CITY'] = Tools::getValue('DHLDP_DP_CITY', Configuration::get('DHLDP_DP_CITY'));
        $helper->fields_value['DHLDP_DP_COUNTRY'] = Tools::getValue('DHLDP_DP_COUNTRY', Configuration::get('DHLDP_DP_COUNTRY'));
        $helper->fields_value['DHLDP_DP_ADDITIONAL'] = Tools::getValue('DHLDP_DP_ADDITIONAL', Configuration::get('DHLDP_DP_ADDITIONAL'));

        return $helper->generateForm($this->getFormFieldsDPSettings());
    }

    private function renderDPLogInformation()
    {
        $this->context->smarty->assign(array(
            'general_log_file_path' => $this->getLogFilePath('dp_general'),
            'api_log_file_path' => $this->getLogFilePath('dp_api'),
            'api_log_file_path_clear' => $this->getAdminControllerLink('AdminModules', array('configure' => $this->module->name, 'view' => 'settings_dp', 'log_file' => 'dp_api_clear')) . '&token=' . Tools::getAdminTokenLite('AdminModules'),
        ));
        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/log_information.tpl'
        );
    }

    private function getLogFilePath($key)
    {
        return $this->module->getLogFileDownloadUrl($key, 'settings_dp');
    }

    protected function getFormFieldsDPSettings()
    {
        $form_fields = array(
            'form1' => array(
                'form' => array(
                    'id_form' => 'dp_global_settings',
                    'legend' => array(
                        'title' => $this->module->l('Global settings'),
                        'icon' => 'icon-circle',
                    ),
                    'description' => $this->module->l('Please select mode and fill form with all relevant information regarding authentication in modes.'),
                    'input' => array(
                        array(
                            'name' => 'DHLDP_DP_LIVE_USERNAME',
                            'type' => 'text',
                            'label' => $this->module->l('Live username'),
                            'desc' => $this->module->l('"Live" username for Authentication'),
                            'required' => true,
                        ),
                        array(
                            'name' => 'DHLDP_DP_LIVE_PASSWORD',
                            'type' => 'text',
                            'label' => $this->module->l('Live password'),
                            'desc' => $this->module->l('"Live" password for Authentication'),
                            'required' => true,
                        ),
                        array(
                            'name' => 'DHLDP_DP_LOG',
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
                            'label' => $this->module->l('PPL'),
                            'name' => 'update_ppl',
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
                        array(
                            'type' => 'radio',
                            'label' => $this->module->l('Reference number in label is '),
                            'name' => 'DHLDP_DP_REF_NUMBER',
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
                            'type' => 'select',
                            'label' => $this->module->l('Default product'),
                            'desc' => $this->module->l('Please select product which will be preselected for creating label'),
                            'name' => 'DHLDP_DP_DEF_PRODUCT',
                            'options' => array(
                                'query' => array_merge(array(array('code' => '0', 'name' => $this->module->l('---- Select product ----'))), $this->dp_api->getProducts()),
                                'id' => 'code',
                                'name' => 'name'
                            )
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->module->l('Enable updating order status'),
                            'desc' => $this->module->l('Order status will be changed "Shipped" automatically after creating DP label'),
                            'name' => 'DHLDP_DP_CHANGE_OS',
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
                        array(
                            'type' => 'radio',
                            'label' => $this->module->l('Enable creating manifest'),
                            'name' => 'DHLDP_DP_CREATE_MANIFEST',
                            'required' => true,
                            'class' => 't',
                            'br' => true,
                            'values' => array(
                                array(
                                    'id' => 'create_manifest_yes',
                                    'value' => 1,
                                    'label' => $this->module->l('Yes')
                                ),
                                array(
                                    'id' => 'create_manifest_no',
                                    'value' => 0,
                                    'label' => $this->module->l('No')
                                )
                            )
                        ),
                        array(
                            'type' => 'select',
                            'label' => $this->module->l('Enable creating shipping list'),
                            'desc' => $this->module->l('Please select product which will be preselected for creating label'),
                            'name' => 'DHLDP_DP_CREATE_SHIPLIST',
                            'options' => array(
                                'query' => array(
                                    array('code' => '0', 'name' => $this->module->l('No')),
                                    array('code' => '1', 'name' => $this->module->l('Yes, shipping list without addresses')),
                                    array('code' => '2', 'name' => $this->module->l('Yes, shipping list with addresses')),
                                ),
                                'id' => 'code',
                                'name' => 'name'
                            )
                        ),
                        array(
                            'type' => 'radio',
                            'label' => $this->module->l('Label file format'),
                            'name' => 'DHLDP_DP_LABEL_FORMAT',
                            'required' => true,
                            'class' => 't',
                            'br' => true,
                            'values' => array(
                                array(
                                    'id' => 'label_format_png',
                                    'value' => 'png',
                                    'label' => $this->module->l('PNG picture')
                                ),
                                array(
                                    'id' => 'label_format_pdf',
                                    'value' => 'pdf',
                                    'label' => $this->module->l('PDF document')
                                )
                            )
                        ),
                        array(
                            'type' => 'free',
                            'label' => $this->module->l('Page format (only for PDF label file format)'),
                            'name' => 'retrieve_page_formats',
                            'desc' => $this->module->l('Please retrieve page formats, if you see empty list of page formats'),
                        ),
                        array(
                            'type' => 'free',
                            'label' => $this->module->l('Label position on PDF document(only for PDF label file format)'),
                            'name' => 'label_position',
                            'desc' => $this->module->l('The values must be greater than 0 if the position is specified'),
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->module->l('Save options'),
                        'name' => 'submitSaveDPOptionsGlobal',
                    )
                ),

            ),
            'form2' => array(
                'form' => array(
                    'id_form' => 'deutschepost_address',
                    'legend' => array(
                        'title' => $this->module->l('Address'),
                        'icon' => 'icon-circle',
                    ),
                    'description' => $this->module->l('Please enter adress of sender(shop)'),
                    'input' => array(
                        array(
                            'type' => 'radio',
                            'label' => $this->module->l('Name'),
                            'name' => 'DHLDP_DP_NAME',
                            'required' => true,
                            'class' => 't',
                            'br' => true,
                            'values' => array(
                                array(
                                    'id' => 'dp_sender_person',
                                    'value' => 0,
                                    'label' => $this->module->l('Person')
                                ),
                                array(
                                    'id' => 'dp_sender_company',
                                    'value' => 1,
                                    'label' => $this->module->l('Company')
                                )
                            ),
                            'desc' => $this->module->l('Please select type of sender'),
                        ),
                        array(
                            'name' => 'DHLDP_DP_COMPANY',
                            'type' => 'text',
                            'label' => $this->module->l('Company'),
                            'desc' => $this->module->l('Name of company. Max. 50 characters.'),
                            'size' => 50,
                            'required' => true,
                            'form_group_class' => 'dp_company deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_SALUTATION',
                            'type' => 'text',
                            'label' => $this->module->l('Salutation'),
                            'desc' => $this->module->l('Max. 10 characters'),
                            'size' => 10,
                            'required' => false,
                            'form_group_class' => 'deutschepost_salutation deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_TITLE',
                            'type' => 'text',
                            'label' => $this->module->l('Title'),
                            'desc' => $this->module->l('Max. 10 characters'),
                            'size' => 10,
                            'required' => false,
                            'form_group_class' => 'deutschepost_title deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_FIRSTNAME',
                            'type' => 'text',
                            'label' => $this->module->l('Firstname'),
                            'desc' => $this->module->l('Max. 35 characters'),
                            'size' => 35,
                            'required' => true,
                            'form_group_class' => 'deutschepost_firstname deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_LASTNAME',
                            'type' => 'text',
                            'label' => $this->module->l('Lastname'),
                            'desc' => $this->module->l('Max. 35 characters'),
                            'size' => 35,
                            'required' => true,
                            'form_group_class' => 'deutschepost_lastname deutschepost_data'
                        ),
                        array(
                            'name' => 'DHLDP_DP_STREET',
                            'type' => 'text',
                            'label' => $this->module->l('Street'),
                            'desc' => $this->module->l('Max. 50 characters'),
                            'size' => 50,
                            'required' => true
                        ),
                        array(
                            'name' => 'DHLDP_DP_HOUSENO',
                            'type' => 'text',
                            'label' => $this->module->l('House number'),
                            'desc' => $this->module->l('Max. 10 characters'),
                            'size' => 10,
                            'required' => true
                        ),
                        array(
                            'name' => 'DHLDP_DP_ZIP',
                            'type' => 'text',
                            'label' => $this->module->l('Postcode'),
                            'desc' => $this->module->l('Max. 10 characters'),
                            'size' => 10,
                            'required' => true
                        ),
                        array(
                            'name' => 'DHLDP_DP_CITY',
                            'type' => 'text',
                            'label' => $this->module->l('City'),
                            'desc' => $this->module->l('Max. 35 characters'),
                            'size' => 35,
                            'required' => true
                        ),
                        array(
                            'name' => 'DHLDP_DP_COUNTRY',
                            'type' => 'select',
                            'label' => $this->module->l('Country'),
                            'required' => true,
                            'options' => array(
                                'query' => array_merge(
                                    array(
                                        array('id_country' => '0', 'name' => $this->module->l('---- Select country ----'))
                                    ),
                                    Country::getCountries($this->context->language->id)
                                ),
                                'id' => 'id_country',
                                'name' => 'name'
                            )
                        ),
                        array(
                            'name' => 'DHLDP_DP_ADDITIONAL',
                            'type' => 'text',
                            'label' => $this->module->l('Additional to address'),
                            'desc' => $this->module->l('Max. 50 characters'),
                            'size' => 50,
                            'required' => false
                        ),
                    ),

                    'submit' => array(
                        'title' => $this->module->l('Save options'),
                        'name' => 'submitSaveDPAddressOptions',
                    )
                )
            )
        );
        return $form_fields;
    }

    protected function renderMessages()
    {
        $messages = '';

        foreach ($this->dpErrors as $error) {
            $messages .= $this->module->displayError($error);
        }

        foreach ($this->dpConfirmations as $confirmation) {
            $messages .= $this->module->displayConfirmation($confirmation);
        }

        return $messages;
    }
}
