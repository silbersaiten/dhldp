<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2025 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.1.1
 * @link      https://www.silbersaiten.de
 */

require_once(dirname(__FILE__) . '/DHLDPRestClient.php');

class DPRestApi
{
    public static $endpoint_live = 'https://api-eu.dhl.com/post/de/shipping/im/v1/';
    public static $tracking_url = 'https://www.deutschepost.de/sendung/simpleQuery.html?form.sendungsnummer=[tracking_number]';
    public static $ppl_update_csv = 'https://prestamodule.silberserver.de/dhl/ppl57.csv';

    public static $products_filename = 'data/ppl.csv';
    public $ppl = 0;
    public $voucher_layout = 'AddressZone';

    public static $partnerid = 'ASNPR';
    public static $apikey = 'nVgwguea8TxFXw8B02GI6uTzY060xW9I';
    public static $keyphase = '1';

    public $errors = array();
    public $user_token = false;

    public function __construct()
    {
        $this->getPPLVersion();
    }

    public function fileGetContents($url, $use_include_path = false, $stream_context = null, $curl_timeout = 5)
    {
        if ($stream_context == null && preg_match('/^https?:\/\//', $url)) {
            $stream_context = @stream_context_create(array('http' => array('timeout' => $curl_timeout)));
        }
        if (in_array(ini_get('allow_url_fopen'), array('On', 'on', '1')) || !preg_match('/^https?:\/\//', $url)) {
            return Tools::file_get_contents($url, $use_include_path, $stream_context);
        } elseif (function_exists('curl_init')) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, $curl_timeout);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            if ($stream_context != null) {
                $opts = stream_context_get_options($stream_context);
                if (isset($opts['http']['method']) && Tools::strtolower($opts['http']['method']) == 'post') {
                    curl_setopt($curl, CURLOPT_POST, true);
                    if (isset($opts['http']['content'])) {
                        parse_str($opts['http']['content'], $post_data);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
                    }
                }
            }
            $content = curl_exec($curl);
            curl_close($curl);
            return $content;
        } else {
            return false;
        }
    }

    public function updatePPL()
    {
        $opts = array(
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true
            )
        );
        $stream_context = stream_context_create($opts);

        $ppl_content = $this->fileGetContents(self::$ppl_update_csv, false, $stream_context);
        if ($ppl_content) {
            file_put_contents(dirname(__FILE__) . '/../' . self::$products_filename, mb_convert_encoding($ppl_content, 'UTF-8', 'ISO-8859-1'));

            if (preg_match('/ppl(?:_v)?(?P<version>\d+)/i', self::$ppl_update_csv, $v_matches)) {
                $version = (int) $v_matches['version'];
                if ($version >= 10) {
                    $version = $version / 10;
                }
                Configuration::updateGlobalValue('DHLDP_DP_PPL_VERSION', $version);
            }
            return true;
        }

        return false;
    }

    public function getProductList()
    {
        $ar = [];
        $url = self::$ppl_update_csv;

        if ($csvContent = file_get_contents($url)) {
            $handle = fopen('php://memory', 'r+');
            fwrite($handle, $csvContent);
            rewind($handle);
            $header = fgetcsv($handle, 0, ';');
            $header = array_map(function ($value) {return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');}, $header);

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                $data = array_map(function ($value) { return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');}, $data);
                $row = array_combine($header, $data); // Связываем заголовки с данными
                $price = isset($row['PROD_BRPREIS'])
                    ? str_replace(',', '.', $row['PROD_BRPREIS'])
                    : 0.0;
                $price = (float)$price;
                $precision = isset(Context::getContext()->currency->precision)
                    ? (int)Context::getContext()->currency->precision
                    : 2;
                $price = Tools::ps_round($price, $precision);
                $ar[] = [
                    'id' => isset($row['PROD_ID']) ? $row['PROD_ID'] : null,
                    'name' => isset($row['PROD_NAME']) ? $row['PROD_NAME'] : null,
                    'price' => $price,
                ];
            }
            fclose($handle);
        }
        if (is_array($ar)) {
            Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'dhldp_dp_productlist');
            Db::getInstance()->execute('ALTER TABLE ' . _DB_PREFIX_ . 'dhldp_dp_productlist AUTO_INCREMENT = 1');
            foreach ($ar as $product) {
                $p = array(
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                );
                Db::getInstance()->insert('dhldp_dp_productlist', $p);
            }
            return true;
        }
        return false;
    }

    public function getPPLVersion()
    {
        $this->ppl = Configuration::getGlobalValue('DHLDP_DP_PPL_VERSION') ? Configuration::getGlobalValue('DHLDP_DP_PPL_VERSION') : 32;
        return $this->ppl;
    }

    public function retrievePageFormats()
    {
        $response = $this->callApi(
            'retrievePageFormats',
            array(),
            Context::getContext()->shop->id,
            false
        );
        $page_formats = array();
        $formats = array();
        if (isset($response->pageFormats) && is_array($response->pageFormats)) {
            $formats = $response->pageFormats;
        } elseif (isset($response->pageFormat) && is_array($response->pageFormat)) {
            $formats = $response->pageFormat;
        }

        foreach ($formats as $page_format) {
            if (!isset($page_format->isAddressPossible) || $page_format->isAddressPossible == 1) {
                $page_formats[(int)$page_format->id] = array(
                    'name' => $page_format->name,
                    'type' => $page_format->pageType,
                    'orie' => isset($page_format->pageLayout->orientation) ? $page_format->pageLayout->orientation : null,
                    'col' => isset($page_format->pageLayout->labelCount->labelX) ? $page_format->pageLayout->labelCount->labelX : null,
                    'row' => isset($page_format->pageLayout->labelCount->labelY) ? $page_format->pageLayout->labelCount->labelY : null,
                );
            }
        }

        if (count($page_formats) > 0) {
            ksort($page_formats);
            Configuration::updateGlobalValue('DHLDP_DP_PAGE_FORMATS', json_encode($page_formats));
            return true;
        }
        return false;
    }

    public function retrieveContractProducts()
    {
        $response = $this->callApi(
            'retrieveContractProducts',
            array(),
            Context::getContext()->shop->id,
            true
        );
        if (is_object($response) && isset($response->products)) {
            $pt = array();
            foreach ($response->products as $p) {
                $pt[$p->productCode] = array(
                    'id' => $p->productCode,
                    'price_contract' => $p->price / 100
                );
                Db::getInstance()->update('dhldp_dp_productlist', array('price_contract' => $pt[$p->productCode]['price_contract'], 'date_upd' => date('Y-m-d H:i:s')), 'id=' . (int)$p->productCode);
            }
            if (count($pt)) {
                Db::getInstance()->update('dhldp_dp_productlist',
                    array('price_contract' => 0, 'date_upd' => date('Y-m-d H:i:s')),
                    'id not in (' . implode(',', array_keys($pt)) . ')');
                return true;
            }
        }
        return false;
    }

    public function getPageFormats($id = null)
    {
        $formats = json_decode(Configuration::getGlobalValue('DHLDP_DP_PAGE_FORMATS'), true);
        if ($id != null) {
            if (isset($formats[(int)$id])) {
                return $formats[(int)$id];
            }
            return false;
        }
        return $formats;
    }

    public function prepareAddress(Address $address)
    {
        $country_and_state = Address::getCountryAndState($address->id);

        if ($country_and_state) {
            $country = new Country((int)$country_and_state['id_country']);
            $state = $country_and_state['id_state'] ? new State((int)$country_and_state['id_state']) : false;

            $additional = $address->address2;

            $matches = array();
            preg_match(
                '/^(?P<streetname>[^\d]+) (?P<streetnumber>([ \/0-9-])+.?)$/',
                trim($address->address1),
                $matches
            );
            if (!count($matches)) {
                preg_match(
                    '/^(?P<streetnumber>[ \/0-9-]+.?) (?P<streetname>[^\d]+.?)$/',
                    trim($address->address1),
                    $matches
                );
                if (!count($matches)) {
                    preg_match(
                        '/(?P<streetnumber>[ \/0-9-]+.?) (?P<streetname>[^\d]+.?)/',
                        trim($address->address1),
                        $matches
                    );
                    if (!count($matches)) {
                        $street_name = $address->address1;
                        preg_match(
                            '/(?P<streetnumber>[ \/0-9-]+.?)/',
                            trim($additional),
                            $matches
                        );
                        if (isset($matches['streetnumber'])) {
                            $street_number = $matches['streetnumber'];
                            $additional = str_replace($matches['streetnumber'], '', $additional);
                        } else {
                            $street_number = '';
                        }
                    } else {
                        $street_name = trim($matches['streetname']);
                        $street_number = trim($matches['streetnumber']);
                    }
                } else {
                    $street_name = trim($matches['streetname']);
                    $street_number = trim($matches['streetnumber']);
                }
            } else {
                $street_name = trim($matches['streetname']);
                $street_number = trim($matches['streetnumber']);
            }

            $receiver = new stdClass();
            $receiver->name = new stdClass();
            if ($address->company != '') {
                $receiver->name->companyName = new stdClass();
                $receiver->name->companyName->company = $address->company; // max 50
                $receiver->name->companyName->personName = new stdClass();
                $receiver->name->companyName->personName->salutation = ''; //max 10
                $receiver->name->companyName->personName->title = ''; //max 10
                $receiver->name->companyName->personName->firstname = $address->firstname; //max 35
                $receiver->name->companyName->personName->lastname = $address->lastname; //max 35
            } else {
                $receiver->name->personName = new stdClass();
                $receiver->name->personName->salutation = ''; //max 10
                $receiver->name->personName->title = ''; //max 10
                $receiver->name->personName->firstname = $address->firstname; //max 35
                $receiver->name->personName->lastname = $address->lastname; //max 35
            }

            $receiver->address = new stdClass();
            $receiver->address->street = $street_name; // max 50
            $receiver->address->houseNo = $street_number; //max 10
            $receiver->address->additional = (($state != false) ? $state->iso_code . ' ' : '') . $additional;//max 50
            $receiver->address->zip = $address->postcode; // max 10
            $receiver->address->city = $address->city; // max 35 *
            $receiver->address->country = $this->getCountries(Tools::strtoupper($country->iso_code)); //iso 3 letters *

            return $receiver;
        }

        return false;
    }

    public function getSender($id_shop)
    {
        $sender = new stdClass();
        $sender->name = new stdClass();
        if ((int)Configuration::get('DHLDP_DP_NAME') == 1) {
            $sender->name->companyName = new stdClass();
            $sender->name->companyName->company = Configuration::get('DHLDP_DP_COMPANY', null, null, $id_shop); // max 50
            $sender->name->companyName->personName = new stdClass();
            $sender->name->companyName->personName->salutation = Configuration::get('DHLDP_DP_SALUTATION', null, null, $id_shop); //max 10
            $sender->name->companyName->personName->title = Configuration::get('DHLDP_DP_TITLE', null, null, $id_shop); //max 10
            $sender->name->companyName->personName->firstname = Configuration::get('DHLDP_DP_FIRSTNAME', null, null, $id_shop); //max 35
            $sender->name->companyName->personName->lastname = Configuration::get('DHLDP_DP_LASTNAME', null, null, $id_shop); //max 35
        } else {
            $sender->name->personName = new stdClass();
            $sender->name->personName->salutation = Configuration::get('DHLDP_DP_SALUTATION', null, null, $id_shop); //max 10
            $sender->name->personName->title = Configuration::get('DHLDP_DP_TITLE', null, null, $id_shop); //max 10
            $sender->name->personName->firstname = Configuration::get('DHLDP_DP_FIRSTNAME', null, null, $id_shop); //max 35
            $sender->name->personName->lastname = Configuration::get('DHLDP_DP_LASTNAME', null, null, $id_shop); //max 35
        }

        $sender->address = new stdClass();
        $sender->address->street = Configuration::get('DHLDP_DP_STREET', null, null, $id_shop); // max 50
        $sender->address->houseNo = Configuration::get('DHLDP_DP_HOUSENO', null, null, $id_shop); //max 10
        $sender->address->additional = Configuration::get('DHLDP_DP_ADDITIONAL', null, null, $id_shop);//max 50
        $sender->address->zip = Configuration::get('DHLDP_DP_ZIP', null, null, $id_shop); // max 10
        $sender->address->city = Configuration::get('DHLDP_DP_CITY', null, null, $id_shop); // max 35 *
        $country = new Country((int)Configuration::get('DHLDP_DP_COUNTRY', null, null, $id_shop));
        $sender->address->country = $this->getCountries(Tools::strtoupper($country->iso_code)); //iso 3 letters *

        return $sender;
    }

    public function authenticateUser($mode, $partner_id, $key_phase, $api_key, $username, $password, $id_shop = null)
    {
        if ($this->user_token != false) {
            return $this->user_token;
        }

        $this->errors = array();
        $headers = $this->getRestHeaders($partner_id, $key_phase, $api_key);
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $client_id = getenv('DHLDP_DP_CLIENT_ID');
        if (!$client_id) {
            $client_id = Configuration::get('DHLDP_DP_CLIENT_ID', null, null, $id_shop);
        }
        $client_secret = getenv('DHLDP_DP_CLIENT_SECRET');
        if (!$client_secret) {
            $client_secret = Configuration::get('DHLDP_DP_CLIENT_SECRET', null, null, $id_shop);
        }
        $payload = array(
            'grant_type' => 'client_credentials',
            'username' => $username,
            'password' => $password,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        );

        $response = $this->request(
            $mode,
            '/user',
            $payload,
            'POST',
            $headers,
            false
        );

        $token_keys = array('token', 'userToken', 'accessToken', 'access_token');
        foreach ($token_keys as $token_key) {
            if ($response && isset($response[$token_key])) {
                $this->user_token = $response[$token_key];
                return (object) array('userToken' => $this->user_token);
            }
        }

        if (isset($response['error'])) {
            $this->errors[] = $response['error'];
        } else {
            $this->errors[] = 'Authentication failed';
        }

        return false;
    }

    public function callApi($function, $params, $id_shop, $user_token = false)
    {
        $this->errors = array();
        $mode = Configuration::get('DHLDP_DP_MODE', null, null, $id_shop);
        $headers = $this->getRestHeaders(self::$partnerid, self::$keyphase, self::$apikey);

        if ($user_token == true) {
            $this->authenticateUser(
                $mode,
                self::$partnerid,
                self::$keyphase,
                self::$apikey,
                (Configuration::get('DHLDP_DP_MODE', null, null, $id_shop) == 1) ? Configuration::get('DHLDP_DP_LIVE_USERNAME', null, null, $id_shop) : Configuration::get('DHLDP_DP_SBX_USERNAME', null, null, $id_shop),
                (Configuration::get('DHLDP_DP_MODE', null, null, $id_shop) == 1) ? Configuration::get('DHLDP_DP_LIVE_PASSWORD', null, null, $id_shop) : Configuration::get('DHLDP_DP_SBX_PASSWORD', null, null, $id_shop),
                $id_shop
            );

            if ($this->user_token) {
                $headers['Authorization'] = 'Bearer ' . $this->user_token;
            }
        }

        $method = 'POST';
        $endpoint = '/' . $function;
        $query = array();

        switch ($function) {
            case 'retrievePageFormats':
                $method = 'GET';
                $endpoint = '/pageformats';
                break;
            case 'retrieveContractProducts':
                $method = 'GET';
                $endpoint = '/products/contract';
                break;
            case 'createShopOrderId':
                $endpoint = '/shoppingcart';
                $params = array();
                break;
            case 'checkoutShoppingCartPDF':
                $endpoint = '/shoppingcart/checkout';
                $query = array('format' => 'PDF');
                break;
            case 'checkoutShoppingCartPNG':
                $endpoint = '/shoppingcart/checkout';
                $query = array('format' => 'PNG');
                break;
        }

        if (!empty($query)) {
            $endpoint .= '?' . http_build_query($query);
        }

        $payload = $method === 'GET' ? null : $this->normalizePayload($params);
        $response = $this->request($mode, $endpoint, $payload, $method, $headers);

        $this->logRestCall($function, $endpoint, $payload, $response, $headers);

        if ($response === false) {
            return false;
        }

        $normalized = $this->normalizeResponse($function, $response);
        return $normalized;
    }

    private function getRestHeaders($partner_id, $key_phase, $api_key)
    {
        $request_timestamp = date('dmY-His');
        $partner_signature = Tools::substr(md5($partner_id . '::' . $request_timestamp . '::' . $key_phase . '::' . $api_key), 0, 8);

        return array(
            'PARTNER_ID' => $partner_id,
            'REQUEST_TIMESTAMP' => $request_timestamp,
            'KEY_PHASE' => $key_phase,
            'PARTNER_SIGNATURE' => $partner_signature,
            'SIGNATURE_ALGORITHM' => 'md5',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        );
    }

    private function request($mode, $endpoint, $payload, $method, array $headers, $encode_json = true)
    {
        $client = new DHLDPRestClient(array(
            'base_url' => self::$endpoint_live,
            'headers' => $headers,
        ));

        if ($payload === null) {
            $body = '';
        } elseif ($encode_json) {
            $body = json_encode($payload);
        } elseif (is_array($payload)) {
            $body = http_build_query($payload);
        } else {
            $body = (string) $payload;
        }
        $response = $client->execute($endpoint, $method, $body, array());

        if ($response->error) {
            $this->errors[] = $response->error;
            return false;
        }

        if (!Tools::strlen($response->response)) {
            return array();
        }

        $decoded = json_decode($response->response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('raw' => $response->response);
        }

        return $decoded;
    }

    private function normalizePayload($params)
    {
        if (is_object($params)) {
            $params = (array) $params;
        }

        if (is_array($params)) {
            foreach ($params as $key => $value) {
                if (is_object($value) || is_array($value)) {
                    $params[$key] = $this->normalizePayload($value);
                }
            }
        }

        return $params;
    }

    private function normalizeResponse($function, $response)
    {
        $object = $this->toObject($response);

        if (in_array($function, array('checkoutShoppingCartPDF', 'checkoutShoppingCartPNG'), true)) {
            return $this->normalizeCheckoutResponse($object);
        }

        if ($function === 'createShopOrderId') {
            if (!isset($object->shopOrderId) && isset($object->id)) {
                $object->shopOrderId = $object->id;
            }
        }

        return $object;
    }

    private function normalizeCheckoutResponse($response)
    {
        if (isset($response->shoppingCart) && isset($response->shoppingCart->voucherList)) {
            return $response;
        }

        if (isset($response->vouchers) && is_array($response->vouchers)) {
            $voucher = $response->vouchers[0];
        } elseif (isset($response->shoppingCart->vouchers) && is_array($response->shoppingCart->vouchers)) {
            $voucher = $response->shoppingCart->vouchers[0];
        } else {
            return $response;
        }

        $response->shoppingCart = isset($response->shoppingCart) ? $response->shoppingCart : new stdClass();
        $response->shoppingCart->voucherList = new stdClass();
        $response->shoppingCart->voucherList->voucher = $voucher;

        if (isset($response->labelLink) && !isset($response->link)) {
            $response->link = $response->labelLink;
        }

        if (isset($response->walletBalance) && !isset($response->walletBallance)) {
            $response->walletBallance = $response->walletBalance;
        }

        return $response;
    }

    private function toObject($data)
    {
        if (is_array($data)) {
            $object = new stdClass();
            foreach ($data as $key => $value) {
                $object->$key = $this->toObject($value);
            }
            return $object;
        }

        return $data;
    }

    private function logRestCall($function, $endpoint, $payload, $response, array $headers)
    {
        $msg = sprintf(
            "\nREQUEST:\n%s %s\nHEADERS:\n%s\nBODY:\n%s\nRESPONSE:\n%s\n",
            $function,
            $endpoint,
            json_encode($headers, JSON_PRETTY_PRINT),
            json_encode($payload, JSON_PRETTY_PRINT),
            json_encode($response, JSON_PRETTY_PRINT)
        );

        DhlDp::logToFile('DP', $msg, 'api');
    }

    public function getProducts($product_code = '')
    {
        $list = Db::getInstance()->executes('select * from ' . _DB_PREFIX_ . 'dhldp_dp_productlist' . (($product_code != '') ? ' where id=' . (int)$product_code : '') . ' order by id');
        $res = array();
        foreach ($list as $p) {
            $r = array(
                'code' => $p['id'],
                'name' => $p['name'],
                'price' => ($p['price_contract'] > 0) ? $p['price_contract'] : $p['price'],
                'price_orig' => $p['price'],
            );
            if (((int)$product_code > 0) && $p['id'] == $product_code) {
                return $r;
            }
            $res[] = $r;
        }
        return $res;
    }

    public function getCountries($iso_code = '')
    {
        $countries = array(
            'AU' => 'AUS', 'AT' => 'AUT', 'AZ' => 'AZE',
            'AX' => 'ALA', 'AL' => 'ALB', 'DZ' => 'DZA',
            'VI' => 'VIR', 'AS' => 'ASM', 'AI' => 'AIA',
            'AO' => 'AGO', 'AD' => 'AND', 'AQ' => 'ATA',
            'AG' => 'ATG', 'AR' => 'ARG', 'AM' => 'ARM',
            'AW' => 'ABW', 'AF' => 'AFG', 'BS' => 'BHS',
            'BD' => 'BGD', 'BB' => 'BRB', 'BH' => 'BHR',
            'BZ' => 'BLZ', 'BY' => 'BLR', 'BE' => 'BEL',
            'BJ' => 'BEN', 'BM' => 'BMU', 'BG' => 'BGR',
            'BO' => 'BOL', 'BQ' => 'BES', 'BA' => 'BIH',
            'BW' => 'BWA', 'BR' => 'BRA', 'IO' => 'IOT',
            'VG' => 'VGB', 'BN' => 'BRN', 'BF' => 'BFA',
            'BI' => 'BDI', 'BT' => 'BTN', 'VU' => 'VUT',
            'VA' => 'VAT', 'GB' => 'GBR', 'HU' => 'HUN',
            'VE' => 'VEN', 'UM' => 'UMI', 'TL' => 'TLS',
            'VN' => 'VNM', 'GA' => 'GAB', 'HT' => 'HTI',
            'GY' => 'GUY', 'GM' => 'GMB', 'GH' => 'GHA',
            'GP' => 'GLP', 'GT' => 'GTM', 'GF' => 'GUF',
            'GN' => 'GIN', 'GW' => 'GNB', 'DE' => 'DEU',
            'GG' => 'GGY', 'GI' => 'GIB', 'HN' => 'HND',
            'HK' => 'HKG', 'GD' => 'GRD', 'GL' => 'GRL',
            'GR' => 'GRC', 'GE' => 'GEO', 'GU' => 'GUM',
            'DK' => 'DNK', 'JE' => 'JEY', 'DJ' => 'DJI',
            'DM' => 'DMA', 'DO' => 'DOM', 'CD' => 'COD',
            'EG' => 'EGY', 'ZM' => 'ZMB', 'EH' => 'ESH',
            'ZW' => 'ZWE', 'IL' => 'ISR', 'IN' => 'IND',
            'ID' => 'IDN', 'JO' => 'JOR', 'IQ' => 'IRQ',
            'IR' => 'IRN', 'IE' => 'IRL', 'IS' => 'ISL',
            'ES' => 'ESP', 'IT' => 'ITA', 'YE' => 'YEM',
            'CV' => 'CPV', 'KZ' => 'KAZ', 'KY' => 'CYM',
            'KH' => 'KHM', 'CM' => 'CMR', 'CA' => 'CAN',
            'QA' => 'QAT', 'KE' => 'KEN', 'CY' => 'CYP',
            'KG' => 'KGZ', 'KI' => 'KIR', 'TW' => 'TWN',
            'KP' => 'PRK', 'CN' => 'CHN', 'CC' => 'CCK',
            'CO' => 'COL', 'KM' => 'COM', 'CR' => 'CRI',
            'CI' => 'CIV', 'CU' => 'CUB', 'KW' => 'KWT',
            'CW' => 'CUW', 'LA' => 'LAO', 'LV' => 'LVA',
            'LS' => 'LSO', 'LR' => 'LBR', 'LB' => 'LBN',
            'LY' => 'LBY', 'LT' => 'LTU', 'LI' => 'LIE',
            'LU' => 'LUX', 'MU' => 'MUS', 'MR' => 'MRT',
            'MG' => 'MDG', 'YT' => 'MYT', 'MO' => 'MAC',
            'MK' => 'MKD', 'MW' => 'MWI', 'MY' => 'MYS',
            'ML' => 'MLI', 'MV' => 'MDV', 'MT' => 'MLT',
            'MA' => 'MAR', 'MQ' => 'MTQ', 'MH' => 'MHL',
            'MX' => 'MEX', 'FM' => 'FSM', 'MZ' => 'MOZ',
            'MD' => 'MDA', 'MC' => 'MCO', 'MN' => 'MNG',
            'MS' => 'MSR', 'MM' => 'MMR', 'NA' => 'NAM',
            'NR' => 'NRU', 'NP' => 'NPL', 'NE' => 'NER',
            'NG' => 'NGA', 'NL' => 'NLD', 'NI' => 'NIC',
            'NU' => 'NIU', 'NZ' => 'NZL', 'NC' => 'NCL',
            'NO' => 'NOR', 'AE' => 'ARE', 'OM' => 'OMN',
            'BV' => 'BVT', 'IM' => 'IMN', 'CK' => 'COK ',
            'NF' => 'NFK', 'CX' => 'CXR', 'PN' => 'PCN',
            'SH' => 'SHN', 'PK' => 'PAK', 'PW' => 'PLW',
            'PS' => 'PSE', 'PA' => 'PAN', 'PG' => 'PNG',
            'PY' => 'PRY', 'PE' => 'PER', 'PL' => 'POL',
            'PT' => 'PRT', 'PR' => 'PRI', 'CG' => 'COG',
            'KR' => 'KOR', 'RE' => 'REU ', 'RU' => 'RUS',
            'RW' => 'RWA', 'RO' => 'ROU', 'SV' => 'SLV',
            'WS' => 'WSM', 'SM' => 'SMR', 'ST' => 'STP',
            'SA' => 'SAU', 'SZ' => 'SWZ', 'MP' => 'MNP',
            'SC' => 'SYC', 'BL' => 'BLM', 'MF' => 'MAF',
            'PM' => 'SPM', 'SN' => 'SEN', 'VC' => 'VCT',
            'KN' => 'KNA', 'LC' => 'LCA', 'RS' => 'SRB',
            'SG' => 'SGP', 'SX' => 'SXM', 'SY' => 'SYR',
            'SK' => 'SVK', 'SI' => 'SVN', 'SB' => 'SLB',
            'SO' => 'SOM', 'SD' => 'SDN', 'SU' => 'SUN',
            'SR' => 'SUR', 'US' => 'USA', 'SL' => 'SLE',
            'TJ' => 'TJK', 'TH' => 'THA', 'TZ' => 'TZA',
            'TC' => 'TCA', 'TG' => 'TGO', 'TK' => 'TKL',
            'TO' => 'TON', 'TT' => 'TTO', 'TV' => 'TUV',
            'TN' => 'TUN', 'TM' => 'TKM', 'TR' => 'TUR',
            'UG' => 'UGA', 'UZ' => 'UZB', 'UA' => 'UKR',
            'WF' => 'WLF', 'UY' => 'URY', 'FO' => 'FRO',
            'FJ' => 'FJI', 'PH' => 'PHL', 'FI' => 'FIN',
            'FK' => 'FLK', 'FR' => 'FRA', 'PF' => 'PYF',
            'TF' => 'ATF', 'HM' => 'HMD', 'HR' => 'HRV',
            'CF' => 'CAF', 'TD' => 'TCD', 'ME' => 'MNE',
            'CZ' => 'CZE', 'CL' => 'CHL', 'CH' => 'CHE',
            'SE' => 'SWE', 'SJ' => 'SJM', 'LK' => 'LKA',
            'EC' => 'ECU', 'GQ' => 'GNQ', 'ER' => 'ERI',
            'EE' => 'EST', 'ET' => 'ETH', 'ZA' => 'ZAF',
            'GS' => 'SGS', 'SS' => 'SSD', 'JM' => 'JAM',
            'JP' => 'JPN');
        if ($iso_code != '') {
            return $countries[$iso_code];
        }

        return $countries;
    }
}
