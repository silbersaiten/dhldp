<?php

class SbslunsjmenyCronModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        parent::initContent();
        if (PHP_SAPI !== 'cli') {
            header('HTTP/1.1 403 Forbidden');
            exit('CLI only');
        }
        $this->runCronTask();
        exit;
    }

    private function runCronTask()
    {
        $task = (string)Tools::getValue('task');
        if ($task === 'stock-import') {
            return $this->module->runStockImportCronTask();
        }
        if ($task === 'order-export') {
            return $this->module->runOrderExportCronTask();
        }
        return [
            'status' => 'error',
            'message' => 'Unknown task. Use task=stock-import or task=order-export.',
        ];
    }
}
