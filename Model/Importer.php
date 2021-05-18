<?php

namespace Develodesign\Easymanage\Model;

use Magento\Framework\App\Filesystem\DirectoryList;

use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\Adapter as ImportAdapter;

class Importer implements \Develodesign\Easymanage\Api\ImporterInterface{

  const ENTITY = 'catalog_product';
  const BEHAVIOR = 'append';

  const READY_FOR_IMPORT = 'file_ready';

  const VALIDATION_STRATEGY = 'validation-stop-on-errors';
  const VALIDATION_CONTINUE_ANYWAY = 'validation-skip-errors';

  const FIELD_FIELD_SEPARATOR = ',';

  const FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR = ',';


  const PRODUCT_DATA_INDEX = 'products';

  const IMPORT_FILE = 'catalog_products';

  const STATUS_BEGIN = 0;

  //const STATUS_IMPORT_FILE_CREATED = 1;

  const STATUS_IMPORT_DONE = 1;

  const STATUS_STATUS_REINDEX = 2;

  const PROCESS_STEP = 500;

  protected $_saveModel;

  protected $_magentoImportModel;

  protected $request;

  protected $logger;

  protected $_fileSystem;

  protected $_helperImages;

  protected $_helperIndexer;

  protected $_helperAttributes;
  protected $_helperProcess;

  protected $_headers;

  protected $_data;

  protected $_errors = [];

  protected $_errorRows = [];

  protected $_revisionUid = null;

  protected $_exportProducts;

  protected $_helperProducts;

  protected $_defualtExportHeaders = [
    ['name' => 'sku'],
    ['name' => 'name'],
    ['name' => 'visibility']
  ];

  protected $_step;

  protected $_isDebug = false;

  protected $_loadedCSV = null;

  public function __construct(
    \Magento\Framework\App\Request\Http $request,
    \Develodesign\Easymanage\Model\SaveProducts $saveModel,
    \Magento\ImportExport\Model\Import $magentoImportModel,
    \Magento\Framework\Filesystem $fileSystem,
    \Develodesign\Easymanage\Helper\Images $helperImages,
    \Develodesign\Easymanage\Helper\Indexer $helperIndexer,
    \Develodesign\Easymanage\Helper\Attributes $helperAttributes,
    \Develodesign\Easymanage\Model\Importer\ExportProducts $exportProducts,
    \Develodesign\Easymanage\Helper\Products $helperProducts,
    \Develodesign\Easymanage\Helper\Process $helperProcess,
    \Psr\Log\LoggerInterface $logger
  ) {

    $this->request    = $request;
    $this->logger     = $logger;
    $this->_saveModel = $saveModel;
    $this->_fileSystem   = $fileSystem;
    $this->_helperImages  = $helperImages;
    $this->_helperIndexer = $helperIndexer;
    $this->_helperAttributes = $helperAttributes;

    $this->_magentoImportModel = $magentoImportModel;

    $this->_exportProducts = $exportProducts;
    $this->_helperProducts = $helperProducts;
    $this->_helperProcess = $helperProcess;
  }

  public function save() {

    try {

      $logMessage   = null;
      $startProcess = true;
      $complete     = false;
      $this->initImagesHelper();

      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      $this->prepareData($data);

      //$this->generateUIDFile();

      //$this->updateLockFile();

      $this->_helperImages->finish();
      $revisionId = $this->_helperProcess->saveProcessData(array_merge([$this->_headers], $this->_data));

      if(count($this->_data) < self::PROCESS_STEP) {
        $processData = $this->_helperProcess->getProcessData( $revisionId, self::PROCESS_STEP );

        $csvArray = array_merge([$processData['headers']], $processData['data']);
        $errors = $this->createImportData($csvArray);

        if($errors){
          $this->cleanImportFile();
        }

        $this->runImport(true);
        $logMessage   = __('Completed import process for %1 products', count($processData['data']));
        $this->_helperIndexer->reindexAll();
        $complete  = true;


        $startProcess = false;
      }

      return [[
        'status' => 'ok',
        'type'   => $data['extra']['type'],
        'sheet_id' => $data['sheet_id'],
        'revisionId' => $revisionId,
        'total_saved' => $complete ? count($this->_data) : 0,
        'total' => count($this->_data),
        'reindex' => 0,
        'start_process' => $startProcess,
        'not_found_sku' => [],
        'log_errors' => $this->getErrors(),
        'error_rows' => $this->_errorRows,
        'log_message' => !empty($logMessage) ? [$logMessage] : null
      ]];
    }catch(\Exception $e) {

      $message = 'Error saving products data: ' . $e->getMessage();
      $this->logger->debug( $message );

      return [[
          'error' => $message
      ]];
    }
  }

