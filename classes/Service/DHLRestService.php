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

namespace PrestaShop\Module\dhldp\Service;

use Customer;
use DHLDPPackage;
use DHLDPLabel;
use PrestaShop\Module\dhldp\classes\DHLTokenManager;
use Mail;
use Order;
use OrderCarrier;
use PrestaShop\Module\dhldp\Helper\ConfigurationHelperTrait;
use PrestaShop\Module\dhldp\classes\DHLDPApiRest;
use Tools;

class DHLRestService
{
    use ConfigurationHelperTrait;

    /** @var \DhlDp */
    private $module;

    /** @var DHLDPApiRest */
    private $api;

    /**
     * @param \DhlDp $module
     * @param DHLDPApiRest $api
     */
    public function __construct($module, DHLDPApiRest $api)
    {
        $this->module = $module;
        $this->api = $api;
    }

    /**
     * Delete a delivery label via the DHL REST API.
     *
     * @param string $shipment_number
     * @param int|null $id_shop
     *
     * @return bool
     */
    public function deleteDeliveryLabel($shipment_number, $id_shop = null)
    {
        if (self::getConfig('DHL_MODE', $id_shop) == 1) {
            $trackingEndpoint = DHLDPApiRest::$cig_endpoint_live;
        } else {
            $trackingEndpoint = DHLDPApiRest::$cig_endpoint_sandbox;
        }

        $trackingEndpoint .= '/orders?shipment=' . $shipment_number . '&profile=' . \DhlDp::DHL_PROFILE;

        $dhlManager = new DHLTokenManager();
        $rclient = new \DHLDPRestClient(array('savelog_callback' => 'DHLDP::logToFile'));

        $request = array(
            'profile' => \DhlDp::DHL_PROFILE,
            'shipmentNumber' => $shipment_number,
        );

        $rclient->saveLogData('DHL', 'deleteDeliveryLabel', array(
            'endpoint' => $trackingEndpoint,
            'data' => $request,
        ));

        $response = $dhlManager->makeApiRequest($trackingEndpoint, 'DELETE', $request);

        $rclient->saveLogData('DHL', 'deleteDeliveryLabel', null, $response);

        if (is_array($response) && isset($response['items'][0]['shipmentNo'])) {
            $id_dhldp_label = DHLDPLabel::getLabelIDByShipmentNumber($shipment_number);
            if ($id_dhldp_label > 0) {
                $dhldp_label = new DHLDPLabel($id_dhldp_label);
                if ($dhldp_label->delete()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Trigger manifest creation for a shipment.
     *
     * @param string $shipment_number
     * @param int|null $id_shop
     *
     * @return bool
     */
    public function doManifest($shipment_number, $id_shop = null)
    {
        $this->api->setApiVersionByIdShop($id_shop);

        $response = $this->api->callDHLApi(
            'doManifest',
            array('shipmentNumber' => $shipment_number),
            $id_shop
        );

        return is_object($response) && isset($response->shipmentNumber);
    }

    /**
     * Create a DHL return label.
     *
     * @param array $sender_address
     * @param int $id_order_carrier
     * @param string $reference_number
     * @param int $id_order_return
     * @param int|null $id_shop
     *
     * @return bool
     */
    public function createReturnLabel($sender_address, $id_order_carrier, $reference_number, $id_order_return = 0, $id_shop = null)
    {
        $countries = $this->module->getCountriesIDsForRA($sender_address['country']['countryISOCode']);
        $receiverId = '';
        if ($countries !== false) {
            $receiverId = strtolower($countries['iso_code3']);
            $sender_address['country']['countryISOCode'] = $countries['iso_code3'];
        }

        $return_order = array(
            'receiverId' => $receiverId,
            'customerReference' => 'Retoure ' . $reference_number,
            'shipmentReference' => 'Retoure ' . $reference_number,
            'shipper' => $sender_address,
//            'email' => '',
//            'telephoneNumber' => '',
//            'returnDocumentType' => 'SHIPMENT_LABEL',
        );

        $response = $this->api->getDhlReturnLabel($return_order, $id_shop);

        if (!is_array($response) || empty($response['shipmentNo']) || empty($response['label']) || empty($response['label']['b64'])) {
            return false;
        }

        $dhldp_label = new DHLDPLabel();
        $dhldp_label->id_order_carrier = (int) $id_order_carrier;
        $dhldp_label->product_code = 'ra';
        $dhldp_label->options = '';
        $dhldp_label->shipment_number = $response['shipmentNo'];

        $labelIdentifier = 'return-' . $dhldp_label->shipment_number;
        $dhldp_label->label_url = $this->module->saveLabelFile($labelIdentifier, base64_decode($response['label']['b64']));
        if (!$dhldp_label->label_url) {
            return false;
        }

        $dhldp_label->export_label_url = isset($response['items']['customsDoc']['url']) ? $response['items']['customsDoc']['url'] : '';
        $dhldp_label->cod_label_url = '';
        $dhldp_label->return_label_url = '';
        $dhldp_label->is_complete = 1;
        $dhldp_label->is_return = 1;
        $dhldp_label->with_return = 0;
        $dhldp_label->id_order_return = (int) $id_order_return;
        $dhldp_label->shipment_date = '';
        $dhldp_label->api_version = $this->api->getApiVersion();
        $dhldp_label->routing_code = isset($response['routingCode']) ? $response['routingCode'] : '';
        $dhldp_label->idc = '';
        $dhldp_label->idc_type = '';
        $dhldp_label->int_idc = '';
        $dhldp_label->int_idc_type = '';

        if (!$dhldp_label->save()) {
            return false;
        }

        $dhldp_package = new DHLDPPackage();
        $dhldp_package->id_dhldp_label = $dhldp_label->id;
        $dhldp_package->weight = 1;
        $dhldp_package->length = 1;
        $dhldp_package->width = 1;
        $dhldp_package->height = 1;
        $dhldp_package->package_type = 'PK';
        $dhldp_package->shipment_number = $dhldp_label->shipment_number;
        $dhldp_package->save();

        $order_carrier = new OrderCarrier((int) $id_order_carrier);
        $order = new Order((int) $order_carrier->id_order);
        $id_shop = $order->id_shop;

        if (self::getConfig('DHL_RETURN_MAIL', $id_shop)) {
            $customer = new Customer((int) $order->id_customer);
            $data = array(
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{order_name}' => $order->reference,
                '{id_order}' => $order->id,
            );

            $pdf_file = $this->module->getLabelFilePathByLabelUrl($dhldp_label->label_url);

            $file_attachment = array();
            if ($pdf_file !== '') {
                $file_attachment = array(
                    'dhl_return_label' => array(
                        'content' => Tools::file_get_contents($pdf_file),
                        'name' => 'dhldp_return_label_' . $order->id . '_' . $id_order_return . '.pdf',
                        'mime' => 'application/pdf',
                    ),
                );
            }

            if (!Mail::Send(
                (int) $order->id_lang,
                'dhl_return_label',
                $this->module->l('Return label'),
                $data,
                $customer->email,
                $customer->firstname . ' ' . $customer->lastname,
                null,
                null,
                $file_attachment,
                null,
                $this->module->getLocalPath() . 'mails/',
                false,
                (int) $order->id_shop
            )) {
                return false;
            }
        }

        return true;
    }
}
