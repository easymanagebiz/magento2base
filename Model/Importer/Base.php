<?php

namespace Develodesign\Easymanage\Model\Importer;

use Magento\Framework\App\ResourceConnection;

class Base {

  const CATALOG_PRODUCT_ENTITY_TABLE = 'catalog_product_entity';

  protected $resourceConnection;

  public function __construct(
     ResourceConnection $resourceConnection
    )
  {
    $this->resourceConnection = $resourceConnection;
  }

  /*
    $identity  = [
      'field' => value
    ];

  */
  public function insertOrUpdateData($tableName, $data, $identity)
  {
    $connection  = $this->resourceConnection->getConnection();
    if($this->isRecordExists($tableName, $data, $identity)) {
      $this->updateRecord($tableName, $data, $identity);
    }else{
      $this->insertRecord($tableName, $data);
    }
  }

  protected function insertRecord($tableName, $data)
  {
    $connection  = $this->resourceConnection->getConnection();
    $connection->insert($this->getTableName($tableName), $data);
  }

  protected function updateRecord($tableName, $data, $identity)
  {
    $connection  = $this->resourceConnection->getConnection();
    $connection->update(
        $this->getTableName($tableName),
        $data,
        $this->getIdentityStatement($identity)
    );
  }

  public function deleteEntity($tableName, $identity)
  {
    $connection  = $this->resourceConnection->getConnection();
    $connection->delete(
        $this->getTableName($tableName),
        $data,
        $this->getIdentityStatement($identity)
    );
  }

  public function isRecordExists($tableName, $data, $identity)
  {
    $connection  = $this->resourceConnection->getConnection();
    $sql = $connection->select()->from(
                    [$this->getTableName($tableName)], [$this->getIdentityFields($identity)]
            )
            ->where( $this->getIdentityStatement($identity) )
            ;

    return $connection->fetchOne($sql);
  }

  protected function getIdentityStatements($identity)
  {
    $statements = [];
    foreach($identity as $field => $value) {
      $statements[$field . '=?'] = $value;
    }

    return $statements;
  }

  protected function getIdentityFields($identity)
  {
    $fields = [];
    foreach($identity as $field => $value) {
      $fields[] = $field;
    }

    return $fields;
  }

  public function getTableName($tableName)
  {
    $connection = $this->resourceConnection->getConnection();
    return $connection->getTableName( $tableName );
  }

  public function getProductIdFromSku($sku)
  {
    $connection = $this->resourceConnection->getConnection();
    $sql = $connection->select()->from(
                    [$this->getTableName(self::CATALOG_PRODUCT_ENTITY_TABLE)], ['entity_id']
            )
            ->where( 'sku=?', $sku )
            ;

    return $connection->fetchOne($sql);
  }

  public function getConnection()
  {
    return $this->resourceConnection->getConnection();
  }
}
