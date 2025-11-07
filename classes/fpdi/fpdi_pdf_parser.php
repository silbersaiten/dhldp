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

require_once('pdf_parser.php');

class FPDIPDFParser extends PDFParser
{
    protected $_pages;
    protected $_pageCount;
    public $pageNo;
    public $_pdfVersion;
    public $availableBoxes = array('/MediaBox', '/CropBox', '/BleedBox', '/TrimBox', '/ArtBox');


    public function __construct($filename)
    {
        parent::__construct($filename);

        // resolve Pages-Dictonary
        $pages = $this->resolveObject($this->_root[1][1]['/Pages']);

        // Read pages
        $this->_readPages($pages, $this->_pages);

        // count pages;
        $this->_pageCount = count($this->_pages);
    }


    public function getPageCount()
    {
        return $this->_pageCount;
    }

    public function setPageNo($pageNo)
    {
        $pageNo = ((int)$pageNo) - 1;

        if ($pageNo < 0 || $pageNo >= $this->getPageCount()) {
            throw new InvalidArgumentException('Invalid page number!');
        }

        $this->pageNo = $pageNo;
    }

    public function getPageResources()
    {
        return $this->_getPageResources($this->_pages[$this->pageNo]);
    }

    protected function _getPageResources($obj)
    {
        $obj = $this->resolveObject($obj);

        if (isset($obj[1][1]['/Resources'])) {
            $res = $this->resolveObject($obj[1][1]['/Resources']);
            if ($res[0] == PDFParser::TYPE_OBJECT) {
                return $res[1];
            }
            return $res;
        }

        if (!isset($obj[1][1]['/Parent'])) {
            return false;
        }

        $res = $this->_getPageResources($obj[1][1]['/Parent']);
        if ($res[0] == PDFParser::TYPE_OBJECT) {
            return $res[1];
        }
        return $res;
    }

    public function getContent()
    {
        $buffer = '';

        if (isset($this->_pages[$this->pageNo][1][1]['/Contents'])) {
            $contents = $this->_getPageContent($this->_pages[$this->pageNo][1][1]['/Contents']);
            foreach ($contents as $tmpContent) {
                $buffer .= $this->_unFilterStream($tmpContent) . ' ';
            }
        }

        return $buffer;
    }

    protected function _getPageContent($contentRef)
    {
        $contents = array();

        if ($contentRef[0] == PDFParser::TYPE_OBJREF) {
            $content = $this->resolveObject($contentRef);
            if ($content[1][0] == PDFParser::TYPE_ARRAY) {
                $contents = $this->_getPageContent($content[1]);
            } else {
                $contents[] = $content;
            }
        } else {
            if ($contentRef[0] == PDFParser::TYPE_ARRAY) {
                foreach ($contentRef[1] as $tmp_content_ref) {
                    $contents = array_merge($contents, $this->_getPageContent($tmp_content_ref));
                }
            }
        }

        return $contents;
    }

    protected function _getPageBox($page, $boxIndex, $k)
    {
        $page = $this->resolveObject($page);
        $box = null;
        if (isset($page[1][1][$boxIndex])) {
            $box = $page[1][1][$boxIndex];
        }

        if (!is_null($box) && $box[0] == PDFParser::TYPE_OBJREF) {
            $tmp_box = $this->resolveObject($box);
            $box = $tmp_box[1];
        }

        if (!is_null($box) && $box[0] == PDFParser::TYPE_ARRAY) {
            $b = $box[1];
            return array(
                'x'   => $b[0][1] / $k,
                'y'   => $b[1][1] / $k,
                'w'   => abs($b[0][1] - $b[2][1]) / $k,
                'h'   => abs($b[1][1] - $b[3][1]) / $k,
                'llx' => min($b[0][1], $b[2][1]) / $k,
                'lly' => min($b[1][1], $b[3][1]) / $k,
                'urx' => max($b[0][1], $b[2][1]) / $k,
                'ury' => max($b[1][1], $b[3][1]) / $k,
            );
        } else {
            if (!isset($page[1][1]['/Parent'])) {
                return false;
            } else {
                return $this->_getPageBox($this->resolveObject($page[1][1]['/Parent']), $boxIndex, $k);
            }
        }
    }

    public function getPageBoxes($pageNo, $k)
    {
        if (!isset($this->_pages[$pageNo - 1])) {
            throw new InvalidArgumentException('Page ' . $pageNo . ' does not exists.');
        }

        return $this->_getPageBoxes($this->_pages[$pageNo - 1], $k);
    }

    protected function _getPageBoxes($page, $k)
    {
        $boxes = array();

        foreach ($this->availableBoxes as $box) {
            if ($_box = $this->_getPageBox($page, $box, $k)) {
                $boxes[$box] = $_box;
            }
        }

        return $boxes;
    }

    public function getPageRotation($pageNo)
    {
        if (!isset($this->_pages[$pageNo - 1])) {
            throw new InvalidArgumentException('Page ' . $pageNo . ' does not exists.');
        }

        return $this->_getPageRotation($this->_pages[$pageNo - 1]);
    }

    protected function _getPageRotation($obj)
    {
        $obj = $this->resolveObject($obj);
        if (isset($obj[1][1]['/Rotate'])) {
            $res = $this->resolveObject($obj[1][1]['/Rotate']);
            if ($res[0] == PDFParser::TYPE_OBJECT) {
                return $res[1];
            }
            return $res;
        }

        if (!isset($obj[1][1]['/Parent'])) {
            return false;
        }

        $res = $this->_getPageRotation($obj[1][1]['/Parent']);
        if (!$res) {
            return false;
        }
        if ($res[0] == PDFParser::TYPE_OBJECT) {
            return $res[1];
        }

        return $res;
    }

    protected function _readPages(&$pages, &$result)
    {
        // Get the kids dictionary
        $_kids = $this->resolveObject($pages[1][1]['/Kids']);

        if (!is_array($_kids)) {
            throw new Exception('Cannot find /Kids in current /Page-Dictionary');
        }

        if ($_kids[0] === self::TYPE_OBJECT) {
            $_kids = $_kids[1];
        }

        $kids = $_kids[1];

        foreach ($kids as $v) {
            $pg = $this->resolveObject($v);
            if ($pg[1][1]['/Type'][1] === '/Pages') {
                $this->_readPages($pg, $result);
            } else {
                $result[] = $pg;
            }
        }
    }
}
