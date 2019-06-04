<?php

namespace Develodesign\Easymanage\Helper;

class Products extends \Magento\Framework\App\Helper\AbstractHelper
{

  protected $_collection;
  protected $resource;
  protected $_stockRegistry;
  protected $_productModel;
  protected $_productResource;
  protected $_productRepository;

  protected $_specPriceStorage;

  protected $notAttribute = [
    'qty', 'in_stock'
  ];

  protected $specialProcessData = [
    'qty', 'price', 'special_price', 'status'
  ];

  protected $notUpdateFields = [
    'sku'
  ];

  protected $_priceFields = [
    'price', 'special_price'
  ];

  public function __construct(
    \Magento\Framework\App\Helper\Context $context,
    //\Magento\Catalog\Model\ProductFactory $productModel,
    \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
    \Magento\Catalog\Model\ResourceModel\Product $productResource,
    \Magento\Framework\App\ResourceConnection $resource,
    \Magento\Framework\ObjectManagerInterface $objectManager,
    \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
    //\Magento\Catalog\Model\Product\Price\SpecialPriceStorage $specPriceStorage

  ) {
    $this->resource = $resource;
    $this->_stockRegistry = $stockRegistry;
    if(class_exists('\Magento\Catalog\Model\Product\Price\SpecialPriceStorage')) {
      $this->_specPriceStorage = $objectManager->get('\Magento\Catalog\Model\Product\Price\SpecialPriceStorage');
    }
    //$this->_productModel = $productModel;
    $this->_productRepository = $productRepository;
    $this->_productResource = $productResource;
    parent::__construct($context);
  }

  public function updateProduct($sku, $row, $fields) {
    $product = $this->_productRepository->get($sku);
    //$productModel = $this->_productModel->create();
    //$product = $productModel->load($productModel->getIdBySku($sku));

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

      if(!in_array($name, $this->notAttribute) && !in_array($name, $this->notUpdateFields)) {


        if($name == 'special_price' && 0 == intval($value)) {

          //fix for some magento versions bug 0 in cart
          //https://github.com/magento/magento2/issues/18268
          //https://github.com/magento/magento2/pull/18631
          if($this->_specPriceStorage) {
            $this->specialProcessSaveData($product, $name, $value, $sku);
            continue;
          }
          $value = null;
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
        $this->specialProcessSaveData($product, $name, $value, $sku);
      }
      $numField++;
    }
  }

  public function specialProcessSaveData($product, $name, $value, $sku) {

    switch($name) {
      case 'special_price':
        $updateArr = $this->_specPriceStorage->get([$sku]);
        if(empty($updateArr) || empty($updateArr[0])) {
          continue;
        }
        $updateObj = $updateArr[0];
        if($updateObj->getSku() != $sku) {
          continue;
        }
        $updateObj->setPrice( $value );
        $this->_specPriceStorage->delete([$updateObj]);

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

  protected function getPriceFieldIndexes($fields){
    $indexes = [];
    foreach($fields as $index => $field) {
      $name = $field['name'];
      if(in_array($name, $this->_priceFields)) {
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
      $this->_collection->addAttributeToSelect($header['name']);
    }

    return $this->_collection;
  }

  protected function addCustomDataToCollection($name) {
    switch($name) {
      case 'qty':
        $this->_collection
          ->joinTable($this->resource->getTableName('cataloginventory_stock_item'),
          'product_id=entity_id',
          [
              'qty' => 'qty',
              'in_stock' => 'is_in_stock'
          ]
        );
      break;
    }
  }

  public function collectData($headers) {
    if(!$this->_collection->getSize()) {
      return [];
    }

    $output = [];
    foreach($this->_collection as $productObj) {
      $row = [];

      foreach($headers as $header) {
        $value = $productObj->getData($header['name']);
        if(in_array($header['name'], $this->specialProcessData)) {
          $value = $this->specialProcessData($header['name'], $value);
        }
        $row[] = $value;
      }

      $output[] = $row;
    }

    return $output;
  }

  protected function specialProcessData($name, $value) {
    switch($name) {
      case 'status':
        return intval($value) > 0 ? 1 : 0;
      break;
      case 'qty':
        return intval($value);
      break;

      case 'price':
        return number_format( $value, 2, "," ,"" ) ;
      break;

      case 'special_price':
        return number_format( $value, 2, "," ,"" ) ;
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
