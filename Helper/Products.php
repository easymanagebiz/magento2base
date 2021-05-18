<?php

namespace Develodesign\Easymanage\Helper;

use \Magento\Store\Model\Store;

class Products extends \Magento\Framework\App\Helper\AbstractHelper
{

  protected $_collection;
  protected $resource;
  protected $_stockRegistry;

  protected $_productResource;
  protected $_productRepository;

  protected $_attributeHelper;

  protected $_specPriceStorage;

  protected $storeRepository;

  protected $_isInventoryAdded = false;

  protected $_errors = [];
  protected $_errorsAttrCode = [];

  protected $notAttribute = [
    'qty', 'in_stock'
  ];

  protected $specialProcessData = [
    'qty', 'price', 'special_price', 'status', 'tax_class_id'
  ];

  protected $notUpdateFields = [
    'sku'
  ];

  protected $_priceFields = [
    'price', 'special_price'
  ];

  protected $_stockFields = [
    'qty', 'in_stock'
  ];

  public function __construct(
    \Magento\Framework\App\Helper\Context $context,

    \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
    \Magento\Catalog\Model\ResourceModel\Product $productResource,
    \Magento\Framework\App\ResourceConnection $resource,
    \Magento\Framework\ObjectManagerInterface $objectManager,
    \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,

    \Develodesign\Easymanage\Helper\Attributes $attributeHelper,

    \Magento\Store\Api\StoreRepositoryInterface $storeRepository

  ) {
    $this->resource = $resource;
    $this->_stockRegistry = $stockRegistry;
    if(class_exists('\Magento\Catalog\Model\Product\Price\SpecialPriceStorage')) {
      $this->_specPriceStorage = $objectManager->get('\Magento\Catalog\Model\Product\Price\SpecialPriceStorage');
    }

    $this->storeRepository    = $storeRepository;
    $this->_productRepository = $productRepository;
    $this->_productResource = $productResource;
    $this->_attributeHelper = $attributeHelper;
    parent::__construct($context);
  }

