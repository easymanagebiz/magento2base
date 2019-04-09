<?php

namespace Develodesign\Easymanage\Model;

class RevisionProducts extends \Develodesign\Easymanage\Model\SaveProducts
implements \Develodesign\Easymanage\Api\RevisionProductsInterface{

    protected $_countAllRevFolders = 300;

    public function loadrevision() {
      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);

      if(empty($data) || empty($data['timestamp']) || empty($data['filename'])) {
        return;
      }
      $folder = $this->getRevisionFolderPath($data['timestamp']);
      $file   = $folder . DIRECTORY_SEPARATOR . $data['filename'];
      if(!is_file($file)) {
        return;
      }
      $data = [];
      $csvArr = array_map('str_getcsv', file($file));

      return [ $csvArr ];
    }

    public function allrevisions() {
      $folders = $this->getRevisionFolder();
      $basePath = $this->getRevisionFolderPathBase();
      $iterator = $this->getDirectoryIterator($basePath);
      $output = [];
      $count = 0;
      foreach($iterator as $fileObject){
        if($fileObject->isDir() && !$fileObject->isDot()) {

          $iterator = new \FilesystemIterator($fileObject->getPathname());
          $isDirEmpty = !$iterator->valid();
          if($isDirEmpty) {
            $count++;
            continue;
          }
          $output[] = $fileObject->getFilename();
          $count++;
        }

        if($count == $this->_countAllRevFolders) {
          break;
        }
      }
      return $output;
    }

    public function revision() {
      $postValues   = $this->request->getContent();
      $data         = \Zend_Json::decode($postValues);
      if(empty($data) || empty($data['timestamp'])) {
        return;
      }
      $timestamp = $data['timestamp'];
      $revisionDir = $this->getRevisionFolderPath($timestamp);
      if(!is_dir($revisionDir)) {
        return;
      }
      $output = [];
      $iterator = $this->getDirectoryIterator($revisionDir);
      foreach($iterator as $fileObject){
          if($fileObject->isFile()) {
              if(!is_file($revisionDir . '/' . $fileObject->getFilename())) {
                continue;
              }
              $output[$fileObject->getMTime()] = [
                'filename'  => $fileObject->getFilename(),
                'timestamp' => $timestamp,
                'filetime'  => $fileObject->getMTime()
              ];
          }
      }
      sort($output);
      return [
        $output
      ];
    }
}
