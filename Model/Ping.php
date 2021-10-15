<?php

namespace Develodesign\Easymanage\Model;

class Ping implements \Develodesign\Easymanage\Api\PingInterface{

  protected $_helper;

  protected $_addons;

  protected $_eventManager;

  public function __construct(
    \Develodesign\Easymanage\Helper\Data $helper,
    \Magento\Framework\Event\ManagerInterface $eventManager
  ) {
    $this->_helper = $helper;
    $this->_eventManager = $eventManager;
  }

  public function ping() {

    $this->_addons = new \Magento\Framework\DataObject();
    $this->_addons->setAddons([]);

    if($this->_helper->getIsAddonEnebled()) {
      $this->_eventManager->dispatch('easymanage_addons_create', [
        'addons' => $this->_addons
      ]);
    }

    return [[
      'status'  => 'ok',
      'version' => $this->_helper->getVersion(),
      'addons'  => $this->_addons->getAddons()
    ]];
  }
}
