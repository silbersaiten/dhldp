<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2023 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   2.0.0
 * @link      http://www.silbersaiten.de
 */

class DHLDPApi
{
    public static $cig_endpoint_sandbox = 'https://cig.dhl.de/services/sandbox/soap';
    public static $cig_endpoint_live = 'https://cig.dhl.de/services/production/soap';
    public static $wsdl = array(
        //'3.4' => 'geschaeftskundenversand-api-3.4.0.wsdl',
        '3.5' => 'geschaeftskundenversand-api-3.5.0.wsdl'
    );

    //public static $wsdl_pf = 'https://cig.dhl.de/cig-wsdls/com/dpdhl/wsdl/standortsuche-api/1.0/standortsuche-api-1.0.wsdl';
    public static $wsdl_pf = 'https://cig.dhl.de/cig-wsdls/com/dpdhl/wsdl/standortsuche-api/1.1/standortsuche-api-1.1.wsdl';
    public static $tracking_url = 'http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=[tracking_number]';
    public static $dhl_live_ciguser = array(
        //'3.4' => 'silbersaiten_3_1',
        '3.5' => 'silbersaiten_3_1'
    );
    public static $dhl_live_cigpass = array(
        //'3.4' => '7YxCbQRqPJ1G0jw1j5VVZBDWSRbXWF',
        '3.5' => '7YxCbQRqPJ1G0jw1j5VVZBDWSRbXWF'
    );

    public static $dhl_sbx_ciguser;
    public static $dhl_sbx_cigpass;
    public static $dhl_sbx_user = array(
        //'3.4' => '2222222222_01', //2222222222_03 - thermal printer
        '3.5' => '2222222222_01', //2222222222_03 - thermal printer
    );
    public static $dhl_sbx_sign = array(
        //'3.4' => 'pass',
        '3.5' => 'pass'
    );

    public static $dhl_sbx_ekp = array(
        //'3.4' => '2222222222',
        '3.5' => '2222222222',
    );

    public static $dhl_live_tracking_appname = '';
    public static $dhl_live_tracking_password = '';


    public static $rp_endpoint_live = 'https://amsel.dpwn.net/abholportal/gw/lp/SoapConnector';
    public static $rp_wsdl = 'var3ws.wsdl';

    public static $cig_endpoint_retoure_sandbox = 'https://cig.dhl.de/services/sandbox/rest/returns/';
    public static $cig_endpoint_retoure_live = 'https://cig.dhl.de/services/production/rest/returns/';
    public static $dhl_sbx_retoure_user;
    public static $dhl_sbx_retoure_sign;
    //public static $dhl_sbx_retoure_token = 'MjIyMjIyMjIyMl9jdXN0b21lcjp1QlFiWjYyIVppQmlWVmJoYw==';

    public static $cig_endpoint_tracking_sandbox = 'https://cig.dhl.de/services/sandbox/rest/sendungsverfolgung';
    public static $cig_endpoint_tracking_live = 'https://cig.dhl.de/services/production/rest/sendungsverfolgung';

    public static $supported_shipper_countries = array('DE' => array('api_versions' => array('3.4', '3.5')));

    // for rest api
    public static $rest_endpoint_sbx = 'https://api-sandbox.dhl.com/location-finder/v1';
    public static $rest_endpoint_live = 'https://api.dhl.com/location-finder/v1';

    //https://api-test.dhl.com/track
    //https://api-eu.dhl.com/track

    public $errors;
    public $warnings;
    public static $soap_client;
    public static $soap_client_pf;
    public static $soap_client_rp;
    public $user_token = false;
    public $module;
    public $api_version;

    public function __construct($module, $api_version = '3.5')
    {
        $this->module = $module;
        $this->setApiVersion($api_version);
        self::$dhl_sbx_ciguser = getenv('DHL_SDX_CIGUSER');
        self::$dhl_sbx_cigpass = getenv('DHL_SDX_CIGPASS');
        self::$dhl_sbx_retoure_user = getenv('DHL_SDX_RETOURE_USER');
        self::$dhl_sbx_retoure_sign = getenv('DHL_SDX_RETOURE_SIGN');
    }

    public function setApiVersion($api_version)
    {
        if (in_array((string)$api_version, self::getSupportedApiVersions())) {
            $this->api_version = $api_version;
        } else {
            $this->api_version = '3.5';
        }
        return true;
    }

    public function setApiVersionByIdShop($id_shop)
    {
        return $this->setApiVersion(Configuration::get('DHLDP_DHL_API_VERSION', null, null, $id_shop));
    }

    public function getApiVersion()
    {
        return $this->api_version;
    }

    public function getMajorApiVersion($api_version = '')
    {
        preg_match('/(?P<major>\d+).(?P<minor>\d+)/', ($api_version != '')?$api_version:$this->api_version, $matches);
        if (isset($matches['major'])) {
            return $matches['major'];
        }
        return false;
    }

    public static function getSupportedApiVersions($country_code = 'DE')
    {
        return self::$supported_shipper_countries[$country_code]['api_versions'];
    }

    public function getRequestDefaultParams()
    {
        list($majorRelease, $minorRelease) = explode('.', $this->api_version);
        return array(
            'Version' =>
                array(
                    'majorRelease' => $majorRelease,
                    'minorRelease' => $minorRelease
                )
        );
    }

    public function getShipperCountry($id_shop = null)
    {
        return  Configuration::get('DHLDP_DHL_COUNTRY', null, null, $id_shop);
    }

    public function getShipper($id_shop = null)
    {
        $shipper = array(
            'Name' => array(
                'name1' => Configuration::get('DHLDP_DHL_COMPANY_NAME_1', null, null, $id_shop),
                'name2' => Configuration::get('DHLDP_DHL_COMPANY_NAME_2', null, null, $id_shop)
            ),
            'Address' => array(
                'streetName' => Configuration::get('DHLDP_DHL_STREET_NAME', null, null, $id_shop),
                'streetNumber' => Configuration::get('DHLDP_DHL_STREET_NUMBER', null, null, $id_shop),
                'zip' => Configuration::get('DHLDP_DHL_ZIP', null, null, $id_shop),
                'city' => Configuration::get('DHLDP_DHL_CITY', null, null, $id_shop),
                'Origin' => array(
                    'countryISOCode' => Configuration::get('DHLDP_DHL_COUNTRY', null, null, $id_shop),
                    'state' => Configuration::get('DHLDP_DHL_STATE', null, null, $id_shop)
                ),
            ),
            'Communication' => array(
                'email' => Configuration::get('DHLDP_DHL_EMAIL', null, null, $id_shop),
                'phone' => Configuration::get('DHLDP_DHL_PHONE', null, null, $id_shop),
            ),
            'countryISOCode' => Configuration::get('DHLDP_DHL_COUNTRY', null, null, $id_shop)
        );
        if (Configuration::get('DHLDP_DHL_CONTACT_PERSON', null, null, $id_shop) != '') {
            $shipper['Communication']['contactPerson'] = Configuration::get('DHLDP_DHL_CONTACT_PERSON', null, null, $id_shop);
        }

        return $shipper;
    }

