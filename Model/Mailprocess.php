<?php

namespace Develodesign\Easymanage\Model;

class Mailprocess implements \Develodesign\Easymanage\Api\MailprocessInterface{

  const EMAIL_TEMPLATE = 'easymanage_email';

  protected $_helperShortcode;

  protected $logger;

  protected $_eventManager;

  protected $request;

  protected $_blockEmail;

  protected $_modelEmails;

  protected $_modelUnsubscriberEmails;

  protected $_emailObject;

  protected $_mailTemplateModel;

  protected $_loadedModel;

  public $_shortcodes = [
    'product',
    'category',
    'unsubscribe_link',
  ];

  public function __construct(
    \Develodesign\Easymanage\Helper\Shortcode $helperShortcode,
    \Develodesign\Easymanage\Block\Email $blockEmail,
    \Develodesign\Easymanage\Model\Email $modelEmails,
    \Develodesign\Easymanage\Model\EmailunsubscriberFactory $modelUnsubscriberEmails,
    \Magento\Framework\App\Request\Http $request,
    \Psr\Log\LoggerInterface $logger,
    \Magento\Framework\Event\ManagerInterface $eventManager,
    \Develodesign\Easymanage\Model\EmailTemplateFactory $mailTemplateModel
  ) {
    $this->_mailTemplateModel = $mailTemplateModel;
    $this->_helperShortcode = $helperShortcode;
    $this->_eventManager = $eventManager;
    $this->_blockEmail   = $blockEmail;
    $this->_modelUnsubscriberEmails = $modelUnsubscriberEmails;
    $this->_modelEmails = $modelEmails;
    $this->logger  = $logger;
    $this->request = $request;
  }

  public function process() {
    $newContent = '';
    $postValues    = $this->request->getContent();
    $postValuesArr = \Zend_Json::decode($postValues);

    $emailContent = $this->prepareEmailContent($postValuesArr['email_id']);
    //$emailTo      = $postValuesArr['email_to'];

    if(empty($emailContent)) {
      return [[
        'error' => true
      ]];
    }

    $shortCodes = $this->_helperShortcode->parseShortcodes($emailContent);

    $this->_emailObject = new \Magento\Framework\DataObject();

    $newContent = $emailContent;

    foreach($shortCodes as $code) {
      if(!in_array($code['name'], $this->_shortcodes)) {
        continue;
      }
      $newContent = $this->_processShortcode($newContent, $code);
    }

    $this->_emailObject->setData([
      'email_content' => $emailContent,
      'short_codes' => $shortCodes,
      'new_content' => trim($newContent, '"')
    ]);

    $this->_eventManager->dispatch('easymanage_after_process_email_content', [
      'email_object' => $this->_emailObject,
      'object' => $this
    ]);

    $emailText = (string) $this->_blockEmail->getEmailTemplateText(self::EMAIL_TEMPLATE);

    return [[
      'short_codes'    => $this->_emailObject->getShortCodes(),
      'content_email'  => $this->_emailObject->getNewContent(),
      'subject' => $this->_loadedModel->getEmailSubject(),
      'base_template'  => stripslashes($emailText),
      'unsubscribers' => $this->getUnsubscribersEmails()
    ]];
  }

  public function subscriberids() {
    $postValues    = $this->request->getContent();
    $postValuesArr = \Zend_Json::decode($postValues);
    $output = [];
    foreach($postValuesArr as $row => $email) {
      $emailObject  = $this->getEmail($email);
      $output[$row] = $emailObject->getUniqueId();
    }

    return [
      $output
    ];
  }

  protected function getUnsubscribersEmails() {
    $collection = $this->_modelUnsubscriberEmails->create()
                            ->getCollection();

    if(!$collection->getSize()) {
      return [];
    }
    $out = [];
    foreach($collection as $item) {
      $out[] = $item->getEmailAddress();
    }

    return $out;
  }

  protected function getEmail($email) {
    $emailModel = $this->_modelEmails->load($email, 'email_address');
    if(!$emailModel->getId()) {
      $emailModel->setEmailAddress($email);
      $emailModel->save();
    }

    return $emailModel;
  }

  protected function prepareEmailContent($emailId) {
    $model = $this->_mailTemplateModel->create();
    $model->load( $emailId );

    if(!$model->getId()) {
      return;
    }
    $this->_loadedModel = $model;
    $text = str_replace(['\r', '\n', '&quot;'], ['', '', '"'], $model->getEmailContent());
    return trim(stripslashes(stripslashes($text)), '"');
  }

  protected function _processShortcode($newContent, $code) {

    switch($code['name']) {

      case 'product':
        if(empty($code['attrs'][0])) {
          return $newContent;
        }
        $content = $this->_blockEmail->getProductData($code['attrs'][0]);
        $newContent = str_replace($code['shortcode'], $content, $newContent);
      break;

      case 'unsubscribe_link':
        $title = !empty($code['attrs'][0]['title']) ? $code['attrs'][0]['title'] : __('Unsubscribe');
        $link  = $this->_blockEmail->getUnsubscribeLink($title);
        $newContent = str_replace($code['shortcode'], $link, $newContent);
      break;

      case 'category':

        $content = $this->_blockEmail->getCategoryData($code['attrs'][0]);

        $newContent = str_replace($code['shortcode'], $content, $newContent);
      break;

    }

    return $newContent;
  }
}
