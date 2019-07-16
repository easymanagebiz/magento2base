<?php

namespace Develodesign\Easymanage\Model\Addon\Mailunsubscribers;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;

class Event implements ObserverInterface{

  protected $_addon;

  const SHORTCODE_DESC = '[unsubscribe_link title="text title"]';

  public function __construct(
    \Develodesign\Easymanage\Model\Addon\Mailunsubscribers\Addon $addon
  ) {
    $this->_addon = $addon;
  }

  public function execute(\Magento\Framework\Event\Observer $observer)
  {
    $addonsObj = $observer->getEvent()->getData('addons');
    $addons    = $addonsObj->getAddons();
    $addons    = $this->_addon->getSidebarUpgrade($addons);
    $emailShortcodes = [];
    if(!empty($addons[\Develodesign\Easymanage\Model\Addon\Base::EMAIL_SHORTCODES_VAR])) {
      $emailShortcodes = $addons[\Develodesign\Easymanage\Model\Addon\Base::EMAIL_SHORTCODES_VAR];
    }
    $emailShortcodes[] = self::SHORTCODE_DESC;
    $addons[\Develodesign\Easymanage\Model\Addon\Base::EMAIL_SHORTCODES_VAR] = $emailShortcodes;
    
    $addonsObj->setAddons($addons);
  }
}
