<?php

namespace Develodesign\Easymanage\Helper;

class Images extends \Magento\Framework\App\Helper\AbstractHelper{

  const COUNT_IMAGES_TO_CALL = 50;
  const PUB_MEDIA_IMPORT_DIR = 'pub/media/import';

  protected $_directory;

  protected $_multiCurl;

  protected $_multiCurlCalls = [];

  protected $_folder;

  protected $_images = [];

  protected $_errorImages = [];

  protected $_allowedExtensions = [
    'png', 'jpg', 'jpeg', 'gif'
  ];

  public function __construct(
          \Magento\Framework\App\Helper\Context $context,
          \Magento\Framework\Filesystem\DirectoryList $directory
          )
  {
      parent::__construct($context);
      $this->_directory = $directory;
  }

  public function init($folder) {
    $this->initCurl();
    $this->setFolder($folder);
  }

  protected function initCurl() {
    $this->_multiCurl = curl_multi_init();
  }

  public function finish() {
    if($this->_multiCurl) {
      curl_multi_close($this->_multiCurl);
    }
  }

  public function setFolder($folder) {
    $this->_folder = $folder;
  }

  public function fetchImages($noReOpen = false) {
    if(!$this->_multiCurl) {
      $this->initCurl();
    }

    if(count($this->_images)) {

      $directory = $this->getPubImportDirectory();
      $fullDir = $directory . DIRECTORY_SEPARATOR . $this->_folder;
      if(!is_dir($fullDir)) {
        mkdir($fullDir, 0775);
      }

      foreach($this->_images as $imageNewName => $_fecthUrl) {

        $this->_multiCurlCalls[$imageNewName] = curl_init();
        curl_setopt($this->_multiCurlCalls[$imageNewName], CURLOPT_URL, $_fecthUrl);
        curl_setopt($this->_multiCurlCalls[$imageNewName], CURLOPT_HEADER, 0);
        curl_setopt($this->_multiCurlCalls[$imageNewName], CURLOPT_RETURNTRANSFER,1);
        curl_setopt($this->_multiCurlCalls[$imageNewName], CURLOPT_TIMEOUT, 60);
        curl_multi_add_handle($this->_multiCurl, $this->_multiCurlCalls[$imageNewName]);
      }

      $running = null;
      //execute the handles
      do {

          curl_multi_exec($this->_multiCurl, $running);
          curl_multi_select($this->_multiCurl);

      } while ($running > 0);


      foreach($this->_multiCurlCalls as $imageNewName => $ch) {
          $contentImage = curl_multi_getcontent($ch);

          $savefile = fopen($fullDir . DIRECTORY_SEPARATOR . $imageNewName, 'w');
          fwrite($savefile, $contentImage);
          fclose($savefile);
          curl_multi_remove_handle($this->_multiCurl, $ch);
      }

     foreach($this->_images as $imageNewName => $_fecthUrl) {

       if(!is_file($fullDir . DIRECTORY_SEPARATOR . $imageNewName)) {
         $this->setErrorImage($this->_folder . DIRECTORY_SEPARATOR . $imageNewName);
         continue;
       }

       try{
         list($width, $height, $type, $attr) = getimagesize($fullDir . DIRECTORY_SEPARATOR . $imageNewName);
         if(!$width || $width < 1) {
           $this->setErrorImage($this->_folder . DIRECTORY_SEPARATOR . $imageNewName);
           continue;
         }
       }catch (\Exception $e) {
         $this->setErrorImage($this->_folder . DIRECTORY_SEPARATOR . $imageNewName);
         continue;
       }

     }

     //erase images to fetch
     $this->_images = [];
     $this->_multiCurlCalls = [];

     /* close and resetup multicurl var */
     curl_multi_close($this->_multiCurl);

     $this->_multiCurl = null;
     //unset( $this->_multiCurl );
     if(!$noReOpen) {
       $this->_multiCurl = curl_multi_init();
       sleep(1);
     }
    }
  }

  public function clearErrorImages() {
    $this->_errorImages = [];
  }

  public function getErrorImages() {
    return $this->_errorImages;
  }

  protected function setErrorImage($imageName) {
    $this->_errorImages[] = $imageName;
  }

  public function setImage($image){
    $imageName = $image;
    if($this->checkIsUrl($image)) {
      $added = $this->_isExists( $image );
      if($added) {
        return $this->_folder . '/' . $added;
      }
      $imageName = $this->getImageName($image);
      if(!$imageName) {
        return '';
      }
      $this->_images[ $imageName ] = $image;
      $this->createImages();
      return $this->_folder . '/' . $imageName;
    }
    return $imageName;
  }

  protected function createImages() {
    if(count($this->_images) >= self::COUNT_IMAGES_TO_CALL) {
        $this->fetchImages();
    }
  }

  protected function _isExists($image) {
    foreach($this->_images as $imageNewName => $fetchUrl) {
      if($fetchUrl == $image) {
        return $imageNewName;
      }
    }
  }

  protected function getImageName($image) {
    $extension = $this->getExtension($image);
    if($extension) {
      return $this->getUniqueId() . '.' . $extension;
    }
  }

  protected function getUniqueId() {
    return uniqid(rand(), true);
  }

  protected function getExtension($image) {
    $path      = parse_url($image, PHP_URL_PATH);
    $extension = pathinfo($path, PATHINFO_EXTENSION);

    if(in_array($extension, $this->_allowedExtensions)) {
      return $extension;
    }

  }

  protected function checkIsUrl($image) {
    if(strstr($image, 'http://') || strstr($image, 'https://')) {
      return true;
    }
  }

  protected function getPubImportDirectory() {
    $directory = $this->_directory->getRoot() . DIRECTORY_SEPARATOR . self::PUB_MEDIA_IMPORT_DIR;
    if(!is_dir($directory)) {
      mkdir($directory);
    }
    return $directory;
  }

}
