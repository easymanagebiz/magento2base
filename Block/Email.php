<?php

namespace Develodesign\Easymanage\Block;

class Email extends \Magento\Framework\View\Element\Template{

  protected $_blockProducts;

  protected $_emailTemplate;

  protected $_storeManager;

  public function __construct(
    \Magento\Catalog\Block\Product\Context $context,
    \Develodesign\Easymanage\Block\Email\Products $blockProducts,
    \Magento\Email\Model\Template $emailTemplate,
    array $data = array()
  ) {

    $this->_blockProducts = $blockProducts;
    $this->_emailTemplate = $emailTemplate;
    parent::__construct($context, $data);
  }

  public function getEmailTemplateText($type) {

    $data = (string) $this->_emailTemplate
      ->setForcedArea($type)
      ->setId($type)
      ->processTemplate();
    return $data;
  }

  public function getUnsubscribeLink($title = '') {
    return '<a href="' . $this->getUrl('easymanage/unsubscribe/index') . 'id/[$subscriberId]' . '" class="easymanage-unsubscribe">' . $title . '</a>';
  }

  public function getProductData($attrs) {
    return $this->_blockProducts->getProductData($attrs);
  }

  public function getCategoryData($attrs) {
    return $this->_blockProducts->getCategoryData($attrs);
  }
}
