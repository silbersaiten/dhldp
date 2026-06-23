<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MenyApi.php';

class Sbslunsjmeny extends Module
{
    public const CONF_CLIENT_ID = 'SBSLUNSJMENY_CLIENT_ID';
    public const CONF_CLIENT_SECRET = 'SBSLUNSJMENY_CLIENT_SECRET';
    public const CONF_INTEGRATION_BASE_URL = 'SBSLUNSJMENY_INTEGRATION_BASE_URL';
    public const CONF_TOKEN_URL = 'SBSLUNSJMENY_TOKEN_URL';
    public const CONF_ACCESS_TOKEN = 'SBSLUNSJMENY_ACCESS_TOKEN';
    public const CONF_TOKEN_EXPIRES_AT = 'SBSLUNSJMENY_TOKEN_EXPIRES_AT';
    public const CONF_LAST_STOCK_SYNC_AT = 'SBSLUNSJMENY_LAST_STOCK_SYNC_AT';
    public const CONF_LAST_STOCK_SYNC_RESPONSE = 'SBSLUNSJMENY_LAST_STOCK_SYNC_RESPONSE';
    public const CONF_ENABLE_LOGGING = 'SBSLUNSJMENY_ENABLE_LOGGING';
    public const CONF_LAST_LOG_FILE = 'SBSLUNSJMENY_LAST_LOG_FILE';
    public const CONF_LAST_STOCK_IMPORT_LOG_FILE = 'SBSLUNSJMENY_LAST_STOCK_IMPORT_LOG_FILE';
    public const CONF_LAST_ORDER_EXPORT_LOG_FILE = 'SBSLUNSJMENY_LAST_ORDER_EXPORT_LOG_FILE';
    public const CONF_DEACTIVATE_OUT_OF_STOCK_PRODUCTS = 'SBSLUNSJMENY_DEACTIVATE_OUT_OF_STOCK_PRODUCTS';
    public const CONF_INTEGRATION_TIMEZONE = 'SBSLUNSJMENY_INTEGRATION_TIMEZONE';
    public const DEFAULT_INTEGRATION_TIMEZONE = 'Europe/Oslo';

    public function __construct()
    {
        $this->name = 'sbslunsjmeny';
        $this->tab = 'administration';
        $this->version = '1.0.13';
        $this->author = 'Lunsj';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Meny API integration');
        $this->description = $this->l('Integration with Meny/Trumf API for background tasks.');
    }

    public function install()
    {
        return parent::install()
            && $this->installConfiguration()
            && $this->installDatabase()
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionOrderEditChange') // Currently not in use
            && $this->registerHook('displayBackOfficeHeader')
            && $this->installTab();
    }

    public function uninstall()
    {
        return $this->uninstallTab()
            && $this->uninstallDatabase()
            && $this->uninstallConfiguration()
            && parent::uninstall();
    }

    public function hookActionValidateOrder(array $params)
    {
        if (empty($params['order']) || !Validate::isLoadedObject($params['order'])) {
            return;
        }

        /** @var Order $order */
        $order = $params['order'];
        $this->queueOrderForExport((int) $order->id, 'hook_actionValidateOrder');
    }

    public function hookActionOrderEditChange(array $params)
    {
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        if ($idOrder <= 0 || !$this->hasExportedOrderProducts($idOrder)) {
            return;
        }

        $this->exportOrderImmediately($idOrder, 'hook_actionOrderEditChange');
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (!$this->isAdminOrdersPage()) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/admin-order-exported-products.css');

        if ($this->isOrderViewPage()) {
            $idOrder = (int) Tools::getValue('id_order');
            if ($idOrder <= 0) {
                return;
            }

            $exportedProducts = $this->getExportedOrderProducts($idOrder);
            Media::addJsDef([
                'sbslunsjmenyExportedOrderProducts' => $exportedProducts,
            ]);

            $this->context->controller->addJS($this->_path . 'views/js/admin-order-exported-products.js');

            return;
        }

        if ($this->isOrderListPage()) {
            Media::addJsDef([
                'sbslunsjmenyOrderExportStatusesAjaxUrl' => $this->context->link->getAdminLink('AdminSbslunsjmenyConfig'),
                'sbslunsjmenyOrderExportStatusesLabels' => [
                    'exported' => $this->l('Fully exported'),
                    'partial' => $this->l('More products to export'),
                ],
            ]);

            $this->context->controller->addJS($this->_path . 'views/js/admin-order-export-statuses.js');
        }
    }

    private function isAdminOrdersPage()
    {
        if (!isset($this->context->controller) || !is_object($this->context->controller)) {
            return false;
        }

        $controllerName = Tools::strtolower((string) $this->context->controller->controller_name);

        return $controllerName === 'adminorders';
    }

    private function isOrderViewPage()
    {
        return $this->isAdminOrdersPage() && (int) Tools::getValue('id_order') > 0;
    }

    private function isOrderListPage()
    {
        return $this->isAdminOrdersPage() && (int) Tools::getValue('id_order') <= 0;
    }

    private function getExportedOrderProducts($idOrder)
    {
        $this->applyIntegrationDatabaseTimezone();

        $rows = Db::getInstance()->executeS(
            'SELECT `id_cart_distribution_shipping`, `id_order_detail`, `id_product`, `shipping_date`
            FROM `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product`
            WHERE `id_order` = ' . (int) $idOrder
        );

        if (empty($rows)) {
            return [];
        }

        $products = [];
        foreach ($rows as $row) {
            $shippingDate = isset($row['shipping_date']) ? $this->normalizeShippingDate($row['shipping_date']) : null;
            if ($shippingDate === null) {
                continue;
            }

            $products[] = [
                'id_cart_distribution_shipping' => isset($row['id_cart_distribution_shipping']) ? (int) $row['id_cart_distribution_shipping'] : 0,
                'id_order_detail' => isset($row['id_order_detail']) ? (int) $row['id_order_detail'] : 0,
                'id_product' => isset($row['id_product']) ? (int) $row['id_product'] : 0,
                'shipping_date' => $shippingDate,
            ];
        }

        return $products;
    }

    public function getContent()
    {
        $link = $this->context->link->getAdminLink('AdminSbslunsjmenyConfig');

        Tools::redirectAdmin($link);

        return '';
    }

    public function runCronTasks()
    {
        return [
            'status' => 'ok',
            'message' => 'Use dedicated cron methods: runStockImportCronTask / runOrderExportCronTask.',
        ];
    }

    public function runStockImportCronTask()
    {
        $connector = new MenyApi();
        if (!$connector->hasValidToken()) {
            $result = $connector->authorize();
            if (empty($result['success'])) {
                return ['status' => 'error', 'message' => $result['message']];
            }
        }

        return $this->runStockImport();
    }

