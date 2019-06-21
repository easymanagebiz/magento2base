<?php

namespace Develodesign\Easymanage\Helper;

class Attributes extends \Magento\Framework\App\Helper\AbstractHelper
{

  protected $_notUpdatedAttr = [
      'tax_class_id'
  ];

  protected $_loadedAttributes;

  public function __construct(
    \Magento\Framework\App\Helper\Context $context


  ) {

    parent::__construct($context);
  }
}
