<?php
/** Run with: php scripts/test-rest-client.php (requires cURL). No DHL requests are made. */
if (PHP_SAPI === 'cli-server') {
    if (strpos($_SERVER['REQUEST_URI'], '/orders?validate=true') === 0) {
        header('Content-Type: application/json');
        echo '{"status":{"status":200,"title":"OK","detail":"Shipment validated"}}';
    } elseif (strpos($_SERVER['REQUEST_URI'], '/echo') === 0) {
        header('Content-Type: application/json');
        echo json_encode(array('method' => $_SERVER['REQUEST_METHOD'], 'received' => file_get_contents('php://input')));
    } elseif ($_SERVER['REQUEST_URI'] === '/empty') {
        http_response_code(204);
    } elseif ($_SERVER['REQUEST_URI'] === '/success') {
        header('Content-Type: application/json');
        echo '{"access_token":"test-access-secret","expires_in":3600}';
    } elseif ($_SERVER['REQUEST_URI'] === '/invalid') {
        http_response_code(502);
        header('Content-Type: application/problem+json');
        echo '{broken json';
    } elseif ($_SERVER['REQUEST_URI'] === '/html') {
        http_response_code(502);
        header('Content-Type: text/html');
        echo '<html>Gateway unavailable</html>';
    } else {
        http_response_code(401);
        header('Content-Type: application/problem+json; charset=utf-8');
        echo '{"status":401,"title":"Unauthorized","detail":"Invalid DHL credentials"}';
    }
    return;
}

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

class Tools
{
    public static function strlen($value) { return strlen($value); }
    public static function strtolower($value) { return strtolower($value); }
    public static function strtoupper($value) { return strtoupper($value); }
    public static function substr($value, $start, $length = null) { return mb_substr($value, $start, $length, 'UTF-8'); }
    public static function getIsset($key) { return false; }
}
class Configuration
{
    public static $values = array();
    public static function get($key) { return isset(self::$values[$key]) ? self::$values[$key] : 'test-credential-secret'; }
    public static function updateValue($key, $value) { self::$values[$key] = $value; }
}
class DHLDP
{
    public function getCountriesIDsForRA($code) { return array('iso_code3' => 'DEU'); }
    public static $logs = array();
    public static $loggingEnabled = true;
    public static function logToFile($service, $message, $key)
    {
        checkRest($service === 'DHL' && $key === 'dhl_api', 'Incorrect log routing');
        if (self::$loggingEnabled) {
            self::$logs[] = $message;
        }
    }
}
require_once dirname(__DIR__) . '/classes/DHLDPRestClient.php';
require_once dirname(__DIR__) . '/classes/DHLTokenManager.php';
require_once dirname(__DIR__) . '/classes/DHLDPApiRest.php';

