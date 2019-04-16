<?php

namespace Develodesign\Easymanage\Model;

class ExportCustomers implements \Develodesign\Easymanage\Api\ExportCustomersInterface{

  protected  $collectionFactory;

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

    return [
      'data'=> [
        'postValues' => $postValuesArr,
        //'totalCount' => $this->_collection->getSize(),
        //'dataCustomers' => $dataCustomers
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
        //'totalCount' => $this->_collection->getSize(),
        'dataCustomers' => $dataCustomers
      ]
    ];
  }

  protected function getSearchCustomers($postValuesArr) {
    $search = !empty($postValuesArr['search']) ? addslashes($postValuesArr['search']) : 'none';
    $customersCollection = $this->collectionFactory->create();
    $customersCollection = $this->_helper
      ->setCollection($customersCollection)
      ->addFieldsToSelect($postValuesArr['headers']);

    $customersCollection->addAttributeToFilter(
          [
           ['attribute' => 'email', 'like' => '%' . $search . '%'],
           ['attribute' => 'firstname', 'like' => '%' . $search . '%'],
           ['attribute' => 'lastname', 'like' => '%' . $search . '%']
          ]);

    return $this->_helper->collectData($postValuesArr['headers']);
  }

  protected function prepareCustomerData($customer) {
    $data = [];
    foreach($this->_fieldsToSelectSend as $attr => $label) {

    }
  }
}
