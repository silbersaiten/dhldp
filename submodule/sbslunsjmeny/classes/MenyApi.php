<?php

class MenyApi
{
    public function hasValidToken()
    {
        $token = (string) Configuration::get(Sbslunsjmeny::CONF_ACCESS_TOKEN);
        $expiresAt = (int) Configuration::get(Sbslunsjmeny::CONF_TOKEN_EXPIRES_AT);

        return !empty($token) && $expiresAt > time();
    }

    public function authorize($grantType = 'client_credentials')
    {
        $payload = [
            'client_id' => (string) Configuration::get(Sbslunsjmeny::CONF_CLIENT_ID),
            'client_secret' => (string) Configuration::get(Sbslunsjmeny::CONF_CLIENT_SECRET),
            'grant_type' => (string) $grantType,
        ];

        $tokenUrl = trim((string) Configuration::get(Sbslunsjmeny::CONF_TOKEN_URL));
        if ($tokenUrl === '') {
            return [
                'success' => false,
                'message' => 'Token URL is not configured.',
            ];
        }

        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $error,
            ];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400 || !is_array($decoded) || empty($decoded['access_token'])) {
            return [
                'success' => false,
                'message' => 'Authorization failed. HTTP ' . $httpCode . '. Response: ' . $response,
            ];
        }

        $accessToken = (string) $decoded['access_token'];
        $expiresIn = isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3600;
        $expiresAt = time() + max(60, $expiresIn);

        Configuration::updateValue(Sbslunsjmeny::CONF_ACCESS_TOKEN, $accessToken);
        Configuration::updateValue(Sbslunsjmeny::CONF_TOKEN_EXPIRES_AT, (string) $expiresAt);

        return [
            'success' => true,
            'message' => 'Access token updated successfully.',
            'token' => $accessToken,
        ];
    }

    public function postStockLevelsQuery(array $gtins, $since = null)
    {
        if (empty($gtins)) {
            return [
                'success' => false,
                'message' => 'Stock levels query requires at least one GTIN.',
            ];
        }

        $payload = [
            'gtins' => array_values($gtins),
        ];

        if (!empty($since)) {
            $payload['since'] = (string) $since;
        }

        return $this->sendAuthorizedJsonRequest('/lunsj/stocklevels/query', $payload);
    }

    public function postOrderDistribution(array $orderDistribution)
    {
        if (!isset($orderDistribution['distributionId'], $orderDistribution['routeId'], $orderDistribution['distributionLines'])
            || (string) $orderDistribution['distributionId'] === ''
            || (string) $orderDistribution['routeId'] === ''
            || empty($orderDistribution['distributionLines'])
        ) {
            return [
                'success' => false,
                'message' => 'Order distribution payload is invalid: distributionId, routeId and distributionLines are required.',
            ];
        }

        $this->logIntegrationEvent('order-export', 'payload', $orderDistribution);

        return $this->sendAuthorizedJsonRequest('/lunsj/orderdistribution', $orderDistribution);
    }

    private function sendAuthorizedJsonRequest($path, array $payload)
    {
        $accessTokenResult = $this->getAccessToken();
        if (!$accessTokenResult['success']) {
            return $accessTokenResult;
        }

        $integrationBaseUrl = trim((string) Configuration::get(Sbslunsjmeny::CONF_INTEGRATION_BASE_URL));
        if ($integrationBaseUrl === '') {
            return [
                'success' => false,
                'message' => 'Integration Base URL is not configured.',
            ];
        }

        $url = rtrim($integrationBaseUrl, '/') . '/' . ltrim((string) $path, '/');
        $normalizedPayload = $this->normalizeToUtf8($payload);
        $jsonPayload = json_encode($normalizedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonPayload === false) {
            return [
                'success' => false,
                'message' => 'Failed to encode request payload to JSON: ' . json_last_error_msg() . '.',
            ];
        }

        $ch = curl_init($url);
        $requestHeaders = [
            'Authorization: Bearer ' . $accessTokenResult['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ((string) $path === '/lunsj/stocklevels/query') {
            $this->logIntegrationEvent('stock-import', 'request_headers', $requestHeaders);
            $this->logIntegrationEvent('stock-import', 'request_payload_raw_json', [
                'payload' => $jsonPayload,
            ]);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            if ((string) $path === '/lunsj/stocklevels/query') {
                $this->logIntegrationEvent('stock-import', 'response_error', [
                    'httpCode' => $httpCode,
                    'error' => $error,
                ]);
            }

            return [
                'success' => false,
                'message' => 'cURL error: ' . $error,
            ];
        }

        $decodedResponse = json_decode($response, true);
        if ((string) $path === '/lunsj/orderdistribution') {
            $this->logIntegrationEvent('order-export', 'response', [
                'httpCode' => $httpCode,
                'body' => $this->formatJsonForLog($response, $decodedResponse),
            ]);
        } elseif ((string) $path === '/lunsj/stocklevels/query') {
            $this->logIntegrationEvent('stock-import', 'response', [
                'httpCode' => $httpCode,
                'body' => $decodedResponse,
                'raw' => $response,
            ]);
        }

        if ($httpCode >= 400) {
            return [
                'success' => false,
                'httpCode' => $httpCode,
                'message' => 'Integration API error. HTTP ' . $httpCode . '. Response: ' . $response,
                'data' => is_array($decodedResponse) ? $decodedResponse : null,
            ];
        }

        return [
            'success' => true,
            'httpCode' => $httpCode,
            'message' => 'Request successfully accepted by integration API.',
            'data' => is_array($decodedResponse) ? $decodedResponse : null,
            'raw' => $response,
        ];
    }

    private function getAccessToken()
    {
        if ($this->hasValidToken()) {
            return [
                'success' => true,
                'token' => (string) Configuration::get(Sbslunsjmeny::CONF_ACCESS_TOKEN),
            ];
        }

        $authResult = $this->authorize();
        if (!$authResult['success']) {
            return $authResult;
        }

        return [
            'success' => true,
            'token' => (string) $authResult['token'],
        ];
    }

    private function logIntegrationEvent($channel, $section, $data)
    {
        if ((int) Configuration::get(Sbslunsjmeny::CONF_ENABLE_LOGGING) !== 1) {
            return;
        }

        $moduleDir = rtrim(_PS_MODULE_DIR_, '/\\') . '/sbslunsjmeny';
        $logsRootDir = $moduleDir . '/logs';
        $periodDir = date('Y.m');
        $targetDir = $logsRootDir . '/' . $periodDir;
        $filePrefix = (string) $channel === 'order-export' ? 'order-export' : 'stock-import';
        $filePath = $targetDir . '/' . $filePrefix . '-' . date('d.H') . '.log';

        $this->ensureLogDirectory($logsRootDir);
        $this->ensureLogDirectory($targetDir);

        $lines = [];
        $lines[] = '[' . date('Y-m-d H:i:s') . '] [' . (string) $channel . '] [' . (string) $section . ']';
        $this->appendFlattenedLogLines((array) $data, $lines, '');
        $lines[] = '';

        file_put_contents($filePath, implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND);
        $logFileUrl = $this->buildPublicLogFileUrl($filePath);
        if ((string) $channel === 'order-export') {
            Configuration::updateValue(Sbslunsjmeny::CONF_LAST_ORDER_EXPORT_LOG_FILE, $logFileUrl);
        } else {
            Configuration::updateValue(Sbslunsjmeny::CONF_LAST_STOCK_IMPORT_LOG_FILE, $logFileUrl);
        }
        Configuration::updateValue(Sbslunsjmeny::CONF_LAST_LOG_FILE, $logFileUrl);
    }

    private function buildPublicLogFileUrl($filePath)
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

    private function ensureLogDirectory($directory)
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $indexPath = rtrim($directory, '/\\') . '/index.php';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, "<?php\n\n");
        }
    }

    private function appendFlattenedLogLines(array $data, array &$lines, $prefix)
    {
        foreach ($data as $key => $value) {
            $fieldKey = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (is_array($value)) {
                if (empty($value)) {
                    $lines[] = $fieldKey . ' = []';
                    continue;
                }

                $this->appendFlattenedLogLines($value, $lines, $fieldKey);
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

    private function formatJsonForLog($rawJson, $decodedJson)
    {
        if (is_array($decodedJson)) {
            $formattedJson = json_encode($decodedJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            if ($formattedJson !== false) {
                return $formattedJson;
            }
        }

        return (string) $rawJson;
    }

    private function normalizeToUtf8($value)
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalizedKey = is_string($key) ? $this->normalizeStringToUtf8($key) : $key;
                $normalized[$normalizedKey] = $this->normalizeToUtf8($item);
            }

            return $normalized;
        }

        if (is_string($value)) {
            return $this->normalizeStringToUtf8($value);
        }

        return $value;
    }

    private function normalizeStringToUtf8($value)
    {
        if ($value === '') {
            return $value;
        }

        if (preg_match('//u', $value)) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($converted !== false) {
            return $converted;
        }

        return utf8_encode($value);
    }
}
