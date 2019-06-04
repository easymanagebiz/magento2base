<?php

namespace Develodesign\Easymanage\Model\Addon\Blockshortcode;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;

class Event implements ObserverInterface{

  const SHORTCODE_DESC = '[cms_block block_id="integer"]';

  public function execute(\Magento\Framework\Event\Observer $observer)
  {
    $addonsObj = $observer->getEvent()->getData('addons');
    $addons    = $addonsObj->getAddons();
    $emailShortcodes = [];
    if(!empty($addons[\Develodesign\Easymanage\Model\Addon\Base::EMAIL_SHORTCODES_VAR])) {
      $emailShortcodes = $addons[\Develodesign\Easymanage\Model\Addon\Base::EMAIL_SHORTCODES_VAR];
    }
    $emailShortcodes[] = self::SHORTCODE_DESC;
    $addons[\Develodesign\Easymanage\Model\Addon\Base::EMAIL_SHORTCODES_VAR] = $emailShortcodes;
    $addonsObj->setAddons($addons);
  }


}
