<?php

namespace Develodesign\Easymanage\Model;

class ExportProducts implements \Develodesign\Easymanage\Api\ExportProductsInterface{

  protected $request;
  protected $productCollection;

  protected $_collection;

  protected $_helperProducts;

  public function __construct(
    \Magento\Framework\App\Request\Http $request,
    \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
    //\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $productCollection,
    \Develodesign\Easymanage\Helper\Products $helperProducts
  ) {

    $this->request = $request;
    $this->productCollection = $productCollection;

    $this->_helperProducts = $helperProducts;
  }

  public function search() {
    $postValues = $this->request->getContent();
    $postValuesArr = \Zend_Json::decode($postValues);

    $dataProducts = $this->getSearchProducts($postValuesArr);

    return [
      'data'=> [
        'postValues' => $postValuesArr,
        'totalCount' => $this->_collection->getSize(),
        'dataProducts' => $dataProducts
      ]
    ];
  }

  public function export() {
    $postValues = $this->request->getContent();
    $postValuesArr = \Zend_Json::decode($postValues);

    $dataProducts = $this->getDataProducts($postValuesArr);

    return [
      'data'=> [
        'postValues' => $postValuesArr,
        'totalCount' => $this->_collection->getSize(),
        'dataProducts' => $dataProducts
      ]
    ];
  }

  protected function getSearchProducts($postValuesArr) {
    $this->_collection = $this->productCollection->create();
    $this->_collection = $this->_helperProducts
      ->setCollection($this->_collection)
      ->addFieldsToSelect($postValuesArr['headers']);

    $this->addSearchValue($postValuesArr);

    return $this->_helperProducts
                ->collectData($postValuesArr['headers']);
  }

  protected function addSearchValue($postValuesArr) {
    $search = !empty($postValuesArr['search']) ? addslashes($postValuesArr['search']) : 'none';

    $this->_collection->addAttributeToSelect('sku')
      ->addAttributeToSelect('name');

      $this->_collection->addAttributeToFilter(
        [
         ['attribute' => 'name', 'like' => '%' . $search . '%'],
         ['attribute' => 'sku', 'like' => '%' . $search . '%']
        ]);
  }

  protected function getDataProducts($postValuesArr) {
    $this->_collection = $this->productCollection->create();
    $this->addStoreFilter($postValuesArr);
    $this->addCategoryIdsFilter($postValuesArr);

    $this->_collection = $this->_helperProducts
      ->setCollection($this->_collection)
      ->addFieldsToSelect($postValuesArr['headers']);

    return $this->_helperProducts
                ->collectData($postValuesArr['headers']);
  }

  protected function addCategoryIdsFilter($postValuesArr) {
    $categories = !empty($postValuesArr['category']) ? $postValuesArr['category'] : null;
    if(!$categories || (count($categories) == 1 && $categories[0] == '')) {
      return;
    }
    $this->_collection->addCategoriesFilter(['in' => $categories]);
  }

  protected function addStoreFilter($postValuesArr) {
    $store = !empty($postValuesArr['store']) ? $postValuesArr['store'] : null;
    if(!$store) {
      return;
    }
    $this->_collection->addStoreFilter($store);
  }

}