  public function process() {

    try {
      $logMessage   = '';
      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      $revisionId   = !empty($data['revison_id']) ? $data['revison_id'] : null;

      if(!$revisionId) {
        $message = 'Updater process revision ID not found: ' . $revisionId;
        $this->logger->debug($message);
        return [[
            'error' => $message
        ]];
      }

      $processData = $this->_helperProcess->getProcessData( $revisionId, self::PROCESS_STEP );
      $step = !empty($processData['step']) ? $processData['step'] : 0;

      $this->currentStep($step);
      $reindexing = 0;

      if(empty($processData['data']) && $processData['total'] <= $step && empty($processData['reindex'])) { //reindex required

        $logMessage   = __('Reindexing');
        $reindexing = \Develodesign\Easymanage\Model\SaveProducts::REINDEX_RUN;
        $this->_helperIndexer->reindexAll();
        $this->_helperProcess->updateLockFile($revisionId, ['reindex' => true]);

      }else if(!empty($processData['reindex'])) {

        $logMessage   = __('Done');
        $reindexing = \Develodesign\Easymanage\Model\SaveProducts::REINDEX_COMPLETE;
        $this->_helperProcess->removeWorkingFiles($revisionId);

      }else if(!empty($processData['data']) && empty($processData['status'])){
        if($this->_isDebug) {
          $start_time = microtime(true);
        }
        $step  += count($processData['data']);

        $csvArray = array_merge([$processData['headers']], $processData['data']);
        $errors = $this->createImportData($csvArray);
        $lockDataUpdate = [
          'step' => $step
        ];
        if($errors){
          $this->cleanImportFile();
        }
        $logMessage   = __('File created %1-%2', $processData['step'], $step);

        $lockDataUpdate['status'] = self::READY_FOR_IMPORT;
        $this->_helperProcess->updateLockFile($revisionId, $lockDataUpdate);

        if($this->_isDebug) {
          $end_time = microtime(true);
          $this->logger-> notice($logMessage . ' complete time ' . ($end_time - $start_time));
        }

      }else if(!empty($processData['data']) && $processData['status'] == self::READY_FOR_IMPORT){
        if($this->_isDebug) {
          $start_time = microtime(true);
        }
        $this->runImport();

        $totalSaved = $this->_magentoImportModel->getProcessedEntitiesCount();
        $logMessage   = __('Import complete %1-%2', ($processData['step'] - self::PROCESS_STEP), $processData['step']);

        $this->_helperProcess->updateLockFile($revisionId, [
          'status' => ''
        ]);
        if($this->_isDebug) {
          $end_time = microtime(true);
          $this->logger-> notice($logMessage . ' complete time ' . ($end_time - $start_time));
        }

      }else{
        $errorMessage = 'Unknown process data revision ID: ' . $revisionId;
        return [[
          'error' => $errorMessage
        ]];
      }

      /* start re-index */
      if($step >= $processData['total'] && empty($processData['reindex'])) {
        $reindexing = \Develodesign\Easymanage\Model\SaveProducts::REINDEX_RUN;
        $this->_helperIndexer->reindexAll();
        $this->_helperProcess->updateLockFile($revisionId, ['reindex' => true]);
      }


      return [[
        'status'        => 'ok',
        'revisionId'    => $revisionId,
        'total_saved'   => !empty($step) ? $step : $processData['total'],
        'total'         => $processData['total'],
        'reindex'       => $reindexing,
        'not_found_sku' => [],
        'log_errors'  => $this->getErrors(),
        'log_message' => !is_array($logMessage) ? [ $logMessage ] : $logMessage,
        'error_rows'  => $this->_errorRows
      ]];

    } catch(\Exception $e) {

      $message = 'Error saving products data: ' . $e->getMessage();
      $this->logger->debug( $message );

      return [[
          'error' => $message
      ]];
    }

  }

