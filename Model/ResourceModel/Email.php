<?php

namespace Develodesign\Easymanage\Model\ResourceModel;

/**
 *
 */
class Email extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb {

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct() {
        $this->_init('easymanage_emails', 'email_id');
    }

    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object) {
        if(!$object->getUniqueId()) {
          $object->setUniqueId(uniqid());
        }
        return parent::_beforeSave($object);
    }

}
