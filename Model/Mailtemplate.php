<?php

namespace Develodesign\Easymanage\Model;

class Mailtemplate implements \Develodesign\Easymanage\Api\MailTemplateInterface{

  protected $_mailTemplateModel;

  protected $request;

  public function __construct(
      \Magento\Framework\App\Request\Http $request,
      \Develodesign\Easymanage\Model\EmailTemplateFactory $mailTemplateModel
  ) {
    $this->_mailTemplateModel = $mailTemplateModel;
    $this->request = $request;
  }

  public function save() {
    $postValues   = $this->request->getContent();
    $data         = \Zend_Json::decode($postValues);

    $subject = !empty($data['subject']) ? $data['subject'] : null;
    $content = !empty($data['content']) ? $data['content'] : null;

    $template_id = !empty($data['template_id']) ? $data['template_id'] : null;

    if(empty($content) || empty($subject)) {
      return $this->all();
    }

    $model = $this->_mailTemplateModel->create()
                    ->load( $template_id );

    $model->setEmailSubject($subject);
    $model->setEmailContent($content);

    $model->save();

    return [[
      'all' => $this->getListTemplates(),
      'selected' => $model->getId(),
      'status' => 'ok'
    ]];

  }

  public function all() {
    return [
      $this->getListTemplates()
    ];
  }

  public function deleteone() {

    try {
      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      $templateId = !empty($data['template_id']) ? $data['template_id'] : null;
      if(empty($templateId)) {
        return $this->all();
      }
      $model = $this->_mailTemplateModel->create();
      $model->load( $templateId );

      if(!$model->getId()) {
        return $this->all();
      }

      $model->delete();

      return [[
        'all' => $this->getListTemplates(),
        'status' => 'ok'
      ]];

    }catch(Exception $e) {
      return [[
        'error' => $e->getMessage()
      ]];
    }

  }

  public function getone() {
    $postValues   = $this->request->getContent();
    $data         = \Zend_Json::decode($postValues);

    $templateId = !empty($data['template_id']) ? $data['template_id'] : null;
    if(empty($templateId)) {
      return $this->all();
    }
    $model = $this->_mailTemplateModel->create()
                    ->load( $templateId );

    if(!$model->getId()) {
      return $this->all();
    }
    return [[
      'all' => $this->getListTemplates(),
      'selected' => $model->getId(),
      'template_code' => $model->getEmailContent(),
      'subject' => $model->getEmailSubject(),
      'status' => 'ok'
    ]];
  }

  protected function getListTemplates() {
    $collection = $this->_mailTemplateModel->create()
                  ->getCollection();
    if(!$collection->getSize()) {
      return [];
    }
    $out = [];
    foreach($collection as $_template) {
      $out[] = [
        'label' => $_template->getEmailSubject(),
        'value' => $_template->getId()
      ];
    }

    return $out;
  }
}
