<?php

namespace Develodesign\Easymanage\Model;

class Importer implements \Develodesign\Easymanage\Api\ImporterInterface{

  const PRODUCT_DATA_INDEX = 'products';

  const IMPORT_FILE = 'catalog_produdcts.csv';

  protected $_saveModel;

  protected $request;

  protected $logger;

  protected $_headers;

  protected $_data;

  protected $_errors = [];

  protected $_revisionUid = null;

  const COUNT_SIMPLE_VISIBLE_SAVE = 2;

  public function __construct(
    \Magento\Framework\App\Request\Http $request,
    \Develodesign\Easymanage\Model\SaveProducts $saveModel,
    \Psr\Log\LoggerInterface $logger
  ) {

    $this->request    = $request;
    $this->logger     = $logger;
    $this->_saveModel = $saveModel;
  }

  public function save() {

    try {
      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      $this->prepareData($data);

      $this->generateUIDFile();

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
        'errors' => $this->getErrors()
      ]];
    }catch(\Exception $e) {

      $message = 'Error saving products data: ' . $e->getMessage();
      $this->logger->debug( $message );

      return [[
          'error' => true,
          'message' => $message
      ]];
    }
  }

  public function process() {

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
        $tmpData[$indexField] = $fieldValue;
      }
      $_data[] = $tmpData;
    }
    $this->_data = $_data;
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
