<?php

namespace Develodesign\Easymanage\Helper;

class Customers extends \Magento\Framework\App\Helper\AbstractHelper
{

  protected $_collection;

  public function __construct(
    \Magento\Framework\App\Helper\Context $context
  ) {
    parent::__construct($context);
  }

  public function addFieldsToSelect($headers) {
    foreach($headers as $header) {
      $this->_collection->addAttributeToSelect($header['name']);
    }

    return $this->_collection;
  }

  public function collectData($headers) {
    if(!$this->_collection->getSize()) {
      return [];
    }

    $output = [];
    foreach($this->_collection as $_obj) {
      $row = [];
      foreach($headers as $header) {
        $value = $_obj->getData($header['name']);
        $row[] = $value;
      }
      $output[] = $row;
    }

    return $output;
  }

  public function setCollection($collection) {
    $this->_collection = $collection;
    return $this;
  }

  public function getCollection() {
    return $this->_collection;
  }
}
