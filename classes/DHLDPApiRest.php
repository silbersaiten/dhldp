<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.2
 * @link      https://www.silbersaiten.de
 */

namespace PrestaShop\Module\dhldp\classes;

use Address;
use Configuration;
use Country;
use Customer;
use DhlDp;
use PrestaShop\Module\dhldp\classes\DHLTokenManager;
use State;
use Tools;
use DHLDPRestClient;
use Validate;

require_once(dirname(__FILE__) . '/DHLTokenManager.php');
require_once(dirname(__FILE__) . '/DHLDPRestClient.php');

class DHLDPApiRest
{
    public $errors = [];
    public $warnings = [];
    public $confirmations = [];
    public static $cig_endpoint_sandbox = 'https://api-sandbox.dhl.com/parcel/de/shipping/v2';
    public static $cig_endpoint_live = 'https://api-eu.dhl.com/parcel/de/shipping/v2/';
    public static $tracking_url = 'https://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=[tracking_number]';
    public static $supported_shipper_countries = ['DE' => ['api_versions' => ['2.1']]];
    public static $dhl_sbx_user;
    public static $dhl_sbx_pass;
    public static $dhl_live_user;
    public static $dhl_live_pass;
    public static $dhl_sbx_ciguser;
    public static $dhl_sbx_cigpass;
    public static $dhl_sbx_ekp = ['2.1' => '3333333333'];
    public static $cig_endpoint_retoure_sandbox = 'https://api-sandbox.dhl.com/parcel/de/shipping/returns/v1/orders';
    public static $cig_endpoint_retoure_live = 'https://api-eu.dhl.com/parcel/de/shipping/returns/v1/orders';//#TODO !!!!!?????
    public static $dhl_sbx_retoure_user;
    public static $dhl_sbx_retoure_sign;

    public function __construct($module, $api_version = '2')
    {
        $this->module = $module;
        $this->setApiVersion($api_version);
        self::$dhl_live_user = Configuration::get('DHL_LIVE_USER');
        self::$dhl_live_pass = Configuration::get('DHL_LIVE_PASS');
        self::$dhl_sbx_ciguser = Configuration::get('DHL_SDX_CIGUSER');
        self::$dhl_sbx_cigpass = Configuration::get('DHL_SDX_CIGPASS');
        self::$dhl_sbx_retoure_user = Configuration::get('DHLDP_DHL_SDX_USER');
        self::$dhl_sbx_retoure_sign = Configuration::get('DHLDP_DHL_SDX_PASS');
    }

    public function setApiVersion($api_version)
    {
        if ($api_version !== null) {
            $this->api_version = $api_version;
        } else {
            $this->api_version = Configuration::get('DHLDP_DHL_API_VERSION', null, null, Context::getContext()->shop->id);
        }
        return true;
    }

    public function getApiVersion()
    {
        return $this->api_version;
    }

