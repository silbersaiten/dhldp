<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2022 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.1.0
 * @link      http://www.silbersaiten.de
 */

class DHLDPSoapClient extends SoapClient
{
    public function __construct($a, $b)
    {
        parent::__construct($a, $b);
    }

    public function __doRequest($request, $location, $action, $version, $one_way = 0): ?string
    {
        // removing references from XML
        $tags = array('Shipper', 'ReturnReceiver', 'Name', 'Address', 'Communication');
        foreach ($tags as $tag) {
            if (preg_match("~<{$tag} id=\"ref(.+)\">(.+)</{$tag}>~ismU", $request, $matches)) {
                $ref = $matches[1];
                $request = str_replace(array(' id="ref'.$ref.'"'), '', $request);
                foreach ($tags as $tag2) {
                    $tagValue = "<{$tag2}>{$matches[2]}</{$tag2}>";
                    $request = str_replace("<{$tag2} href=\"#ref{$ref}\"/>", $tagValue, $request);
                }
            }
        }
        return parent::__doRequest($request, $location, $action, $version);
    }
}