  public function fetch() {

    $postValues = $this->request->getContent();

    $postValuesArr = $postValues ? \Zend_Json::decode($postValues) : [];
    $pagination = isset($postValuesArr['paginate']) ? $postValuesArr['paginate'] : [];

    $storeId = $this->getStoreFilter($postValuesArr);

    if($storeId) {
      $this->_exportProducts->setStore($storeId);
    }

    $categories = $this->getCategoryIdsFilter($postValuesArr);
    if($categories) {
      $this->_exportProducts->setCategoriesIds($categories);
    }

    if(empty($pagination['page'])) {
      $page = 1;
    }else{
      $page = $pagination['page'];
      $page++;
    }

    $this->_exportProducts->setPage($page);
    $data = $this->_exportProducts->_export();
    $dataProducts = $this->_helperProducts
      ->setCollection($data['collection'])
      ->collectData(isset($postValuesArr['headers']) ? $postValuesArr['headers'] : $this->_defualtExportHeaders);

      return [
        'data'=> [
          'postValues' => $postValuesArr,
          'totalCount' => $data['total'],
          'dataProducts' => $dataProducts,

          'paginate' => [
            'count' => $page * $data['limit'],
            'limit' => $data['limit'],
            'all'   => $data['total']
          ]
        ]
      ];
  }

  protected function runImport($notValidate = false)
  {
    if($this->_loadedCSV && count($this->_loadedCSV) == 1) {
      return;
    }
    $this->_magentoImportModel->setData([
        'entity' => self::ENTITY,
        'behavior' => self::BEHAVIOR,
        'validation_strategy' => self::VALIDATION_CONTINUE_ANYWAY,
        '_import_field_separator' => self::FIELD_FIELD_SEPARATOR,
        '_import_multiple_value_separator' => self::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR

    ]);
    $this->_magentoImportModel->importSource();

    if(!$notValidate) {

      $errorAggregator = $this->_magentoImportModel
              ->getErrorAggregator();

      $errors = $errorAggregator->getAllErrors();

      if ($errors && count($errors)) {
          $this->_processErrors($this->_magentoImportModel, $errorAggregator);
      }

    }

    if($this->_isDebug) {
      $this->logger-> notice( 'Import done' );
    }
  }

  protected function currentStep($step)
  {
    $this->_step = $step;
  }

  protected function getCategoryIdsFilter($postValuesArr) {
    $categoriesFilterOld = !empty($postValuesArr['category']) ? $postValuesArr['category'] : null;
    if(!$categoriesFilterOld) {
      $filterParams = ['default filter'];

      if(!empty($postValuesArr['params'])) {
        $str = parse_str($postValuesArr['params'], $filterParams);
      }
      $categories = !empty($filterParams['from_categories']) ? $filterParams['from_categories'] : null;
    }else{
      $categories = $categoriesFilterOld;
    }
    if(!$categories || (count($categories) == 1 && $categories[0] == '')) {
      return;
    }
    return $categories;
  }

  protected function getStoreFilter($postValuesArr) {
    $storeFilterOld = !empty($postValuesArr['store']) ? $postValuesArr['store'] : null;
    if(!$storeFilterOld) {
      $filterParams = ['default filter'];

      if(!empty($postValuesArr['params'])) {
        $str = parse_str($postValuesArr['params'], $filterParams);
      }
      $store = !empty($filterParams['from_stores']) ? $filterParams['from_stores'] : null;
    }else{
      $store = $storeFilterOld;
    }
    if(!$store) {
      return;
    }
    return $store;
  }

  protected function createImportData($csvArray, $noValidation = false)
  {
    $this->createDataFile($csvArray);
    if(!$noValidation) {
      return $this->validateImportFile();
    }
  }

