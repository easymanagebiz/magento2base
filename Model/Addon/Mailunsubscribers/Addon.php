<?php

namespace Develodesign\Easymanage\Model\Addon\Mailunsubscribers;

class Addon extends \Develodesign\Easymanage\Model\Addon\Base{

  const PARENT_UI_COMPONENET = 'menu_customers';

  const ICON_NAME    = 'user_remove';
  const TABLE_INDEX  = 'unsubscribers_mails';


  public function getSidebarUpgrade($configAddons = []) {
    $this->_config = $configAddons;
    $this->_initVars();
    if(empty($this->_config[self::PARENT_VAR][self::PARENT_UI_COMPONENET])) {
      $this->_config[self::PARENT_VAR][self::PARENT_UI_COMPONENET] = [];
    }
    $this->_config[self::PARENT_VAR][self::PARENT_UI_COMPONENET][] = $this->getConfig();
    $this->_config[self::TABLES_VAR][self::TABLE_INDEX] = $this->getTables();
    $this->_config[self::ICONS_VAR] [self::ICON_NAME]   = $this->getIconCode();
    $this->_config[self::ENDPOINTS_VAR] = array_merge($this->_config[self::ENDPOINTS_VAR], $this->getEndpoints());
    return $this->_config;
  }

  protected function getConfig() {
    return [
      'class' => 'uicomponent-secondary',
      'icon' => self::ICON_NAME,
      'label' => __('Unsubscribed emails'),
      'active_table' => self::TABLE_INDEX,
      'quick_menu' => true,
      'childs' => [
        $this->getTitleSidebar(),
        $this->getFetchButtonConfig(),
        $this->getSaveButtonConfig()
      ]
    ];
  }

  protected function getTitleSidebar() {
    return [
      'name'   => self::UI_TYPE_TITLE,
      'params' => [
        'label' => __('Manage unsubscribed users'),
        'icon'  => self::ICON_NAME
      ]
    ];
  }

  public function getTables() {
    return [
        'index' => self::TABLE_INDEX,
        'header' => [
          [
            'name' => 'email',
            'label' => __('Email'),
            'width' => 200,
            'validation' => [
              [
                'type' => 'required'
              ],
              [
                'type' => 'email'
              ]
            ]
          ]
        ],
        'extra' => [
          'not_highlight' => true
        ],
        'title' => __('Unsubscribed emails')
    ];
  }

  public function getEndpoints() {
    return [
      'endpointExportUnsubscribers' => 'rest/V1/easymanage/exportunsubscribers',
      'endpointSaveUnsubscribers'   => 'rest/V1/easymanage/saveunsubscribers'
    ];
  }

  protected function getFetchButtonConfig() {
    return [
      'name' => self::UI_TYPE_BUTTON_FETCH,
      'params' => [
        'name' => 'export_unsubscribers',
        'type' => self::TABLE_INDEX,
        'label' => __('Export unsubscribed from store'),
        'endpoint' => 'endpointExportUnsubscribers'
      ]
    ];
  }

  protected function getSaveButtonConfig() {
    return [
      'name' => self::UI_TYPE_BUTTON_SAVE,
      'params' => [
        'name' => 'save_unsubscribers',
        'type' => self::TABLE_INDEX,
        'label' => __('Update unsubscribed users'),
        'endpoint' => 'endpointSaveUnsubscribers'
      ]
    ];
  }

  protected function getIconCode() {
    return '<svg enable-background="new 0 0 48 48" height="48px" id="Layer_3" version="1.1" viewBox="0 0 48 48" width="48px" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><g><circle cx="20.897" cy="10.092" fill="#241F20" r="10.092"/><path d="M25,38c0-6.415,4.651-11.732,10.763-12.794c-1.653-2.127-3.714-3.894-6.06-5.164   c-2.349,2.08-5.425,3.352-8.806,3.352c-3.366,0-6.431-1.261-8.774-3.321C6.01,23.409,1.834,30.102,1.834,37.818   c0,1.215,0.109,2.401,0.307,3.557h23.317C25.169,40.297,25,39.17,25,38z" fill="#241F20"/><path d="M38,28c-5.522,0-10,4.478-10,10s4.478,10,10,10s10-4.478,10-10S43.522,28,38,28z M43.679,41.558   l-2.121,2.121L38,40.121l-3.558,3.559l-2.121-2.122L35.879,38l-3.558-3.558l2.121-2.121L38,35.879l3.558-3.558l2.121,2.122   L40.121,38L43.679,41.558z" fill="#241F20"/></g></svg>';
  }
}
