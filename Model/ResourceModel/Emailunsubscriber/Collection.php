<?php

namespace Develodesign\Easymanage\Model\ResourceModel\Emailunsubscriber;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * @var string
     */
    protected $_idFieldName = 'email_usubscribe_id';


    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Develodesign\Easymanage\Model\Emailunsubscriber', 'Develodesign\Easymanage\Model\ResourceModel\Emailunsubscriber');
    }

}