    public function getDHLRASenderAddress($id_address, $address_input = false, $id_shop = null)
    {
        if ($address_input == false) {
            $address = $this->normalizeAddressForRA(new Address((int)$id_address));
        } else {
            $address = array();

            $address['name1'] = $address_input['name1'];
            $address['name2'] = $address_input['name2'];
            $address['name3'] = $address_input['name3'];
            $address['streetName'] = $address_input['streetName'];
            $address['houseNumber'] = $address_input['houseNumber'];
            $address['postCode'] = $address_input['postCode'];
            $address['city'] = $address_input['city'];
            $address['country'] = array('countryISOCode' => $address_input['country']['countryISOCode']);
        }
        return $address;
    }

    public function normalizeAddressForRA(Address $address)
    {
        $country_and_state = Address::getCountryAndState($address->id);

        if ($country_and_state) {
            $country = new Country((int)$country_and_state['id_country']);
            $state_obj = new State((int)$country_and_state['id_state']);
            if (Validate::isLoadedObject($state_obj)) {
                $state = $state_obj->iso_code;
            } else {
                $state = '';
            }
            $customer = new Customer($address->id_customer);

            $res_address = array();
            if ($address->company != '') {
                $res_address['name1'] = $address->firstname.' '.$address->lastname;
                $res_address['name2'] = $address->company;
            } else {
                $res_address['name1'] = $address->firstname.' '.$address->lastname;
                $res_address['name2'] = '';
            }

            //$res_address['sendercareofname'] = $address->address2;
            //$res_address['sendercontactphone'] = ($address->phone_mobile != '')?$address->phone_mobile:$address->phone;
            //$res_address['sendercontactemail'] = $customer->email;
            $res_address['postCode'] = $address->postcode;
            $res_address['city'] = $address->city;

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
                        $street_number = '';
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
            $res_address['streetName'] = $street_name;
            $res_address['houseNumber'] = $street_number;
            $res_address['country'] = array('state' => $state, 'countryISOCode' => Tools::strtoupper($country->iso_code));

            return $res_address;
        }

        return false;
    }

    public function getDHLDeliveryAddress($id_address, $address_input = false, $id_shop = null)
    {
        if ($address_input == false) {
            $address = $this->normalizeAddress(new Address((int)$id_address));
        } else {
            $address = array();

            $norm_address = $this->normalizeAddress(new Address((int)$id_address));

            $address['Company']['Company']['name1'] = $address_input['name1'];
            $address['Company']['Company']['name2'] = $address_input['name2'];
            $address['Company']['Company']['name3'] = isset($address_input['name3'])?$address_input['name3']:'';
            if ($address_input['comm_person'] != '') {
                $address['Communication']['contactPerson'] = $address_input['comm_person'];
            }
            $address['Communication']['email'] = isset($address_input['comm_email'])?$address_input['comm_email']:'';
            $address['Communication']['phone'] = isset($address_input['comm_phone'])?$address_input['comm_phone']:'';
            $address['Communication']['mobile'] = isset($address_input['comm_mobile'])?$address_input['comm_mobile']:'';
            if ($address['Communication']['phone'] == '' && $address['Communication']['mobile'] != '') {
                $address['Communication']['phone'] = $address_input['comm_mobile'];
            }
            if ($address_input['address_type'] == 'ps') {
                $address['Packstation'] = array(
                    'PackstationNumber' => $address_input['ps_packstation_number'],
                    'PostNumber'        => $address_input['ps_post_number'],
                    'Zip'               => $address_input['ps_zip'],
                    'City'              => $address_input['ps_city'],
                );
            } else {
                if ($address_input['address_type'] == 'pf') {
                    $address['Postfiliale'] = array(
                        'PostfilialeNumber' => $address_input['pf_postfiliale_number'],
                        'PostNumber'        => $address_input['pf_post_number'],
                        'Zip'               => $address_input['pf_zip'],
                        'City'              => $address_input['pf_city'],
                    );
                } else {
                    $address['Address'] = array(
                        'streetName'   => $address_input['street_name'],
                        //'streetNumber' => $address_input['street_number'],
                        'addressAddition'   => $address_input['address_addition'],
                        'dispatchingInformation'   => $address_input['dispatching_information'],
                        'Zip'          => array(),
                        'city'         => $address_input['city'],
                        'Origin'       => array(
                            'countryISOCode' => $norm_address['Address']['Origin']['countryISOCode'], //Tools::strtoupper($address_input['country_iso_code']),
                        ),
                    );

                    if ($address_input['state'] != '') {
                        $address['Address']['Origin']['state'] = $address_input['state'];
                    }

                    if ($address['Address']['Origin']['countryISOCode'] == 'DE') {
                        $address['Address']['Zip']['germany'] = $address_input['zip'];
                    } elseif ($address['Address']['Origin']['countryISOCode'] == 'GB') {
                        $address['Address']['Zip']['england'] = $address_input['zip'];
                    } else {
                        $address['Address']['Zip']['other'] = $address_input['zip'];
                    }
                }
            }
        }

        if ($this->getMajorApiVersion() == 2 || $this->getMajorApiVersion() == 3) {
            if (isset($address['Company']['Company']['name1'])) {
                $address['name1'] = $address['Company']['Company']['name1'];
            }

            if (isset($address['Address'])) {

                if (isset($address['Company']['Company']['name2'])) {
                    $address['Address']['name2'] = $address['Company']['Company']['name2'];
                }
                if (isset($address['Company']['Company']['name3'])) {
                    $address['Address']['name3'] = $address['Company']['Company']['name3'];
                }
                unset($address['Company']);

                if ($address['Address']['Origin']['countryISOCode'] == 'DE') {
                    $address['Address']['zip'] = $address['Address']['Zip']['germany'];
                } elseif ($address['Address']['Origin']['countryISOCode'] == 'GB') {
                    $address['Address']['zip'] = $address['Address']['Zip']['england'];
                } else {
                    $address['Address']['zip'] = $address['Address']['Zip']['other'];
                }

                //fill name3 for Countries which have no addressAddition field
                /*if (in_array($address['Address']['Origin']['countryISOCode'], array('DE', 'NL', 'IT', 'LU', 'US'))) {
                    $address['Address']['name3'] = $address['Address']['addressAddition'];
                }*/

                unset($address['Address']['Zip']);
                $address['countryISOCode'] = $address['Address']['Origin']['countryISOCode'];
            }

            if (isset($address['Packstation'])) {
                $address['Packstation']['packstationNumber'] = $address['Packstation']['PackstationNumber'];
                $address['Packstation']['postNumber'] = $address['Packstation']['PostNumber'];
                $address['Packstation']['zip'] = $address['Packstation']['Zip'];
                $address['Packstation']['city'] = $address['Packstation']['City'];
                $address['Packstation']['Origin']['countryISOCode'] = 'DE';
                unset($address['Packstation']['PackstationNumber']);
                unset($address['Packstation']['PostNumber']);
                unset($address['Packstation']['Zip']);
                unset($address['Packstation']['City']);
                unset($address['Address']);
                $address['countryISOCode'] = $address['Packstation']['Origin']['countryISOCode'];
            }
            if (isset($address['Postfiliale'])) {
                $address['Postfiliale']['postfilialNumber'] = $address['Postfiliale']['PostfilialeNumber'];
                $address['Postfiliale']['postNumber'] = $address['Postfiliale']['PostNumber'];
                $address['Postfiliale']['zip'] = $address['Postfiliale']['Zip'];
                $address['Postfiliale']['city'] = $address['Postfiliale']['City'];
                $address['Postfiliale']['Origin']['countryISOCode'] = 'DE';
                unset($address['Postfiliale']['PostfilialeNumber']);
                unset($address['Postfiliale']['PostNumber']);
                unset($address['Postfiliale']['Zip']);
                unset($address['Postfiliale']['City']);
                unset($address['Address']);
                $address['countryISOCode'] = $address['Postfiliale']['Origin']['countryISOCode'];
            }
        }

        return $address;
    }

