<?php

namespace Develodesign\Easymanage\Model\ResourceModel;

/**
 *
 */
class EmailTemplate extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb {

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct() {
        $this->_init('easymanage_email_template', 'template_email_id');
    }

    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object) {
        return parent::_beforeSave($object);
    }

}
