<?php
/**
 * DHL Deutschepost
 */

class AdminDhldpSettingsDpController extends ModuleAdminController
{
    /** @var DPRestApi */
    protected $dp_api;

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

    public function postProcess()
    {
        $is_global_submit = Tools::isSubmit('submitSaveDPOptionsGlobal') || Tools::isSubmit('submitSaveDPOptions');
        $is_address_submit = Tools::isSubmit('submitSaveDPAddressOptions');

        if (!$is_global_submit && !$is_address_submit) {
            return;
        }

        $form_errors = array();
        if ($is_global_submit) {
            $this->processGlobalSettings($form_errors);
        }

        if ($is_address_submit) {
            $this->processAddressSettings($form_errors);
        }

        if (count($form_errors) == 0) {
            $this->module->_confirmations[] = $this->module->l('Settings updated');
        }
    }

    protected function processGlobalSettings(&$form_errors)
    {
        $deutschepost_mode = 1;
        $deutschepost_live_username = Tools::getValue('DHLDP_DP_LIVE_USERNAME');
        $deutschepost_live_password = Tools::getValue('DHLDP_DP_LIVE_PASSWORD');
        $deutschepost_sbx_username = Tools::getValue('DHLDP_DP_SBX_USERNAME');
        $deutschepost_sbx_password = Tools::getValue('DHLDP_DP_SBX_PASSWORD');
        $deutschepost_carriers = Tools::getValue('deutschepost_carriers', array());
        $deutschepost_log = Tools::getValue('DHLDP_DP_LOG');

        if (!in_array($deutschepost_mode, array('0', '1'))) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please select mode');
        }

        if (($deutschepost_mode == '0' && $deutschepost_sbx_username == '') ||
            ($deutschepost_mode == '1' && $deutschepost_live_username == '')
        ) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please fill user name');
        }

        if (($deutschepost_mode == '0' && $deutschepost_sbx_password == '') ||
            ($deutschepost_mode == '1' && $deutschepost_live_password == '')
        ) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please fill password');
        }

        if (count($form_errors) == 0) {
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
                    $form_errors[] = $this->module->_errors[] = $this->module->l('Authentication data is incorrect(' . implode(', ', $this->dp_api->errors) . ').');
                    DhlDp::logToFile(implode(', ', $this->dp_api->errors), 'general');
                } else {
                    Configuration::updateValue('DHLDP_DP_LIVE_PASSWORD', Tools::getValue('DHLDP_DP_LIVE_PASSWORD'));
                }
            }
        }

        if (!in_array($deutschepost_log, array('0', '1'))) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please select log mode');
        }

        if (!in_array(Tools::getValue('DHLDP_DP_CREATE_MANIFEST'), array('0', '1'))) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please select value for Enable creating manifest');
        }
        if (!in_array(Tools::getValue('DHLDP_DP_CREATE_SHIPLIST'), array('0', '1', '2'))) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please select value for Enable creating shipping list');
        }
        if (!in_array(Tools::getValue('DHLDP_DP_LABEL_FORMAT'), array('png', 'pdf'))) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please select format of label file');
        }
        $page_formats = json_decode(Configuration::getGlobalValue('DHLDP_DP_PAGE_FORMATS'), true);
        $page_formats_keys = is_array($page_formats) ? array_keys($page_formats, true) : array();
        if (count($page_formats_keys) == 0) {
            $page_formats_keys = array(1);
        }
        $page_format_id = (Tools::getValue('DHLDP_DP_PAGE_FORMAT', 1)) == '' ? 1 : Tools::getValue('DHLDP_DP_PAGE_FORMAT', 1);

        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && !in_array($page_format_id, $page_formats_keys)) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please select valid page format for PDF document');
        }
        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_PAGE', 1) < 1)) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please specify page by positive integer value');
        }
        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_ROW', 1) < 1)) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please specify row on page by positive integer value');
        }
        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && ((int)Tools::getValue('DHLDP_DP_POSITION_COL', 1) < 1)) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please specify column on page by positive integer value');
        }
        if (Tools::getValue('DHLDP_DP_LABEL_FORMAT') == 'pdf' && isset($page_formats[$page_format_id])) {
            $page_format = $page_formats[$page_format_id];
            if ((int)Tools::getValue('DHLDP_DP_POSITION_COL', 1) > $page_format['col']) {
                $form_errors[] = $this->module->_errors[] = $this->module->l('Column of Label position must be maximum') . ' ' . $page_format['col'];
            }
            if ((int)Tools::getValue('DHLDP_DP_POSITION_ROW', 1) > $page_format['row']) {
                $form_errors[] = $this->module->_errors[] = $this->module->l('Row of Label position must be maximum') . ' ' . $page_format['row'];
            }
        }

        if (count($form_errors) == 0) {
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

    protected function processAddressSettings(&$form_errors)
    {
        if (!in_array((int)Tools::getValue('DHLDP_DP_NAME', 0), array('0', '1'))) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('Please select company or person in address');
        }

        if (Tools::getValue('DHLDP_DP_NAME') == 1 && Tools::strlen(Tools::getValue('DHLDP_DP_COMPANY')) > 50) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The company name is too long');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 1 && Tools::strlen(Tools::getValue('DHLDP_DP_COMPANY')) == 0) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The company name is required');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_FIRSTNAME')) > 30) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The first name is too long');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_FIRSTNAME')) == 0) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The first name is required');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_LASTNAME')) > 30) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The last name is too long');
        }
        if (Tools::getValue('DHLDP_DP_NAME') == 0 && Tools::strlen(Tools::getValue('DHLDP_DP_LASTNAME')) == 0) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The last name is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_STREET')) > 50) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The street is too long');
        }
        if (Tools::strlen(Tools::getValue('DHLDP_DP_STREET')) == 0) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The street is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_HOUSENO')) > 10) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The house number is too long');
        }
        if (Tools::strlen(Tools::getValue('DHLDP_DP_HOUSENO')) == 0) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The house number is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_ZIP')) > 10) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The postcode is too long');
        }
        if (Tools::strlen(Tools::getValue('DHLDP_DP_ZIP')) == 0) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The postcode is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_CITY')) > 35) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The city is too long');
        }
        if (Tools::strlen(Tools::getValue('DHLDP_DP_CITY')) == 0) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The city is required');
        }

        if ((int)Tools::getValue('DHLDP_DP_COUNTRY') == 0) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The country is required');
        }

        if (Tools::strlen(Tools::getValue('DHLDP_DP_ADDITIONAL')) > 50) {
            $form_errors[] = $this->module->_errors[] = $this->module->l('The additional address is too long');
        }

        if (count($form_errors) == 0) {
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

    public function initContent()
    {
        if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
            $this->displayInformation($this->module->l('You can only display the page in a shop context.'));
            return;
        }

        $this->postProcess();
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