function checkRest($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function tokenManagerFor($url)
{
    Configuration::$values = array('DHLDP_DHL_TOKEN_EXPIRY' => 0);
    $manager = new PrestaShop\Module\dhldp\classes\DHLTokenManager();
    $property = new ReflectionProperty($manager, 'tokenUrl');
    $property->setAccessible(true);
    $property->setValue($manager, $url);
    return $manager;
}

foreach (array('application/json', 'application/problem+json; charset=utf-8', 'Application/Problem+JSON', 'application/vnd.dhl+json') as $type) {
    $client = new DHLDPRestClient();
    $client->parseResponse("HTTP/1.1 401 Unauthorized\r\nContent-Type: " . $type . "\r\n\r\n{\"detail\":\"Invalid credentials\"}");
    checkRest($client->decodeResponse()['detail'] === 'Invalid credentials', 'Failed to decode ' . $type);
}

$client = new DHLDPRestClient(array('savelog_callback' => 'DHLDP::logToFile'));
$client->saveLogData('DHL', 'mask test', array('password' => 'request-secret'), array(
    'body' => '{"access_token":"response-secret","nested":{"client_secret":"nested-secret"}}',
    'label' => array('url' => 'https://example.test/?token=url-secret'),
));
$logs = implode("\n", DHLDP::$logs);
foreach (array('request-secret', 'response-secret', 'nested-secret', 'url-secret') as $secret) {
    checkRest(strpos($logs, $secret) === false, 'Secret leaked into logs');
}

// API diagnostics must reach the error list consumed by the page alerts.
foreach (array(
    array(array('status' => 400, 'title' => 'Bad Request', 'detail' => 'Invalid billing number'), 400, array('Bad Request', 'Invalid billing number')),
    array(array('status' => array('status' => 403, 'title' => 'Forbidden', 'detail' => 'Product not enabled')), 403, array('Forbidden', 'Product not enabled')),
    array(array('sstatus' => array('status' => 400, 'detail' => 'Invalid return receiver')), 400, array('Invalid return receiver')),
    array(array('message' => 'Account blocked'), 403, array('Account blocked')),
    array(array('error' => 'invalid_request', 'error_description' => 'Missing profile'), 400, array('invalid_request', 'Missing profile')),
    array(array('errors' => array(array('property' => 'shipper.postalCode', 'message' => 'Invalid postcode'))), 400, array('shipper.postalCode: Invalid postcode')),
    array(array('status' => array('status' => 207), 'items' => array(
        array('validationMessages' => array(array('validationState' => 'Warning', 'validationMessage' => 'Address corrected'))),
        array('validationMessages' => array(array('validationState' => 'Error', 'property' => 'consignee.city', 'validationMessage' => 'City required'))),
    )), 207, array('consignee.city: City required')),
    array(null, 502, array('DHL API: HTTP 502. Empty or invalid JSON response.')),
    array(array(), 503, array('DHL API: HTTP 503')),
) as $case) {
    $api = new PrestaShop\Module\dhldp\classes\DHLDPApiRest(null);
    checkRest($api->getResponse($case[0], $case[1]) === false, 'Failed response accepted');
    foreach ($case[2] as $expected) {
        checkRest(in_array($expected, $api->errors), 'API detail missing from errors: ' . $expected);
    }
    checkRest(!in_array('Address corrected', $api->errors), 'Warning treated as error');
}
$api = new PrestaShop\Module\dhldp\classes\DHLDPApiRest(null);
$success = array('status' => array('status' => 201, 'title' => 'Created', 'detail' => 'Label ready'), 'items' => array(array('shipmentNo' => '123')));
checkRest($api->getResponse($success, 201) === $success && !$api->errors, 'Successful response changed');
checkRest(in_array('Created: Label ready', $api->confirmations), 'Success confirmation lost');

// Regression: a rejected shipment can contain only Warning validation messages.
$weakValidation = array('status' => array('title' => 'Bad Request', 'status' => 400,
    'detail' => '0 of 1 shipment validated OK. Details included.'), 'items' => array(array(
        'sstatus' => array('title' => 'Within the shipment there was at least one weak validation error.', 'status' => 400),
        'validationMessages' => array(
            array('property' => 'customs.items[0]', 'validationMessage' => 'The description is too long. The maximum length is 50 characters.', 'validationState' => 'Warning'),
            array('property' => 'details.weight', 'validationMessage' => 'Please enter the total weight of the shipment including packaging here.', 'validationState' => 'Warning'),
        ),
    )));
$api = new PrestaShop\Module\dhldp\classes\DHLDPApiRest(null);
checkRest($api->getResponse($weakValidation, 400) === false, 'Weak validation rejection accepted');
checkRest(count($api->warnings) === 2, 'Original warning details lost');
foreach ($api->warnings as $warning) {
    checkRest(in_array('Warning: ' . $warning, $api->errors), 'Rejected validation warning missing from failure alert');
}
$api = new PrestaShop\Module\dhldp\classes\DHLDPApiRest(null);
$success['items'][0]['validationMessages'] = $weakValidation['items'][0]['validationMessages'];
checkRest($api->getResponse($success, 201) === $success && !$api->errors, 'Successful shipment warnings treated as rejection');
checkRest(count($api->warnings) === 2, 'Successful shipment warnings lost');

// Form values are strings; customs JSON must contain numbers and omit unset enums.
$customs = array(
    'exportType' => 'COMMERCIAL_GOODS', 'exportTypeDescription' => '', 'termsOfTrade' => '',
    'placeOfCommital' => 'Hannover', 'invoiceNumber' => '', 'permitNumber' => '', 'attestationNumber' => '',
    'additionalFee' => '0', 'ExportDocPosition' => array(array(
        'description' => 'Test product', 'amount' => '1', 'countryCodeOrigin' => 'DE',
        'customsValue' => '89.00', 'netWeightInKG' => '1',
    )),
);
$prepared = json_decode(json_encode($api->getPreparationCustoms($customs)), true);
checkRest(!array_key_exists('shippingConditions', $prepared), 'Empty shipping condition sent');
checkRest($prepared['items'][0]['packagedQuantity'] === 1, 'Quantity must be a JSON integer');
checkRest(is_numeric($prepared['items'][0]['itemValue']['value']) && !is_string($prepared['items'][0]['itemValue']['value']), 'Customs value must be a JSON number');
checkRest($prepared['items'][0]['itemWeight']['value'] === 1, 'Weight must be a JSON number');
$customs['termsOfTrade'] = 'DDP';
$customs['ExportDocPosition'][0]['customsValue'] = '1234.56';
$prepared = $api->getPreparationCustoms($customs);
checkRest($prepared['shippingConditions'] === 'DDP', 'Selected shipping condition lost');
checkRest($prepared['items'][0]['itemValue']['value'] === 1234.56, 'Thousands separator corrupted customs value');
foreach (array('Short description', str_repeat('A', 50), str_repeat('A', 51), str_repeat('ü', 49) . 'Ж終') as $description) {
    $customs['ExportDocPosition'][0]['description'] = $description;
    $prepared = $api->getPreparationCustoms($customs);
    $actual = $prepared['items'][0]['itemDescription'];
    checkRest($actual === mb_substr($description, 0, 50, 'UTF-8'), 'Customs description not truncated to 50 characters');
    checkRest(mb_strlen($actual, 'UTF-8') <= 50 && json_encode($prepared) !== false, 'Description truncation broke UTF-8');
}

// Bind an ephemeral local port, then run a fake API in a separate PHP process.
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkRest($socket !== false, 'Cannot allocate local test port');
$address = stream_socket_get_name($socket, false);
fclose($socket);
$output = tempnam(sys_get_temp_dir(), 'dhl-rest-test-');
$process = proc_open('"' . PHP_BINARY . '" -S ' . $address . ' "' . __FILE__ . '"', array(
    0 => array('pipe', 'r'), 1 => array('file', $output, 'a'), 2 => array('file', $output, 'a'),
), $pipes, null, null, array('bypass_shell' => true));
checkRest(is_resource($process), 'Cannot start fake API');
try {
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; ++$attempt) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    checkRest($ready, 'Fake API did not start: ' . file_get_contents($output));
    foreach (array('/problem' => 'Invalid DHL credentials', '/html' => 'HTTP 502', '/invalid' => 'Invalid JSON response') as $path => $expected) {
        try {
            tokenManagerFor('http://' . $address . $path)->getToken();
            throw new RuntimeException('Failed request was accepted');
        } catch (Exception $e) {
            checkRest(strpos($e->getMessage(), $expected) !== false, 'Unexpected error: ' . $e->getMessage());
        }
        checkRest(!isset(Configuration::$values['DHLDP_DHL_ACCESS_TOKEN']), 'Failed response cached a token');
    }
    checkRest(tokenManagerFor('http://' . $address . '/success')->getToken() === 'test-access-secret', 'Successful token request failed');
    $logs = implode("\n", DHLDP::$logs);
    foreach (array('401', 'application/problem+json', 'Invalid DHL credentials', '502', 'Gateway unavailable') as $expected) {
        checkRest(strpos($logs, $expected) !== false, 'Missing diagnostic: ' . $expected);
    }
    checkRest(strpos($logs, 'test-access-secret') === false, 'Access token leaked');
    checkRest(strpos($logs, 'test-credential-secret') === false, 'Credentials leaked');

    $manager = new PrestaShop\Module\dhldp\classes\DHLTokenManager();
    foreach (array('GET', 'POST', 'DELETE') as $method) {
        DHLDP::$logs = array();
        $response = $manager->makeApiRequest('http://' . $address . '/echo', $method, array('refNo' => 'order-42'));
        checkRest($response['method'] === $method, 'HTTP method changed');
        checkRest(count(DHLDP::$logs) === 2, 'Expected one request and one response');
        $logs = implode("\n", DHLDP::$logs);
        foreach (array('DHL API Request', $method, '/echo', 'order-42', 'http_status', '200', 'application/json', 'received') as $expected) {
            checkRest(strpos($logs, $expected) !== false, 'Missing API diagnostic: ' . $expected);
        }
    }
    foreach (array('/invalid' => '{broken json', '/html' => 'Gateway unavailable', '/empty' => '204', '/problem' => 'Invalid DHL credentials') as $path => $expected) {
        DHLDP::$logs = array();
        $manager->makeApiRequest('http://' . $address . $path);
        checkRest(strpos(implode("\n", DHLDP::$logs), $expected) !== false, 'Missing raw response: ' . $expected);
    }

    // Validation used to bypass all request/response logging.
    DHLDP::$logs = array();
    Configuration::$values['DHLDP_DHL_MODE'] = 0;
    PrestaShop\Module\dhldp\classes\DHLDPApiRest::$cig_endpoint_sandbox = 'http://' . $address;
    $api = new PrestaShop\Module\dhldp\classes\DHLDPApiRest(null);
    $params = array('ShipmentOrder' => array('profile' => 'test-profile', 'shipments' => array(
        'product' => 'V01PAK', 'billingNumber' => '1234567890', 'refNo' => 'validation-order',
        'shipper' => array('city' => 'Berlin'), 'consignee' => array('city' => 'Bonn'), 'details' => array('weight' => 1),
    )));
    $api->validateDHLShipment('validate', $params);
    $logs = implode("\n", DHLDP::$logs);
    foreach (array('validateDHLShipment', '/orders?validate=true', 'validation-order', 'Shipment validated') as $expected) {
        checkRest(strpos($logs, $expected) !== false, 'Missing validation diagnostic: ' . $expected);
    }

    DHLDP::$logs = array();
    $client->get('http://' . $address . '/echo', array('postalCode' => '10115'), array('DHL-API-Key' => 'location-secret'));
    $logs = implode("\n", DHLDP::$logs);
    checkRest(strpos($logs, '10115') !== false && strpos($logs, 'received') !== false, 'Missing GET query or response');
    checkRest(strpos($logs, 'location-secret') === false, 'API key leaked');

    // A refused local connection must still produce a request and an error response.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $closedAddress = stream_socket_get_name($socket, false);
    fclose($socket);
    DHLDP::$logs = array();
    $failed = $client->get('http://' . $closedAddress);
    checkRest($failed->error && count(DHLDP::$logs) === 2, 'Transport failure not logged');
    checkRest(strpos(implode("\n", DHLDP::$logs), 'Error:') !== false, 'Missing cURL error');
    DHLDP::$logs = array();
    try {
        $manager->makeApiRequest('http://' . $closedAddress);
        throw new RuntimeException('Transport failure was accepted');
    } catch (Exception $e) {
        checkRest(strpos($e->getMessage(), 'DHL API Request Error:') !== false, 'Unexpected transport exception');
    }
    checkRest(count(DHLDP::$logs) === 2, 'DHL transport failure not logged');

    DHLDP::$logs = array();
    DHLDP::$loggingEnabled = false;
    $manager->makeApiRequest('http://' . $address . '/echo', 'POST', array('refNo' => 'disabled'));
    $client->get('http://' . $address . '/echo');
    checkRest(DHLDP::$logs === array(), 'Disabled logging emitted data');
    echo "REST client, token and DHL API logging tests passed.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    unlink($output);
}
