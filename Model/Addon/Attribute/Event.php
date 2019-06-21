<?php

namespace Develodesign\Easymanage\Model\Addon\Attribute;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;

class Event implements ObserverInterface{

  protected $_addon;

  public function __construct(
    \Develodesign\Easymanage\Model\Addon\Attribute\Addon $addon
  ) {
    $this->_addon = $addon;
  }

  public function execute(\Magento\Framework\Event\Observer $observer)
  {
    $addonsObj = $observer->getEvent()->getData('addons');
    $addons    = $addonsObj->getAddons();
    $addons    = $this->_addon->getSidebarUpgrade($addons);
    $addonsObj->setAddons($addons);
  }
}
