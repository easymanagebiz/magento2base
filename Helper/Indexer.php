<?php

namespace Develodesign\Easymanage\Helper;

class Indexer extends \Magento\Framework\App\Helper\AbstractHelper
{
    /*
    $indexerIds = array(
        'catalog_category_product',
        'catalog_product_category',
        'catalog_product_price',
        'catalog_product_attribute',
        'cataloginventory_stock',
        'catalogrule_product',
        'catalogsearch_fulltext',
    );
    */

    protected $_indexes = [
      'catalog_product_price',
      'catalogrule_product',
    ];

    protected $_indexFactory;

    public function __construct(
      \Magento\Framework\App\Helper\Context $context,
      \Magento\Indexer\Model\IndexerFactory $indexFactory
    ) {
      parent::__construct($context);
      $this->_indexFactory = $indexFactory;
    }

    public function reindexAll() {

      foreach ($this->_indexes as $indexerId) {
          $indexer = $this->_indexFactory->create();
          $indexer->load($indexerId);
          $indexer->reindexAll();
      }

    }
}
