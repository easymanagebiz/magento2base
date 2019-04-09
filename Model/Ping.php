<?php

namespace Develodesign\Easymanage\Model;

class Ping implements \Develodesign\Easymanage\Api\PingInterface{

  protected $_helper;

  public function __construct(
    \Develodesign\Easymanage\Helper\Data $helper
  ) {
    $this->_helper = $helper;
  }

  public function ping() {
    return [[
      'status'  => 'ok',
      'version' => $this->_helper->getVersion()
    ]];
  }
}
