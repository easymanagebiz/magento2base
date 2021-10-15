<?php

namespace Develodesign\Easymanage\Model;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;


class Attributes implements \Develodesign\Easymanage\Api\AttributesInterface{

  protected $_helper;

  protected $_collection;

  public function __construct(
    \Develodesign\Easymanage\Helper\Data $_helper,
    CollectionFactory $collection
  ) {
    $this->_helper = $_helper;
    $this->_collection = $collection;
  }

  public function all() {

    return [
      $this->getAttributes()
    ];
  }

  protected function getAttributes(){
    $out = [];
    $_collectionItems = $this->_collection->create();
    foreach($_collectionItems as $_item) {

      $out[] = [
        'label' => $_item->getAttributeCode(),
        'value' => $_item->getAttributeCode(),
      ];
      
    }

    return $out;
  }
}
