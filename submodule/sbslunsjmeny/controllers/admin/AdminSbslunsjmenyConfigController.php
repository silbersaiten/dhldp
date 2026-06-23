<?php

class AdminSbslunsjmenyConfigController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        if ((int) Tools::getValue('showLastExportedOrders') === 1) {
            $this->content .= $this->renderLastExportedOrdersPage();
        } else {
            $this->content .= $this->renderForm();
        }

        parent::initContent();
    }


    public function ajaxProcessGetOrderExportStatuses()
    {
        $orderList = Tools::getValue('orderList');
        if (!is_array($orderList)) {
            $orderList = [];
        }

        $orderIds = [];
        foreach ($orderList as $idOrder) {
            $idOrder = (int) $idOrder;
            if ($idOrder > 0) {
                $orderIds[] = $idOrder;
            }
        }

        $orderIds = array_values(array_unique($orderIds));
        $statuses = [];

        if (!empty($orderIds)) {
            $rows = Db::getInstance()->executeS(
                'SELECT q.`id_order`, q.`exported_at`, COUNT(ep.`id_sbslunsjmeny_order_exported_product`) AS `exported_products_count`
                FROM `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue` q
                LEFT JOIN `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product` ep ON (ep.`id_order` = q.`id_order`)
                WHERE q.`id_order` IN (' . implode(',', $orderIds) . ')
                GROUP BY q.`id_order`, q.`exported_at`'
            );

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $idOrder = (int) $row['id_order'];
                    if ($idOrder <= 0) {
                        continue;
                    }

                    $exportedAt = isset($row['exported_at']) ? trim((string) $row['exported_at']) : '';
                    $exportedProductsCount = isset($row['exported_products_count']) ? (int) $row['exported_products_count'] : 0;

                    if ($exportedAt !== '') {
                        $statuses[] = [
                            'id_order' => $idOrder,
                            'status' => 'exported',
                        ];
                    } elseif ($exportedProductsCount > 0) {
                        $statuses[] = [
                            'id_order' => $idOrder,
                            'status' => 'partial',
                        ];
                    }
                }
            }
        }

        $this->ajaxDie(json_encode([
            'orderStatuses' => $statuses,
        ]));
    }

    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            parent::postProcess();

            return;
        }

        if (Tools::isSubmit('submitSbslunsjmenyConfig')) {
            $this->processSaveSettings();
        }

        if (Tools::isSubmit('submitSbslunsjmenyAuthorize')) {
            $this->processAuthorization();
        }

        if (Tools::isSubmit('submitSbslunsjmenyRunCronStock')) {
            $this->processManualCronRun('stock');
        }

        if (Tools::isSubmit('submitSbslunsjmenyRunCronOrders')) {
            $this->processManualCronRun('orders');
        }
        parent::postProcess();
    }

    private function processSaveSettings()
    {
        Configuration::updateValue(Sbslunsjmeny::CONF_CLIENT_ID, trim((string) Tools::getValue('SBSLUNSJMENY_CLIENT_ID')));
        Configuration::updateValue(Sbslunsjmeny::CONF_CLIENT_SECRET, trim((string) Tools::getValue('SBSLUNSJMENY_CLIENT_SECRET')));
        Configuration::updateValue(Sbslunsjmeny::CONF_INTEGRATION_BASE_URL, trim((string) Tools::getValue('SBSLUNSJMENY_INTEGRATION_BASE_URL')));
        Configuration::updateValue(Sbslunsjmeny::CONF_TOKEN_URL, trim((string) Tools::getValue('SBSLUNSJMENY_TOKEN_URL')));
        Configuration::updateValue(Sbslunsjmeny::CONF_ENABLE_LOGGING, (int) Tools::getValue('SBSLUNSJMENY_ENABLE_LOGGING'));
        Configuration::updateValue(Sbslunsjmeny::CONF_DEACTIVATE_OUT_OF_STOCK_PRODUCTS, (int) Tools::getValue('SBSLUNSJMENY_DEACTIVATE_OUT_OF_STOCK_PRODUCTS'));

        $integrationTimezone = $this->module->getIntegrationTimezoneName();
        Configuration::updateValue(Sbslunsjmeny::CONF_INTEGRATION_TIMEZONE, $integrationTimezone);

        $this->confirmations[] = $this->module->l('Settings updated.');
        $this->processAuthorization();
    }

    private function processAuthorization()
    {
        $connector = new MenyApi();
        $result = $connector->authorize();

        if ($result['success']) {
            $this->confirmations[] = $this->module->l('Authorization completed, token saved in database.');

            return;
        }

        $this->errors[] = $result['message'];
    }

    private function processManualCronRun($type)
    {
        if ($type === 'orders') {
            $result = $this->module->runOrderExportCronTask();
            $cronLabel = $this->module->l('Order export cron');
        } else {
            $result = $this->module->runStockImportCronTask();
            $cronLabel = $this->module->l('Stock import cron');
        }
        $status = isset($result['status']) ? (string) $result['status'] : 'error';
        $message = isset($result['message']) ? (string) $result['message'] : 'Cron execution finished.';
        $errorMessages = $this->extractManualCronErrorMessages($result);

        if ($status === 'ok') {
            $this->confirmations[] = $cronLabel . ' ' . $this->module->l('executed manually.') . ' ' . $message;

            return;
        }

        $this->errors[] = $cronLabel . ' ' . $this->module->l('manual execution failed.') . ' ' . $message;

        foreach ($errorMessages as $errorMessage) {
            $this->errors[] = $errorMessage;
        }
    }

    private function extractManualCronErrorMessages(array $result)
    {
        $messages = [];

        if (isset($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $error) {
                if (!is_array($error)) {
                    continue;
                }

                $source = isset($error['source']) ? (string) $error['source'] : '';
                $idOrder = isset($error['idOrder']) ? (int) $error['idOrder'] : 0;
                $description = isset($error['message']) ? trim((string) $error['message']) : '';

                if ($description === '') {
                    continue;
                }

                $prefix = $source !== '' ? '[' . $source . '] ' : '';
                if ($idOrder > 0) {
                    $prefix .= '[order #' . $idOrder . '] ';
                }

                $messages[] = $prefix . $description;
            }
        }

        if (empty($messages) && isset($result['message'])) {
            $fallbackMessage = trim((string) $result['message']);
            if ($fallbackMessage !== '') {
                $messages[] = $fallbackMessage;
            }
        }
        return array_values(array_unique($messages));
    }


    private function getLastExportedOrders()
    {
        $latestShippingDay = Db::getInstance()->getValue(
            'SELECT MAX(DATE(`shipping_date`))
            FROM `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product`'
        );

        if (empty($latestShippingDay)) {
            return [
                'shipping_day' => '',
                'orders' => [],
            ];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT ep.`id_order`, DATE(ep.`shipping_date`) AS `shipping_day`, MIN(ep.`shipping_date`) AS `first_shipping_date`, o.`reference`, o.`date_add`
            FROM `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product` ep
            LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = ep.`id_order`)
            WHERE DATE(ep.`shipping_date`) = \'' . pSQL((string) $latestShippingDay) . '\'
            GROUP BY ep.`id_order`, DATE(ep.`shipping_date`), o.`reference`, o.`date_add`
            ORDER BY `first_shipping_date` ASC, ep.`id_order` ASC'
        );

        return [
            'shipping_day' => (string) $latestShippingDay,
            'orders' => !empty($rows) && is_array($rows) ? $rows : [],
        ];
    }

    private function renderLastExportedOrdersPage()
    {
        $data = $this->getLastExportedOrders();
        $shippingDay = (string) $data['shipping_day'];
        $orders = $data['orders'];
        $backUrl = $this->context->link->getAdminLink('AdminSbslunsjmenyConfig');
        $html = '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-list"></i> ' . $this->module->l('Last exported orders') . '</div>';
        $html .= '<p><a class="btn btn-default" href="' . Tools::safeOutput($backUrl) . '"><i class="icon-arrow-left"></i> ' . $this->module->l('Back to configuration') . '</a></p>';

        if ($shippingDay === '' || empty($orders)) {
            $html .= '<p class="alert alert-info">' . $this->module->l('No exported orders found yet.') . '</p>';
            $html .= '</div>';

            return $html;
        }

        $html .= '<p>' . sprintf($this->module->l('Showing distinct orders exported for shipping date %s.'), Tools::safeOutput($shippingDay)) . '</p>';
        $html .= '<div class="table-responsive"><table class="table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . $this->module->l('Order ID') . '</th>';
        $html .= '<th>' . $this->module->l('Reference') . '</th>';
        $html .= '<th>' . $this->module->l('Shipping date') . '</th>';
        $html .= '<th>' . $this->module->l('Order date') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($orders as $order) {
            $idOrder = isset($order['id_order']) ? (int) $order['id_order'] : 0;
            $orderUrl = $idOrder > 0 ? $this->context->link->getAdminLink('AdminOrders', true, [], ['vieworder' => 1, 'id_order' => $idOrder]) : '';
            $reference = isset($order['reference']) ? (string) $order['reference'] : '';
            $firstShippingDate = isset($order['first_shipping_date']) ? (string) $order['first_shipping_date'] : '';
            $orderDate = isset($order['date_add']) ? (string) $order['date_add'] : '';

            $html .= '<tr>';
            $html .= '<td>' . ($orderUrl !== '' ? '<a href="' . Tools::safeOutput($orderUrl) . '">#' . $idOrder . '</a>' : '#' . $idOrder) . '</td>';
            $html .= '<td>' . Tools::safeOutput($reference) . '</td>';
            $html .= '<td>' . Tools::safeOutput($firstShippingDate) . '</td>';
            $html .= '<td>' . Tools::safeOutput($orderDate) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></div>';

        return $html;
    }

    public function renderForm()
    {
        $token = (string) Configuration::get(Sbslunsjmeny::CONF_ACCESS_TOKEN);
        $lastStockImportLogFile = (string) Configuration::get(Sbslunsjmeny::CONF_LAST_STOCK_IMPORT_LOG_FILE);
        $lastOrderExportLogFile = (string) Configuration::get(Sbslunsjmeny::CONF_LAST_ORDER_EXPORT_LOG_FILE);
        if ($lastStockImportLogFile === '' && $lastOrderExportLogFile === '') {
            $lastStockImportLogFile = (string) Configuration::get(Sbslunsjmeny::CONF_LAST_LOG_FILE);
        }
        $lastStockSyncAt = $this->formatLastStockSyncAt((string) Configuration::get(Sbslunsjmeny::CONF_LAST_STOCK_SYNC_AT));
        $stockImportCronCommand = $this->module->getStockImportCronCommand();
        $orderExportCronCommand = $this->module->getOrderExportCronCommand();
        $timezoneName = $this->module->getIntegrationTimezoneName();
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Meny API configuration'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => 'Client ID',
                        'name' => 'SBSLUNSJMENY_CLIENT_ID',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Client Secret',
                        'name' => 'SBSLUNSJMENY_CLIENT_SECRET',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Integration Base URL',
                        'name' => 'SBSLUNSJMENY_INTEGRATION_BASE_URL',
                        'required' => true,
                        'desc' => 'Base URL used for stock import and order export API requests.',
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Token URL',
                        'name' => 'SBSLUNSJMENY_TOKEN_URL',
                        'required' => true,
                        'desc' => 'URL used to request OAuth access tokens.',
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Integration timezone',
                        'name' => 'SBSLUNSJMENY_INTEGRATION_TIMEZONE',
                        'required' => true,
                        'readonly' => true,
                        'desc' => 'Timezone used to read PrestaShop/MySQL date fields and format order export timestamps. This value is managed by the module and cannot be edited from this form.',
                    ],
                    [
                        'type' => 'switch',
                        'label' => 'Enable integration logging',
                        'name' => 'SBSLUNSJMENY_ENABLE_LOGGING',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'logging_on',
                                'value' => 1,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'logging_off',
                                'value' => 0,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                        'desc' => 'Controls logging for both order export and stock import API operations.',
                    ],
                    [
                        'type' => 'switch',
                        'label' => 'Deactivate out-of-stock products',
                        'name' => 'SBSLUNSJMENY_DEACTIVATE_OUT_OF_STOCK_PRODUCTS',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'deactivate_out_of_stock_on',
                                'value' => 1,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'deactivate_out_of_stock_off',
                                'value' => 0,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                        'desc' => 'When enabled, stock import will synchronize product active status with stock quantity, deactivating products with zero stock.',
                    ],
                    [
                        'type' => 'textarea',
                        'label' => 'Current Access Token',
                        'name' => 'SBSLUNSJMENY_ACCESS_TOKEN_READONLY',
                        'readonly' => true,
                        'rows' => 4,
                        'cols' => 120,
                        'desc' => 'Token is saved in PrestaShop database (configuration table).',
                    ],
                    [
                        'type' => 'free',
                        'label' => 'Last stock import log file',
                        'name' => 'SBSLUNSJMENY_LAST_STOCK_IMPORT_LOG_FILE_READONLY',
                        'readonly' => true,
                    ],
                    [
                        'type' => 'free',
                        'label' => 'Last order export log file',
                        'name' => 'SBSLUNSJMENY_LAST_ORDER_EXPORT_LOG_FILE_READONLY',
                        'readonly' => true,
                    ],
                    [
                        'type' => 'free',
                        'label' => 'Last stock import',
                        'name' => 'SBSLUNSJMENY_LAST_STOCK_SYNC_AT_READONLY',
                        'readonly' => true,
                    ],
                    [
                        'type' => 'free',
                        'label' => 'Cron command: stock import (CLI)',
                        'name' => 'SBSLUNSJMENY_CRON_STOCK_IMPORT_CLI_READONLY',
                        'readonly' => true,
                    ],
                    [
                        'type' => 'free',
                        'label' => 'Cron command: order export (CLI)',
                        'name' => 'SBSLUNSJMENY_CRON_ORDER_EXPORT_CLI_READONLY',
                        'readonly' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->module->l('Save'),
                    'name' => 'submitSbslunsjmenyConfig',
                ],
                'buttons' => [
                    [
                        'title' => $this->module->l('Show last exported orders'),
                        'icon' => 'process-icon-preview',
                        'href' => $this->context->link->getAdminLink('AdminSbslunsjmenyConfig') . '&showLastExportedOrders=1',
                    ],
                    [
                        'title' => $this->module->l('Authorize now'),
                        'icon' => 'process-icon-refresh',
                        'name' => 'submitSbslunsjmenyAuthorize',
                        'type' => 'submit',
                    ],
                    [
                        'title' => $this->module->l('Cron stock'),
                        'icon' => 'process-icon-cogs',
                        'name' => 'submitSbslunsjmenyRunCronStock',
                        'type' => 'submit',
                    ],
                    [
                        'title' => $this->module->l('Cron orders'),
                        'icon' => 'process-icon-cogs',
                        'name' => 'submitSbslunsjmenyRunCronOrders',
                        'type' => 'submit',
                    ],
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = 'AdminSbslunsjmenyConfig';
        $helper->token = Tools::getAdminTokenLite('AdminSbslunsjmenyConfig');
        $helper->currentIndex = AdminController::$currentIndex;
        $helper->fields_value = [
            'SBSLUNSJMENY_CLIENT_ID' => Configuration::get(Sbslunsjmeny::CONF_CLIENT_ID),
            'SBSLUNSJMENY_CLIENT_SECRET' => Configuration::get(Sbslunsjmeny::CONF_CLIENT_SECRET),
            'SBSLUNSJMENY_INTEGRATION_BASE_URL' => Configuration::get(Sbslunsjmeny::CONF_INTEGRATION_BASE_URL),
            'SBSLUNSJMENY_TOKEN_URL' => Configuration::get(Sbslunsjmeny::CONF_TOKEN_URL),
            'SBSLUNSJMENY_ENABLE_LOGGING' => (int) Configuration::get(Sbslunsjmeny::CONF_ENABLE_LOGGING),
            'SBSLUNSJMENY_DEACTIVATE_OUT_OF_STOCK_PRODUCTS' => (int) Configuration::get(Sbslunsjmeny::CONF_DEACTIVATE_OUT_OF_STOCK_PRODUCTS),
            'SBSLUNSJMENY_INTEGRATION_TIMEZONE' => $timezoneName,
            'SBSLUNSJMENY_ACCESS_TOKEN_READONLY' => $token,
            'SBSLUNSJMENY_LAST_STOCK_IMPORT_LOG_FILE_READONLY' => $this->renderLastLogFileLink($lastStockImportLogFile),
            'SBSLUNSJMENY_LAST_ORDER_EXPORT_LOG_FILE_READONLY' => $this->renderLastLogFileLink($lastOrderExportLogFile),
            'SBSLUNSJMENY_LAST_STOCK_SYNC_AT_READONLY' => $lastStockSyncAt !== '' ? Tools::safeOutput($lastStockSyncAt) : 'No stock import completed yet.',
            'SBSLUNSJMENY_CRON_STOCK_IMPORT_CLI_READONLY' => '<b>' . $stockImportCronCommand . '</b>',
            'SBSLUNSJMENY_CRON_ORDER_EXPORT_CLI_READONLY' => '<b>' . $orderExportCronCommand . '</b>',
        ];
        return $helper->generateForm([$fieldsForm]);
    }

    private function renderLastLogFileLink($lastLogFile)
    {
        $lastLogFile = (string) $lastLogFile;
        if ($lastLogFile === '') {
            return 'No log file generated yet.';
        }

        return '<a href="' . Tools::safeOutput($lastLogFile) . '" target="_blank" rel="noopener">Open last log file</a>';
    }

    private function formatLastStockSyncAt($lastStockSyncAt)
    {
        $timestamp = (int) $lastStockSyncAt;
        if ($timestamp <= 0) {
            return '';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

