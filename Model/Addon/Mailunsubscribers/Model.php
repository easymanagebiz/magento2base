<?php


namespace Develodesign\Easymanage\Model\Addon\Mailunsubscribers;

class Model implements \Develodesign\Easymanage\Api\MailunsubscribersInterface{

  protected $_unsubscriberModel;

  protected $_collection;

  protected $request;

  public function __construct(
    \Magento\Framework\App\Request\Http $request,
    \Develodesign\Easymanage\Model\EmailunsubscriberFactory $unsubscriberModel
  ) {
    $this->_unsubscriberModel = $unsubscriberModel;
    $this->request = $request;
  }

  public function exportunsubscribers() {

    $postValues = $this->request->getContent();
    $postValuesArr = \Zend_Json::decode($postValues);

    return [
      'data'=> [
        'data'       => $this->getAllUnsubscribers(),
        'postValues' => $postValuesArr,
        'totalCount' => $this->_collection->getSize()
      ]
    ];
  }

  public function saveunsubscribers() {
    $postValues   = $this->request->getContent();
    $post         = \Zend_Json::decode($postValues);

    $this->clearExisting();
    if(empty($post) || empty($post['data'])) {
      return [[
        'status' => 'ok',
        'type'   => $post['extra']['type'],
        'sheet_id' => $post['sheet_id'],
        'total_saved' => '0',
        'total' => 0,
        //'saveRows' => $this->_saveDataRows
      ]];
    }

    $c = 0;
    foreach($post['data'] as $emailRow) {
      $email = !empty($emailRow[0]) ? $emailRow[0] : null;

      if(!$email) {
        continue;
      }

      $model = $this->_unsubscriberModel->create()
              ->load(null);

      $model->setEmailAddress($email);
      $model->save();
      $c++;
    }

    return [[
      'status' => 'ok',
      'type'   => $post['extra']['type'],
      'sheet_id' => $post['sheet_id'],
      'total_saved' => $c ? $c : '0',
      'total' => $c,
      //'saveRows' => $this->_saveDataRows
    ]];
  }

  protected function clearExisting() {

    $this->_collection = $this->_unsubscriberModel->create()
                                    ->getCollection();
    $this->_collection->walk('delete');
  }

  protected function getAllUnsubscribers() {
    $outData = [];
    $this->_collection = $this->_unsubscriberModel->create()
                                    ->getCollection();

    foreach ($this->_collection as $key => $value) {
      $outData[] =
        [
          $value->getEmailAddress()
        ];
    }

    return $outData;
  }
}
