<?php

namespace Develodesign\Easymanage\Helper;

class Shortcode extends \Magento\Framework\App\Helper\AbstractHelper
{
  public function __construct(
    \Magento\Framework\App\Helper\Context $context
  ){
    parent::__construct($context);
  }

  // Regex101 reference: https://regex101.com/r/pJ7lO1
  const SHORTOCODE_REGEXP = "/(?P<shortcode>(?:(?:\\s?\\[))(?P<name>[\\w\\-]{3,})(?:\\s(?P<attrs>[\\w\\d,\\s=\\\"\\'\\-\\+\\#\\%\\!\\~\\`\\&\\.\\s\\:\\/\\?\\|]+))?(?:\\])(?:(?P<content>[\\w\\d\\,\\!\\@\\#\\$\\%\\^\\&\\*\\(\\\\)\\s\\=\\\"\\'\\-\\+\\&\\.\\s\\:\\/\\?\\|\\<\\>]+)(?:\\[\\/[\\w\\-\\_]+\\]))?)/u";

  // Regex101 reference: https://regex101.com/r/sZ7wP0

  const ATTRIBUTE_REGEXP = "/(?<name>\\S+)=[\"']?(?P<value>(?:.(?![\"']?\\s+(?:\\S+)=|[>\"']))+.)[\"']?/u";

  public function parseShortcodes($text) {
      preg_match_all(self::SHORTOCODE_REGEXP, $text, $matches, PREG_SET_ORDER);
      $shortcodes = array();
      foreach ($matches as $i => $value) {
          $shortcodes[$i]['shortcode'] = $value['shortcode'];
          $shortcodes[$i]['name'] = $value['name'];
          if (isset($value['attrs'])) {
              $attrs = $this->parse_attrs($value['attrs']);
              $shortcodes[$i]['attrs'] = $attrs;
          }
          if (isset($value['content'])) {
              $shortcodes[$i]['content'] = $value['content'];
          }
      }

      return $shortcodes;
  }

  private function parse_attrs($attrs) {
      preg_match_all(self::ATTRIBUTE_REGEXP, $attrs, $matches, PREG_SET_ORDER);
      $attributes = array();
      foreach ($matches as $i => $value) {
          $key = $value['name'];
          $attributes[$i][$key] = str_replace('"', '', $value['value']);
      }
      return $attributes;
  }

}
