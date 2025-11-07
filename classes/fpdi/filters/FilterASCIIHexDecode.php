<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2020 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.0
 * @link      http://www.silbersaiten.de
 */

class FilterASCIIHexDecode
{
    public function decode($data)
    {
        $data = preg_replace('/[^0-9A-Fa-f]/', '', rtrim($data, '>'));
        if ((Tools::strlen($data) % 2) == 1) {
            $data .= '0';
        }

        return pack('H*', $data);
    }

    public function encode($data, $leave_eod = false)
    {
        return current(unpack('H*', $data)) . ($leave_eod ? '' : '>');
    }
}