    public function getDefinedProducts($code = '', $to_country = '', $from_country = '', $api_version = '')
    {
        $products = array(
            'V01PAK' => array(
                'procedure' => '01',
                'alias_v2' => 'V01PAK',
                'active' => true,
                'name' => 'DHL Paket',
                'type' => 'DD',
                'to_country_codes' => array('DE'),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 15, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 11, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 1, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
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

                //For REST API
                'services' => [
                    'AdditionalInsurance',
                    'CashOnDelivery',
                    'BulkyGoods',
                    'VisualCheckOfAge',
                    'PreferredNeighbour',
                    'PreferredLocation',
                    'NamedPersonOnly',
                    'NoticeOfNonDeliverability',
                    'IdentCheck',
                    'PreferredDay',
                    'NoticeOfNonDeliverability',
                    'DHLRetoure',
                    'GoGreenPlus',
                    'ParcelOutletRouting', // *NEW
                    'SignedForByRecipient', // *NEW
                ],
            ),
            'V53WPAK' => array(
                'procedure' => '53',
                'alias_v2' => 'V53WPAK',
                'active' => true,
                'name' => 'DHL Paket International',
                'type' => 'DD',
                'to_country_codes' => array(), //other countries - export documents
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 15, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 11, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 1, 'max' => 200, 'step' => 1, 'unit' => 'cm'),
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
                //For REST API
                'services' => [
                    'Endorsement',
                    'Premium',
                    'AdditionalInsurance',
                    'CashOnDelivery',
                    'BulkyGoods',
                    'Notification',
                    'ProofOfDelivery',
                    'Economy',
                    'DirectInjection',
                    'Bypass',
                    'ReturnReceipt',
                ],
            ),
            'V54EPAK' => array(
                'procedure' => '54',
                'alias_v2' => 'V54EPAK',
                'active' => true,
                'name' => 'DHL Europaket',
                'type' => 'DD',
                'to_country_codes' => $this->module->getEUCountriesCodes(),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 15, 'max' => 120, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 11, 'max' => 60, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 3.5, 'max' => 60, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'weight' => array('min' => 0.1, 'max' => 31.5, 'step' => 0.1, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 1),
                    'HigherInsurance' => array(
                        'InsuranceAmount' => array('min' => 0, 'max' => 10000, 'step' => 1),
                        'InsuranceCurrency' => 'EUR'
                    ),
                ),
                //For REST API
                'services' => [
                    'AdditionalInsurance',
                ],
            ),
            'V62KP' => array(
                'procedure' => '62',
                'alias_v2' => 'V62KP',
                'active' => true,
                'name' => 'DHL Kleinpaket',
                'to_country_codes' => array('DE'),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 10, 'max' => 35, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 7, 'max' => 25, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 0.1, 'max' => 8, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.01, 'max' => 1, 'step' => 0.01, 'unit' => 'kg'),
                    'weight' => array('min' => 0.01, 'max' => 1, 'step' => 0.01, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 1),
                    'HigherInsurance' => array(
                        'InsuranceAmount' => array('min' => 0, 'max' => 10000, 'step' => 1),
                        'InsuranceCurrency' => 'EUR'
                    ),
                ),
                //For REST API
                'services' => [
                    'AdditionalInsurance',
                    'PreferredNeighbour',
                    'PreferredLocation',
                    'GoGreenPlus',
                    'ParcelOutletRouting', // *NEW
                ],
            ),
            'V66WPI' => array(
                'procedure' => '66',
                'alias_v2' => 'V66WPI',
                'active' => true,
                'name' => 'Warenpost International',
                'to_country_codes' => array(),
                'from_country_codes' => array('DE'),
                'excluding_country_codes' => false,
                'params' => array(
                    'length' => array('min' => 14, 'max' => 35.3, 'step' => 1, 'unit' => 'cm'),
                    'width' => array('min' => 9, 'max' => 25, 'step' => 1, 'unit' => 'cm'),
                    'height' => array('min' => 0.1, 'max' => 10, 'step' => 1, 'unit' => 'cm'),
                    'weight_package' => array('min' => 0.01, 'max' => 1, 'step' => 0.01, 'unit' => 'kg'),
                    'weight' => array('min' => 0.01, 'max' => 1, 'step' => 0.01, 'unit' => 'kg'),
                    'packages' => array('min' => 1, 'max' => 1),
                ),
                'services' => [
                    'Premium',
                ],
            )
        );

        foreach ($products as $product_code => $product) {
            if ($product['active'] != true) {
                unset($products[$product_code]);
            }
        }

        if ($code != '' && isset($products[$code])) {
            foreach ($products as $product_code => $product) {
                if ((count($products[$product_code]['to_country_codes']) == 0) && $to_country != $from_country && !in_array($to_country, $this->module->getEUCountriesCodes())) {
                    $products[$product_code]['export_documents'] = 1;
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
            foreach ($products as $product_code => $product) {
                if ((count($products[$product_code]['to_country_codes']) == 0) && $to_country != $from_country && !in_array($to_country, $this->module->getEUCountriesCodes())) {
                    $products[$product_code]['export_documents'] = 1;
                }
            }
        }
        return $products;
    }

    public function getMajorApiVersion($api_version = '')
    {
        preg_match('/(?P<major>\d+).(?P<minor>\d+)/', ($api_version != '') ? $api_version : $this->api_version, $matches);

        if (isset($matches['major'])) {
            return $matches['major'];
        }
        return false;
    }

    public static function getSupportedApiVersions($country_code = 'DE')
    {
        return self::$supported_shipper_countries[$country_code]['api_versions'];
    }

    public function checkDHLAccount($dhl_mode)
    {
        $this->errors = array();
        $dhlManager = new DHLTokenManager();
        try {
            $dhlManager->getToken();
            return true;
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        return false;
    }

    public function setApiVersionByIdShop($id_shop)
    {
        return $this->setApiVersion(Configuration::get('DHLDP_DHL_API_VERSION', null, null, $id_shop));
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
                $receiver['name2'] = $address->firstname . ' ' . $address->lastname;
            } else {
                $receiver['name1'] = $address->firstname . ' ' . $address->lastname;
                $receiver['name2'] = ' ';
            }

            if (preg_match('/^Packstation/', $address->address1) && Tools::strtoupper($country->iso_code) == 'DE') {
                $receiver['Packstation'] = array(
                    'PackstationNumber' => trim(str_replace('Packstation', '', $address->address1)),
                    'PostNumber' => trim($address->address2),
                    'Zip' => $address->postcode,
                    'City' => $address->city
                );
                $receiver['Address']['Origin'] = array(
                    'countryISOCode' => Tools::strtoupper($country->iso_code),
                );
            } elseif (preg_match('/^Postfiliale/', $address->address1) && Tools::strtoupper($country->iso_code) == 'DE') {
                $receiver['Postfiliale'] = array(
                    'PostfilialeNumber' => trim(str_replace('Postfiliale', '', $address->address1)),
                    'PostNumber' => trim($address->address2),
                    'Zip' => $address->postcode,
                    'City' => $address->city
                );
                $receiver['Address']['Origin'] = array(
                    'countryISOCode' => Tools::strtoupper($country->iso_code),
                );
            } else {
                $receiver['Address'] = array(
                    'additionalAddressInformation1' => $address->address2,
                    'Zip' => array(),
                    'city' => $address->city,
                    'Origin' => array(
                        'countryISOCode' => ($country->iso_code ? Tools::strtoupper($country->iso_code) : ''),
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

            $receiver['Address']['streetName'] = trim($address->address1);
            $receiver['name3'] = trim($address->address2);
            $receiver['Address']['addressAddition'] = trim($address->address2);
            $receiver['Communication']['email'] = $customer->email;
            $receiver['Communication']['phone'] = $address->phone;
            $receiver['Communication']['mobile'] = $address->phone_mobile;
            $receiver['Address']['dispatchingInformation'] = '';
            return $receiver;
        }
        return false;
    }

    public function getDHLDeliveryAddress($id_address, $address_input = false, $id_shop = null)
    {
        if ($address_input === false) {
            $address = $this->normalizeAddress(new Address((int)$id_address));
        } else {
            $address = [];

            $norm_address = $this->normalizeAddress(new Address((int)$id_address));

            $address['consignee']['name1'] = $this->cutText($address_input['name1'], 49);
            $address['consignee']['name2'] = isset($address_input['name2']) ? $address_input['name2'] : '';
            $address['consignee']['name3'] = isset($address_input['name3']) ? $address_input['name3'] : '';
            if ($address_input['comm_person'] != '') {
                $address['consignee']['contactName'] = $address_input['comm_person'];
            }
            $address['consignee']['email'] = isset($address_input['comm_email']) ? $address_input['comm_email'] : '';
            $address['consignee']['phone'] = isset($address_input['comm_phone']) ? $address_input['comm_phone'] : '';
            $address['consignee']['mobile'] = isset($address_input['comm_mobile']) ? $address_input['comm_mobile'] : '';
            if ($address['consignee']['phone'] == '' && $address['consignee']['mobile'] != '') {
                $address['consignee']['phone'] = $address_input['comm_mobile'];
            }

            $country = (new \DhlDp)->getCountriesIDsForRA($norm_address['Address']['Origin']['countryISOCode']);
            $address['consignee']['countryISOCode'] = $country['iso_code'];
            $address['consignee']['addressStreet'] = substr($address_input['street_name'], 0, 50);
            $address['consignee']['additionalAddressInformation1'] = $address_input['address_addition'];
            $address['consignee']['dispatchingInformation'] = trim($address_input['dispatching_information']);
            $address['consignee']['postalCode'] = $address_input['zip'];
            $address['consignee']['city'] = $address_input['city'];
            $address['consignee']['country'] = $country['iso_code3'];
            if ($address_input['state'] != '') {
                $address['consignee']['state'] = $address_input['state'];
            }
            if (in_array($country['iso_code'], array('NL', 'IT', 'LU', 'US', 'DE'))) {
                $address['consignee']['name3'] = $address['consignee']['additionalAddressInformation1'];
                unset($address['consignee']['additionalAddressInformation1']);
            }

            if ($address_input['address_type'] === 'ps') {
                $address['Packstation'] = array(
                    'lockerID' => $address_input['ps_packstation_number'],
                    'postNumber' => $address_input['ps_post_number'],
                    'postalCode' => $address_input['ps_zip'],
                    'city' => $address_input['ps_city'],
                    'country' => $country['iso_code3'],
                );
                $address['consignee']['postalCode'] = $address_input['ps_zip'];
                $address['consignee']['city'] = $address_input['ps_city'];
                $address['consignee']['name'] = $this->cutText($address_input['name1'], 49);
                $address['consignee']['name2'] = $address_input['ps_post_number'];
            } elseif ($address_input['address_type'] === 'pf') {
                $address['Postfiliale'] = array(
                    'retailID' => $address_input['pf_postfiliale_number'],
                    'postNumber' => $address_input['pf_post_number'],
                    'postalCode' => $address_input['pf_zip'],
                    'city' => $address_input['pf_city'],
                    'country' => $country['iso_code3'],
                );
                $address['consignee']['postalCode'] = $address_input['pf_zip'];
                $address['consignee']['city'] = $address_input['pf_city'];
                $address['consignee']['name'] = $this->cutText($address_input['name1'], 49);
                $address['consignee']['name2'] = $address_input['pf_post_number'];
            }
        }

        if ($address_input['address_type'] === 'pf') {
            $returned = $this->removeEmptyElements($address['Postfiliale']);
            $consignee = $this->removeEmptyElements($address['consignee']);
            $consignee['PostOffice'] = $returned;
            return $consignee;
        } elseif ($address_input['address_type'] === 'ps') {
            $returned = $this->removeEmptyElements($address['Packstation']);
            $consignee = $this->removeEmptyElements($address['consignee']);
            $consignee['Locker'] = $returned;
            return $consignee;
        }
        return $this->removeEmptyElements($address['consignee']);
    }

    public function getShipperCountry($id_shop = null)
    {
        return Configuration::get('DHLDP_DHL_COUNTRY', null, null, $id_shop);
    }

    public function getShipper($id_shop = null)
    {
        $country = (new \DhlDp)->getCountriesIDsForRA(Configuration::get('DHLDP_DHL_COUNTRY', null, null, $id_shop));
        $shipper = array(
            'name1' => Configuration::get('DHLDP_DHL_COMPANY_NAME_1', null, null, $id_shop),
            'name2' => Configuration::get('DHLDP_DHL_COMPANY_NAME_2', null, null, $id_shop),
            'addressStreet' => substr(Configuration::get('DHLDP_DHL_STREET_NAME', null, null, $id_shop) . ' ' . Configuration::get('DHLDP_DHL_STREET_NUMBER', null, null, $id_shop), 0, 50),
            'postalCode' => Configuration::get('DHLDP_DHL_ZIP', null, null, $id_shop),
            'city' => Configuration::get('DHLDP_DHL_CITY', null, null, $id_shop),
            'country' => $country['iso_code3'],
            'countryISOCode' => $country['iso_code'],
            'email' => Configuration::get('DHLDP_DHL_EMAIL', null, null, $id_shop),
            'phone' => Configuration::get('DHLDP_DHL_PHONE', null, null, $id_shop),
            'Address' => array(
                'Origin' => array(
                    'countryISOCode' => Configuration::get('DHLDP_DHL_COUNTRY', null, null, $id_shop),
                    'state' => Configuration::get('DHLDP_DHL_STATE', null, null, $id_shop)
                ),
            ),
        );
        if (Configuration::get('DHLDP_DHL_CONTACT_PERSON', null, null, $id_shop) != '') {
            $shipper['contactName'] = Configuration::get('DHLDP_DHL_CONTACT_PERSON', null, null, $id_shop);
        }
        return $shipper;
    }

    public function getShipperDetails($packages)
    {
        $details = array();
        foreach ($packages as $package) {
            $details['dim'] = array(
                'uom' => "mm",
                'height' => $this->convertSizeToMillimeters($package['height']),
                'length' => $this->convertSizeToMillimeters($package['length']),
                'width' => $this->convertSizeToMillimeters($package['width']),
            );
            $details['weight'] = array(
                'uom' => "kg",
                'value' => $this->convertWeightToKg($package['weight']),
            );
        }
        return $details;
    }

    public function getDHLRASenderAddress($id_address, $address_input = false)
    {
        if ($address_input == false) {
            $address = $this->normalizeAddressForRA(new Address((int)$id_address));
        } else {
            $address = [];
            $address['name1'] = $address_input['name1'];
            $address['name2'] = $address_input['name2'];
            $address['name3'] = $address_input['name3'];
            $address['addressStreet'] = $address_input['streetName'];
            $address['addressHouse'] = $address_input['houseNumber'];
            $address['postalCode'] = $address_input['postCode'];
            $address['city'] = $address_input['city'];
            $address['country'] = ['countryISOCode' => $address_input['country']['countryISOCode']];
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

            $res_address = array();
            if ($address->company != '') {
                $res_address['name1'] = $address->firstname . ' ' . $address->lastname;
                $res_address['name2'] = $address->company;
            } else {
                $res_address['name1'] = $address->firstname . ' ' . $address->lastname;
                $res_address['name2'] = '';
            }

            $res_address['postalCode'] = $address->postcode;
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
            $res_address['addressStreet'] = $street_name;
            $res_address['addressHouse'] = $street_number;
            $res_address['country'] = array('state' => $state, 'countryISOCode' => Tools::strtoupper($country->iso_code));

            return $res_address;
        }
        return false;
    }

    function convertSizeToMillimeters($size)
    {
        $dimension_unit = Configuration::get('PS_DIMENSION_UNIT');
        switch ($dimension_unit) {
            case 'm':
                $size_in_mm = $size * 1000;
                break;
            case 'cm':
                $size_in_mm = $size * 10;
                break;
            case 'in':
                $size_in_mm = $size * 25.4;
                break;
            case 'mm':
            default:
                $size_in_mm = $size;
                break;
        }
        return $size_in_mm;
    }

    function convertMillimetersToPSDimensionUnit($size_in_mm)
    {
        $dimension_unit = Configuration::get('PS_DIMENSION_UNIT');
        switch ($dimension_unit) {
            case 'cm':
                $size_PS = $size_in_mm / 10;
                break;
            case 'm':
                $size_PS = $size_in_mm / 1000;
                break;
            case 'in':
                $size_PS = $size_in_mm / 25.4;
                break;
            default:
                $size_PS = $size_in_mm;
                break;
        }
        return $size_PS;
    }

    public function convertWeightToKg($weight)
    {
        $weight_unit = Configuration::get('PS_WEIGHT_UNIT');
        switch ($weight_unit) {
            case 'lb':
                $weight_in_kg = $weight * 0.453592;
                break;
            case 'oz':
                $weight_in_kg = $weight * 0.0283495;
                break;
            case 'g':
                $weight_in_kg = $weight / 1000;
                break;
            case 'kg':
            default:
                $weight_in_kg = $weight;
                break;
        }
        return $weight_in_kg;
    }

    function convertKGToPSWeightUnit($weight_in_kg)
    {
        $weight_unit = Configuration::get('PS_WEIGHT_UNIT');
        switch ($weight_unit) {
            case 'g':
                $weight_PS = $weight_in_kg * 1000;
                break;
            case 'lb':
                $weight_PS = $weight_in_kg * 2.20462;
                break;
            case 'oz':
                $weight_PS = $weight_in_kg * 35.274;
                break;
            default:
                $weight_PS = $weight_in_kg;
                break;
        }
        return $weight_PS;
    }

    public function getDhlReturnLabel($data, $id_shop = null)
    {
        $this->errors = [];
        $this->warnings = [];
        $this->confirmations = [];
        $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        if ($mode == 1) {
            $trackingEndpoint = self::$cig_endpoint_retoure_live . '?labelType=BOTH';
        } else {
            $trackingEndpoint = self::$cig_endpoint_retoure_sandbox . '?labelType=BOTH';
        }

        $rclient = new DHLDPRestClient(array('savelog_callback' => 'DHLDP::logToFile'));
        try {
            $method = 'POST';
            $dhlManager = new DHLTokenManager();
            $rclient->saveLogData('DHL', 'Return label', [
                'endpoint' => $trackingEndpoint,
                'data' => $data
            ]);
            $response = $dhlManager->makeApiRequest($trackingEndpoint, $method, $data);
            $rclient->saveLogData('DHL', 'Return label', null, $response);
            return $this->getResponse($response);
        } catch (\Exception $e) {
            $error_msg = $e->getMessage() . ((isset($e->detail)) ? ', ' . $e->detail : '');
            $this->errors[] = $error_msg;
            $rclient->saveLogData('DHL', 'Return label', null, null, $error_msg);
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function callDhlApi($function, $params, $id_shop = null)
    {
        $this->errors = array();
        $this->warnings = array();
        $this->confirmations = [];

        $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        if ($mode == 1) {
            $trackingEndpoint = self::$cig_endpoint_live;
        } else {
            $trackingEndpoint = self::$cig_endpoint_sandbox;
        }

        try {
            $data = [];
            $method = 'GET';
            if ($function === 'createShipmentOrder') {
                $trackingEndpoint .= '/orders?includeDocs=URL';
                $shipments = [];
                $shipments['product'] = isset($params['ShipmentOrder']['shipments']['product']) ? $params['ShipmentOrder']['shipments']['product'] : null;
                $shipments['billingNumber'] = isset($params['ShipmentOrder']['shipments']['billingNumber']) ? $params['ShipmentOrder']['shipments']['billingNumber'] : null;
                $shipments['refNo'] = isset($params['ShipmentOrder']['shipments']['refNo']) ? $params['ShipmentOrder']['shipments']['refNo'] : null;
                $shipments['shipper'] = isset($params['ShipmentOrder']['shipments']['shipper']) ? $params['ShipmentOrder']['shipments']['shipper'] : null;
                if (isset($params['ShipmentOrder']['shipments']['shipperRef'])) {
                    $shipments['shipperRef'] = $params['ShipmentOrder']['shipments']['shipperRef'];
                    unset($shipments['shipper']);
                }
                $shipments['consignee'] = isset($params['ShipmentOrder']['shipments']['consignee']) ? $params['ShipmentOrder']['shipments']['consignee'] : null;
                $shipments['details'] = isset($params['ShipmentOrder']['shipments']['details']) ? $params['ShipmentOrder']['shipments']['details'] : null;
                $shipments['customs'] = isset($params['ShipmentOrder']['customs']) ? $params['ShipmentOrder']['customs'] : null;
                $shipments['services'] = isset($params['ShipmentOrder']['services']) ? $params['ShipmentOrder']['services'] : null;

                $data = array(
                    'profile' => isset($params['ShipmentOrder']['profile']) ? $params['ShipmentOrder']['profile'] : null,
                );
                $method = 'POST';
                $data['shipments'] = [$shipments];
            }

            if ($function === 'getManifest') {
                $trackingEndpoint .= '/manifests?date=' . $params['manifestDate'] . '&includeDocs=URL';
                $method = 'GET';
            }

            $dhlManager = new DHLTokenManager();

            $rclient = new DHLDPRestClient(array('savelog_callback' => 'DHLDP::logToFile'));
            $rclient->saveLogData('DHL', $function, [
                'endpoint' => $trackingEndpoint,
                'data' => $data
            ]);

            $response = $dhlManager->makeApiRequest($trackingEndpoint, $method, $data);

            $rclient->saveLogData('DHL', $function, null, $response);
            return $this->getResponse($response);
        } catch (\Exception $e) {
            $error_msg = $e->getMessage() . ((isset($e->detail)) ? ', ' . $e->detail : '');
            $this->errors[] = $error_msg;

            $rclient = new DHLDPRestClient(array('savelog_callback' => 'DHLDP::logToFile'));
            $rclient->saveLogData('DHL', $function, null, null, $error_msg);

            echo 'Error: ' . $e->getMessage();
        }
        return false;
    }

    public function getResponse($res)
    {
        if (isset($res['sstatus'])) {
            // Return label
            $http_status = $res['sstatus']['status'];
            $http_status_title = $res['sstatus']['title'];
            $http_status_detail = $res['sstatus']['detail'];
        } elseif(isset($res['status']['status'])) {
            $http_status = $res['status']['status'];
            $http_status_title = $res['status']['title'];
            $http_status_detail = $res['status']['detail'];
        } else {
            // Return label
            $http_status = $res['status'];
            $http_status_title = $res['title'];
            $http_status_detail = $res['detail'];
        }

        if ($http_status == '200' || $http_status == '201' || $http_status == '207') {
            $this->confirmations[] = $http_status_title . ': ' . $http_status_detail;
        }
        if (isset($res['items'])) {
            if (isset($res['items'][0]['validationMessages'])) {
                foreach ($res['items'][0]['validationMessages'] as $validationMessage) {
                    if ($validationMessage['validationState'] === "Error") {
                        $this->errors[] = (isset($validationMessage['property']) ? $validationMessage['property'] : '') . ': ' . $validationMessage['validationMessage'];
                    }
                    if ($validationMessage['validationState'] === "Warning") {
                        $this->warnings[] = (isset($validationMessage['property']) ? $validationMessage['property'] : '') . ': ' . $validationMessage['validationMessage'];
                    }
                }
            }
        }

        if (is_array($this->errors) && count($this->errors) > 0) {
            return false;
        }
        return $res;
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

    public function validateDHLShipment($function, $params, $id_shop = null)
    {
        $this->errors = array();
        $this->warnings = array();
        $this->confirmations = [];
        $mode = Configuration::get('DHLDP_DHL_MODE', null, null, $id_shop);
        if ($mode == 1) {
            $trackingEndpoint = self::$cig_endpoint_live;
        } else {
            $trackingEndpoint = self::$cig_endpoint_sandbox;
        }
        $trackingEndpoint = $trackingEndpoint . '/orders?validate=true';

        $shipments = [];
        $shipments['product'] = $params['ShipmentOrder']['shipments']['product'];
        $shipments['billingNumber'] = $params['ShipmentOrder']['shipments']['billingNumber'];
        $shipments['refNo'] = $params['ShipmentOrder']['shipments']['refNo'];
        if (isset($params['ShipmentOrder']['shipments']['shipperRef'])) {
            $shipments['shipperRef'] = $params['ShipmentOrder']['shipments']['shipperRef'];
        } else {
            $shipments['shipper'] = $params['ShipmentOrder']['shipments']['shipper'];
        }
        $shipments['consignee'] = $params['ShipmentOrder']['shipments']['consignee'];
        $shipments['details'] = $params['ShipmentOrder']['shipments']['details'];
        if (isset($params['ShipmentOrder']['customs'])) {
            $shipments['customs'] = $params['ShipmentOrder']['customs'];
        }
        if (!empty($params['ShipmentOrder']['services'])) {
            $shipments['services'] = $params['ShipmentOrder']['services'];
        }

        $r = array(
            'profile' => $params['ShipmentOrder']['profile'],
        );
        $r['shipments'] = [$shipments];
        $dhlManager = new DHLTokenManager();
        $response = $dhlManager->makeApiRequest($trackingEndpoint, 'POST', $r);
        return $this->getResponse($response);
    }

    public function extractHouseNumberAndMoveText(&$address, &$name3)
    {
        // Regular expression to find the first sequence of digits followed by optional symbols
        preg_match('/\b\d+[\w\-\/]*/', $address, $matches, PREG_OFFSET_CAPTURE);

        if (!empty($matches)) {
            $houseNumber = $matches[0][0];  // First match, actual value of the house number
            $position = $matches[0][1];     // Position of the house number in the string
            $name3 = substr($address, $position + strlen($houseNumber));  // Text after the house number
            $address = substr($address, 0, $position + strlen($houseNumber)); // Text including house number
            $address = trim($address);
            $name3 = trim($name3);

            return $houseNumber;
        } else {
            return "No house number found";
        }
    }

    public function removeEmptyElements(array $array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyElements($value);
            }
            if (empty($array[$key])) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    public function cutText($text, $maxLength)
    {
        $text = strip_tags($text);
        if (Tools::strlen($text) <= $maxLength) {
            return $text;
        }
        $cutText = Tools::substr($text, 0, $maxLength);
        $lastSpace = strrpos($cutText, ' ');
        if ($lastSpace !== false) {
            $cutText = Tools::substr($cutText, 0, $lastSpace);
        }
        return rtrim($cutText) . '...';
    }

    public function getPreparationCustoms($customs)
    {
        $result = [
            "exportType" => $customs['exportType'], //exportType
            "exportDescription" => $customs['exportTypeDescription'], //exportTypeDescription
            //"shipperCustomsRef" => "DE11111",
            //"consigneeCustomsRef" => "GB22222",
            "invoiceNo" => $customs['invoiceNumber'], //invoiceNumber
            "permitNo" => $customs['permitNumber'], //permitNumber
            "attestationNo" => $customs['attestationNumber'], //attestationNumber
            "postalCharges" => $this->convertToMonetaryObject($customs['additionalFee']),
            "items" => []
        ];

        foreach ($customs['ExportDocPosition'] as $item) {
            if ((float)$item['amount'] <= 0) {
                continue;
            }

            $country = (new \DhlDp)->getCountriesIDsForRA($item['countryCodeOrigin']);
            $itemData = [
                "itemDescription" => $item['description'], // description
                "packagedQuantity" => $item['amount'], // amount
                "countryOfOrigin" => $country['iso_code3'],// countryCodeOrigin
                "itemValue" => [
                    "currency" => "EUR",
                    "value" => number_format($item['customsValue'], 2, '.', ','), // customsValue
                ],
                "itemWeight" => [
                    "uom" => "kg",
                    "value" => $item['netWeightInKG'], // netWeightInKG
                ]
            ];
            if (!empty($item['customsTariffNumber'])) {
                $itemData["hsCode"] = (string)$item['customsTariffNumber'];// customsTariffNumber
            }
            $result['items'][] = $itemData;
        }

        if (empty($result)) {
            return null;
        }
        return $result;
    }

    public function getPreparationServices(array $services)
    {
        $result = [];

        if (isset($services['Service']['PreferredLocation']) && $services['Service']['PreferredLocation']['active'] == "1") {
            $result['preferredLocation'] = $this->cutText($services['Service']['PreferredLocation']['details'], 99);
        }
        if (isset($services['Service']['PreferredNeighbour']) && $services['Service']['PreferredNeighbour']['active'] == "1") {
            $result['preferredNeighbour'] = $this->cutText($services['Service']['PreferredNeighbour']['details'], 99);
        }
        if (isset($services['Service']['VisualCheckOfAge']) && $services['Service']['VisualCheckOfAge']['active'] == "1") {
            $result['visualCheckOfAge'] = $services['Service']['VisualCheckOfAge']['type'];
        }
        if (isset($services['Service']['NamedPersonOnly']) && $services['Service']['NamedPersonOnly']['active'] == "1") {
            $result['namedPersonOnly'] = true;
        }
        if (isset($services['Service']['SignedForByRecipient']) && $services['Service']['SignedForByRecipient']['active'] == "1") {
            $result['signedForByRecipient'] = true;
        }
        if (isset($services['Service']['NoticeOfNonDeliverability']) && $services['Service']['NoticeOfNonDeliverability']['active'] == "1") {
            $result['noNeighbourDelivery'] = true;
        }
        if (isset($services['Service']['ParcelOutletRouting']) && $services['Service']['ParcelOutletRouting']['active'] == "1") {
            $result['parcelOutletRouting'] = $services['Service']['ParcelOutletRouting']['details'];
        }
        //Ident Check
        if (isset($services['Service']['IdentCheck']) && $services['Service']['IdentCheck']['active'] == "1") {
            $result['identCheck'] = [
                "firstName" => $services['Service']['IdentCheck']['Ident']['surname'],
                "lastName" => $services['Service']['IdentCheck']['Ident']['givenName'],
                "dateOfBirth" => $services['Service']['IdentCheck']['Ident']['dateOfBirth'],
                "minimumAge" => $services['Service']['IdentCheck']['Ident']['minimumAge'],
            ];
        }
        // Preferred Day
        if (isset($services['Service']['PreferredDay']) && $services['Service']['PreferredDay']['active'] == "1") {
            $result['preferredDay'] = $services['Service']['PreferredDay']['details'];
        }
        //Cash On Delivery
        if (isset($services['Service']['CashOnDelivery']) && $services['Service']['CashOnDelivery']['active'] == "1") {
            $result["cashOnDelivery"] = [
                "amount" => $this->convertToMonetaryObject($services['Service']['CashOnDelivery']['codAmount'])
                ,
            ];
            if (isset($services['BankData'])) {
                $result["cashOnDelivery"]["bankAccount"] = [
                    "accountHolder" => isset($services['BankData']["accountOwner"]) ? $services['BankData']["accountOwner"] : '',
                    "bankName" => isset($services['BankData']["bankName"]) ? $services['BankData']["bankName"] : '',
                    "iban" => isset($services['BankData']["iban"]) ? $services['BankData']["iban"] : '',
                    "bic" => isset($services['BankData']["bic"]) ? $services['BankData']["bic"] : '',
                ];
                $result["cashOnDelivery"]["transferNote1"] = isset($services['BankData']["note1"]) ? $services['BankData']["note1"] : '';
                $result["cashOnDelivery"]["transferNote2"] = isset($services['BankData']["note2"]) ? $services['BankData']["note2"] : '';
            }
        }

        if (isset($services['Service']['AdditionalInsurance']) && $services['Service']['AdditionalInsurance']['active'] == "1") {
            $result["additionalInsurance"] = $this->convertToMonetaryObject($services['Service']['AdditionalInsurance']['insuranceAmount']);
        }

        //endorsement
        if (isset($services['Service']['Endorsement']) && $services['Service']['Endorsement']['active'] == "1") {
            $result['endorsement'] = $services['Service']['Endorsement']['type'];
        }

        if (isset($services['Service']['BulkyGoods']) && $services['Service']['BulkyGoods']['active'] == "1") {
            $result['bulkyGoods'] = true;
        }
        if (isset($services['Service']['GoGreenPlus']) && $services['Service']['GoGreenPlus']['active'] == "1") {
            $result['goGreenPlus'] = true;
        }
        if (isset($services['Service']['premium'])) {
            $result['premium'] = true;
        }
        if (isset($services['dhlRetoure'])) {
            $result['dhlRetoure'] = $services['dhlRetoure'];
        }
        if (empty($result)) {
            return null;
        }
        return $result;
    }

    protected function convertToMonetaryObject($value, $currency = 'EUR')
    {
        $validCurrencies = ['EUR', 'UNKNOWN'];

        if (!in_array($currency, $validCurrencies)) {
            throw new \Exception("Invalid currency code.");
        }

        if (!is_numeric($value)) {
            throw new \Exception("Value must be a numeric value.");
        }

        if ($value < 0 || $value > 100000) {
            throw new \Exception("Value must be between 0 and 100,000.");
        }
        return [
            "currency" => $currency,
            "value" => (float)$value
        ];
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
                                'remark' => isset($item['place']['containedInPlace']['name']) ? $item['place']['containedInPlace']['name'] : ''
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
                                'remark' => isset($item['place']['containedInPlace']['name']) ? $item['place']['containedInPlace']['name'] : ''
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
}
