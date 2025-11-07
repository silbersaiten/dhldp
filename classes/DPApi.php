<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2021 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.12
 * @link      http://www.silbersaiten.de
 */

require_once(dirname(__FILE__).'/DPWsseAuthHeader.php');

class DPApi
{
    public static $endpoint_sbx = 'https://internetmarke.deutschepost.de/OneClickForAppV3';
    public static $endpoint_live = 'https://internetmarke.deutschepost.de/OneClickForAppV3';
    public static $wsdl = 'https://internetmarke.deutschepost.de/OneClickForAppV3?wsdl';
    public static $prodws_wsdl = 'https://prodws.deutschepost.de:8443/ProdWSProvider_1_1/prodws?wsdl';
    public static $endpoint_prodws_sbx = 'https://prodws.deutschepost.de:8443/ProdWSProvider_1_1/prodws';
    public static $endpoint_prodws_live = 'https://prodws.deutschepost.de:8443/ProdWSProvider_1_1/prodws';
    public static $ppl_update_csv = 'https://prestamodule.silberserver.de/dhl/ppl57.csv';
    public static $tracking_url = 'https://www.deutschepost.de/sendung/simpleQuery.html?form.sendungsnummer=[tracking_number]';

    public static $products_filename = 'data/ppl.csv';
    public $ppl = 0;
    public $voucher_layout = 'AddressZone';

    public static $partnerid = 'ASNPR';
    public static $apikey = 'nVgwguea8TxFXw8B02GI6uTzY060xW9I';
    public static $keyphase = '1';

    public static $prodws_mandatid = 'silbersaiten';
    public static $prodws_username ;
    public static $prodws_password ;

    public $errors;
    public static $soap_client;
    public $user_token = false;

