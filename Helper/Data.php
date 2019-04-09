<?php

namespace Develodesign\Easymanage\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\ModuleListInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const MODULE_NAME = 'Develodesign_Easymanage';

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
}
