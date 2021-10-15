<?php

namespace Develodesign\Easymanage\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\ModuleListInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const MODULE_NAME = 'Develodesign_Easymanage';

    const MODULE_ENEBLED_ADDONS   = 'develo_easymanage/general/enable_addons';
    const MODULE_ENEBLED_TRIGGERS = 'develo_easymanage/general/enable_triggers';

    protected $_moduleList;

    public function __construct(
        Context $context,
        ModuleListInterface $moduleList)
    {
        $this->_moduleList = $moduleList;
        parent::__construct($context);
    }

    public function getVersion()
    {
        return $this->_moduleList
            ->getOne(self::MODULE_NAME)['setup_version'];
    }

    public function getIsAddonEnebled() {
      return $this->scopeConfig->getValue(self::MODULE_ENEBLED_ADDONS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getIsTriggersEnebled() {
      return $this->scopeConfig->getValue(self::MODULE_ENEBLED_TRIGGERS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
