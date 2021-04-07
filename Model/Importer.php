<?php

namespace Develodesign\Easymanage\Model;

use Magento\Framework\App\Filesystem\DirectoryList;

use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\Adapter as ImportAdapter;

class Importer implements \Develodesign\Easymanage\Api\ImporterInterface{

  const ENTITY = 'catalog_product';
  const BEHAVIOR = 'append';

  const VALIDATION_STRATEGY = 'validation-stop-on-errors';
  const VALIDATION_CONTINUE_ANYWAY = 'validation-skip-errors';

  const FIELD_FIELD_SEPARATOR = ',';

  const FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR = ',';


  const PRODUCT_DATA_INDEX = 'products';

  const IMPORT_FILE = 'catalog_products';

  const STATUS_BEGIN = 0;

  const STATUS_IMPORT_FILE_CREATED = 1;

  const STATUS_IMPORT_DONE = 2;

  const STATUS_STATUS_REINDEX = 3;

  const CHMOD_FOLDER = 0755;

  protected $_saveModel;

  protected $_magentoImportModel;

  protected $request;

  protected $logger;

  protected $_fileSystem;

  protected $_helperImages;

  protected $_helperIndexer;

  protected $_helperAttributes;

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
  }

  public function save() {

    try {

      $this->initImagesHelper();

      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      $this->prepareData($data);

      $this->generateUIDFile();

      $this->updateLockFile();

      $this->_helperImages->finish();

      return [[
        'status' => 'ok',
        'type'   => $data['extra']['type'],
        'sheet_id' => $data['sheet_id'],
        'revisionId' => $this->getRevisiionUID(),
        'total_saved' => 0,
        'total' => count($this->_data),
        'reindex' => 0,
        'start_process' => true,
        'not_found_sku' => [],
        'log_errors' => $this->getErrors()
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
      $this->_revisionUid = $revisionId;
      $lockData = $this->readLockFileData($this->_revisionUid);

      if($lockData['status'] == self::STATUS_BEGIN) {

        $this->createImporterFile($revisionId);
        $lockData['status']  = self::STATUS_IMPORT_FILE_CREATED;

        $this->updateLockFile($lockData);

        $this->validateImportFile();

        if(!count($this->getErrors())) {
          $logMessage = [  __('Import file created!') ];
        }else{
          $logMessage = null;
        }

        return [[
          'status' => 'ok',
          'revisionId' => $revisionId,
          'total_saved' => 0,
          'total' => $lockData['total'],
          'reindex' => 0,
          'log_message' => $logMessage,
          'not_found_sku' => [],
          'error' => (count($this->getErrors()) || count($this->_errorRows)) ? __('Invalid product data') : null,
          'log_errors' => $this->getErrors(),
          'error_rows' => $this->_errorRows
        ]];

      }

      if($lockData['status']  == self::STATUS_IMPORT_FILE_CREATED) {

        $this->_magentoImportModel->setData([
            'entity' => self::ENTITY,
            'behavior' => self::BEHAVIOR,
            'validation_strategy' => self::VALIDATION_CONTINUE_ANYWAY,
            '_import_field_separator' => self::FIELD_FIELD_SEPARATOR,
            '_import_multiple_value_separator' => self::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR

        ]);
        $this->_magentoImportModel->importSource();

        $errorAggregator = $this->_magentoImportModel
                ->getErrorAggregator();

        $errors = $errorAggregator->getAllErrors();

        if ($errors && count($errors)) {
            $this->_processErrors($this->_magentoImportModel, $errorAggregator);
        }
        $totalSaved = $this->_magentoImportModel->getProcessedEntitiesCount();

        $logMessage = [  __('Import done!'), __('Total imported items %1', $totalSaved) ];

        $lockData['status']  = self::STATUS_IMPORT_DONE;
        $this->updateLockFile($lockData);

        return [[
          'status' => 'ok',
          'revisionId' => $revisionId,
          'total_saved' => $totalSaved,
          'total' => $lockData['total'],
          'reindex' => \Develodesign\Easymanage\Model\SaveProducts::REINDEX_RUN,
          'log_message' => $logMessage,
          'not_found_sku' => [],
          'log_errors' => $this->getErrors(),
          'error_rows' => $this->_errorRows
        ]];

      }

      if($lockData['status'] == self::STATUS_IMPORT_DONE) {

        $lockData['status']  = self::STATUS_STATUS_REINDEX;
        $this->updateLockFile($lockData);

        $this->_helperIndexer->reindexImporterAll();


        return [[
          'status' => 'ok',
          'revisionId' => $revisionId,
          'total_saved' => $lockData['total'],
          'total' => $lockData['total'],
          'reindex' => \Develodesign\Easymanage\Model\SaveProducts::REINDEX_COMPLETE,
          'log_message' => null,
          'not_found_sku' => [],
          'log_errors' => $this->getErrors(),
          'error_rows' => $this->_errorRows
        ]];

      }

      //wrong status!
      return [[
        'status' => 'ok',
        'revisionId' => $revisionId,
        'total_saved' => $lockData['total'],
        'total' => $lockData['total'],
        'reindex' => \Develodesign\Easymanage\Model\SaveProducts::REINDEX_COMPLETE,
        'not_found_sku' => [],
        'errors' => []
      ]];
      //end debug!

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
            $errorMessage .= $errorCode . ' ' . __('in row(s):') . ' ' . implode(', ', $rows) . '<br>';
        }
        //$this->_totalErrors += $errorAggregator->getErrorsCount();
        $errorMessage .= __(
                    'Checked rows: %1, checked entities: %2, invalid rows: %3, total errors: %4',
                    $magentoImportModel->getProcessedRowsCount(),
                    $magentoImportModel->getProcessedEntitiesCount(),
                    $errorAggregator->getInvalidRowsCount(),
                    $errorAggregator->getErrorsCount()
                );

      $this->setError($errorMessage);

      if($this->_revisionUid) {
        $this->removeWorkingFile();
        $this->removeRevisionFile();
      }
  }

  protected function removeWorkingFile() {
    $wokingFolder = $this->getWorkingFolder();
    $lockFileName = $this->getLockingFileName($this->_revisionUid);

    unlink($wokingFolder . DIRECTORY_SEPARATOR . $lockFileName);
  }

  protected function removeRevisionFile() {
    $folder = $this->_saveModel->getRevisionFolder();
    $filePath     = $folder . DIRECTORY_SEPARATOR . $this->_revisionUid;

    unlink( $filePath );
  }

  protected function createImporterFile($revisionId) {
    $folder = $this->_saveModel->getRevisionFolder();
    $filePath     = $folder . DIRECTORY_SEPARATOR . $revisionId;
    $importFilePath = $this->_magentoImportModel->getWorkingDir() . self::IMPORT_FILE . '.csv';
    if(!is_dir(dirname($importFilePath))) {
      mkdir(dirname($importFilePath), self::CHMOD_FOLDER, true);
    }
    copy($filePath, $importFilePath);
  }

  protected function updateLockFile($data = null) {
    $data = $data ? $data : [
      'status' => 0,
      'step' => 0,
      'total' => count($this->_data)
    ];
    $wokingFolder = $this->getWorkingFolder();
    $lockFileName = $this->getLockingFileName($this->_revisionUid);
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

  protected function getWorkingFolder() {
    return $this->_saveModel->getWorkingFolder();
  }

  protected function getLockingFileName($revisionId) {
    return $this->_saveModel->getLockingFileName($revisionId);
  }

  protected function generateUIDFile() {
    $uniqId = $this->getRevisiionUID();
    $folder = $this->_saveModel->getRevisionFolder();

    $file = fopen($folder . DIRECTORY_SEPARATOR . $uniqId, "w");

    fputcsv($file, $this->_headers);

    foreach ($this->_data as $line){
        fputcsv($file, $line);
    }

    fclose($file);
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
      $headers[] = $_header['name'];
    }
    $this->_headers = $headers;
  }

  protected function getRevisiionUID() {
    if($this->_revisionUid) {
      return $this->_revisionUid;
    }
    $this->_revisionUid = uniqid();
    return $this->_revisionUid;
  }

  protected function setError($error) {
    $this->_errors[] = $error;
  }

  protected function getErrors() {
    return $this->_errors;
  }
}
