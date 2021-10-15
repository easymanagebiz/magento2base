<?php

namespace Develodesign\Easymanage\Model;

class EmailTemplate extends \Magento\Framework\Model\AbstractModel
{
  protected function _construct()
  {
      $this->_init('Develodesign\Easymanage\Model\ResourceModel\EmailTemplate');
  }
}
