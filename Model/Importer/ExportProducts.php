<?php

/* depricated */

namespace Develodesign\Easymanage\Model\Importer;

use \Magento\Store\Model\Store;

class ExportProducts extends \Magento\CatalogImportExport\Model\Export\Product
{

  const PER_PAGE_COUNT = 500;

  protected $_page;

  protected $_store;

  protected $_categoryIds = [];

  protected $_fakeWriter;

  public function setFakeWriter($writeClass)
  {
    $this->_fakeWriter = $writeClass;
  }

  public function getWriter()
  {
    return $this->_fakeWriter;
  }

  public function _export()
  {

    $out = [
      'headers' => [],
      'collection' => []
    ];

    $entityCollection = $this->_getEntityCollection(true);
    //$entityCollection->setOrder('entity_id', 'asc');

    $entityCollection->addStoreFilter( $this->getStore() );
    if($this->getStore()) {
      $entityCollection->setStoreId($this->getStore());
    }
    $categories = $this->getCategoriesIds();
    if($categories) {
      $entityCollection->addCategoriesFilter(['in' => $categories]);
    }

    $this->_prepareEntityCollection($entityCollection);
    $this->__paginateCollection($this->getPage(), $this->__getItemsPerPage());

    $exportData = $this->getExportData();
    $headers    = $this->_getHeaderColumns();

    /* collection */
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $collection = $objectManager->create('Magento\Framework\Data\Collection');

    $count = 0;
    $dataOut = [];
    foreach ($exportData as $key => $dataRow) {
      $dataRow = $this->_customFieldsMapping($dataRow);
      if(isset($dataRow['store_view_code']) && $dataRow['store_view_code'] == Store::DEFAULT_STORE_ID) {
        continue;
      }

      $dataOut[$count] = $dataRow;
      $count++;
    }

    return [
      'headers' => $headers,
      'data_array' => $dataOut,
      'total' => $this->getTotal(),
      'limit' => $this->__getItemsPerPage()
    ];
  }

  public function setPage($page) {
    $this->_page = $page;
  }

  protected function updateWithImportData($dataRow) {
    if(!empty($dataRow['_attribute_set'])) {
      $dataRow['attribute_set_code'] = $dataRow['_attribute_set'];
    }

    if(!empty($dataRow['_type'])) {
      $dataRow['product_type'] = $dataRow['_type'];
    }

    if(!empty($dataRow['_category'])) {
      $dataRow['categories'] = $dataRow['_category'];
    }

    if(!empty($dataRow['_product_websites'])) {
      $dataRow['product_websites'] = $dataRow['_product_websites'];
    }
    return $dataRow;
  }

  protected function getPage() {
    return $this->_page ? $this->_page : 1;
  }

  public function setStore($storeId) {
    $this->_store = $storeId;
  }

  protected function getStore() {
    return $this->_store ? $this->_store : Store::DEFAULT_STORE_ID;
  }

  public function setCategoriesIds($ids) {
    $this->_categoryIds = $ids;
  }

  protected function getCategoriesIds() {
    return $this->_categoryIds;
  }

  protected function __getItemsPerPage() {
    return self::PER_PAGE_COUNT;
  }

  protected function __paginateCollection($page, $pageSize) {
    $this->_getEntityCollection()->setPage($page, $pageSize);
  }

  protected function getTotal() {
    return $this->_getEntityCollection()->getSize();
  }
}
