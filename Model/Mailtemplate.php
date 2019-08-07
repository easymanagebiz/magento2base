<?php

namespace Develodesign\Easymanage\Model;

class Mailtemplate implements \Develodesign\Easymanage\Api\MailTemplateInterface{

  protected $_mailTemplateModel;

  protected $request;

  public function __construct(
      \Magento\Framework\App\Request\Http $request,
      \Develodesign\Easymanage\Model\EmailTemplate $mailTemplateModel
  ) {
    $this->_mailTemplateModel = $mailTemplateModel;
    $this->request = $request;
  }

  public function save() {
    $postValues   = $this->request->getContent();
    $data         = \Zend_Json::decode($postValues);

    $subject = !empty($data['subject']) ? $data['subject'] : null;
    $content = !empty($data['content']) ? $data['content'] : null;

    if(empty($content) || empty($subject)) {

    }
  }

  public function all() {
    return [[

      ]];
  }

  public function deleteone() {

  }

  public function getone() {

  }

}
