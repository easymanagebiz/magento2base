<?php

namespace Develodesign\Easymanage\Model;

class SaveProducts implements \Develodesign\Easymanage\Api\SaveProductsInterface{

  const PROCESS_STEP = 100;
  const SKU_KEY = 'sku';

  const REINDEX_RUN = 1;
  const REINDEX_COMPLETE = 2;

  protected $request;

  protected $_helperProducts;
  protected $_helperIndexer;
  protected $_helperProcess;
  protected $logger;

  protected $_notFoundSkus = [];

  protected $_isDebug = true;

  public function __construct(
    \Magento\Framework\App\Request\Http $request,
    \Develodesign\Easymanage\Helper\Indexer $helperIndexer,
    \Develodesign\Easymanage\Helper\Process $helperProcess,
    \Develodesign\Easymanage\Helper\Products $helperProducts,
    \Psr\Log\LoggerInterface $logger
  ) {

    $this->request = $request;
    $this->logger = $logger;
    $this->_helperProducts = $helperProducts;
    $this->_helperIndexer = $helperIndexer;
    $this->_helperProcess = $helperProcess;
  }

  public function process() {

    try {
      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      $revisionId   = !empty($data['revison_id']) ? $data['revison_id'] : null;

      if(!$revisionId) {
        $errorMessage = 'Updater process revision ID not found: ' . $revisionId;
        $this->logger->debug($errorMessage);
        return [[
            'error' => $errorMessage
        ]];
      }

      $processData = $this->_helperProcess->getProcessData( $revisionId, self::PROCESS_STEP );
      $reindexing = 0;
      $step = 0;
      if(empty($processData['data']) && $processData['total'] == $processData['step'] && empty($processData['reindex'])) { //reindex required

        $reindexing = self::REINDEX_RUN;
        $this->_helperIndexer->reindexAll();
        $this->_helperProcess->updateLockFile($revisionId, ['reindex' => true]);

      }else if(!empty($processData['reindex'])) {

        $reindexing = self::REINDEX_COMPLETE;
        $this->_helperProcess->removeWorkingFiles($revisionId);

      }else if(!empty($processData['data'])){

        $totalSaved = 0;
        $step  = $processData['step'];
        foreach($processData['data'] as $indx => $row) {
          $saved = $this->processDataRow($row, $processData['headers'], $indx);
          if($saved) {
            $totalSaved++;
          }
          $step++;
        }
        $this->_helperProcess->updateLockFile($revisionId, ['step' => $step]);

      }else{
        $errorMessage = 'Unknown process data revision ID: ' . $revisionId;
        return [[
          'error' => $errorMessage
        ]];
      }

      /* start re-index */
      if($step == $processData['total'] && empty($processData['reindex']) ) {
        $reindexing = self::REINDEX_RUN;
        $this->_helperIndexer->reindexAll();
        $this->_helperProcess->updateLockFile($revisionId, ['reindex' => true]);
      }


      return [[
        'status' => 'ok',
        'revisionId' => $revisionId,
        'total_saved' => !empty($step) ? $step : $processData['total'],
        'total' => $processData['total'],
        'reindex' => $reindexing,
        'not_found_sku' => $this->_notFoundSkus,
        'log_errors' => $this->_helperProducts->getErrors()
      ]];

    }catch(Exception $e) {
      return [[
          'error' => $errorMessage
        ]];
    }
  }

  public function save() {

    try {

      $startNewProcess = true;
      $totalSaved      = 0;
      $revisionId      = '';

      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      $products = $data['products'];
      $headers   = $this->dataFieldsToHeaders($data['headers']);

      if(count($products) <= self::PROCESS_STEP) {

        foreach($products as $indx => $row) {
          $saved = $this->processDataRow($row, $headers, $indx);
          if($saved) {
            $totalSaved++;
          }
        }

        $startNewProcess = false;
      }else{
        $revisionId = $this->_helperProcess->saveProcessData(array_merge([$headers], $products));
      }

      if(!$startNewProcess) {
        $this->_helperIndexer->reindexAll();
      }

      return [[
        'status' => 'ok',
        'type'   => $data['extra']['type'],
        'sheet_id' => $data['sheet_id'],
        'not_found_sku' => $this->_notFoundSkus,
        'revisionId' => $revisionId,
        'total_saved' => $totalSaved,
        'start_process' => $startNewProcess,
        'total' => count($products),
        'log_errors' => $this->_helperProducts->getErrors()
        //'saveRows' => $this->_saveDataRows
      ]];

    }catch(Exception $e) {
      return [[
          'error' => $errorMessage
        ]];
    }
  }

  protected function dataFieldsToHeaders($fields)
  {
    $headers = [];
    foreach($fields as $field) {
      $headers[] = $field['name'];
    }
    return $headers;
  }

  protected function processDataRow($row, $headers, $indx)
  {
    if($this->_isDebug) {
      $start_time = microtime(true);
    }

    $row = $this->_helperProducts->correctPriceFieldsData($row, $headers);

    $sku = $this->getProductSkuFromRow($row, $headers);
    if( !$sku ) {
      $this->_notFoundSkus[] = 'EMPTY(' . $this->convertIndexToLineNumber($indx) . ')';
      return;
    }
    $currentProduct = $this->_helperProducts->getProduct($sku, $row, $headers, true);
    if(!$currentProduct) {
      $this->_notFoundSkus[] = $sku;
      return;
    }

    $currentRow = $this->collectProductData($currentProduct, $headers);
    $difference  = array_diff_assoc($row, $currentRow);

    $dataToSave = $this->getNewSaveRow($currentRow, $difference);

    $sku = $this->getProductSkuFromRow($row, $headers);
    $this->_helperProducts->updateProduct($sku, $row, $headers);

    if($this->_isDebug) {
      $end_time = microtime(true);
      $this->logger-> notice('Save product time ' . ($end_time - $start_time) . ' s - SKU: ' . $sku);
    }

    return true; //flag save done
  }

  protected function getNewSaveRow($currentRow, $difference) {
    $newRow = [];
    foreach($currentRow as $index=>$val) {
      if($index == 0) {//sku
        $newRow[] = $val;
        continue;
      }

      $val = isset($difference[$index]) ? $difference[$index] : null;
      if(!isset($val) && !is_numeric($val)) {
        $newRow[] = '';
        continue;
      }
      $newRow[] = $difference[$index];
    }
    return $newRow;
  }

  protected function collectProductData($currentProduct, $fields)
  {
    $data = [];
    foreach($fields as $field) {
      if(is_array($field)) {
        $name  = $field['name'];
      }else{
        $name  = $field;
      }

      if($name == 'NOT_USE') {
          $data[] = '';
      }

      $data[] = $currentProduct->getData($name);
    }
    $data = $this->_helperProducts->correctPriceFieldsData($data, $fields);
    return $this->_helperProducts->correctStockQtyFields($data, $fields, $currentProduct, true);
  }

  protected function convertIndexToLineNumber($indx)
  {
    return $indx+1;
  }

  protected function getProductSkuFromRow($row, $headers)
  {
    $index = $this->findSkuIndex($headers);
    return !empty($row[$index]) ? $row[$index] : null;
  }

  protected function findSkuIndex($headers)
  {
    foreach( $headers as $indx => $header) {
      if($header == self::SKU_KEY) {
        return $indx;
      }
    }
  }
}
