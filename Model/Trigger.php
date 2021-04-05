<?php

namespace Develodesign\Easymanage\Model;

class Trigger implements \Develodesign\Easymanage\Api\TriggerInterface{

  protected $_helper;

  protected $_eventManager;

  protected $triggers;

  public function __construct(
    \Develodesign\Easymanage\Helper\Data $helper,
    \Magento\Framework\Event\ManagerInterface $eventManager
  ) {
    $this->_helper = $helper;
    $this->_eventManager = $eventManager;
  }

  public function trigger()
  {
    $this->triggers = new \Magento\Framework\DataObject();
    $this->triggers->setTriggers([]);

    if($this->_helper->getIsTriggersEnebled()) {
      $this->_eventManager->dispatch('easymanage_triggers', [
        'triggers' => $this->triggers
      ]);
    }

    return [
      $this->triggers->getTriggers()
    ];
  }
}
