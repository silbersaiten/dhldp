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

class FilterLZW
{
    protected $pr_stable = array();
    protected $pr_data = null;
    protected $pr_data_length = 0;
    protected $pr_tidx;
    protected $pr_bitstoget = 9;
    protected $pr_bytepointer;
    protected $pr_bitpointer;
    protected $pr_nextdata = 0;
    protected $pr_nextbits = 0;
    protected $pr_andtable = array(511, 1023, 2047, 4095);

    public function decode($data)
    {
        if ($data[0] == 0x00 && $data[1] == 0x01) {
            throw new Exception('LZW flavour not supported.');
        }

        $this->initsTable();

        $this->pr_data = $data;
        $this->pr_data_length = Tools::strlen($data);

        // Initialize pointers
        $this->pr_bytepointer = 0;
        $this->pr_bitpointer = 0;

        $this->pr_nextdata = 0;
        $this->pr_nextbits = 0;

        $old_code = 0;

        $uncomp_data = '';

        while (($code = $this->getNextCode()) != 257) {
            if ($code == 256) {
                $this->initsTable();
                $code = $this->getNextCode();

                if ($code == 257) {
                    break;
                }

                if (!isset($this->pr_stable[$code])) {
                    throw new Exception('Error while decompression LZW compressed data.');
                }

                $uncomp_data .= $this->pr_stable[$code];
                $old_code = $code;
            } else {
                if ($code < $this->pr_tidx) {
                    $string = $this->pr_stable[$code];
                    $uncomp_data .= $string;

                    $this->addStringToTable($this->pr_stable[$old_code], $string[0]);
                    $old_code = $code;
                } else {
                    $string = $this->pr_stable[$old_code];
                    $string = $string . $string[0];
                    $uncomp_data .= $string;

                    $this->addStringToTable($string);
                    $old_code = $code;
                }
            }
        }

        return $uncomp_data;
    }

    protected function initsTable()
    {
        $this->pr_stable = array();

        for ($i = 0; $i < 256; $i++) {
            $this->pr_stable[$i] = chr($i);
        }

        $this->pr_tidx = 258;
        $this->pr_bitstoget = 9;
    }

    protected function addStringToTable($old_string, $new_string = '')
    {
        $string = $old_string . $new_string;

        // Add this new String to the table
        $this->pr_stable[$this->pr_tidx++] = $string;

        if ($this->pr_tidx == 511) {
            $this->pr_bitstoget = 10;
        } else {
            if ($this->pr_tidx == 1023) {
                $this->pr_bitstoget = 11;
            } else {
                if ($this->pr_tidx == 2047) {
                    $this->pr_bitstoget = 12;
                }
            }
        }
    }

    protected function getNextCode()
    {
        if ($this->pr_byte_pointer == $this->pr_data_length) {
            return 257;
        }

        $this->pr_nextdata = ($this->pr_nextdata << 8) | (ord($this->pr_data[$this->pr_bytepointer++]) & 0xff);
        $this->pr_nextbits += 8;

        if ($this->pr_nextbits < $this->pr_bitstoget) {
            $this->pr_nextdata = ($this->pr_nextdata << 8) | (ord($this->pr_data[$this->pr_bytepointer++]) & 0xff);
            $this->pr_nextbits += 8;
        }

        $code = ($this->pr_nextdata >> ($this->pr_nextbits - $this->pr_bitstoget)) & $this->pr_andtable[$this->pr_bitstoget - 9];
        $this->pr_nextbits -= $this->pr_bitstoget;

        return $code;
    }

    public function encode($in)
    {
        unset($in);
        throw new LogicException('LZW encoding not implemented.');
    }
}
