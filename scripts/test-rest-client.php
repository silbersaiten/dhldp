<?php
/** Run with: php scripts/test-rest-client.php (requires cURL). No DHL requests are made. */
if (PHP_SAPI === 'cli-server') {
    if ($_SERVER['REQUEST_URI'] === '/success') {
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
    public static function substr($value, $start) { return substr($value, $start); }
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
    public static $logs = array();
    public static function logToFile($service, $message, $key)
    {
        checkRest($service === 'DHL' && $key === 'dhl_api', 'Incorrect log routing');
        self::$logs[] = $message;
    }
}
require_once dirname(__DIR__) . '/classes/DHLDPRestClient.php';
require_once dirname(__DIR__) . '/classes/DHLTokenManager.php';

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
    echo "REST client and token error tests passed.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    unlink($output);
}