    public function __construct()
    {
        $this->getPPLVersion();
        self::$prodws_username = getenv('PRODWS_USERNAME');
        self::$prodws_password = getenv('PRODWS_PASSWORD');
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

        $xml_content = $this->fileGetContents(self::$ppl_update_xml, false, $stream_context);

        $match = preg_match("'<updateLink>(.*?)</updateLink>'si", $xml_content, $matches);
        if ($match && isset($matches[1])) {
            $v_match = preg_match('/ppl_v(?P<version>\d+)\.csv/i', $matches[1], $v_matches);
            if ($v_match && isset($v_matches['version'])) {

                $ppl_content = $this->fileGetContents($matches[1], false, $stream_context);
                if ($ppl_content) {
                    file_put_contents(dirname(__FILE__).'/../'.self::$products_filename, mb_convert_encoding($ppl_content, 'UTF-8', 'ISO-8859-1'));

                    Configuration::updateGlobalValue('DHLDP_DP_PPL_VERSION', /*32*/$v_matches['version']/10);
                    return true;
                }
            }
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
//                    $pt = Db::getInstance()->getRow('select id_dhldp_dp_productlist, id, price from '._DB_PREFIX_.'dhldp_dp_productlist where id='.(int)$p['id']);
//                    if ($pt) {
//                        if ($pt['price'] != $p['price']) {
//                            Db::getInstance()->update('dhldp_dp_productlist', array('price' => $p['price'], 'date_upd' => date('Y-m-d H:i:s')), 'id=' . (int)$p['id']);
//                        }
//                    } else {
                    Db::getInstance()->insert('dhldp_dp_productlist', $p);
//                    }
                }
                return true;
            }
        return false;
    }

    public function getPPLVersion()
    {
        $this->ppl = Configuration::getGlobalValue('DHLDP_DP_PPL_VERSION')?Configuration::getGlobalValue('DHLDP_DP_PPL_VERSION'):32;
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
        if (isset($response->pageFormat) && is_array($response->pageFormat)) {
            foreach ($response->pageFormat as $page_format) {
                if ($page_format->isAddressPossible == 1) {
                    $page_formats[(int)$page_format->id] = array(
                        'name' => $page_format->name,
                        'type' => $page_format->pageType,
                        'orie' => $page_format->pageLayout->orientation,
                        'col' =>  $page_format->pageLayout->labelCount->labelX,
                        'row' =>  $page_format->pageLayout->labelCount->labelY,
                    );
                }
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
                    'price_contract' => $p->price/100
                );
                Db::getInstance()->update('dhldp_dp_productlist', array('price_contract' => $pt[$p->productCode]['price_contract'], 'date_upd' => date('Y-m-d H:i:s')), 'id='.(int)$p->productCode);
            }
            if (count($pt)) {
                Db::getInstance()->update('dhldp_dp_productlist',
                    array('price_contract' => 0, 'date_upd' => date('Y-m-d H:i:s')),
                    'id not in ('.implode(',', array_keys($pt)).')');
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

            //$customer = new Customer($address->id_customer);

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
            $receiver->address->additional = (($state != false)?$state->iso_code.' ':'').$additional;//max 50
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

    public function getSoapClient($mode)
    {
        if (self::$soap_client) {
            return self::$soap_client;
        }

        $location = ($mode == 1) ? self::$endpoint_live : self::$endpoint_sbx;

        $options = array(
            'trace' => true,
            'compression' => true,
            'exceptions' => true,
            'location' => $location,
            'soap_version' => SOAP_1_1
        );
        return new SoapClient(self::$wsdl, $options);
    }

    public function getProdwsSoapClient($mode)
    {
        if (self::$soap_client) {
            return self::$soap_client;
        }

        $location = ($mode == 1) ? self::$endpoint_prodws_live : self::$endpoint_prodws_sbx;

        $options = array(
            'trace' => true,
            'compression' => true,
            'exceptions' => true,
            'location' => $location,
            'soap_version' => SOAP_1_1,
        );
        return new SoapClient(self::$prodws_wsdl, $options);
    }

    public function getHeadersCurl($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            //curl_setopt($ch, CURLOPT_POST, true);

            $r = curl_exec($ch);
            $r = explode("\n", $r);

            return $r;
        } else {
            return get_headers($url);
        }
    }

    public function getSoapHeaders($partner_id, $key_phase, $api_key)
    {
        $headers = array();

        $request_timestamp = date('dmY-His');
        $partner_signature = Tools::substr(md5($partner_id.'::'.$request_timestamp.'::'.$key_phase.'::'.$api_key), 0, 8);

        $headers[] = new SoapHeader('NAMESPACE', 'PARTNER_ID', $partner_id);
        $headers[] = new SoapHeader('NAMESPACE', 'REQUEST_TIMESTAMP', date('dmY-His'));
        $headers[] = new SoapHeader('NAMESPACE', 'KEY_PHASE', $key_phase);
        $headers[] = new SoapHeader('NAMESPACE', 'PARTNER_SIGNATURE', $partner_signature);
        $headers[] = new SoapHeader('NAMESPACE', 'SIGNATURE_ALGORITHM', 'md5');

        return $headers;
    }

    public function authenticateUser($mode, $partner_id, $key_phase, $api_key, $username, $password)
    {
        if ($this->user_token != false) {
            return $this->user_token;
        }

        $this->errors = array();
        try {
            $soap_client = $this->getSoapClient($mode);

            $soap_client->__setSoapHeaders($this->getSoapHeaders($partner_id, $key_phase, $api_key));

            $login = new stdClass();
            $login->username = $username;
            $login->password = $password;
            $res = $soap_client->authenticateUser($login);

            $this->user_token = $res->userToken;

            return isset($res->userToken) ? $res : false;
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            if (isset($e->detail->ServiceException)) {
                if (is_object($e->detail->ServiceException->exceptionItems)) {
                    $this->errors[] = $e->detail->ServiceException->exceptionItems->errorMessage;
                } elseif (is_array($e->detail->ServiceException->exceptionItems)) {
                    foreach ($e->detail->ServiceException->exceptionItems as $item) {
                        $this->errors[] = $item->errorMessage;
                    }
                }
            }
        }
        return false;
    }

    public function callProdwsApi($function, $params, $id_shop)
    {
        $this->errors = array();
        try {
            $soap_client = $this->getProdwsSoapClient(Configuration::get('DHLDP_DP_MODE', null, null, $id_shop));
            $headers = array();
            $headers[] = new DPWsseAuthHeader(self::$prodws_username, self::$prodws_password);
            $soap_client->__setSoapHeaders($headers);


            $res = $soap_client->$function($params);

            $msg = "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
            $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
            $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";


            DHLDP::logToFile('DP', $msg, 'api_pl');

            return $res;
        } catch (SoapFault $e) {
            $msg = "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
            $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
            $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";
            DHLDP::logToFile('DP', $msg, 'api_pl');
            $this->errors[] = $e->getMessage();
        }
        return false;
    }

    public function callApi($function, $params, $id_shop, $user_token = false)
    {
        $this->errors = array();
        try {
            if ($user_token == true) {
                $this->authenticateUser(
                    Configuration::get('DHLDP_DP_MODE', null, null, $id_shop),
                    self::$partnerid,
                    self::$keyphase,
                    self::$apikey,
                    (Configuration::get('DHLDP_DP_MODE', null, null, $id_shop) == 1) ? Configuration::get('DHLDP_DP_LIVE_USERNAME', null, null, $id_shop) : Configuration::get('DHLDP_DP_SBX_USERNAME', null, null, $id_shop),
                    (Configuration::get('DHLDP_DP_MODE', null, null, $id_shop) == 1) ? Configuration::get('DHLDP_DP_LIVE_PASSWORD', null, null, $id_shop) : Configuration::get('DHLDP_DP_SBX_PASSWORD', null, null, $id_shop)
                );
                //echo Configuration::get('DHLDP_DP_MODE').', '.self::$partnerid.', '.self::$keyphase.', '.self::$apikey.', '.((Configuration::get('DHLDP_DP_MODE') == 1) ? Configuration::get('DHLDP_DP_LIVE_USERNAME') : Configuration::get('DHLDP_DP_SBX_USERNAME')).', '.((Configuration::get('DHLDP_DP_MODE') == 1) ? Configuration::get('DHLDP_DP_LIVE_PASSWORD') : Configuration::get('DHLDP_DP_SBX_PASSWORD')).'<br>';
                $params['userToken'] = $this->user_token;
            }

            $soap_client = $this->getSoapClient(Configuration::get('DHLDP_DP_MODE'));

            $soap_client->__setSoapHeaders(
                $this->getSoapHeaders(
                    self::$partnerid,
                    self::$keyphase,
                    self::$apikey
                )
            );

            //echo '<pre>'.print_r($params, true).'</pre>'; exit;

            $res = $soap_client->$function($params);

            $msg = "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
            $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
            $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";

            DHLDP::logToFile('DP', $msg, 'api');
            //exit;

            return $res;
        } catch (SoapFault $e) {
            $msg = "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
            $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
            $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";
            DHLDP::logToFile('DP', $msg, 'api');

            $this->errors[] = $e->getMessage();

            if (isset($e->detail->ShoppingCartValidationException)) {
                $exceptions = $e->detail->ShoppingCartValidationException;
                if (is_object($exceptions->errors)) {
                    $this->errors[] = $exceptions->errors->message;

                    // auto update PPL
                    //if ($exceptions->errors->id == 'invalidPplId') {
                    //    $this->updatePPL();
                    //}
                } elseif (is_array($exceptions->errors)) {
                    foreach ($exceptions->errors as $item) {
                        $this->errors[] = $item->message;

                        // auto update PPL
                        //if ($item->id == 'invalidPplId') {
                        //    $this->updatePPL();
                        //}
                    }
                }
            }
        }
        return false;
    }

    public function getProducts($product_code = '')
    {
        /*
        $res = array();
        $row = 0;
        $filepath = dirname(__FILE__).'/../'.self::$products_filename;
        if (file_exists($filepath) && ($handle = fopen($filepath, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, ";")) !== false) {
                $res[$row] = array(
                    'code' => $data[2],
                    'international' => $data[3],
                    'name' => $data[4],
                    'price' => $data[5],
                    'price_numeric' => (float)str_replace(',', '.', $data[5]),
                    'descr' => $data[41],
                    'link' => $data[42]
                );

                if (((int)$product_code > 0) && $data[2] == $product_code) {
                    return $res[$row];
                }

                $row++;
            }
            fclose($handle);
        }
        return $res;
        */
        $list = Db::getInstance()->executes('select * from '._DB_PREFIX_.'dhldp_dp_productlist'.(($product_code != '')?' where id='.(int)$product_code :'').' order by id');
        $res = array();
        foreach ($list as $p) {
            $r = array(
                'code' => $p['id'],
                'name' => $p['name'],
                'price' => ($p['price_contract'] > 0)?$p['price_contract']:$p['price'],
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
