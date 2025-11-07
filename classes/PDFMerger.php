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
require_once(dirname(__FILE__).'/fpdi/fpdi.php');

class PDFMerger
{
    private $_files;

    public function __construct()
    {
    }

    public function fileExists($filepath)
    {
        if (file_exists($filepath)) {
            return true;
        } else {
            $file_headers = get_headers($filepath);
            if ($file_headers[0] != 'HTTP/1.1 404 Not Found') {
                return true;
            }
        }
        return false;
    }

    public function addPDF($filepath, $local_file = false, $pages = 'all')
    {
        unset($local_file);
        if ($this->fileExists($filepath)) {
            if (Tools::strtolower($pages) != 'all') {
                $pages = $this->rewritePages($pages);
            }

            $this->_files[] = array($filepath, $pages);
        } else {
            throw new \Exception("Could not locate PDF on '$filepath'");
        }

        return $this;
    }

    public function merge($outputmode = 'browser', $outputpath = 'newfile.pdf')
    {
        if (!isset($this->_files) || !is_array($this->_files)) {
            throw new \Exception('No PDFs to merge.');
        }

        $fpdi = new FPDI();

        //merger operations
        foreach ($this->_files as $file) {
            $filename = $file[0];
            $filepages = $file[1];

            try {
                $count = $fpdi->setSourceFile($filename);

                //add the pages
                if ($filepages == 'all') {
                    for ($i = 1; $i <= $count; $i++) {
                        $template = $fpdi->importPage($i);
                        $size = $fpdi->getTemplateSize($template);

                        $fpdi->AddPage(($size['w'] > $size['h'])?'L':'P', array($size['w'], $size['h']));
                        $fpdi->useTemplate($template);
                    }
                } else {
                    foreach ($filepages as $page) {
                        if (!$template = $fpdi->importPage($page)) {
                            throw new \Exception('Could not load page '.$page.' in PDF '.$filename.'. Check that the page exists.');
                        }
                        $size = $fpdi->getTemplateSize($template);

                        $fpdi->AddPage(($size['w'] > $size['h'])?'L':'P', array($size['w'], $size['h']));
                        $fpdi->useTemplate($template);
                    }
                }
            } catch (\Exception $e) {
//                throw new \Exception($e->getMessage());
                dump($e);die;
            }
        }

        //output operations
        $mode = $this->switchMode($outputmode);

        if ($mode == 'S') {
            return $fpdi->Output($outputpath, $mode);
        } elseif($mode == 'F'){
            $fpdi->Output($outputpath, $mode);
            return true;
        } else {
            @ob_end_clean();
            if ($fpdi->Output($outputpath, $mode)) {
                return true;
            } else {
                throw new \Exception("Error outputting PDF to '$outputmode'.");
            }
        }
    }

    public function switchMode($mode)
    {
        switch (Tools::strtolower($mode)) {
            case 'download':
                return 'D';
            case 'browser':
                return 'I';
            case 'file':
                return 'F';
            case 'string':
                return 'S';
            default:
                return 'I';
        }
    }

    public function rewritePages($pages)
    {
        $pages = str_replace(' ', '', $pages);
        $part = explode(',', $pages);
        $newpages = array();

        //parse hyphens
        foreach ($part as $i) {
            $ind = explode('-', $i);

            if (count($ind) == 2) {
                $x = $ind[0]; //start page
                $y = $ind[1]; //end page

                if ($x > $y) {
                    throw new \Exception('Starting page, '.$x.' is greater than ending page '.$y.'.');
                }

                //add middle pages
                while ($x <= $y) {
                    $newpages[] = (int)$x;
                    $x++;
                }
            } else {
                $newpages[] = (int)$ind[0];
            }
        }
        return $newpages;
    }
}
