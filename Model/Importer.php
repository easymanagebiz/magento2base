<?php

namespace Develodesign\Easymanage\Model;

class Importer implements \Develodesign\Easymanage\Api\ImporterInterface{

  protected $request;

  protected $logger;

  public function __construct(
    \Magento\Framework\App\Request\Http $request,
    \Psr\Log\LoggerInterface $logger
  ) {

    $this->request = $request;
    $this->logger  = $logger;
  }

  public function save() {
    $postValues   = $this->request->getContent();
    $data         = \Zend_Json::decode($postValues);

    return [
      $data
    ];
  }

  public function process() {

  }

}
