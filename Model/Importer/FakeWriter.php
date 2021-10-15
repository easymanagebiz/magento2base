<?php

/* depricated */

namespace Develodesign\Easymanage\Model\Importer;

class FakeWriter{

  protected $_allRows = [];

  public function setHeaderCols()
  {

  }

  public function writeRow($dataRow)
  {
    $this->_allRows[] = $dataRow;
  }

  public function getAllData()
  {
    return $this->_allRows;
  }
}
