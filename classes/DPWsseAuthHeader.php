<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2020 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.2
 * @link      http://www.silbersaiten.de
 */

class DPWsseAuthHeader extends SoapHeader
{
    public function __construct($user, $password)
    {
        // Initializing namespaces
        $ns_wsse = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $ns_wsu = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
        $password_type = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';

        // Creating WSS identification header using SimpleXML
        $root = new SimpleXMLElement('<root/>');

        $security = $root->addChild('wsse:Security', null, $ns_wsse);
        $security->addAttribute('xmlns:wsu', $ns_wsu);
        $security->addAttribute('soapenv:mustUnderstand', '1');

        $username_token = $security->addChild('wsse:UsernameToken', null, $ns_wsse);
        $username_token->addAttribute('wsu:Id', 'UsernameToken-1');
        $username_token->addChild('wsse:Username', $user, $ns_wsse);
        $username_token->addChild('wsse:Password', 'cvsd', $ns_wsse)->addAttribute('Type', $password_type);
        //$username_token->addChild('wsse:Nonce', base64_encode(pack('H*', mt_rand())), $ns_wsse)->addAttribute('EncodingType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary');
        //$username_token->addChild('wsu:Created', date('c'), $ns_wsse);

        // Recovering XML value from that object
        $root->registerXPathNamespace('wsse', $ns_wsse);
        $full = $root->xpath('/root/wsse:Security');
        $auth = $full[0]->asXML();

        //'<![CDATA['.$password.']]>'

        $auth = '<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" mustUnderstand="1">
        <wsse:UsernameToken Id="UsernameToken-1">
        <wsse:Username>silbersaiten</wsse:Username>
        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText"><![CDATA['.$password.']]></wsse:Password>
        </wsse:UsernameToken>
        </wsse:Security>';

        parent::__construct($ns_wsse, 'Security', new SoapVar($auth, XSD_ANYXML), true);
    }
}