<?php

namespace Develodesign\Easymanage\Model\Addon\Blockshortcode;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;

class EventMail implements ObserverInterface{

  const SHORTCODE = 'cms_block';

  protected $_blockFactory;

  protected $filterProvider;

  public function __construct(
    \Magento\Cms\Model\BlockFactory $blockFactory,
    \Magento\Cms\Model\Template\FilterProvider $filterProvider
  ) {
    $this->_blockFactory  = $blockFactory;
    $this->filterProvider = $filterProvider;
  }

  public function execute(\Magento\Framework\Event\Observer $observer)
  {
    $emailObj   = $observer->getEvent()->getData('email_object');
    $shortCodes = $emailObj->getShortCodes();

    $newContent = $emailObj->getNewContent();
    $this->processShortCodeAndContent($shortCodes, $newContent, $emailObj);
  }

  protected function processShortCodeAndContent($shortCodes, $newContent, $emailObj) {

    foreach($shortCodes as $code){

      if($code['name'] == self::SHORTCODE && !empty( $code['attrs'][0]['block_id'] )) {
        $newContent = $this->getCMSBlockContent($newContent, $code, $code['attrs'][0]['block_id']);
      }
    }

    $emailObj->setNewContent($newContent);
  }

  protected function getCMSBlockContent($newContent, $code, $blockId) {
    $blockModel = $this->_blockFactory->create()
                          ->load($blockId);
    $blockHtml = '';
    if($blockModel->getId()) {
      $blockHtml = $this->filterProvider->getBlockFilter()->filter($blockModel->getContent());
    }
    return str_replace($code['shortcode'], $blockHtml, $newContent);;
  }
}