    public function runOrderExportCronTask()
    {
        $targetShippingDate = $this->getTomorrowShippingDate();
        $this->logOrderExportCronEvent('cron_start', [
            'target_shipping_date' => $targetShippingDate,
            'integration_timezone' => $this->getIntegrationTimezoneName(),
            'php_sapi' => PHP_SAPI,
        ]);

        $connector = new MenyApi();
        if (!$connector->hasValidToken()) {
            $this->logOrderExportCronEvent('auth_start', [
                'message' => 'No valid token found, requesting a new access token.',
            ]);
            $result = $connector->authorize();
            if (empty($result['success'])) {
                $message = isset($result['message']) ? (string) $result['message'] : 'Authorization failed.';
                $this->logOrderExportCronEvent('auth_failed', [
                    'message' => $message,
                ]);

                return ['status' => 'error', 'message' => $message];
            }

            $this->logOrderExportCronEvent('auth_success', [
                'message' => 'Access token refreshed successfully.',
            ]);
        } else {
            $this->logOrderExportCronEvent('auth_skipped', [
                'message' => 'Existing access token is still valid.',
            ]);
        }

        $result = $this->processOrderExportQueue($targetShippingDate);
        $this->logOrderExportCronEvent('cron_finish', [
            'status' => isset($result['status']) ? (string) $result['status'] : '',
            'exported' => isset($result['exported']) ? (int) $result['exported'] : 0,
            'errors_count' => !empty($result['errors']) && is_array($result['errors']) ? count($result['errors']) : 0,
            'message' => isset($result['message']) ? (string) $result['message'] : '',
        ]);

        return $result;
    }

    public function getStockImportCronCommand()
    {
        return '/usr/bin/php8.1 -f ' . _PS_MODULE_DIR_ . $this->name . '/cron.php task=stock-import';
    }

    public function getOrderExportCronCommand()
    {
        return '/usr/bin/php8.1 -f ' . _PS_MODULE_DIR_ . $this->name . '/cron.php task=order-export';
    }

    private function runStockImport()
    {
        $gtins = $this->getGtinsFromProducts();
        if (empty($gtins)) {
            return [
                'status' => 'skipped',
                'message' => 'No GTIN values found in product table for stock import.',
            ];
        }

        $api = new MenyApi();
        $updatedProducts = 0;
        $processedGtins = 0;
        $rawResponses = [];
        $gtinChunks = array_chunk($gtins, 10);

        foreach ($gtinChunks as $gtinChunk) {
            $result = $api->postStockLevelsQuery($gtinChunk);
            if (empty($result['success'])) {
                return [
                    'status' => 'error',
                    'message' => sprintf(
                        'Stock import request failed after processing %d of %d GTINs. %s',
                        $processedGtins,
                        count($gtins),
                        isset($result['message']) ? $result['message'] : 'Unknown error.'
                    ),
                ];
            }

            $updatedProducts += $this->applyStockLevelsFromResponse(isset($result['data']) ? $result['data'] : []);
            $processedGtins += count($gtinChunk);

            if (isset($result['raw']) && $result['raw'] !== '') {
                $rawResponses[] = (string) $result['raw'];
            }
        }

        Configuration::updateValue(self::CONF_LAST_STOCK_SYNC_AT, (string) time());
        Configuration::updateValue(self::CONF_LAST_STOCK_SYNC_RESPONSE, implode(PHP_EOL, $rawResponses));

        return [
            'status' => 'ok',
            'message' => sprintf('Stock import completed. Updated products: %d.', $updatedProducts),
            'updatedProducts' => $updatedProducts,
        ];
    }