  public function getProduct($sku, $row, $fields, $useStore = false)
  {
    try  {

      if(!$useStore) {
        return  $this->_productRepository->get($sku);
      }
      $storeId = $this->getStoreIdFromCode($row, $fields);
      if($storeId && $storeId != Store::DEFAULT_STORE_ID) {
        return $this->_productRepository->get($sku, false, $storeId);
      }

      return $this->_productRepository->get($sku);

    }catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        return null; //no product found
    }
  }

  public function updateProduct($sku, $row, $fields) {

    $product = $this->getProduct($sku, $row, $fields);
    if(!$product) {
      return;
    }

    $storeId = $this->getStoreIdFromCode($row, $fields);

    if($storeId) {
      $product->setStoreId($storeId);
    }

    $numField = 0;
    foreach($fields as $field) {
      if(is_array($field)) {
        $name  = $field['name'];
      }else{
        $name  = $field;
      }

      if( $name == 'store_code' || $name == 'NOT_USE' || $name == 'line_number') {
        $numField++;
        continue;
      }

      $value = isset($row[$numField]) ? $row[$numField] : null;

      if((empty($value) && !is_numeric($value)) || $name == 'sku') {
        $numField++;
        continue;
      }

      if(!in_array($name, $this->notAttribute) && !in_array($name, $this->notUpdateFields)) {


        if($name == 'special_price' && 0 == intval($value)) {

          //fix for some magento versions bug 0 in cart
          //https://github.com/magento/magento2/issues/18268
          //https://github.com/magento/magento2/pull/18631
          if($this->_specPriceStorage) {
            $this->specialProcessSaveData($product, $name, $value, $sku, $storeId);
            continue;
          }
          $value = null;
        }

        $attribute = $this->_attributeHelper->getAttributeByCode( $name );

        if(!$attribute || !$attribute->getId()) {
          if(!in_array($name, $this->_errorsAttrCode)) {
            $this->addError(__('Attribute with code <strong>"%1"</strong> not found', $name));
            $this->_errorsAttrCode[] = $name;
          }
          continue;
        }

        if($this->_attributeHelper->getIsOptionAttribute($name)) {
          $value = $this->_attributeHelper->getOptionIdsFromLabels($name, $value);
        }
        $product->setData($name, $value);
        $this->_productResource->saveAttribute($product, $name);
      }
      $numField++;
    }

    $numField = 0;
    foreach($fields as $field) {
      if(is_array($field)) {
        $name  = $field['name'];
      }else{
        $name  = $field;
      }
      $value = isset($row[$numField]) ? $row[$numField] : null;

      if((empty($value) && !is_numeric($value)) || $name == 'sku') {
        $numField++;
        continue;
      }

      if(in_array($name, $this->notAttribute)) {
        $this->specialProcessSaveData($product, $name, $value, $sku, $storeId);
      }
      $numField++;
    }

    return true;
  }

  protected function addError($errText) {
    $this->_errors[] = $errText;
  }

  public function getErrors() {
    return $this->_errors;
  }

  public function specialProcessSaveData($product, $name, $value, $sku, $storeId) {

    switch($name) {
      case 'special_price':
        $updateArr = $this->_specPriceStorage->get([$sku]);
        if(empty($updateArr) || empty($updateArr[0])) {
          return;
        }

        foreach($updateArr as $updateObj) {
          if($updateObj->getSku() != $sku) {
            continue;
          }
          if($storeId && $storeId != $updateObj->getStoreId()) {
            continue;
          }
          $updateObj->setPrice( $value );
          $this->_specPriceStorage->delete([$updateObj]);
        }

      break;
      case 'qty':
        $stockItem = $this->_stockRegistry->getStockItemBySku($sku);
        $stockItem->setQty($value);
        $this->_stockRegistry->updateStockItemBySku($sku, $stockItem);
      break;
      case 'in_stock':
        $stockItem = $this->_stockRegistry->getStockItemBySku($sku);
        $stockItem->setIsInStock($value);
        $this->_stockRegistry->updateStockItemBySku($sku, $stockItem);
      break;
    }

  }

  public function correctPriceFieldsData($row, $fields) {
    $indexes = $this->getPriceFieldIndexes($fields);
    foreach($indexes as $name=>$index) {
      $row[$index] = $this->specialProcessData($name, $row[$index]);
    }

    return $row;
  }

  public function correctStockQtyFields($row, $fields, $product = null, $addValues = false)
  {
    $indexes = $this->getStockFieldIndexes($fields);

    foreach($indexes as $name=>$index) {

      if($addValues && $product) {
        $row[$index] = $this->getStockValuesProduct($name, $product);
      }

      $row[$index] = $this->specialProcessData($name, $row[$index]);
    }

    return $row;
  }

  public function getStockValuesProduct($name, $product)
  {
    switch ($name) {

      case 'in_stock':
          $productStock = $this->_stockRegistry->getStockItem($product->getId());
          return $productStock->getIsInStock();
        break;

      case 'qty':
          $productStock = $this->_stockRegistry->getStockItem($product->getId());
          return $productStock->getQty();
        break;

    }
  }

  public function getStoreIdFromCode($row, $fields)
  {
    $storeCode = null;
    foreach($fields as $rowIndex => $field) {

      $name  = '';

      if(is_array($field)) {
        $name  = $field['name'];
      }

      if($name == 'store_code') {
        $storeCode = $row[ $rowIndex ];
        break;
      }
    }

    if(!$storeCode) {
      return;
    }

    try {
        $store = $this->storeRepository->get($storeCode);
        return $store->getId() != null && $store->getId() != Store::DEFAULT_STORE_ID ? $store->getId() : null; // this is the store ID
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        // store not found
    }
  }

  protected function getPriceFieldIndexes($fields){
    $indexes = [];
    foreach($fields as $index => $name) {
      if(is_array($name)) {
        $name = $name['name'];
      }
      if(in_array($name, $this->_priceFields)) {
        $indexes[$name] = $index;
      }
    }

    return $indexes;
  }

  protected function getStockFieldIndexes($fields) {
    $indexes = [];
    foreach($fields as $index => $name) {
      if(is_array($name)) {
        $name = $name['name'];
      }
      if(in_array($name, $this->_stockFields)) {
        $indexes[$name] = $index;
      }
    }

    return $indexes;
  }

  public function addFieldsToSelect($headers) {
    foreach($headers as $header) {
      if(in_array($header['name'], $this->notAttribute)) {
        $this->addCustomDataToCollection($header['name']);
        continue;
      }

      $attribute = $this->_attributeHelper->getAttributeByCode( $header['name'] );
      if(!$attribute || !$attribute->getId()) {
        continue;
      }
      $this->_collection->addAttributeToSelect($header['name']);
    }

    return $this->_collection;
  }

  protected function addCustomDataToCollection($name) {
    switch($name) {
      case 'qty':
        if($this->_isInventoryAdded) {
          return;
        }
        $this->_collection
          ->joinTable($this->resource->getTableName('cataloginventory_stock_item'),
          'product_id=entity_id',
          [
              'qty' => 'qty',
              'in_stock' => 'is_in_stock'
          ]
        );
      $this->_isInventoryAdded = true;
      break;

      case 'in_stock':
        if($this->_isInventoryAdded) {
          return;
        }
        $this->_collection
          ->joinTable($this->resource->getTableName('cataloginventory_stock_item'),
          'product_id=entity_id',
          [
              'qty' => 'qty',
              'in_stock' => 'is_in_stock'
          ]
        );

      $this->_isInventoryAdded = true;
      break;
    }
  }

  public function collectData($headers, $storeId = '') {
    if(!$this->_collection->getSize()) {
      return [];
    }

    $storeCode = $this->getStoreCode($storeId);

    $output = [];
    foreach($this->_collection as $productObj) {
      $row = [];

      foreach($headers as $header) {

        if($header['name'] == 'store_code') {
          $row[] = $storeCode;
          continue;
        }

        $value = $productObj->getData($header['name']);
        if(in_array($header['name'], $this->specialProcessData)) {
          $value = $this->specialProcessData($header['name'], $value);
        }
        if($this->_attributeHelper->getIsOptionAttribute($header['name']) && $header['name'] != 'visibility') {
          $row[] = $this->_attributeHelper->getOptionValues($header['name'], $value);
          continue;
        }
        $row[] = preg_replace( "/\r|\n/", "", $value);
      }

      $output[] = $row;
    }

    return $output;
  }

  protected function getStoreCode($storeId)
  {
    $storeCode = '';
    try {
        $store = $this->storeRepository->getById($storeId);
        $storeCode = $store->getCode(); // this is the store ID
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        // store not found
    }

    return $storeCode;
  }

  protected function specialProcessData($name, $value) {
    switch($name) {
      case 'status':
        return intval($value) > 0 ? 1 : 0;
      break;
      case 'in_stock':
        return intval($value) > 0 ? 1 : 0;
      break;
      case 'tax_class_id':
        return intval($value) > 0 ? intval($value) : 0;
      break;
      case 'qty':
        return intval($value);
      break;

      case 'price':
        return number_format( $value, 2, "." ,"" ) ;
      break;

      case 'special_price':
        $value = $value ? $value : 0;
        return number_format( $value, 2, "." ,"" ) ;
      break;

    }
  }


  public function setCollection($collection) {
    $this->_collection = $collection;
    return $this;
  }

  public function getCollection() {
    return $this->_collection;
  }
}
