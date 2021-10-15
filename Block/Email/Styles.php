<?php

namespace Develodesign\Easymanage\Block\Email;

class Styles{

  protected $_blockCSS = [
    'margin' => '10px auto 0',
    'max-width' => '95%',
    'border-bottom' => '1px solid silver',
    'padding-bottom' => '10px'
  ];

  protected $_titleCSS = [
    'font-weight' => 'bold'
  ];

  protected $_imageContainerCSS = [
    'text-align' => 'center'
  ];

  protected $_imageCSS = [
    'max-width' => '90%',
    'display' => 'inline'
  ];

  protected $_skuCSS = [
    'font-style' => 'italic',
    'float' => 'right'
  ];

  protected $_priceCSS = [
    'color' => '#666666',
    'float' => 'left'
  ];

  protected $_buttonCSS = [
    'padding' => '14px 17px',
    'display' => 'block',
    'background' => '#1979c3',
    'border' => '1px solid #1979c3',
    'color' => '#ffffff !important',
    'text-decoration' => 'none !important',
    'font-size' => '25px',
    'text-align' => 'center'
  ];

  protected function parseStylesConf($stylesArr = []) {
    $string = '';
    foreach($stylesArr as $styleKey => $styleVal) {
      $string .= ' ' . $styleKey . ':' . $styleVal . ';';
    }

    return $string;
  }

  public function getBlockStyles() {
    return $this->parseStylesConf($this->_blockCSS);
  }

  public function getTitleStyles() {
    return $this->parseStylesConf($this->_titleCSS);
  }

  public function getImageContainerStyles() {
    return $this->parseStylesConf($this->_imageContainerCSS);
  }

  public function getImageStyles() {
    return $this->parseStylesConf($this->_imageCSS);
  }

  public function getSkuStyles() {
    return $this->parseStylesConf($this->_skuCSS);
  }

  public function getPriceStyles() {
    return $this->parseStylesConf($this->_priceCSS);
  }

  public function getButtonStyles() {
    return $this->parseStylesConf($this->_buttonCSS);
  }
}
