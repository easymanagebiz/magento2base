<?php

namespace Develodesign\Easymanage\Model\ResourceModel\EmailTemplate;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * @var string
     */
    protected $_idFieldName = 'template_email_id';


    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Develodesign\Easymanage\Model\EmailTemplate', 'Develodesign\Easymanage\Model\ResourceModel\EmailTemplate');
    }

}