    public function normalizeAddress(Address $address)
    {
        $country_and_state = Address::getCountryAndState($address->id);

        if ($country_and_state) {
            $country = new Country((int)$country_and_state['id_country']);
            $customer = new Customer($address->id_customer);

            $receiver = array();
            if ($address->company != '') {
                $receiver['name1'] = $address->company;
                $receiver['name2'] = $address->firstname.' '.$address->lastname;
                $receiver['name3'] = '';
                $receiver['Communication']['contactPerson'] = $address->firstname.' '.$address->lastname;
            } else {
                $receiver['name1'] = $address->firstname.' '.$address->lastname;
                $receiver['name2'] = '';
                $receiver['name3'] = '';
                $receiver['Communication']['contactPerson'] = $address->firstname.' '.$address->lastname;
            }

            if (preg_match('/^Packstation/', $address->address1) && Tools::strtoupper($country->iso_code) == 'DE') {
                $receiver['Packstation'] = array(
                    'PackstationNumber' => trim(str_replace('Packstation', '', $address->address1)),
                    'PostNumber'        => trim($address->address2),
                    'Zip'               => $address->postcode,
                    'City'              => $address->city
                );
                $receiver['Address']['Origin'] = array(
                    'countryISOCode' => Tools::strtoupper($country->iso_code),
                );
            } elseif (preg_match('/^Postfiliale/', $address->address1) && Tools::strtoupper($country->iso_code) == 'DE') {
                $receiver['Postfiliale'] = array(
                    'PostfilialeNumber' => trim(str_replace('Postfiliale', '', $address->address1)),
                    'PostNumber'        => trim($address->address2),
                    'Zip'               => $address->postcode,
                    'City'              => $address->city
                );
                $receiver['Address']['Origin'] = array(
                    'countryISOCode' => Tools::strtoupper($country->iso_code),
                );
            } else {
                $addressAddition = $address->address2;
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
                                trim($addressAddition),
                                $matches
                            );
                            if (isset($matches['streetnumber'])) {
                                $street_number = $matches['streetnumber'];
                                $addressAddition = str_replace($matches['streetnumber'], '', $addressAddition);
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

                $receiver['Address'] = array(
                    'streetName'   => $street_name,
                    'streetNumber' => $street_number,
                    'addressAddition'   => $addressAddition,
                    'Zip'          => array(),
                    'city'         => $address->city,
                    'Origin'       => array(
                        'countryISOCode' => Tools::strtoupper($country->iso_code),
                        'state' => State::getNameById($address->id_state)
                    ),
                );

                if ($receiver['Address']['Origin']['countryISOCode'] == 'DE') {
                    $receiver['Address']['Zip']['germany'] = $address->postcode;
                } elseif ($receiver['Address']['Origin']['countryISOCode'] == 'GB') {
                    $receiver['Address']['Zip']['england'] = $address->postcode;
                } else {
                    $receiver['Address']['Zip']['other'] = $address->postcode;
                }
            }
            $receiver['Communication']['email'] = $customer->email;
            $receiver['Communication']['phone'] = $address->phone;
            $receiver['Communication']['mobile'] = $address->phone_mobile;

            // fixed
            if (in_array(Tools::strtoupper($country->iso_code), array('DE', 'NL', 'IT', 'LU', 'US'))) {
//            if (Tools::strtoupper($country->iso_code) == 'DE') {
                $receiver['Address']['streetName'] = trim($address->address1).((trim($address->address2) != '')?' '.trim($address->address2):'');
                unset($receiver['Address']['streetNumber']);
                $receiver['Address']['name3'] = $address->address2;
                $receiver['Address']['addressAddition'] = '';
            } else {
                $receiver['Address']['streetName'] = $address->address1;
                unset($receiver['Address']['streetNumber']);
                $receiver['name3'] = $address->address2;
                $receiver['Address']['addressAddition'] = '';
            }
            $receiver['Address']['dispatchingInformation'] = '';

            return $receiver;
        }

        return false;
    }

    public function getSoapClient($mode, $dhl_ciguser = '', $dhl_cigpass = '')
    {
        $location = ($mode == 1) ? self::$cig_endpoint_live : self::$cig_endpoint_sandbox;
        if ($dhl_ciguser == '' && $dhl_cigpass == '') {
            if ($mode == 1) {
                $dhl_ciguser = self::$dhl_live_ciguser[$this->api_version];
                $dhl_cigpass = self::$dhl_live_cigpass[$this->api_version];
            } else {
                $dhl_ciguser = self::$dhl_sbx_ciguser;
                $dhl_cigpass = self::$dhl_sbx_cigpass;
            }
        }

        $opts = array(
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true
            )
        );

        $options = array(
            'trace' => true,
            'compression' => true,
            'exceptions' => true,
            'location' => $location,
            'soap_version' => SOAP_1_1,
            'login' => $dhl_ciguser,
            'password' => $dhl_cigpass,
            'stream_context' => stream_context_create($opts),
        );

