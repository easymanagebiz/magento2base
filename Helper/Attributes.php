<?php

namespace Develodesign\Easymanage\Helper;

class Attributes extends \Magento\Framework\App\Helper\AbstractHelper
{

  const ATTRIBUTE_TYPE_PRODUCT = 'catalog_product';

  const OPTIONS_DIVIDER = ',';

  protected $_optionAddType = [
    'select',
    'multiselect'
  ];

  protected $_loadedAttributes;

  protected $_eavConfig;

  protected $_optionManagement;

  protected $_optionFactory;

  public function __construct(
    \Magento\Framework\App\Helper\Context $context,
    \Magento\Eav\Model\Config $eavConfig,

    \Magento\Eav\Api\AttributeOptionManagementInterface $optionManagement,
    \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionFactory

  ) {

    $this->_eavConfig = $eavConfig;
    $this->_optionManagement = $optionManagement;
    $this->_optionFactory = $optionFactory;

    parent::__construct($context);
  }

  public function getIsOptionAttribute($code) {
    $attribute = $this->getAttributeByCode( $code );
    $attributeType = $attribute->getFrontendInput();
    if(in_array($attributeType, $this->_optionAddType)) {
      return true;
    }
  }

  public function getOptionIdsFromLabels($attributeCode, $labelsString) {
    $optionsLabelsToCreate = [];

    if($labelsString == '') {
      return '';
    }
    $newOptionsIdsArr = [];
    $attribute = $this->getAttributeByCode($attributeCode);
    $attrOptions    = $attribute->getSource()->getAllOptions();
    $labelsArr  = explode(self::OPTIONS_DIVIDER, $labelsString);
    $optionsLabelsToCreate = $labelsArr;
    foreach($labelsArr as $label) {
      $labelCheck = trim($label);
      foreach($attrOptions as $optionExists) {
        if($optionExists['label'] == $labelCheck) {
          $newOptionsIdsArr[] = (int)$optionExists['value'];
          $optionsLabelsToCreate = array_diff($optionsLabelsToCreate, array($label));
        }
      }
    }

    foreach($optionsLabelsToCreate as $label) {
      $newLabel = trim( $label );
      $newOptionsIdsArr[] = $this->addAttributeOption($attributeCode, $newLabel);
    }

    return implode(self::OPTIONS_DIVIDER, $newOptionsIdsArr);
  }

  protected function addAttributeOption($attributeCode, $label) {
    $option = $this->_optionFactory->create();
    $option->setLabel($label);

    $this->_optionManagement->add(self::ATTRIBUTE_TYPE_PRODUCT, $attributeCode, $option);
    $items = $this->_optionManagement->getItems(self::ATTRIBUTE_TYPE_PRODUCT, $attributeCode);

    $attribute = $this->getAttributeByCode($attributeCode, true);
    foreach($attribute->getSource()->getAllOptions() as $_option) {
      if($_option['label'] == $label) {
        return $_option['value'];
      }
    }
  }

  public function getOptionValues($attributeCode, $optionsString) {

    if($optionsString == '') {
      return '';
    }
    $attribute = $this->getAttributeByCode($attributeCode);
    $optionsIdsArr  = explode(self::OPTIONS_DIVIDER, $optionsString);
    $attrOptions    = $attribute->getSource()->getAllOptions();
    $newOptionsArr  = [];
    foreach($optionsIdsArr as $optionId) {

      foreach($attrOptions as $optionExists) {
        if($optionExists['value'] == $optionId) {
          $newOptionsArr[] = (string)$optionExists['label'];
        }
      }

    }

    return implode(self::OPTIONS_DIVIDER, $newOptionsArr);
  }

  public function getAttributeByCode($attributeCode, $force_load = false) {
    if(isset($this->_loadedAttributes[$attributeCode]) && !$force_load) {
      return $this->_loadedAttributes[$attributeCode];
    }

    $this->_loadedAttributes[$attributeCode] = $this->_eavConfig->getAttribute(self::ATTRIBUTE_TYPE_PRODUCT, $attributeCode);
    return $this->_loadedAttributes[$attributeCode];
  }
}
