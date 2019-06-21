<?php

namespace Develodesign\Easymanage\Model\Addon;

abstract class Base {

  const PARENT_VAR  = 'parent';
  const TABLES_VAR  = 'tables';
  const ENDPOINTS_VAR = 'endpoints';

  const ICONS_VAR   = 'icons';
  const EMAIL_SHORTCODES_VAR = 'email_shortcodes';

  /* ui elements */

  const UI_COMPONENT_LOAD_SECONDARY_PANEL = 'uicomponent-secondary';

  const UI_TYPE_TITLE = 'title';
  const UI_TYPE_BUTTON_FETCH = 'buttonfetch';
  const UI_TYPE_BUTTON_SAVE  = 'save';
  const UI_TYPE_MENU   = 'menu';
  const UI_TYPE_SEARCH = 'search';
  const UI_TYPE_FORM = 'form';
  const UI_TYPE_STORES = 'stores';
  const UI_TYPE_CATEGORIES = 'categories';

  /* menu classes */
  const UI_MENU_CLASS_ADD_MORE_GRID  = 'add-more-grids';
  const UI_MENU_CLASS_COLUMN_MANAGER = 'grid-column-manager';
  const UI_MENU_CLASS_LOG_VIEW = 'log-viewer';


  /* static defined icons */

  const UI_ICON_PLUS    = 'icons_plus';
  const UI_ICON_COLUMNS = 'icons_columns';
  const UI_ICON_IMPORT  = 'icons_import';
  const UI_ICON_LOG = 'icons_log';

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
