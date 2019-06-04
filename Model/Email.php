<?php

namespace Develodesign\Easymanage\Model;

class Email extends \Magento\Framework\Model\AbstractModel
{
  protected function _construct()
  {
      $this->_init('Develodesign\Easymanage\Model\ResourceModel\Email');
  }
}
