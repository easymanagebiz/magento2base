<?php

namespace Develodesign\Easymanage\Model\Addon\Attribute;

class Addon extends \Develodesign\Easymanage\Model\Addon\Base{

  const PARENT_UI_COMPONENET = 'menu_products';

  const ICON_NAME    = 'attribute';
  const TABLE_INDEX  = 'attributes_manager';


  public function getSidebarUpgrade($configAddons = []) {
    $this->_config = $configAddons;
    $this->_initVars();
    if(empty($this->_config[self::PARENT_VAR][self::PARENT_UI_COMPONENET])) {
      $this->_config[self::PARENT_VAR][self::PARENT_UI_COMPONENET] = [];
    }
    $this->_config[self::PARENT_VAR][self::PARENT_UI_COMPONENET][] = $this->getConfig();
    $this->_config[self::TABLES_VAR][self::TABLE_INDEX] = $this->getTables();
    $this->_config[self::ICONS_VAR] [self::ICON_NAME]   = $this->getIconCode();
    //$this->_config[self::ENDPOINTS_VAR] = array_merge($this->_config[self::ENDPOINTS_VAR], $this->getEndpoints());
    return $this->_config;
  }

  protected function getConfig() {
    return [
      'class' => 'uicomponent-secondary',
      'icon' => self::ICON_NAME,
      'label' => __('Attribute Updater'),
      'active_table' => self::TABLE_INDEX,
      'quick_menu' => true,
      'childs' => [
        $this->getTitleSidebar(),
        $this->getSearchInput(),
        $this->getMenuConfig(),
        //$this->getFetchButtonConfig(),
        $this->getSaveButtonConfig()
      ]
    ];
  }

  protected function getSearchInput() {
    return [
      'name' => self::UI_TYPE_SEARCH,
      'params' => [
        "id" => "search-attribute-products",
        "search_label" =>  __('Sku or name'),
        'translate_label' => true,
        //"title": GOOGLE_SHEETSTOCK_AND_PRICE,
        "type" => self::TABLE_INDEX,
        "endpoint" => "endpointSearch",
        "errorName" => "products",
        "nameData" => "dataProducts"
      ]
    ];
  }

  protected function getMenuConfig() {
    return [
      'name' => self::UI_TYPE_MENU,
      'params' => [
        'childs' => [
          [
            'class' => self::UI_MENU_CLASS_ADD_MORE_GRID,
            'static_icon' => self::UI_ICON_PLUS,
            'label' => __('Add sheet'),
            'type' => self::TABLE_INDEX,
            'translate_label' => true
          ],
          [
            'class' => self::UI_MENU_CLASS_COLUMN_MANAGER,
            'static_icon' => self::UI_ICON_COLUMNS,
            'label' => __('Manage columns'),
            'translate_label' => true,
            'columns_template' => 'mag2_fieldswithattributes'
          ],
          [
            'class' => self::UI_COMPONENT_LOAD_SECONDARY_PANEL,
            'static_icon' => self::UI_ICON_IMPORT,
            'label' => __('Export products from store'),
            'translate_label' => true,

            'childs' => $this->getExportProductsChilds()
          ],
          [
            'class' => self::UI_MENU_CLASS_LOG_VIEW,
            'static_icon' => self::UI_ICON_LOG,
            'label' => __('Log'),
            'translate_label' => true
          ]
        ]
      ]
    ];
  }

  protected function getExportProductsChilds() {
    return [
      [
        'name' => self::UI_TYPE_TITLE,
        'params' => [
          'label' => __('Export products from store'),
          'translate_label' => true,
          'static_icon' => self::UI_ICON_IMPORT
        ]
      ],
      [
        'name' => self::UI_TYPE_FORM,
        'params' => [
          'childs' => [
            [
              'name' => self::UI_TYPE_STORES,
              'params' => [
                'id' => 'from_stores'
              ]
            ],
            [
              'name' => self::UI_TYPE_CATEGORIES,
              'params' => [
                "id" => "from_categories",
                "parent_data" => "from_stores"
              ]
            ],
            $this->getFetchButtonConfig()
          ]
        ]
      ]
    ];
  }

  protected function getTitleSidebar() {
    return [
      'name'   => self::UI_TYPE_TITLE,
      'params' => [
        'label' => __('Attribute updater'),
        'icon'  => self::ICON_NAME
      ]
    ];
  }

  public function getTables() {
    return [
        'index' => self::TABLE_INDEX,
        'header' => [
          [
            'name' => 'sku',
            'label' => __('Sku'),
            'translate_label' => true,
            'validation' => [
              [
                'type' => 'required'
              ]
            ]
          ],

          [
            'name' => 'name',
            'label' => __('Name'),
            'translate_label' => true,
            'validation' => [
              [
                'type' => 'required'
              ]
            ],
            'width' => 250
          ],

          [
            'name' => 'store_code',
            'label' => __('Store code'),
            'translate_label' => true,
            'validation' => [],
            'not_default' => true
          ]

        ],
        'title' => __('Attribute updater')
    ];
  }

  protected function getFetchButtonConfig() {
    return [
      'name' => self::UI_TYPE_BUTTON_FETCH,
      'params' => [
        'name' => 'export_products',
        'type' => self::TABLE_INDEX,
        'label' => __('Fetch Data'),
        "endpoint" => "endpointExportProducts",
        "nameData" => "dataProducts",
        'translate_label' => true,
        'allow_append' => true,
      ]
    ];
  }

  protected function getSaveButtonConfig() {
    return [
      'name' => self::UI_TYPE_BUTTON_SAVE,
      'params' => [
        'name' => 'update_products',
        'type' => self::TABLE_INDEX,
        'endpoint' => 'endpointSave',
        "params_data" => "products",
        "endpoint_process" => "endpointProcess",
        'label' => 'Save',
        'translate_label' => true,
        'allow_highlighted' => true
      ]
    ];
  }

  public function getEndpoints() {

  }

  protected function getIconCode() {
    return '<svg id="Layer_1" style="enable-background:new 0 0 30 30;" version="1.1" viewBox="0 0 30 30" xml:space="preserve"><path d="M14,12V6c0-1.105-0.895-2-2-2H6C4.895,4,4,4.895,4,6v6c0,1.105,0.895,2,2,2h6C13.105,14,14,13.105,14,12z"/><path d="M18,14h6c1.105,0,2-0.895,2-2V6c0-1.105-0.895-2-2-2h-6c-1.105,0-2,0.895-2,2v6C16,13.105,16.895,14,18,14z"/><path d="M12,16H6c-1.105,0-2,0.895-2,2v6c0,1.105,0.895,2,2,2h6c1.105,0,2-0.895,2-2v-6C14,16.895,13.105,16,12,16z"/><path d="M16,18v6c0,1.105,0.895,2,2,2h6c1.105,0,2-0.895,2-2v-6c0-1.105-0.895-2-2-2h-6C16.895,16,16,16.895,16,18z"/></svg>';
  }
}