        require_once(dirname(__FILE__).'/DHLDPSoapClient.php');
        self::$soap_client = new DHLDPSoapClient(dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'wsdl'.DIRECTORY_SEPARATOR.self::$wsdl[$this->api_version], $options);
        return self::$soap_client;
    }

    public function getPFSoapClient($mode)
    {
        if (self::$soap_client_pf) {
            return self::$soap_client_pf;
        }

        $location = ($mode == 1) ? self::$cig_endpoint_live : self::$cig_endpoint_sandbox;
        if ($mode == 1) {
            $dhl_ciguser = self::$dhl_live_ciguser[$this->api_version];
            $dhl_cigpass = self::$dhl_live_cigpass[$this->api_version];
        } else {
            $dhl_ciguser = self::$dhl_sbx_ciguser;
            $dhl_cigpass = self::$dhl_sbx_cigpass;
        }

        $opts = array(
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true
            )
        );

        $options = array(
            'trace' => true,
            'compression' => true,
            'exceptions' => true,
            'location' => $location,
            'soap_version' => SOAP_1_1,
            'login' => $dhl_ciguser,
            'password' => $dhl_cigpass,
            'stream_context' => stream_context_create($opts)
        );
        require_once(dirname(__FILE__).'/DHLDPSoapClient.php');
        self::$soap_client_pf = new DHLDPSoapClient(self::$wsdl_pf, $options);
        return self::$soap_client_pf;
    }

    public function checkDHLAccount($dhl_mode, $dhl_ciguser, $dhl_cigpass, $dhl_user, $dhl_sign)
    {
        $this->errors = array();
        try {
            $soap_client = $this->getSoapClient($dhl_mode, $dhl_ciguser, $dhl_cigpass);

            $authentication = new stdClass();
            $authentication->user = $dhl_user;
            $authentication->signature = $dhl_sign;
            $authentication->type = 0;

            $authHeader = new SoapHeader('http://dhl.de/webservice/cisbase', 'Authentification', $authentication);
            $soap_client->__setSoapHeaders($authHeader);

            $request = $this->getRequestDefaultParams();
            $request['shipmentNumber'] = '0000000000';

            $res = $soap_client->getLabel($request);

            $msg = "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
            $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
            $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";
            DHLDP::logToFile('DHL', $msg, 'dhl_api');

            if (isset($res->Status)) {
                $res->status = $res->Status;
                unset($res->Status);
            }
            if (isset($res->status->StatusCode)) {
                $res->status->statusCode = $res->status->StatusCode;
                unset($res->status->StatusCode);
            }
            if (isset($res->status->StatusMessage)) {
                $res->status->statusMessage = $res->status->StatusMessage;
                unset($res->status->StatusMessage);
            }
            if (isset($res->status->statusCode)) {
                $this->errors[] = $res->status->statusCode.' '.$res->status->statusMessage;
            } else {
                return false;
            }

            return $res;
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        return false;
    }

    public function getVersion()
    {
        $this->errors = array();
        try {
            $soap_client = $this->getSoapClient(Configuration::get('DHLDP_DHL_MODE'));

            $version = array('majorRelease' => '0', 'minorRelease' => '0');
            $res = $soap_client->getVersion($version);
            return $res;
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        return false;
    }

    public function callDhlApi($function, $params, $id_shop = null)
    {
        $this->errors = array();
        $this->warnings = array();
        try {
            $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);

            $soap_client = $this->getSoapClient($mode);


            $authentication = new stdClass();
            if ($mode == 1) {
                $authentication->user = Configuration::get('DHLDP_DHL_LIVE_USER', null, null, $id_shop);
                $authentication->signature = Configuration::get('DHLDP_DHL_LIVE_SIGN', null, null, $id_shop);
            } else {
                $authentication->user = self::$dhl_sbx_user[$this->api_version];
                $authentication->signature = self::$dhl_sbx_sign[$this->api_version];
            }
            $authentication->type = 0;

            $authHeader = new SoapHeader('http://dhl.de/webservice/cisbase', 'Authentification', $authentication);
            $soap_client->__setSoapHeaders($authHeader);

            $params = array_merge($this->getRequestDefaultParams(), $params);

            $res = $soap_client->$function($params);

            $msg = "\n-----------------------------------------";
            $msg .= "\nAPI Version: " . $this->api_version;
            if (isset($soap_client) && is_object($soap_client)) {
                $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
                $msg .= "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
                $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";
                $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            }
            DHLDP::logToFile('DHL', $msg, 'dhl_api');

            return $this->getResponse($res);
        } catch (SoapFault $e) {
            $error_msg = $e->getMessage() . ((isset($e->detail)) ? ', ' . $e->detail : '');
            $this->errors[] = $error_msg;
            $msg = "\n-----------------------------------------";
            $msg .= "\nAPI Version: " . $this->api_version;
            $msg .= "\nSOAP Exception: " . $error_msg;
            if (isset($soap_client) && is_object($soap_client)) {
                $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
                $msg .= "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
                $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";
                $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            }
            DHLDP::logToFile('DHL', $msg, 'dhl_api');
        }
        return false;
    }

    public function callDhlRetoureApi($params, $id_shop = null)
    {
        $this->errors = array();
        $this->warnings = array();

        $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        $curl_handle = curl_init($mode?self::$cig_endpoint_retoure_live:self::$cig_endpoint_retoure_sandbox);
        if ($mode == 1) {
            $token = base64_encode(Configuration::get('DHLDP_DHL_LIVE_USER', null, null, $id_shop).':'.Configuration::get('DHLDP_DHL_LIVE_SIGN', null, null, $id_shop));
        } else {
            $token = base64_encode(self::$dhl_sbx_retoure_user.':'.self::$dhl_sbx_retoure_sign);
        }
        $parameters_string = json_encode($params);

        $curlopt = array();
        $curlopt[CURLINFO_HEADER_OUT] = true;
        $curlopt[CURLINFO_PRIVATE] = true;
        $curlopt[CURLOPT_HEADER] = true;
        $curlopt[CURLOPT_RETURNTRANSFER] = true;
        $curlopt[CURLOPT_CUSTOMREQUEST] = "POST";
        $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;

        $curlopt[CURLOPT_FOLLOWLOCATION] = true;
        $curlopt[CURLOPT_HTTPHEADER] = array(
            'Content-Length:'.Tools::strlen($curlopt[CURLOPT_POSTFIELDS]),
            'cache-control:nocache',
            'Connection:keep-alive',
            'accept_encoding:gzip, deflate',
            'Content-Type:application/json',
            'Accept:application/json',
            'Authorization:Basic '.base64_encode($mode?self::$dhl_live_ciguser['3.5'].':'.self::$dhl_live_cigpass['3.5']:self::$dhl_sbx_ciguser.':'.self::$dhl_sbx_cigpass),
            'DPDHL-User-Authentication-Token:'.$token
        );
        curl_setopt_array($curl_handle, $curlopt);

        $res = curl_exec($curl_handle);
        $header_size = curl_getinfo($curl_handle, CURLINFO_HEADER_SIZE);
        $http_code = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
        //$header = substr($res, 0, $header_size);
        $body = Tools::substr($res, $header_size);
        $msg = "\nREQUEST:\n" . $parameters_string . "\n";
        $msg .= "\nRESPONSE:\n" . $res . "\n";
        DHLDP::logToFile('DHL', $msg, 'dhl_api');

        return $this->getResponseRA($http_code, $body);
    }

    public function callDhlTrackingApi($shipment_number, $id_shop = null)
    {
        $this->errors = array();
        $this->warnings = array();

        $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);

        if ($mode == 1) {
            $appname = self::$dhl_live_tracking_appname;
            $password = self::$dhl_live_tracking_password;
        } else {
            $appname =  getenv('APPNAME');
            $password = getenv('PASSWORD');
        }

        $request = '<?xml version="1.0" encoding="UTF-8" standalone="no"?> <data appname="' . $appname . '" language-code="en" password="' . $password . '" piece-code="' . $shipment_number . '" request="d-get-piece"/> ';

        $query = 'xml='.rawurlencode($request);
        $url = (($mode?self::$cig_endpoint_tracking_live:self::$cig_endpoint_tracking_sandbox)).'?'.$query;
        $curl_handle = curl_init($url);
        $curlopt = array();
        $curlopt[CURLOPT_HEADER] = true;
        $curlopt[CURLOPT_RETURNTRANSFER] = true;
        $curlopt[CURLOPT_FOLLOWLOCATION] = true;
        $curlopt[CURLOPT_HTTPHEADER] = array(
            'cache-control:nocache',
            'Connection:keep-alive',
            'accept_encoding:gzip, deflate',
            'Authorization:Basic '.base64_encode($mode?self::$dhl_live_ciguser['3.5'].':'.self::$dhl_live_cigpass['3.5']:self::$dhl_sbx_ciguser.':'.self::$dhl_sbx_cigpass),
        );
        curl_setopt_array($curl_handle, $curlopt);

        $res = curl_exec($curl_handle);

        $header_size = curl_getinfo($curl_handle, CURLINFO_HEADER_SIZE);
        $http_code = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
        $body = Tools::substr($res, $header_size);
        $msg = "\nREQUEST:\n" . print_r($url, true) . "\n";
        $msg .= "\nRESPONSE:\n" . $res . "\n";
        DHLDP::logToFile('DHL', $msg, 'dhl_api');

        return $this->getResponseTA($http_code, $body);
    }

    public function getResponseTA($http_code, $res)
    {
        if ($http_code == '201') {
            $this->errors[] = 'Validation failed';
            return false;
        } elseif ($http_code == '400') {
            $this->errors[] = 'Bad Request';
            return false;
        } elseif ($http_code == '401') {
            $this->errors[] = 'Authentication failed';
            return false;
        } elseif ($http_code == '403') {
            $this->errors[] = 'Authorization failed';
            return false;
        } elseif ($http_code == '500') {
            $this->errors[] = 'Body faulty';
            return false;
        }

        $xml = simplexml_load_string($res);
        $arr = json_decode(json_encode($xml), 1);
        //echo '<pre>'.print_r($arr, true).'</pre>'; exit;
        if (isset($arr['data']['@attributes'])) {
            if (isset($arr['data']['@attributes']['piece-status'])) {
                $this->errors[] = $arr['data']['@attributes']['piece-status'].': '.$arr['data']['@attributes']['piece-status-desc'];
                return false;
            }
            if (isset($arr['data']['@attributes']['ice'])) {
                return array('status-code' => $arr['data']['@attributes']['ice'], 'delivery-event-flag' => $arr['data']['@attributes']['delivery-event-flag'], 'short-status' => $arr['data']['@attributes']['short-status']);
            }
        } else {
            $this->errors[] = 'No data';
        }

        return false;
    }

    public function getResponseRA($http_code, $res)
    {
        return json_decode($res, true);
    }

    public function getResponse($res)
    {
        if (isset($res->Status->statusCode)) {
            if ($res->Status->statusCode != '0') {
                if (isset($res->Status->statusText)) {
                    $this->errors[] = $res->Status->statusText;
                }
                if (isset($res->Status->statusMessage)) {
                    $this->errors[] = $res->Status->statusMessage;
                }
                if (isset($res->CreationState->LabelData->Status->statusCode)) {
                    if ($res->CreationState->LabelData->Status->statusCode != '0' && isset($res->CreationState->LabelData->Status->statusMessage)) {
                        if (is_array($res->CreationState->LabelData->Status->statusMessage)) {
                            foreach ($res->CreationState->LabelData->Status->statusMessage as $message) {
                                $this->warnings[] = $message;
                            }
                        } else {
                            $this->warnings[] = $res->CreationState->LabelData->Status->statusMessage;
                        }
                    }
                }
                return false;
            } else {
                if (isset($res->CreationState->LabelData->Status->statusCode)) {
                    if ($res->CreationState->LabelData->Status->statusCode == '0') {
                        if (is_array($res->CreationState->LabelData->Status->statusMessage)) {
                            foreach ($res->CreationState->LabelData->Status->statusMessage as $message) {
                                $this->warnings[] = $message;
                            }
                        } else {
                            $this->warnings[] = $res->CreationState->LabelData->Status->statusMessage;
                        }
                    }
                    $ret = array('shipmentNumber' => isset($res->CreationState->LabelData->shipmentNumber)?$res->CreationState->LabelData->shipmentNumber:(isset($res->CreationState->shipmentNumber)?$res->CreationState->shipmentNumber:''), 'labelUrl' => $res->CreationState->LabelData->labelUrl);
                    if (isset($res->CreationState->LabelData->exportLabelUrl)) {
                        $ret['exportLabelUrl'] = $res->CreationState->LabelData->exportLabelUrl;
                    }
                    if (isset($res->CreationState->LabelData->codLabelUrl)) {
                        $ret['codLabelUrl'] = $res->CreationState->LabelData->codLabelUrl;
                    }
                    if (isset($res->CreationState->LabelData->returnLabelUrl)) {
                        $ret['returnLabelUrl'] = $res->CreationState->LabelData->returnLabelUrl;
                    }
                    return $ret;
                } elseif (isset($res->DeletionState)) {
                    return array('shipmentNumber' => $res->DeletionState->shipmentNumber);
                } elseif (isset($res->ManifestState)) {
                    return $res->ManifestState;
                }
            }
        } else {
            return false;
        }
        return $res;
    }

    public function callPFApi($function, $params, $id_shop = null)
    {
        $this->errors = array();
        try {
            $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);

            $soap_client = $this->getPFSoapClient($mode);

            $res = $soap_client->$function($params);

            $msg = "\n-----------------------------------------";
            if (isset($soap_client) && is_object($soap_client)) {
                $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
                $msg .= "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
                $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";
                $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            }
            DHLDP::logToFile('DHL', $msg, 'dhl_api');

            return $res;
        } catch (SoapFault $e) {
            $msg = "\n-----------------------------------------";
            if (isset($soap_client) && is_object($soap_client)) {
                $msg .= "\nREQUEST HEADERS:\n" . $soap_client->__getLastRequestHeaders() . "\n";
                $msg .= "\nREQUEST:\n" . $soap_client->__getLastRequest() . "\n";
                $msg .= "\nRESPONSE HEADERS:\n" . $soap_client->__getLastResponseHeaders() . "\n";
                $msg .= "\nRESPONSE:\n" . $soap_client->__getLastResponse() . "\n";
            }
            DHLDP::logToFile('DHL', $msg, 'dhl_api');
            $this->errors[] = $this->module->getTranslationPFApiMessage($e->getMessage());
        }
        return false;
    }

    public function getPackstations($address)
    {
        $request = array(
            'key' => '',
            'address' => $address
        );
        $response = $this->callPFApi('getPackstationsFilialeDirektByAddress', $request);

        if (is_object($response) && isset($response->packstation_filialedirekt)) {
            $packstations = array();
            if (is_array($response->packstation_filialedirekt)) {
                foreach ($response->packstation_filialedirekt as $packstation_filialedirekt) {
                    if (isset($packstation_filialedirekt->packstationId) && $packstation_filialedirekt->packstationId > 0) {
                        $packstations[] = array(
                            'packstationId' => $packstation_filialedirekt->packstationId,
                            'address' => $packstation_filialedirekt->address,
                            'location' => $packstation_filialedirekt->location
                        );
                    }
                }
            }
            return $packstations;
        } else {
            return array('errors' => $this->errors);
        }
    }

    public function getPostfiliales($address)
    {
        $request = array(
            'key' => '',
            'address' => $address
        );
        $response = $this->callPFApi('getPackstationsFilialeDirektByAddress', $request);

        if (is_object($response) && isset($response->packstation_filialedirekt)) {
            $postfiliales = array();
            if (is_array($response->packstation_filialedirekt)) {
                foreach ($response->packstation_filialedirekt as $packstation_filialedirekt) {
                    if (isset($packstation_filialedirekt->depotServiceNo) && $packstation_filialedirekt->depotServiceNo > 0) {
                        $postfiliales[] = array(
                            'depotServiceNo' => $packstation_filialedirekt->depotServiceNo,
                            'address' => $packstation_filialedirekt->address,
                            'location' => $packstation_filialedirekt->location
                        );
                    }
                }
            }
            return $postfiliales;
        } else {
            return array('errors' => $this->errors);
        }
    }

    public function getRestPackstations($address)
    {
        $id_shop = null;
        $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        if ($mode == '1') {
            // live
            $url = 'https://api.dhl.com/location-finder/v1/find-by-address';
            $dhl_api_key = self::$dhl_sbx_ciguser; //'prestashop'
        } else {
            $url = 'https://api-sandbox.dhl.com/location-finder/v1/find-by-address';
            $dhl_api_key = self::$dhl_sbx_ciguser; //'prestashop'
        }
        $rclient = new DHLDPRestClient(array('savelog_callback' => ''));

        $res = $rclient->get($url,
            array(
                'countryCode' => 'DE',
                'addressLocality' => $address['city'],
                'postalCode' => $address['zip'],
                'radius' => '2500',
                'limit' => '100'
            ),
            array(
                'DHL-API-Key' => $dhl_api_key
            )
        );
        $response = $res->decodeResponse();

        //echo '<pre>'.print_r($response, true).'</pre>'; exit;
        if (is_array($response) && isset($response['locations'])) {
            $packstations = array();
            if (is_array($response['locations'])) {
                foreach ($response['locations'] as $item) {
                    if (isset($item['location']) && $item['location']['keyword'] == 'Packstation') {
                        $packstations[] = array(
                            'packstationId' => $item['location']['keywordId'],
                            'address' => array(
                                'street' => $item['place']['address']['streetAddress'],
                                'streetNo' => '',
                                'zip' => $item['place']['address']['postalCode'],
                                'countryCode' => $item['place']['address']['countryCode'],
                                'city' => $item['place']['address']['addressLocality'],
                                'district' => '',
                                'remark' => isset($item['place']['containedInPlace']['name'])?$item['place']['containedInPlace']['name']:''
                            ),
                            'location' => $item['place']['geo']
                        );
                    }
                }
            }
            return $packstations;
        } else {
            return array('errors' => $this->errors);
        }
    }

    public function getRestPostfiliales($address)
    {
        $id_shop = null;
        $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        if ($mode == '1') {
            // live
            $url = 'https://api.dhl.com/location-finder/v1/find-by-address';
            $dhl_api_key = self::$dhl_sbx_ciguser; //'prestashop'
        } else {
            $url = 'https://api-sandbox.dhl.com/location-finder/v1/find-by-address';
            $dhl_api_key = self::$dhl_sbx_ciguser; //'prestashop'
        }
        $rclient = new DHLDPRestClient(array('savelog_callback' => ''));

        $res = $rclient->get($url,
            array(
                'countryCode' => 'DE',
                'addressLocality' => $address['city'],
                'postalCode' => $address['zip'],
                'radius' => '2500',
                'limit' => '100'
            ),
            array(
                'DHL-API-Key' => $dhl_api_key
            )
        );
        $response = $res->decodeResponse();

        //echo '<pre>'.print_r($response, true).'</pre>'; exit;
        if (is_array($response) && isset($response['locations'])) {
            $postfiliales = array();
            if (is_array($response['locations'])) {
                foreach ($response['locations'] as $item) {
                    if (isset($item['location']) && $item['location']['keyword'] == 'Postfiliale') {
                        $postfiliales[] = array(
                            'depotServiceNo' => $item['location']['keywordId'],
                            'address' => array(
                                'street' => $item['place']['address']['streetAddress'],
                                'streetNo' => '',
                                'zip' => $item['place']['address']['postalCode'],
                                'countryCode' => $item['place']['address']['countryCode'],
                                'city' => $item['place']['address']['addressLocality'],
                                'district' => '',
                                'remark' => isset($item['place']['containedInPlace']['name'])?$item['place']['containedInPlace']['name']:''
                            ),
                            'location' => $item['place']['geo']
                        );
                    }
                }
            }
            return $postfiliales;
        } else {
            return array('errors' => $this->errors);
        }
    }

    public function getDefinedProducts($code = '', $to_country = '', $from_country = '', $api_version = '')
    {
        $products = array(
            'EPN' => array(
                'procedure' => '01',
                'alias_v2' => 'V01PAK',
                'active' => true,
                'name' => 'DHL Paket',
                'type' => 'DD',
                'options' => array('gogreen' => 'GoGreen'),
                'to_country_codes' => array('DE'),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'services_v1' => array(
                    'ShipmentServiceGroupIdent' => array('Personally', 'Ident', 'IdentPlusAge', 'CheckMinimumAge'),
                    'ServiceGroupDHLPaket' => array('Multipack', 'ParticularDelivery'),
                    'ServiceGroupOther' => array('HigherInsurance', 'COD', 'Unfree', 'DangerousGoods', 'Bulkfreight', 'DirectInjection', 'Bypass'),
                ),
                'params' => array(
                    'length' => array('min' => 0, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 0, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 0, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'weight' => array('min' => 0.1, 'max' => 346.5, 'step' => 0.1, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 11),
                    'CheckMinimumAge' => array(
                        'MinimumAge' => array(0, 16, 18),
                    ),
                    'HigherInsurance' => array(
                        'InsuranceAmount' => array(0, 2500, 25000),
                        'InsuranceCurrency' => 'EUR'
                    ),
                    'COD' => array(
                        'CODAmount' => array('min' => 0, 'max' => 3500, 'step' => 0.1),
                        'CODCurrency' => 'EUR'
                    )
                ),
                // v2 => v1
                'services_v2' => array(
                    'Personally' => 'Personally',
                    'AdditionalInsurance' => 'HigherInsurance',
                    'CashOnDelivery' => 'COD',
                    'BulkyGoods' => 'Bulkfreight',
                    'Notification' => '',
                    'GoGreen' => '',
                    'Ident' => 'Ident',
                    'IdentExtra' => '',
                    'IdentPremium' => '',
                    'VisualCheckOfAge' => 'CheckMinimumAge',
                    'Multipack' => 'Multipack',
                    'RegioPacket' => '',
                    'ParticularDelivery' => 'ParticularDelivery',
                    'ShipmentAdvisory' => '',
                    'Unfree' => 'Unfree',
                    'DangerousGoods' => '',
                    'PreferredNeighbour' => '',
                    'PreferredLocation' => '',
                    'NamedPersonOnly' => '',
                    'IdentCheck' => '',
                    'PreferredDay' => '',
                    'NoNeighbourDelivery' => '',
                    'PackagingReturn' => '',
                    'NoticeOfNonDeliverability' => '',
                    'DHLRetoure' => 'DHLRetoure',
                ),
                'services_v3' => array(
                    'Personally' => 'Personally',
                    'AdditionalInsurance' => 'HigherInsurance',
                    'CashOnDelivery' => 'COD',
                    'BulkyGoods' => 'Bulkfreight',
                    'Notification' => '',
                    'GoGreen' => '',
                    'Ident' => 'Ident',
                    'IdentExtra' => '',
                    'IdentPremium' => '',
                    'VisualCheckOfAge' => 'CheckMinimumAge',
                    'Multipack' => 'Multipack',
                    'RegioPacket' => '',
                    'ParticularDelivery' => 'ParticularDelivery',
                    'ShipmentAdvisory' => '',
                    'Unfree' => 'Unfree',
                    'DangerousGoods' => '',
                    'PreferredNeighbour' => '',
                    'PreferredLocation' => '',
                    'NamedPersonOnly' => '',
                    'IdentCheck' => '',
                    'PreferredDay' => '',
                    'NoNeighbourDelivery' => '',
                    'PackagingReturn' => '',
                    'NoticeOfNonDeliverability' => '',
                    'DHLRetoure' => 'DHLRetoure',
                    'ParcelOutletRouting' => '', // *NEW
                    'SignedForByRecipient' => '', // *NEW
                )
            ),
            'BPI' => array(
                'procedure' => '53',
                'alias_v2' => 'V53WPAK',
                'active' => true,
                'name' => 'DHL Paket International',
                'type' => 'DD',
                'options' => array('gogreen' => 'GoGreen'),
                'to_country_codes' => array(), //other countries - export documents
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'services_v1' => array(
                    'ShipmentServiceGroupIdent' => array('ReturnReceipt'),
                    'ServiceGroupBusinessPackInternational' => array('Premium', 'Seapacket', 'CoilWithoutHelp', 'Endorsement'),
                    'ServiceGroupOther' => array('HigherInsurance', 'COD', 'Bulkfreight', 'Unfree', 'DangerousGoods', 'DirectInjection', 'Bypass'),
                ),
                'params' => array(
                    'length' => array('min' => 0, 'max' => 120, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 0, 'max' => 60, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 0, 'max' => 60, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'weight' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 1),
                    'HigherInsurance' => array(
                        'InsuranceAmount' => array('min' => 0, 'max' => 10000, 'step' => 1),
                        'InsuranceCurrency' => 'EUR'
                    ),
                    'COD' => array(
                        'CODAmount' => array('min' => 0, 'max' => 100000, 'step' => 0.01),
                        'CODCurrency' => 'EUR'
                    )
                ),
                // v2 => v1
                'services_v2' => array(
                    'Premium' => 'Premium',
                    'AdditionalInsurance' => 'HigherInsurance',
                    'CashOnDelivery' => 'COD',
                    'BulkyGoods' => 'Bulkfreight',
                    'Notification' => '',
                    'GoGreen' => '',
                    'ProofOfDelivery' => '',
                    'Economy' => '',
                    'DirectInjection' => 'DirectInjection',
                    'Bypass' => 'Bypass',
                    'ReturnReceipt' => '',
                ),
                'services_v3' => array(
                    'Premium' => 'Premium',
                    'AdditionalInsurance' => 'HigherInsurance',
                    'CashOnDelivery' => 'COD',
                    'BulkyGoods' => 'Bulkfreight',
                    'Notification' => '',
                    'GoGreen' => '',
                    'ProofOfDelivery' => '',
                    'Economy' => '',
                    'DirectInjection' => 'DirectInjection',
                    'Bypass' => 'Bypass',
                    'ReturnReceipt' => '',
                )
            ),
            'EPI' => array(
                'procedure' => '54',
                'alias_v2' => 'V54EPAK',
                'active' => true,
                'name' => 'DHL Europaket',
                'type' => 'DD',
                'options' => array('gogreen' => 'GoGreen'),
                'to_country_codes' => $this->module->getEUCountriesCodes(),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 0, 'max' => 120, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 0, 'max' => 60, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 0, 'max' => 60, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'weight' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 1),
                    'HigherInsurance' => array(
                        'InsuranceAmount' => array('min' => 0, 'max' => 10000, 'step' => 1),
                        'InsuranceCurrency' => 'EUR'
                    ),
                ),
                'services_v1' => array(
                    'ShipmentServiceGroupIdent' => array(),
                    'ServiceGroupBusinessPackInternational' => array(),
                    'ServiceGroupOther' => array('HigherInsurance'),
                ),
                // v2 => v1
                'services_v2' => array(
                    'AdditionalInsurance' => 'HigherInsurance',
                    'Notification' => '',
                    'GoGreen' => '',
                ),
                'services_v3' => array(
                    'AdditionalInsurance' => 'HigherInsurance',
                    'Notification' => '',
                    'GoGreen' => '',
                )
            ),
            'V55PAK' => array(
                'procedure' => '55',
                'alias_v2' => 'V55PAK',
                'active' => true,
                'name' => 'DHL Paket Connect',
                'options' => array('gogreen' => 'GoGreen'),
                'to_country_codes' => $this->module->getEUCountriesCodes(),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 0, 'max' => 120, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 0, 'max' => 60, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 0, 'max' => 60, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'weight' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 1),
                    'HigherInsurance' => array(
                        'InsuranceAmount' => array('min' => 0, 'max' => 10000, 'step' => 1),
                        'InsuranceCurrency' => 'EUR'
                    ),
                ),
                'services_v1' => array(
                    'ShipmentServiceGroupIdent' => array(),
                    'ServiceGroupBusinessPackInternational' => array(),
                    'ServiceGroupOther' => array('HigherInsurance', 'COD', 'Bulkfreight'),
                ),
                // v2 => v1
                'services_v2' => array(
                    'AdditionalInsurance' => 'HigherInsurance',
                    'BulkyGoods' => 'Bulkfreight',
                    'Notification' => '',
                ),
                'services_v3' => array(
                    'AdditionalInsurance' => 'HigherInsurance',
                    'BulkyGoods' => 'Bulkfreight',
                    'Notification' => '',
                )
            ),
            'V62KP' => array(
                'procedure' => '62',
                'alias_v2' => 'V62KP',
                'active' => true,
                'name' => 'DHL Kleinpaket',
                'options' => array('gogreen' => 'GoGreen'),
                'to_country_codes' => array('DE'),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 0, 'max' => 35, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 0, 'max' => 25, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 0, 'max' => 5, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.01, 'max' => 1, 'step' => 0.01, 'unit' => 'kg'),
                    'weight' => array('min' => 0.01, 'max' => 1, 'step' => 0.01, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 1),
                    'HigherInsurance' => array(
                        'InsuranceAmount' => array('min' => 0, 'max' => 10000, 'step' => 1),
                        'InsuranceCurrency' => 'EUR'
                    ),
                ),
                'services_v1' => array(
                    'ShipmentServiceGroupIdent' => array(),
                    'ServiceGroupBusinessPackInternational' => array(),
                    'ServiceGroupOther' => array('HigherInsurance'),
                ),
                // v2 => v1
                'services_v2' => array(
                    'AdditionalInsurance' => 'HigherInsurance',
                    'Notification' => '',
                    'GoGreen' => '',
                    'DHLRetoure' => 'DHLRetoure'
                ),
                'services_v3' => array(
                    'Notification' => '',
                    'AdditionalInsurance' => 'HigherInsurance',
                    'GoGreen' => '',
                    'PreferredNeighbour' => '',
                    'DHLRetoure' => 'DHLRetoure',
                    'ParcelOutletRouting' => '', // *NEW
                ),
            ),
            'V66WPI' => array(
                'procedure' => '66',
                'alias_v2' => 'V66WPI',
                'active' => true,
                'name' => 'Warenpost International',
                'options' => array('gogreen' => 'GoGreen'),
                'to_country_codes' => array(),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 14, 'max' => 35.3, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('9' => 0, 'max' => 25, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 0.1, 'max' => 10, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.01, 'max' => 1, 'step' => 0.01, 'unit' => 'kg'),
                    'weight' => array('min' => 0.01, 'max' => 1, 'step' => 0.01, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 1),
                ),
                'services_v1' => array(
                    'ShipmentServiceGroupIdent' => array(),
                    'ServiceGroupBusinessPackInternational' => array(),
                ),
                // v2 => v1
                'services_v2' => array(
                    'GoGreen' => '',
                    'Premium' => 'Premium',
                    'Notification' => '',
                ),
                'services_v3' => array(
                    'GoGreen' => '',
                    'Premium' => 'Premium',
                    'Notification' => '',
                ),
            )
        );

        foreach ($products as $product_code => $product) {
            if ($product['active'] != true) {
                unset($products[$product_code]);
            }
        }

        if ($code != '' && isset($products[$code])) {
            if ($api_version != '') {
                $major_api_version = $this->getMajorApiVersion($api_version);
                foreach ($products as $product_code => $product) {
                    if (($major_api_version == '2' || $major_api_version == '3') && !isset($product['alias_v2'])) {
                        unset($products[$product_code]);
                    }
                }
                foreach ($products as $product_code => $product) {
                    if ($major_api_version == 2) {
                        $products[$product_code]['services'] = array_keys($products[$product_code]['services_v2']);
                    }
                    if ($major_api_version == 3) {
                        $products[$product_code]['services'] = array_keys($products[$product_code]['services_v3']);
                    }
                    if ((count($products[$product_code]['to_country_codes']) == 0) && $to_country != $from_country && !in_array($to_country, $this->module->getEUCountriesCodes())) {
                        $products[$product_code]['export_documents'] = 1;
                    }
                }
            }
            return $products[$code];
        }

        if ($to_country != '') {
            foreach ($products as $product_code => $product) {
                if (is_array($product['to_country_codes']) && count($product['to_country_codes']) > 0 && !in_array($to_country, $product['to_country_codes'])) {
                    unset($products[$product_code]);
                } elseif (is_array($product['excluding_country_codes']) && in_array($to_country, $product['excluding_country_codes'])) {
                    unset($products[$product_code]);
                }
            }
        }
        if ($from_country != '') {
            foreach ($products as $product_code => $product) {
                if (is_array($product['from_country_codes']) && !in_array($from_country, $product['from_country_codes'])) {
                    unset($products[$product_code]);
                }
            }
        }
        if ($from_country != '' && $to_country != '' && $from_country == $to_country) {
            foreach ($products as $product_code => $product) {
                if (count($product['to_country_codes']) != 1 || count($product['from_country_codes']) != 1 || $product['to_country_codes'][0] != $product['from_country_codes'][0]) {
                    unset($products[$product_code]);
                }
            }
        }

        if ($api_version != '') {
            $major_api_version = $this->getMajorApiVersion($api_version);
            foreach ($products as $product_code => $product) {
                if (($major_api_version == '2' || $major_api_version == '3') && !isset($product['alias_v2'])) {
                    unset($products[$product_code]);
                }
            }
            foreach ($products as $product_code => $product) {
                if ($major_api_version == 2) {
                    $products[$product_code]['services'] = array_keys($products[$product_code]['services_v2']);
                }
                if ($major_api_version == 3) {
                    $products[$product_code]['services'] = array_keys($products[$product_code]['services_v3']);
                }
                if ((count($products[$product_code]['to_country_codes']) == 0) && $to_country != $from_country && !in_array($to_country, $this->module->getEUCountriesCodes())) {
                    $products[$product_code]['export_documents'] = 1;
                }
            }
        }

        return $products;
    }

    public function getConfiguredDHLProducts()
    {
        return array();
    }
}