  protected function cleanImportFile()
  {
    $csvArray  = $this->getLoadedCsvArray();
    $newArray  = [];
    foreach($csvArray as $count => $row) {
      if($count == 0) {
        $newArray[] = $row;
        continue; //header
      }

      $realRowNum = end($row);
      if(in_array($realRowNum, $this->_errorRows)) {
        continue;
      }
      $newArray[] = $row;
    }

    $this->_loadedCSV = $newArray;
    $this->createImportData($newArray, true);
  }

  protected function getImportFilePath()
  {
    return $importFilePath = $this->_magentoImportModel->getWorkingDir() . self::IMPORT_FILE . '.csv';
  }

  protected function createDataFile($csvArray)
  {
    $importFilePath = $this->getImportFilePath();
    if(!is_dir(dirname($importFilePath))) {
      mkdir(dirname($importFilePath), \Develodesign\Easymanage\Helper\Process::CHMOD_FOLDER, true);
    }

    $file = fopen($importFilePath, "w");

    foreach ($csvArray as $line){
        fputcsv($file, $line);
    }
    fclose($file);
  }

  protected function validateImportFile() {

    $this->_magentoImportModel->setData([
                'entity' => self::ENTITY,
                'behavior' => self::BEHAVIOR,
                'validation_strategy' => self::VALIDATION_CONTINUE_ANYWAY,
                '_import_field_separator' => self::FIELD_FIELD_SEPARATOR,
                '_import_multiple_value_separator' => self::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR

    ]);

    $source = ImportAdapter::findAdapterFor(
        $this->_magentoImportModel->getWorkingDir() . self::IMPORT_FILE . '.csv',
        $this->_fileSystem->getDirectoryWrite(DirectoryList::ROOT),
            self::FIELD_FIELD_SEPARATOR
    );

    $this->_magentoImportModel->validateSource($source);

    $rows = $this->_magentoImportModel
              ->getProcessedRowsCount();


    $errorAggregator = $this->_magentoImportModel
            ->getErrorAggregator();


    $errors = $errorAggregator->getAllErrors();

    if($errors && count($errors)) {
        return  $this->_processErrors($this->_magentoImportModel, $errorAggregator);
    }
  }

  protected function _processErrors($magentoImportModel, $errorAggregator)
  {
        $errorMessage = '';
        $errors = $errorAggregator->getRowsGroupedByErrorCode([], [AbstractEntity::ERROR_CODE_SYSTEM_EXCEPTION]);
        foreach ($errors as $errorCode => $rows) {
            $this->_errorRows = array_merge($this->_errorRows, $rows);

            foreach($rows as $index => $row) {
              $realNumber   = $this->getRealRowNumber( $row );
              $rows[$index] = is_numeric($realNumber) ? $realNumber + 1 : $realNumber;
            }

            $errorMessage .= $errorCode . ' ' . __('in row(s):') . ' ' . implode(', ', $rows) . '<br>';
        }

        $this->updateErrorsRowsNumbers();
        //$this->_totalErrors += $errorAggregator->getErrorsCount();
        $errorMessage .= __(
                    'Checked rows: %1, checked entities: %2, invalid rows: %3, total errors: %4',
                    $magentoImportModel->getProcessedRowsCount(),
                    $magentoImportModel->getProcessedEntitiesCount(),
                    $errorAggregator->getInvalidRowsCount(),
                    $errorAggregator->getErrorsCount()
                );

      $this->setError($errorMessage);
      return $errorMessage;
  }

  protected function updateErrorsRowsNumbers()
  {
    foreach($this->_errorRows as $key => $rowNum){
      $this->_errorRows[$key] = $this->getRealRowNumber( $rowNum );
    }
  }

  protected function getRealRowNumber($rowNum)
  {
    $loadedCSV = $this->getLoadedCsvArray();
    $errorRow  = !empty($loadedCSV[$rowNum]) ? $loadedCSV[$rowNum] : null;

    return $errorRow ? end($errorRow) : $rowNum;
  }

  protected function getLoadedCsvArray()
  {
    if($this->_loadedCSV) {
      return $this->_loadedCSV;
    }

    $importFile = $this->getImportFilePath();
    $this->_loadedCSV = array_map('str_getcsv', file($importFile));

    return $this->_loadedCSV;
  }

