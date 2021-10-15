<?php

namespace Develodesign\Easymanage\Model;

class Test implements \Develodesign\Easymanage\Api\TestInterface{

  protected $_helper;

  public function __construct(
    \Develodesign\Easymanage\Helper\Data $helper
  ) {
    $this->_helper = $helper;
  }

  public function test()
  {
    return [[
      'status'  => 'ok',
      'version' => $this->_helper->getVersion()
    ]];
  }
}
