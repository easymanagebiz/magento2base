<?php

namespace Develodesign\Easymanage\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;


class UpgradeSchema implements UpgradeSchemaInterface {

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '1.0.2') < 0) {
            $this->createEmailsTables($setup);
        }

    }

    protected function createEmailsTables($setup) {
      $easymanage_emails = $setup->getConnection()
            ->newTable($setup->getTable('easymanage_emails'))
            ->addColumn(
                    'email_id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true, 'autoincrement' => true], 'Id'
            )
            ->addColumn(
                    'email_address', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 512, ['default' => null, 'nullable' => true], 'Customer email address'
            )
            ->addColumn(
                    'unique_id', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 1255, ['default' => null, 'nullable' => true], 'Unique id of that email'
            )
      ;
      $setup->getConnection()->createTable($easymanage_emails);

      $easymanage_email_unsubscribers =  $setup->getConnection()
            ->newTable($setup->getTable('easymanage_email_unsubscribers'))
            ->addColumn(
                    'email_usubscribe_id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true, 'autoincrement' => true], 'Id'
            )
            ->addColumn(
                    'email_address', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 512, ['default' => null, 'nullable' => true], 'Customer email address'
            )
      ;
      $setup->getConnection()->createTable($easymanage_email_unsubscribers);
    }

}
