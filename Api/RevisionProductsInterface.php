<?php

namespace Develodesign\Easymanage\Api;

interface RevisionProductsInterface {

  /**
   * Get all revisions
   * @return string
   */
  public function allrevisions();

  /**
   * Get all revisions
   * @return string
   */
  public function revision();


  /**
   * Get revision details
   * @return string
   */
  public function loadrevision();

}