    private function getGtinsFromProducts()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT `ean13` FROM `' . _DB_PREFIX_ . 'product` WHERE `ean13` IS NOT NULL AND TRIM(`ean13`) != \'\''
        );

        if (empty($rows)) {
            return [];
        }

        $gtins = [];
        foreach ($rows as $row) {
            $gtin = trim((string) $row['ean13']);
            if ($gtin !== '') {
                $gtins[] = $gtin;
            }
        }

        return array_values(array_unique($gtins));
    }

    private function applyStockLevelsFromResponse(array $response)
    {
        $levels = $this->extractStockLevels($response);
        $updated = 0;

        foreach ($levels as $row) {
            if (empty($row['gtin']) || !isset($row['quantity'])) {
                continue;
            }

            $idProduct = (int) Product::getIdByEan13((string) $row['gtin']);
            if ($idProduct <= 0) {
                continue;
            }

            $quantity = (int) $row['quantity'];
            StockAvailable::setQuantity($idProduct, 0, $quantity);
            if ((int) Configuration::get(self::CONF_DEACTIVATE_OUT_OF_STOCK_PRODUCTS) === 1) {
                $this->syncProductActiveStatusWithQuantity($idProduct, $quantity);
            }
            ++$updated;
        }

        return $updated;
    }


    private function syncProductActiveStatusWithQuantity($idProduct, $quantity)
    {
        $isActive = $quantity > 0 ? 1 : 0;
        $idProduct = (int) $idProduct;

        Db::getInstance()->update(
            'product',
            ['active' => $isActive],
            '`id_product` = ' . $idProduct
        );

        Db::getInstance()->update(
            'product_shop',
            ['active' => $isActive],
            '`id_product` = ' . $idProduct
        );
    }

    private function extractStockLevels(array $response)
    {
        $candidates = [];

        if (isset($response['stockLevels']) && is_array($response['stockLevels'])) {
            $candidates = $response['stockLevels'];
        } elseif (isset($response['data']['stockLevels']) && is_array($response['data']['stockLevels'])) {
            $candidates = $response['data']['stockLevels'];
        } elseif (isset($response['items']) && is_array($response['items'])) {
            $candidates = $response['items'];
        } elseif (isset($response[0]) && is_array($response[0])) {
            $candidates = $response;
        }

        $levels = [];
        foreach ($candidates as $item) {
            if (!is_array($item)) {
                continue;
            }

            $gtin = '';
            if (isset($item['gtin'])) {
                $gtin = (string) $item['gtin'];
            } elseif (isset($item['GTIN'])) {
                $gtin = (string) $item['GTIN'];
            }

            $quantity = null;
            if (isset($item['quantity'])) {
                $quantity = (int) $item['quantity'];
            } elseif (isset($item['stockLevel'])) {
                $quantity = (int) $item['stockLevel'];
            } elseif (isset($item['availableStock'])) {
                $quantity = (int) $item['availableStock'];
            }

            if ($gtin !== '' && $quantity !== null) {
                $levels[] = [
                    'gtin' => $gtin,
                    'quantity' => $quantity,
                ];
            }
        }

        return $levels;
    }


    private function queueOrderForExport($idOrder, $source)
    {
        $idOrder = (int) $idOrder;
        if ($idOrder <= 0) {
            return;
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        Db::getInstance()->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue` (`id_order`, `source`, `created_at`, `updated_at`) VALUES (' . (int) $idOrder . ', \'' . pSQL((string) $source) . '\', NOW(), NOW())'
        );
    }

    private function hasExportedOrderProducts($idOrder)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product` WHERE `id_order` = ' . (int) $idOrder
        );
    }

    private function ensureOrderExportQueueRow($idOrder, $source)
    {
        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue` (`id_order`, `source`, `created_at`, `updated_at`) VALUES (' . (int) $idOrder . ', \'' . pSQL((string) $source) . '\', NOW(), NOW())
            ON DUPLICATE KEY UPDATE `source` = VALUES(`source`), `exported_at` = NULL, `updated_at` = NOW()'
        );
    }

    private function exportOrderImmediately($idOrder, $source)
    {
        $idOrder = (int) $idOrder;
        if ($idOrder <= 0) {
            return [
                'status' => 'error',
                'message' => 'Order export skipped: invalid order id.',
            ];
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return [
                'status' => 'error',
                'message' => 'Order export skipped: order not found.',
            ];
        }

        $this->ensureOrderExportQueueRow($idOrder, $source);
        $result = $this->exportOrder($order, new MenyApi());

        if (!empty($result['success'])) {
            $this->markOrderExported($idOrder);

            return [
                'status' => 'ok',
                'message' => 'Order exported successfully.',
            ];
        }

        $message = isset($result['message']) ? (string) $result['message'] : 'Order export failed.';
        $this->markOrderExportError($idOrder, $message);

        return [
            'status' => 'error',
            'message' => $message,
        ];
    }

    private function processOrderExportQueue($targetShippingDate = null)
    {
        $this->applyIntegrationDatabaseTimezone($targetShippingDate);

        $this->logOrderExportCronEvent('queue_prepare', [
            'target_shipping_date_raw' => $targetShippingDate,
        ]);

        $targetShippingDate = $this->normalizeShippingDate($targetShippingDate);
        $this->logOrderExportCronEvent('queue_target_date', [
            'target_shipping_date' => $targetShippingDate,
            'has_shipping_date_filter' => $targetShippingDate !== null,
        ]);
        $shippingDateCondition = '';
        if ($targetShippingDate !== null) {
            $shippingDateCondition = '
              AND EXISTS (
                  SELECT 1
                  FROM `' . _DB_PREFIX_ . 'cart_distribution` cd
                  INNER JOIN `' . _DB_PREFIX_ . 'cart_distribution_shipping` cds
                    ON (cds.`id_cart_distribution` = cd.`id_cart_distribution`)
                  WHERE cd.`id_order` = q.`id_order`
                    AND DATE(cds.`shipping_date`) = \'' . pSQL($targetShippingDate) . '\'
              )';
        }

        $rows = Db::getInstance()->executeS(
            'SELECT q.`id_order`
            FROM `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue` q
            WHERE q.`exported_at` IS NULL' . $shippingDateCondition . '
            ORDER BY q.`id_order` ASC'
        );

        $queuedOrderIds = [];
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $queuedOrderIds[] = isset($row['id_order']) ? (int) $row['id_order'] : 0;
            }
        }

        $this->logOrderExportCronEvent('queue_rows_selected', [
            'target_shipping_date' => $targetShippingDate,
            'rows_count' => count($queuedOrderIds),
            'order_ids' => implode(',', $queuedOrderIds),
        ]);

        if (empty($rows)) {
            $this->logOrderExportCronEvent('queue_empty', [
                'target_shipping_date' => $targetShippingDate,
            ]);

            return [
                'status' => 'ok',
                'exported' => 0,
                'message' => $targetShippingDate !== null
                    ? sprintf('No queued orders for export with products shipping on %s.', $targetShippingDate)
                    : 'No queued orders for export.',
            ];
        }

        $api = new MenyApi();
        $exported = 0;
        $errors = [];

        foreach ($rows as $row) {
            $idOrder = (int) $row['id_order'];
            $this->logOrderExportCronEvent('order_start', [
                'id_order' => $idOrder,
                'target_shipping_date' => $targetShippingDate,
            ]);
            $order = new Order($idOrder);

            if (!Validate::isLoadedObject($order)) {
                $errorMessage = 'Order not found.';
                $this->markOrderExportError($idOrder, $errorMessage);
                $errors[] = [
                    'idOrder' => $idOrder,
                    'message' => $errorMessage,
                ];
                $this->logOrderExportCronEvent('order_missing', [
                    'id_order' => $idOrder,
                    'message' => $errorMessage,
                ]);
                continue;
            }

            $this->logOrderExportCronEvent('order_loaded', [
                'id_order' => $idOrder,
                'id_customer' => (int) $order->id_customer,
                'date_add' => (string) $order->date_add,
            ]);

            $result = $this->exportOrder($order, $api, $targetShippingDate, true);
            if (!empty($result['success'])) {
                if ($targetShippingDate !== null && $this->hasOrderShippingRowsAfterDate($idOrder, $targetShippingDate)) {
                    $this->markOrderExportAttemptSuccessful($idOrder);
                    $markStatus = 'attempt_successful_more_shipping_rows_pending';
                } else {
                    $this->markOrderExported($idOrder);
                    $markStatus = 'fully_exported';
                }
                ++$exported;
                $this->logOrderExportCronEvent('order_success', [
                    'id_order' => $idOrder,
                    'mark_status' => $markStatus,
                    'message' => isset($result['message']) ? (string) $result['message'] : '',
                ]);
                continue;
            }

            $errorMessage = isset($result['message']) ? (string) $result['message'] : 'Order export failed.';
            $this->markOrderExportError($idOrder, $errorMessage);
            $errors[] = [
                'idOrder' => $idOrder,
                'message' => $errorMessage,
            ];
            $this->logOrderExportCronEvent('order_error', [
                'id_order' => $idOrder,
                'message' => $errorMessage,
            ]);
        }

        $this->logOrderExportCronEvent('queue_finished', [
            'target_shipping_date' => $targetShippingDate,
            'exported' => $exported,
            'errors_count' => count($errors),
        ]);

        return [
            'status' => empty($errors) ? 'ok' : 'error',
            'exported' => $exported,
            'message' => sprintf('Order export processed. Successfully exported: %d.', $exported),
            'errors' => $errors,
        ];
    }

    private function exportOrder(Order $order, MenyApi $api, $targetShippingDate = null, $logCronDetails = false)
    {
        $idOrder = (int) $order->id;
        if ($logCronDetails) {
            $this->logOrderExportCronEvent('order_build_start', [
                'id_order' => $idOrder,
                'target_shipping_date' => $targetShippingDate,
            ]);
        }

        $distributionData = $this->buildOrderDistributionData($order, $targetShippingDate);
        $diagnostics = isset($distributionData['diagnostics']) && is_array($distributionData['diagnostics']) ? $distributionData['diagnostics'] : [];
        if ($logCronDetails) {
            $this->logOrderExportCronEvent('order_build_result', array_merge([
                'id_order' => $idOrder,
                'payloads_count' => !empty($distributionData['payloads']) && is_array($distributionData['payloads']) ? count($distributionData['payloads']) : 0,
                'exported_products_count' => !empty($distributionData['exportedProducts']) && is_array($distributionData['exportedProducts']) ? count($distributionData['exportedProducts']) : 0,
            ], $diagnostics));
        }

        if (empty($distributionData['payloads'])) {
            $message = $targetShippingDate !== null
                ? sprintf('No exportable order products found for shipping date %s.', $targetShippingDate)
                : 'No exportable order products found.';
            if ($logCronDetails) {
                $this->logOrderExportCronEvent('order_no_payloads', [
                    'id_order' => $idOrder,
                    'message' => $message,
                ]);
            }

            return [
                'success' => false,
                'message' => $message,
            ];
        }

        foreach ($distributionData['payloads'] as $payloadIndex => $payload) {
            if ($logCronDetails) {
                $this->logOrderExportCronEvent('payload_send_start', [
                    'id_order' => $idOrder,
                    'payload_index' => (int) $payloadIndex,
                    'distribution_id' => isset($payload['distributionId']) ? (string) $payload['distributionId'] : '',
                    'route_id' => isset($payload['routeId']) ? (string) $payload['routeId'] : '',
                    'lines_count' => !empty($payload['distributionLines']) && is_array($payload['distributionLines']) ? count($payload['distributionLines']) : 0,
                ]);
            }
            $result = $api->postOrderDistribution($payload);
            if (empty($result['success'])) {
                $message = isset($result['message']) && (string) $result['message'] !== ''
                    ? (string) $result['message']
                    : 'Order export failed.';
                if ($logCronDetails) {
                    $this->logOrderExportCronEvent('payload_send_failed', [
                        'id_order' => $idOrder,
                        'payload_index' => (int) $payloadIndex,
                        'http_code' => isset($result['httpCode']) ? (int) $result['httpCode'] : 0,
                        'message' => $message,
                    ]);
                }

                return [
                    'success' => false,
                    'message' => $message,
                ];
            }

            if ($logCronDetails) {
                $this->logOrderExportCronEvent('payload_send_success', [
                    'id_order' => $idOrder,
                    'payload_index' => (int) $payloadIndex,
                    'http_code' => isset($result['httpCode']) ? (int) $result['httpCode'] : 0,
                    'message' => isset($result['message']) ? (string) $result['message'] : '',
                ]);
            }
        }

        $this->saveExportedOrderProducts($idOrder, $distributionData['exportedProducts']);
        if ($logCronDetails) {
            $this->logOrderExportCronEvent('order_exported_products_saved', [
                'id_order' => $idOrder,
                'exported_products_count' => !empty($distributionData['exportedProducts']) && is_array($distributionData['exportedProducts']) ? count($distributionData['exportedProducts']) : 0,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Order exported successfully.',
        ];
    }

    private function buildOrderDistributionData(Order $order, $targetShippingDate = null)
    {
        $this->applyIntegrationDatabaseTimezone($targetShippingDate);

        $products = $order->getProducts();
        $cartDistribution = $this->getCartDistributionForOrder((int) $order->id);
        $shippingRows = [];

        if (!empty($cartDistribution)) {
            $shippingRows = $this->getCartDistributionShippingRows((int) $cartDistribution['id_cart_distribution'], $targetShippingDate);
        }

        $exportedShippingIds = $this->getExportedCartDistributionShippingIds((int) $order->id, $targetShippingDate);
        $distribution = $this->buildOrderDistributionLinesByShippingGroup($products, $shippingRows, (int) $order->id, (int) $order->id_customer, $targetShippingDate === null, $exportedShippingIds);
        $diagnostics = [
            'id_order' => (int) $order->id,
            'id_customer' => (int) $order->id_customer,
            'target_shipping_date' => $targetShippingDate,
            'order_products_count' => is_array($products) ? count($products) : 0,
            'has_cart_distribution' => !empty($cartDistribution),
            'id_cart_distribution' => !empty($cartDistribution['id_cart_distribution']) ? (int) $cartDistribution['id_cart_distribution'] : 0,
            'shipping_rows_count' => count($shippingRows),
            'already_exported_shipping_rows_count' => count($exportedShippingIds),
            'groups_count' => !empty($distribution['groups']) && is_array($distribution['groups']) ? count($distribution['groups']) : 0,
        ];
        if (!empty($distribution['diagnostics']) && is_array($distribution['diagnostics'])) {
            $diagnostics = array_merge($diagnostics, $distribution['diagnostics']);
        }
        $baseDistributionId = !empty($cartDistribution['id_order']) ? (string) (int) $cartDistribution['id_order'] : (string) (int) $order->id;
        $orderPlacedDate = $this->formatIntegrationDateTime((string) $order->date_add);
        $customer = $this->buildOrderCustomer($order);
        $payloads = [];

        foreach ($distribution['groups'] as $group) {
            $distributionId = $baseDistributionId;
            if (isset($group['dayIndex']) && (string) $group['dayIndex'] !== '') {
                $distributionId .= (string) (int) $group['dayIndex'];
            }

            $payload = [
                'distributionId' => $distributionId,
                'routeId' => $group['routeId'],
                'orderPlacedDate' => $orderPlacedDate,
                'distributionLines' => $group['lines'],
                'customer' => $customer,
            ];

            $payload['deliveryTimeFrom'] = $group['deliveryTimeFrom'];
            $payload['deliveryTimeTo'] = $group['deliveryTimeTo'];

            $payloads[] = $payload;
        }

        return [
            'payloads' => $payloads,
            'exportedProducts' => $distribution['exportedProducts'],
            'diagnostics' => $diagnostics,
        ];
    }

    private function buildOrderCustomer(Order $order)
    {
        $customer = new Customer((int) $order->id_customer);
        $deliveryAddress = new Address((int) $order->id_address_delivery);

        $isCustomerLoaded = Validate::isLoadedObject($customer);
        $isDeliveryAddressLoaded = Validate::isLoadedObject($deliveryAddress);

        $isBusinessCustomer = $isCustomerLoaded && isset($customer->type) && (int) $customer->type === 0;

        $orderCustomer = [
            'customerId' => $isCustomerLoaded ? (string) (int) $customer->id : (string) (int) $order->id_customer,
            'address' => [
                'phone' => $isDeliveryAddressLoaded ? $this->getDeliveryAddressPhone($deliveryAddress) : '',
                'email' => $isCustomerLoaded ? (string) $customer->email : '',
                'address' => $isDeliveryAddressLoaded ? $this->getDeliveryAddressStreet($deliveryAddress) : '',
                'city' => $isDeliveryAddressLoaded ? (string) $deliveryAddress->city : '',
                'postalCode' => $isDeliveryAddressLoaded ? (string) $deliveryAddress->postcode : '',
            ],
            'isBusinessCustomer' => $isBusinessCustomer,
            'firstName' => $isCustomerLoaded ? (string) $customer->firstname : '',
            'lastName' => $isCustomerLoaded ? (string) $customer->lastname : '',
        ];

        if ($isBusinessCustomer) {
            $orderCustomer['companyName'] = $this->getCustomerCompanyName($customer, $deliveryAddress, $isDeliveryAddressLoaded);
        }

        return $orderCustomer;
    }

    private function getCustomerCompanyName(Customer $customer, Address $deliveryAddress, $isDeliveryAddressLoaded)
    {
        if (isset($customer->companyName) && trim((string) $customer->companyName) !== '') {
            return trim((string) $customer->companyName);
        }

        if (isset($customer->company) && trim((string) $customer->company) !== '') {
            return trim((string) $customer->company);
        }

        if ($isDeliveryAddressLoaded && !empty($deliveryAddress->company)) {
            return trim((string) $deliveryAddress->company);
        }

        return '';
    }

    private function getDeliveryAddressPhone(Address $deliveryAddress)
    {
        if (!empty($deliveryAddress->phone_mobile)) {
            return (string) $deliveryAddress->phone_mobile;
        }

        if (!empty($deliveryAddress->phone)) {
            return (string) $deliveryAddress->phone;
        }

        return '';
    }

    private function getDeliveryAddressStreet(Address $deliveryAddress)
    {
        $streetParts = [];

        if (!empty($deliveryAddress->address1)) {
            $streetParts[] = (string) $deliveryAddress->address1;
        }

        if (!empty($deliveryAddress->address2)) {
            $streetParts[] = (string) $deliveryAddress->address2;
        }

        return implode(' ', $streetParts);
    }

    private function getCartDistributionForOrder($idOrder)
    {
        return Db::getInstance()->getRow(
            'SELECT `id_cart_distribution`
            FROM `' . _DB_PREFIX_ . 'cart_distribution`
            WHERE `id_order` = ' . (int) $idOrder . '
            ORDER BY `id_cart_distribution` ASC'
        );
    }

    private function getCartDistributionShippingRows($idCartDistribution, $targetShippingDate = null)
    {
        $targetShippingDate = $this->normalizeShippingDate($targetShippingDate);
        $shippingDateCondition = '';
        if ($targetShippingDate !== null) {
            $shippingDateCondition = ' AND DATE(cds.`shipping_date`) = \'' . pSQL($targetShippingDate) . '\'';
        }

        $rows = Db::getInstance()->executeS(
            'SELECT cds.`id_cart_distribution_shipping`, cds.`id_product`, cds.`id_product_attribute`, cds.`shipping_date`, cds.`quantity`, cds.`id_route`, cds.`day_index`, ar.`title` AS `route_title`
            FROM `' . _DB_PREFIX_ . 'cart_distribution_shipping` cds
            LEFT JOIN `' . _DB_PREFIX_ . 'address_route` ar ON ar.`id_address_route` = cds.`id_route`
            WHERE cds.`id_cart_distribution` = ' . (int) $idCartDistribution . $shippingDateCondition . '
            ORDER BY cds.`shipping_date` ASC, cds.`position` ASC, cds.`id_cart_distribution_shipping` ASC'
        );

        return !empty($rows) ? $rows : [];
    }

    private function getExportedCartDistributionShippingIds($idOrder, $targetShippingDate = null)
    {
        $targetShippingDate = $this->normalizeShippingDate($targetShippingDate);
        $shippingDateCondition = '';
        if ($targetShippingDate !== null) {
            $shippingDateCondition = ' AND DATE(`shipping_date`) = \'' . pSQL($targetShippingDate) . '\'';
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_cart_distribution_shipping`
            FROM `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product`
            WHERE `id_order` = ' . (int) $idOrder . '
              AND `id_cart_distribution_shipping` > 0' . $shippingDateCondition
        );

        $indexed = [];
        if (empty($rows)) {
            return $indexed;
        }

        foreach ($rows as $row) {
            $indexed[(int) $row['id_cart_distribution_shipping']] = true;
        }

        return $indexed;
    }

    private function buildOrderDistributionLinesByShippingGroup(array $products, array $shippingRows, $idOrder, $idCustomer, $allowFallback = true, array $exportedShippingIds = [])
    {
        if (empty($shippingRows)) {
            return [
                'groups' => [],
                'exportedProducts' => [],
                'diagnostics' => [
                    'fallback_used' => false,
                    'skip_reason' => !$allowFallback ? 'shipping_rows_empty_target_date' : 'shipping_rows_empty',
                ],
            ];
        }

        $productsByKey = $this->indexOrderProductsByProductKey($products);
        $groups = [];
        $exportedProducts = [];
        $skippedAlreadyExported = 0;
        $skippedMissingProduct = 0;
        $skippedMissingGtin = 0;

        foreach ($shippingRows as $shippingRow) {
            $idCartDistributionShipping = isset($shippingRow['id_cart_distribution_shipping']) ? (int) $shippingRow['id_cart_distribution_shipping'] : 0;
            if ($idCartDistributionShipping > 0 && isset($exportedShippingIds[$idCartDistributionShipping])) {
                ++$skippedAlreadyExported;
                continue;
            }

            $productKey = $this->getProductKey(
                isset($shippingRow['id_product']) ? (int) $shippingRow['id_product'] : 0,
                isset($shippingRow['id_product_attribute']) ? (int) $shippingRow['id_product_attribute'] : 0
            );

            if (empty($productsByKey[$productKey])) {
                ++$skippedMissingProduct;
                continue;
            }

            $product = $productsByKey[$productKey];
            $gtin = trim((string) (isset($product['product_ean13']) ? $product['product_ean13'] : ''));
            if ($gtin === '') {
                ++$skippedMissingGtin;
                continue;
            }

            $unitData = $this->getProductUnitData(
                isset($product['product_id']) ? (int) $product['product_id'] : 0,
                isset($product['product_attribute_id']) ? (int) $product['product_attribute_id'] : 0
            );

            $routeId = 'prestashop';
            if (!empty($shippingRow['shipping_date']) && isset($shippingRow['id_route'])) {
                $routeDate = $this->formatIntegrationDate((string) $shippingRow['shipping_date']);
                $routeId = $routeDate . '-' . (string) (int) $shippingRow['route_title'];
            } elseif (isset($shippingRow['id_route'])) {
                $routeId = (string) (int) $shippingRow['route_title'];
            }

            $dayIndex = isset($shippingRow['day_index']) && (string) $shippingRow['day_index'] !== '' ? (int) $shippingRow['day_index'] : null;
            $deliveryTimeWindow = $this->getDeliveryTimeWindowForShippingRow((int) $idOrder, (int) $idCustomer, $shippingRow);
            $groupKey = $routeId . '|'
                . ($dayIndex !== null ? (string) $dayIndex : 'none') . '|'
                . (!empty($deliveryTimeWindow['from']) ? $deliveryTimeWindow['from'] : 'none') . '|'
                . (!empty($deliveryTimeWindow['to']) ? $deliveryTimeWindow['to'] : 'none');
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'routeId' => $routeId,
                    'deliveryTimeFrom' => $deliveryTimeWindow['from'],
                    'deliveryTimeTo' => $deliveryTimeWindow['to'],
                    'dayIndex' => $dayIndex,
                    'lines' => [],
                ];
            }

            $groups[$groupKey]['lines'][] = [
                'distributionLineId' => (string) (int) $shippingRow['id_cart_distribution_shipping'],
                'gtin' => $gtin,
                'productName' => (string) $product['product_name'],
                'unit' => $unitData['unit'],
                'quantity' => $this->applyUnitPartsToQuantity((int) $shippingRow['quantity'], $unitData['parts']),
                'allowSubstitution' => false,
            ];

            $orderDetailId = isset($product['id_order_detail']) ? (int) $product['id_order_detail'] : 0;
            $idProduct = isset($product['product_id']) ? (int) $product['product_id'] : 0;
            if ($orderDetailId > 0) {
                $exportedProducts[] = [
                    'id_cart_distribution_shipping' => $idCartDistributionShipping,
                    'id_order_detail' => $orderDetailId,
                    'id_product' => $idProduct,
                    'ean13' => $gtin,
                    'shipping_date' => isset($shippingRow['shipping_date']) ? (string) $shippingRow['shipping_date'] : '',
                ];
            }
        }

        if (empty($groups)) {
            return [
                'groups' => [],
                'exportedProducts' => [],
                'diagnostics' => [
                    'fallback_used' => false,
                    'skipped_already_exported_count' => $skippedAlreadyExported,
                    'skipped_missing_product_count' => $skippedMissingProduct,
                    'skipped_missing_gtin_count' => $skippedMissingGtin,
                    'skip_reason' => !$allowFallback ? 'no_lines_for_target_date' : 'no_exportable_shipping_rows',
                ],
            ];
        }

        return [
            'groups' => array_values($groups),
            'exportedProducts' => array_values($exportedProducts),
            'diagnostics' => [
                'fallback_used' => false,
                'skipped_already_exported_count' => $skippedAlreadyExported,
                'skipped_missing_product_count' => $skippedMissingProduct,
                'skipped_missing_gtin_count' => $skippedMissingGtin,
            ],
        ];
    }

    private function getDeliveryTimeWindowForShippingRow($idOrder, $idCustomer, array $shippingRow)
    {
        $deliveryTime = $this->getRouteDeliveryTime(
            (int) $idOrder,
            (int) $idCustomer,
            (int) $shippingRow['id_route']
        );
        return [
            'from' => $this->formatIntegrationDateTimeWithMinuteOffset((string) $shippingRow['shipping_date'], (int) $deliveryTime['delivery_time_from']),
            'to' => $this->formatIntegrationDateTimeWithMinuteOffset((string) $shippingRow['shipping_date'], (int) $deliveryTime['delivery_time_to']),
        ];
    }

    private function getRouteDeliveryTime($idOrder, $idCustomer, $idRoute)
    {
        $orderDeliveryTime = Db::getInstance()->getRow(
            'SELECT `delivery_time_from`, `delivery_time_to`
            FROM `' . _DB_PREFIX_ . 'order_route`
            WHERE `id_order` = ' . (int) $idOrder . '
              AND `id_route` = ' . (int) $idRoute . '
            ORDER BY `id_route` ASC'
        );

        if (!empty($orderDeliveryTime)) {
            return $orderDeliveryTime;
        }

        $customerDeliveryTime = Db::getInstance()->getRow(
            'SELECT `delivery_time_from`, `delivery_time_to`
            FROM `' . _DB_PREFIX_ . 'customer_route`
            WHERE `id_customer` = ' . (int) $idCustomer . '
              AND `id_route` = ' . (int) $idRoute . '
            ORDER BY `id_route` ASC'
        );

        if (!empty($customerDeliveryTime)) {
            return $customerDeliveryTime;
        }

        return [
            'delivery_time_from' => 0,
            'delivery_time_to' => 0,
        ];
    }

    private function formatIntegrationDateTimeWithMinuteOffset($dateTime, $minuteOffset)
    {
        $integrationDateTime = $this->createIntegrationDateTime($dateTime, true);
        $deliveryDateTime = $integrationDateTime->setTime(0, 0, 0);
        $minuteOffset = max(0, (int) $minuteOffset);

        if ($minuteOffset > 0) {
            $deliveryDateTime = $deliveryDateTime->modify('+' . $minuteOffset . ' minutes');
        }

        return $deliveryDateTime->format('Y-m-d\TH:i:sP');
    }

    private function buildFallbackOrderDistributionLines(array $products)
    {
        $lines = [];
        $exportedProducts = [];
        $skippedMissingGtin = 0;

        foreach ($products as $product) {
            $gtin = trim((string) (isset($product['product_ean13']) ? $product['product_ean13'] : ''));
            if ($gtin === '') {
                ++$skippedMissingGtin;
                continue;
            }

            $orderDetailId = isset($product['id_order_detail']) ? (int) $product['id_order_detail'] : 0;
            if ($orderDetailId > 0) {
                $exportedProducts[$orderDetailId] = [
                    'id_order_detail' => $orderDetailId,
                    'id_product' => isset($product['product_id']) ? (int) $product['product_id'] : 0,
                    'ean13' => $gtin,
                ];
            }

            $unitData = $this->getProductUnitData(
                isset($product['product_id']) ? (int) $product['product_id'] : 0,
                isset($product['product_attribute_id']) ? (int) $product['product_attribute_id'] : 0
            );

            $lines[] = [
                'distributionLineId' => (string) $orderDetailId,
                'gtin' => $gtin,
                'productName' => (string) $product['product_name'],
                'unit' => $unitData['unit'],
                'quantity' => $this->applyUnitPartsToQuantity((int) $product['product_quantity'], $unitData['parts']),
                'allowSubstitution' => false,
            ];
        }

        return [
            'lines' => $lines,
            'exportedProducts' => array_values($exportedProducts),
            'skippedMissingGtin' => $skippedMissingGtin,
        ];
    }


    private function getProductUnitData($idProduct, $idProductAttribute)
    {
        static $unitCache = [];

        $idProduct = (int) $idProduct;
        $idProductAttribute = (int) $idProductAttribute;
        $cacheKey = $this->getProductKey($idProduct, $idProductAttribute);

        if (isset($unitCache[$cacheKey])) {
            return $unitCache[$cacheKey];
        }

        $unitData = [
            'unit' => 'piece',
            'parts' => 0.0,
        ];
        $queries = [];
        $queries[] = 
            'SELECT bp.`parts`, bpv.`value`
            FROM `' . _DB_PREFIX_ . 'sbsbaseprice` bp
            INNER JOIN `' . _DB_PREFIX_ . 'sbsbaseprice_value` bpv
                ON bpv.`id_sbsbaseprice_value` = bp.`id_sbsbaseprice_value`
                AND bpv.`id_lang` = 2
            WHERE bp.`id_product` = ' . $idProduct . '
                AND bp.`id_product_attribute` = ' . $idProductAttribute;

        if ($idProductAttribute > 0) {
            $queries[] = 'SELECT bp.`parts`, bpv.`value`
                FROM `' . _DB_PREFIX_ . 'sbsbaseprice` bp
                INNER JOIN `' . _DB_PREFIX_ . 'sbsbaseprice_value` bpv
                    ON bpv.`id_sbsbaseprice_value` = bp.`id_sbsbaseprice_value`
                    AND bpv.`id_lang` = 2
                WHERE bp.`id_product` = ' . $idProduct . '
                    AND bp.`id_product_attribute` = 0';
        }

        foreach ($queries as $query) {
            $row = Db::getInstance()->getRow($query);

            if (!empty($row) && isset($row['value'])) {
                $value = trim((string) $row['value']);
                if ($value === '') {
                    continue;
                }

                $parts = isset($row['parts']) ? (float) $row['parts'] : 0.0;
                $unitData = [
                    'unit' => $value,
                    'parts' => $parts > 0 ? $parts : 0.0,
                ];
                break;
            }
        }

        $unitCache[$cacheKey] = $unitData;

        return $unitData;
    }

    private function applyUnitPartsToQuantity($quantity, $parts)
    {
        $quantity = (int) $quantity;
        $parts = (float) $parts;

        if ($parts <= 0) {
            return $quantity;
        }

        return $quantity * $parts;
    }

    private function saveExportedOrderProducts($idOrder, array $exportedProducts)
    {
        foreach ($exportedProducts as $product) {
            if (empty($product['id_cart_distribution_shipping']) || empty($product['id_order_detail']) || empty($product['id_product']) || empty($product['ean13']) || empty($product['shipping_date'])) {
                continue;
            }

            $idCartDistributionShipping = (int) $product['id_cart_distribution_shipping'];
            $existingId = (int) Db::getInstance()->getValue(
                'SELECT `id_sbslunsjmeny_order_exported_product`
                FROM `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product`
                WHERE `id_cart_distribution_shipping` = ' . $idCartDistributionShipping
            );

            if ($existingId > 0) {
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product`
                    SET `id_order` = ' . (int) $idOrder . ',
                        `id_order_detail` = ' . (int) $product['id_order_detail'] . ',
                        `id_product` = ' . (int) $product['id_product'] . ',
                        `ean13` = \'' . pSQL((string) $product['ean13']) . '\',
                        `shipping_date` = \'' . pSQL((string) $product['shipping_date']) . '\'
                    WHERE `id_sbslunsjmeny_order_exported_product` = ' . $existingId
                );
                continue;
            }

            Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product`
                    (`id_order`, `id_cart_distribution_shipping`, `id_order_detail`, `id_product`, `ean13`, `shipping_date`, `created_at`)
                VALUES (
                    ' . (int) $idOrder . ',
                    ' . $idCartDistributionShipping . ',
                    ' . (int) $product['id_order_detail'] . ',
                    ' . (int) $product['id_product'] . ',
                    \'' . pSQL((string) $product['ean13']) . '\',
                    \'' . pSQL((string) $product['shipping_date']) . '\',
                    NOW()
                )'
            );
        }
    }

    private function indexOrderProductsByProductKey(array $products)
    {
        $indexed = [];

        foreach ($products as $product) {
            $indexed[$this->getProductKey(
                isset($product['product_id']) ? (int) $product['product_id'] : 0,
                isset($product['product_attribute_id']) ? (int) $product['product_attribute_id'] : 0
            )] = $product;
        }

        return $indexed;
    }

    private function getProductKey($idProduct, $idProductAttribute)
    {
        return (int) $idProduct . '-' . (int) $idProductAttribute;
    }

    private function getTomorrowShippingDate()
    {
        $timezone = $this->getIntegrationTimezone();
        $today = new DateTimeImmutable('now', $timezone);

        if ($today->format('N') === '5') {
            return $today->modify('next monday')->format('Y-m-d');
        }

        return $today->modify('+1 day')->format('Y-m-d');
    }

    private function normalizeShippingDate($shippingDate)
    {
        $dateTime = $this->createIntegrationDateTime($shippingDate, false);
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->format('Y-m-d');
    }

    private function hasOrderShippingRowsAfterDate($idOrder, $shippingDate)
    {
        $this->applyIntegrationDatabaseTimezone($shippingDate);

        $shippingDate = $this->normalizeShippingDate($shippingDate);
        if ($shippingDate === null) {
            return false;
        }

        return (bool) Db::getInstance()->getValue(
            'SELECT 1
            FROM `' . _DB_PREFIX_ . 'cart_distribution` cd
            INNER JOIN `' . _DB_PREFIX_ . 'cart_distribution_shipping` cds
                ON (cds.`id_cart_distribution` = cd.`id_cart_distribution`)
            WHERE cd.`id_order` = ' . (int) $idOrder . '
              AND DATE(cds.`shipping_date`) > \'' . pSQL($shippingDate) . '\''
        );
    }

    private function formatIntegrationDateTime($dateTime)
    {
        $integrationDateTime = $this->createIntegrationDateTime($dateTime, true);

        return $integrationDateTime->format('Y-m-d\TH:i:sP');
    }

    private function formatIntegrationDate($dateTime)
    {
        $integrationDateTime = $this->createIntegrationDateTime($dateTime, true);

        return $integrationDateTime->format('Y-m-d');
    }

    private function createIntegrationDateTime($dateTime, $fallbackToNow)
    {
        $value = trim((string) $dateTime);
        if ($value === '') {
            return $fallbackToNow ? new DateTimeImmutable('now', $this->getIntegrationTimezone()) : null;
        }

        try {
            return new DateTimeImmutable($value, $this->getIntegrationTimezone());
        } catch (Exception $exception) {
            return $fallbackToNow ? new DateTimeImmutable('now', $this->getIntegrationTimezone()) : null;
        }
    }

    public function getIntegrationTimezoneName()
    {
        $timezoneName = trim((string) Configuration::get(self::CONF_INTEGRATION_TIMEZONE));
        if ($timezoneName === '' || !in_array($timezoneName, timezone_identifiers_list(), true)) {
            return self::DEFAULT_INTEGRATION_TIMEZONE;
        }

        return $timezoneName;
    }

    private function getIntegrationTimezone()
    {
        return new DateTimeZone($this->getIntegrationTimezoneName());
    }

    private function applyIntegrationDatabaseTimezone($dateTime = 'now')
    {
        $integrationDateTime = $this->createIntegrationDateTime($dateTime, true);
        $offset = $integrationDateTime->getOffset();
        $sign = $offset < 0 ? '-' : '+';
        $offset = abs($offset);
        $hours = floor($offset / 3600);
        $minutes = floor(($offset % 3600) / 60);
        $mysqlOffset = sprintf('%s%02d:%02d', $sign, $hours, $minutes);

        Db::getInstance()->execute('SET time_zone = \'' . pSQL($mysqlOffset) . '\'');
    }

    private function markOrderExportAttemptSuccessful($idOrder)
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue` SET `last_error` = NULL, `attempts` = `attempts` + 1, `updated_at` = NOW() WHERE `id_order` = ' . (int) $idOrder
        );
    }

    private function markOrderExported($idOrder)
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue` SET `exported_at` = NOW(), `last_error` = NULL, `attempts` = `attempts` + 1, `updated_at` = NOW() WHERE `id_order` = ' . (int) $idOrder
        );
    }

    private function markOrderExportError($idOrder, $message)
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue` SET `last_error` = \'' . pSQL((string) $message, true) . '\', `attempts` = `attempts` + 1, `updated_at` = NOW() WHERE `id_order` = ' . (int) $idOrder
        );
    }


    private function logOrderExportCronEvent($section, array $data)
    {
        if ((int) Configuration::get(self::CONF_ENABLE_LOGGING) !== 1) {
            return;
        }

        $logsRootDir = rtrim(_PS_MODULE_DIR_, '/\\') . '/' . $this->name . '/logs';
        $periodDir = date('Y.m');
        $targetDir = $logsRootDir . '/' . $periodDir;
        $filePath = $targetDir . '/order-export-' . date('d.H') . '.log';

        $this->ensureIntegrationLogDirectory($logsRootDir);
        $this->ensureIntegrationLogDirectory($targetDir);

        $lines = [];
        $lines[] = '[' . date('Y-m-d H:i:s') . '] [order-export-cron] [' . (string) $section . ']';
        $this->appendFlattenedIntegrationLogLines($data, $lines, '');
        $lines[] = '';

        file_put_contents($filePath, implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND);
        $logFileUrl = $this->buildPublicIntegrationLogFileUrl($filePath);
        Configuration::updateValue(self::CONF_LAST_ORDER_EXPORT_LOG_FILE, $logFileUrl);
        Configuration::updateValue(self::CONF_LAST_LOG_FILE, $logFileUrl);
    }

    private function buildPublicIntegrationLogFileUrl($filePath)
    {
        $rootPath = rtrim(_PS_ROOT_DIR_, '/\\');
        $normalizedFilePath = str_replace('\\', '/', (string) $filePath);
        $normalizedRootPath = str_replace('\\', '/', $rootPath);

        if (strpos($normalizedFilePath, $normalizedRootPath) !== 0) {
            return '';
        }

        $relativePath = ltrim(substr($normalizedFilePath, strlen($normalizedRootPath)), '/');
        if ($relativePath === '') {
            return '';
        }

        $shopUrl = Context::getContext()->shop->getBaseURL(true);

        return rtrim($shopUrl, '/') . '/' . $relativePath;
    }

    private function ensureIntegrationLogDirectory($directory)
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $indexPath = rtrim($directory, '/\\') . '/index.php';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, "<?php\n\n");
        }
    }

    private function appendFlattenedIntegrationLogLines(array $data, array &$lines, $prefix)
    {
        foreach ($data as $key => $value) {
            $fieldKey = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (is_array($value)) {
                if (empty($value)) {
                    $lines[] = $fieldKey . ' = []';
                    continue;
                }

                $this->appendFlattenedIntegrationLogLines($value, $lines, $fieldKey);
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = 'null';
            } else {
                $value = (string) $value;
            }

            $lines[] = $fieldKey . ' = ' . $value;
        }
    }

    private function installConfiguration()
    {
        return Configuration::updateValue(self::CONF_CLIENT_ID, 'netthandel.lunsj')
            && Configuration::updateValue(self::CONF_CLIENT_SECRET, '951883a532354f0fb2df490b249d4bfd')
            && Configuration::updateValue(self::CONF_INTEGRATION_BASE_URL, 'https://api-dev.test.ngdata.no/sylinder/netthandel/lunsjintegration/v1')
            && Configuration::updateValue(self::CONF_TOKEN_URL, 'https://systest.id.trumf.no/connect/token')
            && Configuration::updateValue(self::CONF_ACCESS_TOKEN, '')
            && Configuration::updateValue(self::CONF_TOKEN_EXPIRES_AT, '')
            && Configuration::updateValue(self::CONF_LAST_STOCK_SYNC_AT, '0')
            && Configuration::updateValue(self::CONF_LAST_STOCK_SYNC_RESPONSE, '')
            && Configuration::updateValue(self::CONF_ENABLE_LOGGING, '1')
            && Configuration::updateValue(self::CONF_DEACTIVATE_OUT_OF_STOCK_PRODUCTS, '0')
            && Configuration::updateValue(self::CONF_INTEGRATION_TIMEZONE, self::DEFAULT_INTEGRATION_TIMEZONE)
            && Configuration::updateValue(self::CONF_LAST_STOCK_IMPORT_LOG_FILE, '')
            && Configuration::updateValue(self::CONF_LAST_ORDER_EXPORT_LOG_FILE, '')
            && Configuration::updateValue(self::CONF_LAST_LOG_FILE, '');
    }

    private function uninstallConfiguration()
    {
        return Configuration::deleteByName(self::CONF_CLIENT_ID)
            && Configuration::deleteByName(self::CONF_CLIENT_SECRET)
            && Configuration::deleteByName(self::CONF_INTEGRATION_BASE_URL)
            && Configuration::deleteByName(self::CONF_TOKEN_URL)
            && Configuration::deleteByName(self::CONF_ACCESS_TOKEN)
            && Configuration::deleteByName(self::CONF_TOKEN_EXPIRES_AT)
            && Configuration::deleteByName(self::CONF_LAST_STOCK_SYNC_AT)
            && Configuration::deleteByName(self::CONF_LAST_STOCK_SYNC_RESPONSE)
            && Configuration::deleteByName(self::CONF_ENABLE_LOGGING)
            && Configuration::deleteByName(self::CONF_DEACTIVATE_OUT_OF_STOCK_PRODUCTS)
            && Configuration::deleteByName(self::CONF_INTEGRATION_TIMEZONE)
            && Configuration::deleteByName(self::CONF_LAST_STOCK_IMPORT_LOG_FILE)
            && Configuration::deleteByName(self::CONF_LAST_ORDER_EXPORT_LOG_FILE)
            && Configuration::deleteByName(self::CONF_LAST_LOG_FILE);
    }

    private function installDatabase()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue` (
            `id_order` INT UNSIGNED NOT NULL,
            `source` VARCHAR(64) NOT NULL,
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_error` TEXT NULL,
            `exported_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        $sqlExportedProducts = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product` (
            `id_sbslunsjmeny_order_exported_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order` INT UNSIGNED NOT NULL,
            `id_order_detail` INT UNSIGNED NOT NULL,
            `id_cart_distribution_shipping` INT(10) UNSIGNED NOT NULL,
            `id_product` INT UNSIGNED NOT NULL,
            `ean13` VARCHAR(32) NOT NULL,
            `shipping_date` TIMESTAMP NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_sbslunsjmeny_order_exported_product`),
            KEY `idx_order` (`id_order`),
            KEY `idx_order_shipping_date` (`id_order`, `shipping_date`),
            KEY `idx_cart_distribution_shipping` (`id_cart_distribution_shipping`),
            KEY `idx_product` (`id_product`),
            KEY `idx_shipping_date` (`shipping_date`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sqlExportedProducts);
    }

    private function uninstallDatabase()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sbslunsjmeny_order_export_queue`')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sbslunsjmeny_order_exported_product`');
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminSbslunsjmenyConfig';
        $tab->name = [];

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Meny API';
        }

        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');
        $tab->module = $this->name;

        return (bool) $tab->add();
    }

    private function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminSbslunsjmenyConfig');
        if (!$idTab) {
            return true;
        }

        $tab = new Tab($idTab);

        return (bool) $tab->delete();
    }
}
