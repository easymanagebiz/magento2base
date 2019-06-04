<?php

namespace Develodesign\Easymanage\Block\Email;

class Products extends \Magento\Framework\View\Element\Template{

  const DEFAULT_LIMIT = 4;

  protected $_product;

  protected $_layout;

  protected $_styles;

  protected $_helperPrice;

  protected $productRepository;

  protected $categoryFactory;

  public function __construct(
    \Magento\Catalog\Block\Product\Context $context,
    \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
    \Magento\Framework\View\LayoutInterface $layout,
    \Develodesign\Easymanage\Block\Email\Styles $styles,
    \Magento\Framework\Pricing\Helper\Data $helperPrice,
    \Magento\Catalog\Model\CategoryFactory $categoryFactory,
    array $data = array()
  ) {
    $this->productRepository = $productRepository;
    $this->_layout = $layout;
    $this->_styles = $styles;
    $this->_helperPrice = $helperPrice;
    $this->categoryFactory = $categoryFactory;
    parent::__construct($context, $data);
  }

  public function getProductData($attrs = []) {
    if(empty($attrs['product_id']) && empty($attrs['product_sku'])) {
      return __('Empty product id and sku params') . '!';
    }

    $productIdentifier = empty($attrs['product_sku']) ? $attrs['product_id'] : $attrs['product_sku'];
    $isSku = empty($attrs['product_sku']) ? false : true;
    $this->loadProduct($productIdentifier, $isSku);
    if(!$this->getProduct() || !$this->getProduct()->getId()) {
      return __('Cant find product with identifier %1 and is SKU %2', $productIdentifier, $isSku);
    }

    return $this->_getProductHtml();
  }

  public function getCategoryData($attrs = []) {

    if(empty($attrs['category_id'])) {
        return __('Empty category id') . '!';
    }
    $category = $this->categoryFactory->create()
                  ->load($attrs['category_id']);
    if(!$category->getId()) {
      return __('Can not find category by it id') . ' ' . $attrs['category_id'];
    }
    $limit = empty($attrs['limit']) ? self::DEFAULT_LIMIT : intval($attrs['limit']);
    $collection = $category->getProductCollection()
      ;

    $output = '';
    $count = 0;

    foreach($collection as $productModel) {
      $this->loadProduct($productModel->getId());
      $output .= $this->_getProductHtml();
      $count++;
      if($count == $limit) {
        break;
      }
    }

    return $output;
  }

  public function getPrice() {
    return $this->_helperPrice->currency($this->getProduct()->getFinalPrice(), true, false);
  }

  protected function _getProductHtml() {
    $block = $this->_layout->createBlock(\Develodesign\Easymanage\Block\Email\Products::class);
    $block->setLayout($this->_layout);
    $block->setProduct($this->getProduct());
    $block->setTemplate('Develodesign_Easymanage::email/product.phtml');
    $html = $block->toHtml();


    return $html;

  }

  public function getStyles() {
    return $this->_styles;
  }

  public function getProduct() {
    return $this->_product;
  }

  protected function setProduct($product) {
    $this->_product = $product;
  }

  protected function loadProduct($productId, $isSku = false) {
    if($isSku) {
      $product = $this->productRepository->get($productId);
    }else{
      $product = $this->productRepository->getById($productId);
    }

    $this->setProduct( $product );
  }
}
