<?php

namespace Develodesign\Easymanage\Model\Addon;

abstract class Base {

  const PARENT_VAR  = 'parent';
  const TABLES_VAR  = 'tables';
  const ENDPOINTS_VAR = 'endpoints';

  const ICONS_VAR   = 'icons';
  const EMAIL_SHORTCODES_VAR = 'email_shortcodes';

  /* ui elements */

  const UI_TYPE_TITLE = 'title';
  const UI_TYPE_BUTTON_FETCH = 'buttonfetch';
  const UI_TYPE_BUTTON_SAVE = 'save';

  protected $_config = [];

  /* return updates of sidebar updates */
  abstract public function getSidebarUpgrade();

  /* return configuration of tables */
  abstract public function getTables();

  /* return endpoint key value */
  abstract public function getEndpoints();

  public function _initVars() {

    if(empty($this->_config[self::ENDPOINTS_VAR])) {
      $this->_config[self::ENDPOINTS_VAR]    = [];
    }

    if(empty($this->_config[self::PARENT_VAR])) {
      $this->_config[self::PARENT_VAR]    = [];
    }

    if(empty($this->_config[self::TABLES_VAR])) {
      $this->_config[self::TABLES_VAR] = [];
    }

    if(empty($this->_config[self::TABLES_VAR])) {
      $this->_config[self::TABLES_VAR] = [];
    }

    if(empty($this->_config[self::ICONS_VAR])) {
      $this->_config[self::ICONS_VAR] = [];
    }
  }

}
