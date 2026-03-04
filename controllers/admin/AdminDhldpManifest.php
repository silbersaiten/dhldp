<?php
/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.1
 * @link      https://www.silbersaiten.de
 */

require_once _PS_MODULE_DIR_ . 'dhldp/classes/fpdi/fpdi.php';

class AdminDhldpManifestController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = '';
        $this->bootstrap = true;
        $this->show_toolbar = false;
        $this->multishop_context = Shop::CONTEXT_SHOP;
        $this->context = Context::getContext();

        parent::__construct();

        $this->display = 'manifest';
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/admin.css');
    }

    public function initContent()
    {
        if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
            $this->displayInformation($this->l('You can only display the page in a shop context.'));
            return;
        }
        $this->content .= $this->module->displayMenu();
        $this->content .= $this->renderManifest();
        parent::initContent();
    }

    public function renderManifest()
    {
        $this->context->smarty->assign('manifestDate', Tools::getValue('manifestDate', date('Y-m-d')));
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/manifest.tpl');
    }

    public function initProcess()
    {
        parent::initProcess();
        if (Tools::getIsset('manifest' . $this->table)) {
            $this->display = 'manifest';
            $this->action = 'manifest';
        }
    }

    public function postProcess()
    {
        if (Tools::getIsset('manifest' . $this->table)) {
            if (Tools::isSubmit('getManifest')) {
                if (Tools::getValue('manifestDate', '') == '') {
                    $this->errors[] = Tools::displayError('Please select manifest date. Manifest date is empty.');
                } else {
                    $this->module->dhldp_api_rest->setApiVersion(Configuration::get('DHLDP_DHL_API_VERSION', null, null, Context::getContext()->shop->id));
                    $response = $this->module->dhldp_api_rest->callDhlApi(
                        ($this->module->dhldp_api_rest->getMajorApiVersion() == 1) ? 'getManifestDD' : 'getManifest',
                        array(
                            'manifestDate' => Tools::getValue('manifestDate')
                        ),
                        Context::getContext()->shop->id
                    );

                    $pdf = new Fpdi();
                    $pdfUrls = [];

                    foreach ($pdfUrls as $url) {
                        // Get the PDF content
                        $pdfContent = file_get_contents($url);
                        // Create a temporary file to store the PDF
                        $tempFile = tempnam(sys_get_temp_dir(), 'pdf');
                        file_put_contents($tempFile, $pdfContent);
                        // Get the number of pages in the PDF
                        $pageCount = $pdf->setSourceFile($tempFile);
                        // Import each page
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $templateId = $pdf->importPage($pageNo);
                            $size = $pdf->getTemplateSize($templateId);
                            $size['orientation'] = '';
                            // Add a new page with the same orientation and size
                            $pdf->AddPage($size['orientation'], [$size['w'], $size['h']]);
                            $pdf->useTemplate($templateId);
                        }
                        // Remove the temporary file
                        unlink($tempFile);
                    }
// Output the merged PDF
                    $pdf->Output('merged.pdf', 'F'); // Save as 'merged.pdf'
                    $data = [
                        "manifest" => [
                            [
                                "url" => "https://api-sandbox.dhl.com/parcel/de/shipping/v2/labels?token=1OJoa4SJK3lfq8%2BZo%2FbYl%2B2bxHW4EeUwWGaoAEYM9anw%2FIsy%2FYWyjaBSD467y30ResZQP%2BYiXig0DHf2HhzTsg%3D%3D",
                                "fileFormat" => "PDF"
                            ],
                            [
                                "url" => "https://api-sandbox.dhl.com/parcel/de/shipping/v2/labels?token=1OJoa4SJK3lfq8%2BZo%2FbYl%2B2bxHW4EeUwWGaoAEYM9anZ%2BlZ1b%2FvUhTI74O1ykXaVesZQP%2BYiXig0DHf2HhzTsg%3D%3D",
                                "fileFormat" => "PDF"
                            ]
                        ]
                    ];

                    $object = json_decode(json_encode($data));
// Create a new FPDI instance
                    $pdf = new Fpdi();
// Loop through each PDF in the manifest
                    foreach ($object->manifest as $pdfData) {
                        // Decode the base64 string
                        $pdfContent = base64_decode($pdfData->b64);

                        // Create a temporary file to hold the decoded PDF
                        $tempFile = tempnam(sys_get_temp_dir(), 'pdf');
                        file_put_contents($tempFile, $pdfContent);

                        // Get the number of pages in the PDF
                        $pageCount = $pdf->setSourceFile($tempFile);

                        // Import each page and add it to the new PDF
                        for ($i = 1; $i <= $pageCount; $i++) {
                            $templateId = $pdf->importPage($i);
                            $pdf->AddPage();
                            $pdf->useTemplate($templateId);
                        }
                        // Remove the temporary file
                        unlink($tempFile);
                    }
// Output the combined PDF
                    $pdf->Output('combined.pdf', 'F');
                    $pdf = new Fpdi();
                    $response = json_decode(json_encode($response));
                    foreach ($response['manifest'] as $manifestItem) {
                        $b64Content = $manifestItem['b64'];
                        $pdfContent = base64_decode($b64Content); // Decode base64 string
                        // Write decoded content to a temporary file
                        $tempFilePath = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
                        file_put_contents($tempFilePath, $pdfContent);
                        // Import each page of the temporary PDF file into FPDI
                        $pageCount = $pdf->setSourceFile($tempFilePath);
                        for ($i = 1; $i <= $pageCount; $i++) {
                            $tplIdx = $pdf->importPage($i);
                            $pdf->AddPage();
                            $pdf->useTemplate($tplIdx);
                        }
                        // Clean up: Delete the temporary file
                        unlink($tempFilePath);
                    }
                    $mergedPdfPath = 'merged_output.pdf';
                    $pdf->Output($mergedPdfPath, 'F');
                    echo "Merged PDF created at: $mergedPdfPath";
// Initialize FPDI
                    $mergedPdf = new Fpdi();
                    $response = json_decode(json_encode($response));
// Loop through each PDF in the manifest
                    $pdfContent = '';
                    foreach ($response->manifest as $item) {
                        if (property_exists($item, 'b64') && property_exists($item, 'fileFormat') && $item->fileFormat === 'PDF') {
                            // Decode the base64-encoded PDF content
                            $pdfContent = base64_decode($item->b64);
                            // Save the decoded content temporarily
                            $tempFile = tempnam(sys_get_temp_dir(), 'pdf') . '.pdf';
                            file_put_contents($tempFile, $pdfContent);
                            // Import each page of the PDF and add it to the merged PDF
                            $pageCount = $mergedPdf->setSourceFile($tempFile);

                            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                                $tplId = $mergedPdf->importPage($pageNo);
                                $mergedPdf->AddPage();
                                $mergedPdf->useTemplate($tplId);
                            }
                            unlink($tempFile);
                        }
                    }
                    $file_path = $this->module->getLocalPath() . '/pdfs/manifest' . str_replace('-', '', preg_replace('#[^a-zA-Z0-9\_\-]#', '', Tools::getValue('manifestDate'))) . '.pdf';
                    $f = fopen($file_path, 'wb');
                    fwrite($f, $pdfContent);
                    fclose($f);
                    if (file_exists($file_path)) {
                        ob_clean();
                        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
                        header('Pragma: public');
                        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                        header('Content-Type: application/pdf');
                        $afile_path = explode('.', basename($file_path));
                        $afile_path[0] .= '_' . uniqid();
                        header('Content-Disposition: attachment; filename="' . implode('.', $afile_path) . '"');
                        header('Content-Transfer-Encoding: binary');
                        //echo file_get_contents($file_path);
                        header('Content-Length: ' . filesize($file_path));
                        readfile($file_path);
                        exit;
                    } else {
                        if (count($this->module->dhldp_api_rest->errors)) {
                            foreach ($this->module->dhldp_api_rest->errors as $err) {
                                $this->errors[] = Tools::displayError($err);
                            }
                        }
                    }
                    $response = json_decode(json_encode($response));

                    if (is_object($response) && (isset($response->manifest))) {
                        $file_path = $this->module->getLocalPath() . '/pdfs/manifest' . str_replace('-', '', preg_replace('#[^a-zA-Z0-9\_\-]#', '', Tools::getValue('manifestDate'))) . '.pdf';
                        $f = fopen($file_path, 'wb');

                        foreach ($response->manifest as $item) {
                            if (property_exists($item, 'b64') && property_exists($item, 'fileFormat') && $item->fileFormat === 'PDF') {
                                fwrite($f, base64_decode($item->b64));
                            }
                        }

                        fclose($f);
                        if (file_exists($file_path)) {
                            ob_clean();
                            header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
                            header('Pragma: public');
                            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                            header('Content-Type: application/pdf');
                            $afile_path = explode('.', basename($file_path));
                            $afile_path[0] .= '_' . uniqid();
                            header('Content-Disposition: attachment; filename="' . implode('.', $afile_path) . '"');
                            header('Content-Transfer-Encoding: binary');
                            //echo file_get_contents($file_path);
                            header('Content-Length: ' . filesize($file_path));
                            readfile($file_path);
                            exit;
                        }
                    } else {
                        if (count($this->module->dhldp_api_rest->errors)) {
                            foreach ($this->module->dhldp_api_rest->errors as $err) {
                                $this->errors[] = Tools::displayError($err);
                            }
                        }
                    }
                }
            }
        } else {
            parent::postProcess();
        }
    }
}