  protected function prepareData($data) {
    $this->prepareHeaders($data);
    $dataProducts = !empty($data[self::PRODUCT_DATA_INDEX]) ? $data[self::PRODUCT_DATA_INDEX] : [];
    if(!is_array($dataProducts) || !count($dataProducts)) {
      $this->_data = [];
      return;
    }
    $_data = [];
    foreach($dataProducts as $row => $rowVal) {
      $tmpData = [];
      foreach($rowVal as $indexField => $fieldValue) {
        $fieldValue = $this->processFiledValue($indexField, $fieldValue);
        if(empty($this->_headers[$indexField])) {
          continue;
        }
        $tmpData[$indexField] = $fieldValue;
      }
      $_data[] = $tmpData;
    }
    $this->_data = $_data;

    $this->_helperImages->fetchImages();

    $errorImages = $this->_helperImages->getErrorImages();

    if(count($errorImages)) {
      $this->_data = $this->removeErrorImages($this->_data, $errorImages);
    }
  }

  protected function removeErrorImages($outPutMainArr, $errorImages)
  {
    $newOutput = [];
    foreach($outPutMainArr as $rowOutput){

      $newRow = [];
      foreach($rowOutput as $ceil) {

        foreach($errorImages as $key=>$errorImage) {

          if(strstr($ceil, $errorImage) && strstr($ceil, ',')) { //additional images
            $newCeilArr = [];
            $arrToCheck = explode(',', $ceil);
            foreach($arrToCheck as $rowImage) {
              if($rowImage == $errorImage) {
                continue;
              }
              $newCeilArr[] = $rowImage;
            }
            if(count($newCeilArr)) {
              $ceil = implode(',', $newCeilArr);
            }else{
              $ceil = '';
            }

          } elseif(strstr($ceil, $errorImage)) {
            $ceil = str_replace($errorImage, '', $ceil);
          }

        }

        $newRow[] = $ceil;
      }
      $newOutput[] = $newRow;
    }

    return $newOutput;
  }

  protected function processFiledValue($indexField, $fieldValue) {
    if(empty( $this->_headers[$indexField] )) {
      return;
    }

    $headerKey = $this->_headers[$indexField];

    switch($headerKey) {

      case 'base_image':
        return $this->_helperImages->setImage($fieldValue);
      break;

      case 'small_image':
        return $this->_helperImages->setImage($fieldValue);
      break;

      case 'thumbnail_image':
        return $this->_helperImages->setImage($fieldValue);
      break;

      case 'additional_images':
        return $this->setAdditonlaImages($fieldValue);
      break;

      case 'additional_attributes':
        $this->addNonExistingOptions( $fieldValue );
        return $fieldValue;
      break;

      default:
        return $fieldValue;
      break;
    }
  }

  protected function addNonExistingOptions($values) {
    if(!$values) {
      return;
    }
    $valuesArr = explode(',', $values);
    if(!count( $valuesArr )) {
      return;
    }

    foreach($valuesArr as $_value) {
      list($attrCode, $attrValue) = explode('=', $_value);
      $attrValue = trim($attrValue);
      $attrCode  = trim($attrCode);

      if($this->_helperAttributes->getIsOptionAttribute($attrCode)) {
        $this->_helperAttributes->getOptionFromLabel($attrCode, $attrValue);
      }
    }
  }

  protected function setAdditonlaImages($images) {
    if($images == '') {
      return '';
    }
    $outImages = [];
    $imagesArr = explode(',', $images);
    foreach($imagesArr as $imgString) {
      $imgString = trim($imgString);
      $_image = $this->_helperImages->setImage($imgString);
      if($_image) {
        $outImages[] = $_image;
      }
    }

    return implode(',', $outImages);
  }

  protected function initImagesHelper() {
    $folder = uniqid();
    $this->_helperImages->init($folder);
  }

  protected function prepareHeaders($data) {
    $headers = [];
    foreach($data['headers'] as $_header) {
      if(is_array($_header)) {
        $headers[] = $_header['name'];
      }else{
        $headers[] = $_header;
      }
    }
    $this->_headers = $headers;
  }

  protected function setError($error) {
    $this->_errors[] = $error;
  }

  protected function getErrors() {
    return $this->_errors;
  }
}
