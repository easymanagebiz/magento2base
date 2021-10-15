<?php

namespace Develodesign\Easymanage\Model;

class ExportCustomers implements \Develodesign\Easymanage\Api\ExportCustomersInterface{

  protected  $collectionFactory;

  protected $_customersCollection;

  protected $_fieldsToSelectSend = [
    'email' => 'Email',
    'firstname' => 'First Name',
    'lastname' => 'Last Name',
  ];

  protected $_labels;

  protected $_helper;

  public function __construct(
    \Magento\Framework\App\Request\Http $request,
    \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $collectionFactory,
    \Develodesign\Easymanage\Helper\Customers $helper
  ) {
    $this->collectionFactory = $collectionFactory;
    $this->request = $request;
    $this->_helper = $helper;
  }

  public function export() {
    $postValues = $this->request->getContent();
    $postValuesArr = \Zend_Json::decode($postValues);

    $dataCustomers = $this->getExportCustomers($postValuesArr);

    return [
      'data'=> [
        'postValues' => $postValuesArr,
        'totalCount' => $this->_customersCollection->getSize(),
        'dataCustomers' => $dataCustomers
      ]
    ];

  }

  public function search() {
    $postValues = $this->request->getContent();
    $postValuesArr = \Zend_Json::decode($postValues);

    $dataCustomers = $this->getSearchCustomers($postValuesArr);

    return [
      'data'=> [
        'postValues' => $postValuesArr,
        'totalCount' => $this->_customersCollection->getSize(),
        'dataCustomers' => $dataCustomers
      ]
    ];
  }

  protected function getExportCustomers($postValuesArr) {
    $filterParams = ['default filter'];
    if(empty($postValuesArr)) {
      return [];
    }
    if(!empty($postValuesArr['params'])) {
      $str = parse_str($postValuesArr['params'], $filterParams);
    }
    $this->_customersCollection = $this->getCustomersCollection( $postValuesArr );
    if(!empty($filterParams['customer_id_from'])) {
      $this->_customersCollection
        ->addAttributeToFilter('entity_id', array('gt' => intval($filterParams['customer_id_from'])));
    }
    if(!empty($filterParams['customer_id_to'])) {
      $this->_customersCollection
        ->addAttributeToFilter('entity_id', array('lteq' => intval($filterParams['customer_id_to'])));

    }
    if(!empty($filterParams['registrated_from-data'])) {
      $date = date('Y-m-d 00:00:00', intval($filterParams['registrated_from-data']));
      $this->_customersCollection
        ->addAttributeToFilter('created_at', array('gt' => $date));

    }
    return $this->_helper->collectData($postValuesArr['headers']);
  }

  protected function getSearchCustomers($postValuesArr) {
    $search = !empty($postValuesArr['search']) ? addslashes($postValuesArr['search']) : 'none';
    $this->_customersCollection = $this->getCustomersCollection( $postValuesArr );

    $this->_customersCollection->addAttributeToFilter(
          [
           ['attribute' => 'email', 'like' => '%' . $search . '%'],
           ['attribute' => 'firstname', 'like' => '%' . $search . '%'],
           ['attribute' => 'lastname', 'like' => '%' . $search . '%']
          ]);

    return $this->_helper->collectData($postValuesArr['headers']);
  }

  protected function getCustomersCollection( $postValuesArr ) {
    $this->_customersCollection = $this->collectionFactory->create();
    $this->_customersCollection = $this->_helper
      ->setCollection($this->_customersCollection)
      ->addFieldsToSelect($postValuesArr['headers']);

    return  $this->_customersCollection;
  }

}
