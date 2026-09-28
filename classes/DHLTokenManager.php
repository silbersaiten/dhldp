<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2025 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.1.0
 * @link      https://www.silbersaiten.de
 */

namespace PrestaShop\Module\dhldp\classes;
use Configuration;
use Tools;
use DHLDPRestClient;

class DHLTokenManager
{
    const CONFIG_TOKEN_KEY = 'DHLDP_DHL_ACCESS_TOKEN';
    const CONFIG_TOKEN_EXPIRY = 'DHLDP_DHL_TOKEN_EXPIRY';
    private $clientId;
    private $clientSecret;
    private $dhl_user;
    private $dhl_pass;
    private $tokenUrlSDX = 'https://api-sandbox.dhl.com/parcel/de/account/auth/ropc/v1/token';  // Sandbox environment
    private $tokenUrlLive = 'https://api-eu.dhl.com/parcel/de/account/auth/ropc/v1/token';  // Production environment
    private $tokenUrl;

    public function __construct($id_shop = null)
    {
        if (Tools::getIsset('DHLDP_DHL_MODE')) {
            $mode = (int)Tools::getValue('DHLDP_DHL_MODE');
        } else {
            $mode = (int)Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        }
        if ($mode == 1) {
            $this->tokenUrl = $this->tokenUrlLive;
            $this->clientId = Configuration::get('DHLDP_DHL_CLIENT_ID');
            $this->clientSecret = Configuration::get('DHLDP_DHL_CLIENT_SECRET');
            if (Tools::getIsset('DHLDP_DHL_LIVE_USER') && Tools::getIsset('DHLDP_DHL_LIVE_SIGN')) {
                $this->dhl_user = Tools::getValue('DHLDP_DHL_LIVE_USER');
                $this->dhl_pass = Tools::getValue('DHLDP_DHL_LIVE_SIGN');
            } else {
                $this->dhl_user = Configuration::get('DHLDP_DHL_LIVE_USER', null, null, $id_shop);
                $this->dhl_pass = Configuration::get('DHLDP_DHL_LIVE_SIGN', null, null, $id_shop);
            }
        } else {
            $this->tokenUrl = $this->tokenUrlSDX;
            $this->clientId = Configuration::get('DHLDP_DHL_CLIENT_ID_TEST');
            $this->clientSecret = Configuration::get('DHLDP_DHL_CLIENT_SECRET_TEST');
            $this->dhl_user = Configuration::get('DHLDP_DHL_SDX_USER');
            $this->dhl_pass = Configuration::get('DHLDP_DHL_SDX_PASS');
        }
    }

    public function getToken()
    {
        if ($this->isTokenExpired()) {
            return $this->requestNewToken();
        }
        return Configuration::get(self::CONFIG_TOKEN_KEY);
    }

    private function isTokenExpired()
    {
        $tokenExpiry = Configuration::get(self::CONFIG_TOKEN_EXPIRY);
        return !$tokenExpiry || time() >= (int)$tokenExpiry;
    }

    private function requestNewToken()
    {
        $rclient = new DHLDPRestClient(array('savelog_callback' => 'DHLDP::logToFile'));
        $requestData = array(
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $this->dhl_user,
            'password' => $this->dhl_pass
        );
        $rclient->saveLogData('DHL', 'requestNewToken', [
            'endpoint' => $this->tokenUrl,
            'data' => array_merge($requestData, ['password' => '***', 'client_secret' => '***']) // Скрываем чувствительные данные
        ]);
        $res = $rclient->post($this->tokenUrl, $requestData);

        if ($res->error) {
            $rclient->saveLogData('DHL', 'requestNewToken', null, null, $res->error);
            throw new \Exception('Error requesting DHL token: ' . $res->error);
        }

        try {
            $tokenData = $res->decodeResponse();
        } catch (\Exception $e) {
            $message = 'DHL token request failed (HTTP ' . (int)$res->info->http_code . '): ' . $e->getMessage();
            $rclient->saveLogData('DHL', 'requestNewToken', null, null, $message);
            throw new \Exception($message, 0, $e);
        }

        if ($res->info->http_code >= 200 && $res->info->http_code < 300 && isset($tokenData['access_token']) && isset($tokenData['expires_in'])) {
            $this->storeToken($tokenData['access_token'], $tokenData['expires_in']);
            return $tokenData['access_token'];
        }

        $rclient->saveLogData('DHL', 'requestNewToken', null, [
            'access_token' => isset($tokenData['access_token']) ? '***' : null,
            'expires_in' => isset($tokenData['expires_in']) ? $tokenData['expires_in'] : null,
            'token_type' => isset($tokenData['token_type']) ? $tokenData['token_type'] : null
        ]);
        $details = array();
        foreach (array('title', 'detail', 'error', 'error_description') as $field) {
            if (isset($tokenData[$field]) && is_string($tokenData[$field])) {
                $details[] = $tokenData[$field];
            }
        }
        $message = 'DHL token request failed (HTTP ' . (int)$res->info->http_code . '): '
            . ($details ? implode('; ', $details) : 'Invalid token response');
        $rclient->saveLogData('DHL', 'requestNewToken', null, null, $message);
        throw new \Exception($message);
    }

    private function storeToken($accessToken, $expiresIn)
    {
        $expiryTime = time() + (int)$expiresIn;
        Configuration::updateValue('DHLDP_DHL_ACCESS_TOKEN', $accessToken);
        Configuration::updateValue('DHLDP_DHL_TOKEN_EXPIRY', $expiryTime);
    }

    // Method to use the token in a DHL API request
    public function makeApiRequest($endpoint, $method = 'GET', $data = [])
    {
        $accessToken = $this->getToken();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
//dump($ch);
        $response = curl_exec($ch);

//dump($response);
//dump(curl_errno($ch));die;
        if (curl_errno($ch)) {
            throw new \Exception('DHL API Request Error: ' . curl_error($ch));
        }

        curl_close($ch);
        return json_decode($response, true);
    }
}
