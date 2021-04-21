<?php

namespace Develodesign\Easymanage\Model;

class SaveProducts implements \Develodesign\Easymanage\Api\SaveProductsInterface{

  const STEP_CREATE_REVISION = 100;

  const COUNT_TO_SAVE_STEP = 100;

  const REINDEX_RUN = 1;
  const REINDEX_COMPLETE = 2;

  protected $request;
  protected $_dir;
  protected $_io;
  protected $_helperProducts;
  protected $_helperIndexer;
  protected $logger;

  protected $_revisionId;

  protected $_notFoundSkus = [];

  protected $_newDataRows  = [];
  protected $_saveDataRows = [];
  protected $_revisionData = [];
  protected $_startNewProcess = 0;

  const REVISION_FOLDER = 'dd_easymanage_revision';
  const WORKING_FOLDER = 'dd_easymanage_process';
  const CHMOD_DIR = 0775;

  const LOCKING_FILE_NAME = 'lockdata';

  public function __construct(
    \Magento\Framework\App\Request\Http $request,
    \Magento\Framework\Filesystem\DirectoryList $dir,
    \Magento\Framework\Filesystem\Io\File $io,
    \Develodesign\Easymanage\Helper\Indexer $helperIndexer,
    \Develodesign\Easymanage\Helper\Products $helperProducts,
    \Psr\Log\LoggerInterface $logger
  ) {

    $this->request = $request;
    $this->logger = $logger;
    $this->_dir = $dir;
    $this->_io = $io;
    $this->_helperProducts = $helperProducts;
    $this->_helperIndexer = $helperIndexer;
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

      $lockData  = $this->readLockFileData($revisionId);
      if(!$lockData) {
        $errorMessage = 'Updater process cant read lockdata for revision ID: ' . $revisionId;
        $this->logger->debug();
        return [[
            'error' => $errorMessage
        ]];
      };

      $step     = $lockData['step'];
      $saveData = $this->readProcessFile($revisionId);
      $count      = 0;
      $headers    = [];
      $totalSaved = 0;
      foreach ($saveData as $row) {
        if($count == 0 && count($headers) == 0) {
          $headers = $row;
          continue;
        }
        if($count < $step) {
          $count++;
          continue;
        }
        $sku = $this->getProductSkuFromRow($row);
        $this->_helperProducts->updateProduct($sku, $row, $headers);
        $totalSaved++;
        $count++;
        if($totalSaved >= self::COUNT_TO_SAVE_STEP) {
          break;
        }
      }
      $lockData['step'] = $count;
      $this->_revisionId = $revisionId;
      $reindexing = 0;
      if($count == $lockData['total'] && empty($lockData['reindex'])) {
        //reindex data
        $reindexing = self::REINDEX_RUN;
        $lockData['reindex'] = true;
        $this->_helperIndexer->reindexAll();
        $this->updateLockFile($lockData);
      }else if($count == $lockData['total'] && !empty($lockData['reindex'])) {
        $reindexing = self::REINDEX_COMPLETE;
        $this->removeWorkingFiles($revisionId);
      }else{
        $this->updateLockFile($lockData);
      }

      return [[
        'status' => 'ok',
        'revisionId' => $revisionId,
        'total_saved' => $count,
        'total' => $lockData['total'],
        'reindex' => $reindexing,
        'not_found_sku' => $lockData['not_found_sku'],
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
      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      $revisionId = $this->createRevisionAndSaveFileData($data);
      $totalSaved = $this->saveNewData($data['headers']);

      if(!$this->_startNewProcess) {
        $this->_helperIndexer->reindexAll();
      }

      return [[
        'status' => 'ok',
        'type'   => $data['extra']['type'],
        'sheet_id' => $data['sheet_id'],
        'not_found_sku' => $this->_notFoundSkus,
        'revisionId' => $this->_revisionId,
        'total_saved' => $totalSaved ? $totalSaved : '0',
        'start_process' => $this->_startNewProcess,
        'total' => count($this->_saveDataRows),
        'log_errors' => $this->_helperProducts->getErrors()
        //'saveRows' => $this->_saveDataRows
      ]];
    }catch(Exception $e) {
      return [[
          'error' => $errorMessage
        ]];
    }
  }

  protected function saveNewData($fields) {
    if(!count($this->_saveDataRows)) {
      return '0';
    }
    if(count($this->_saveDataRows) >= self::COUNT_TO_SAVE_STEP) {
      return $this->prepareProcessData($fields);
    }
    $totalSaved = 0;

    foreach($this->_saveDataRows as $newRow) {
      $sku = $this->getProductSkuFromRow($newRow);
      $this->_helperProducts->updateProduct($sku, $newRow, $fields);
      $totalSaved++;
    }

    return $totalSaved;
  }

  protected function prepareProcessData($fields) {
    $this->_startNewProcess = 1;
    $dataFile = $this->createProcessFile($fields);
    $lockFile = $this->updateLockFile();
  }

  protected function updateLockFile($data = null) {
    $data = $data ? $data : [
      'status' => 0,
      'step' => 0,
      'not_found_sku' => $this->_notFoundSkus,
      'total' => count($this->_saveDataRows)
    ];
    $wokingFolder = $this->getWorkingFolder();
    $lockFileName = $this->getLockingFileName($this->_revisionId);
    $file = fopen($wokingFolder . DIRECTORY_SEPARATOR . $lockFileName, "w");
    $saveString = \Zend_Json::encode($data);
    fwrite($file, $saveString);
    fclose($file);
    return true;
  }

  protected function readLockFileData($revisionId) {
    $wokingFolder = $this->getWorkingFolder();
    $lockFileName = $this->getLockingFileName($revisionId);
    $fullPath = $wokingFolder . DIRECTORY_SEPARATOR . $lockFileName;
    if(!is_file($fullPath)) {
      return;
    }
    $file = fopen($fullPath, "r");
    $contents = fread($file, filesize($fullPath));
    fclose($file);
    return \Zend_Json::decode( $contents );
  }

  protected function readProcessFile($revisionId) {
    $wokingFolder = $this->getWorkingFolder();
    $filePath     = $wokingFolder . DIRECTORY_SEPARATOR . $revisionId;
    return array_map('str_getcsv', file($filePath));
  }

  protected function removeWorkingFiles($revisionId) {
    $wokingFolder = $this->getWorkingFolder();
    $filePathWorkingFile = $wokingFolder . DIRECTORY_SEPARATOR . $revisionId;
    unlink($filePathWorkingFile);
    $filePathLockFile = $wokingFolder . DIRECTORY_SEPARATOR . $this->getLockingFileName($revisionId);
    unlink($filePathLockFile);
  }

  protected function createProcessFile($fields) {
    $wokingFolder = $this->getWorkingFolder();
    $countSku = 0;
    $all = 0;
    foreach($fields as $field) {
      $headers[] = $field['name'];
      if($field['name'] == 'sku') {
        $countSku = $all;
      }
      $all++;
    }
    $csvData[] = $headers;
    foreach($this->_saveDataRows as $sku=>$row) {
      $row[$countSku]    = $sku;
      $csvData[] = $row;
    }

    $file = fopen($wokingFolder . DIRECTORY_SEPARATOR . $this->_revisionId, "w");

    foreach ($csvData as $line){
        fputcsv($file, $line);
    }
    fclose($file);

  }

  protected function createRevisionAndSaveFileData($data) {
    $foldres = $this->getFolders();
    if(empty($foldres['revision'])) {
      return $folders;//errors
    }
    $products = $data['products'];
    $fields   = $data['headers'];
    $revisionId = $this->collectRevisionAndSaveFileData($products, $fields, $foldres);

    return $revisionId;
  }

  protected function collectRevisionAndSaveFileData($products, $fields, $foldres) {
    $countSteps = 0;
    $collectionData = [];
    foreach($products as $productRow) {
      $sku = $this->getProductSkuFromRow($productRow);

      $rowPriceCorrected = $this->_helperProducts->correctPriceFieldsData($productRow, $fields);
      $rowFinal = $this->checkWithFieldsRowData($rowPriceCorrected, $fields);
      $this->_newDataRows[]  = $rowFinal; //$productRow;
      $countSteps++;

      if($countSteps == self::STEP_CREATE_REVISION) {
        $notFoundSkus = $this->processCurrentData($fields);
        $this->_notFoundSkus = array_merge($notFoundSkus, $this->_notFoundSkus);
        $countSteps = 0;
      }
    }

    if($countSteps != 0) {
      $notFoundSkus = $this->processCurrentData($fields);
      $this->_notFoundSkus = array_merge($notFoundSkus, $this->_notFoundSkus);
    }

    $this->_revisionId = $this->saveRevisionData($foldres, $fields);
    return $this->_revisionId;
  }

  protected function checkWithFieldsRowData($rowData, $fields) {
    $correctRow = [];
    foreach($fields as $index=>$field) {
      $correctRow[$index] = $rowData[$index];
    }
    return $correctRow;
  }

  protected function saveRevisionData($foldres, $fields) {
    if(!count($this->_revisionData)) {
      return;
    }
    $revisionId = $this->generateRevisionId();
    $folderRevision = $foldres['revision'];
    $csvData = [];
    $headers = [];
    foreach($fields as $field) {
      $headers[] = $field['name'];
    }
    $csvData[] = $headers;
    foreach($this->_revisionData as $row) {
      $csvData[] = $row;
    }

    $file = fopen($folderRevision . DIRECTORY_SEPARATOR . $revisionId, "w");

    foreach ($csvData as $line){
        fputcsv($file,$line);
    }
    fclose($file);

    return $revisionId;
  }

  protected function generateRevisionId() {
    return uniqid();
  }

  protected function processCurrentData($fields) {
    $notFoundSkus = [];
    foreach($this->_newDataRows as $index => $dataToSave) {
      $sku         = $this->getProductSkuFromRow($dataToSave);
      $currentProduct = $this->_helperProducts->getProduct($sku, $dataToSave, $fields);
      if(!$currentProduct) {
        $notFoundSkus[] = $sku;
        continue;
      }
      $currentRow = $this->collectProductData($currentProduct, $fields);
      $difference  = array_diff_assoc($dataToSave, $currentRow);
      if($difference && count($difference) > 0) {
        $this->_revisionData[] = $dataToSave;
        $this->_saveDataRows[$index] = $this->getNewSaveRow($this->_newDataRows[$index], $difference);
      }
    }

    return $notFoundSkus;
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
      $data[] = $currentProduct->getData($name);
    }

    return $data;
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

  protected function getProductSkuFromRow($row) {
    return $row[0];
  }

  public function getFolders() {
    $revisionFolder = $this->getRevisionFolder();
    if(!$revisionFolder) {
      return [[
        'error' => true,
        'message' => __('Error create folder in pub directory!')
      ]];
    }
    return [
      'revision' => $revisionFolder,
    ];
  }

  public function getRevisionFolder() {
    $filePath = $this->getRevisionFolderPath();
    if (!is_dir($filePath)) {
      try{
        $this->_io->mkdir($filePath, self::CHMOD_DIR);
      }catch(\Exception $e) {
        return false;
      }
    }
    return $filePath;
  }

  public function getWorkingFolder() {
    $filePath = $this->getWorkingFolderPath();
    if (!is_dir($filePath)) {
      try{
        $this->_io->mkdir($filePath, self::CHMOD_DIR);
      }catch(\Exception $e) {
        return false;
      }
    }
    return $filePath;
  }

  public function getLockingFileName($revisionId) {
    return self::LOCKING_FILE_NAME . '_' . $revisionId;
  }

  protected function getWorkingFolderPath() {
    return $this->_dir->getPath('pub') . DIRECTORY_SEPARATOR
                .self::WORKING_FOLDER;
  }

  protected function getRevisionFolderPath($timestamp = null) {
    return $this->getRevisionFolderPathBase() . ($timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d'));
  }

  protected function getRevisionFolderPathBase() {
    return $this->_dir->getPath('pub') . DIRECTORY_SEPARATOR
                .self::REVISION_FOLDER . DIRECTORY_SEPARATOR;
  }

  public function getDirectoryIterator($path){
      return new \DirectoryIterator($path);
  }
}
