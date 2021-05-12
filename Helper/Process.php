<?php

namespace Develodesign\Easymanage\Helper;

class Process extends \Magento\Framework\App\Helper\AbstractHelper
{

    const WORKING_FOLDER = 'dd_easymanage_process';
    const CHMOD_DIR      = 0775;

    const LOCKING_FILE_NAME = 'lockdata';

    protected $_dir;
    protected $_io;

    protected $_error;

    public function __construct(
      \Magento\Framework\Filesystem\DirectoryList $dir,
      \Magento\Framework\Filesystem\Io\File $io
    ) {
      $this->_dir = $dir;
      $this->_io = $io;
    }

    public function saveProcessData($data) {
      $revisionId = $this->generateRevisionId();
      $wokingFolder = $this->getWorkingFolder();

      $file = fopen($wokingFolder . DIRECTORY_SEPARATOR . $revisionId, "w");

      foreach ($data as $line){
          fputcsv($file, $line);
      }
      fclose($file);
      $this->updateLockFile($revisionId, [
        'step' => 0,
        'status' => 0,
        'total'  => count($data) - 1
      ]);
      return $revisionId;
    }

    public function getProcessData($revisionId, $countPerStep = 25) {
      $lockData  = $this->readLockFileData($revisionId);
      if(!$lockData) {
        $errorMessage = 'Updater process cant read lockdata for revision ID: ' . $revisionId;
        $this->setError( $errorMessage );
        return;
      };
      $step     = $lockData['step'];
      $saveData = $this->readProcessFile($revisionId);

      if(count($saveData) <= $step) {
        return [
          'total'           => $lockData['total'],
          'current_step'    => $lockData['step'],
          'reindex'         => !empty($lockData['reindex']) ? $lockData['reindex'] : null
        ];
      }

      $count      = 0;
      $dataOut    = [];
      $headers    = [];
      $total      = 0;

      foreach ($saveData as $line => $row) {
        if(count($headers) == 0) {
          $headers = $row;
          continue;
        }

        if($count < $step) {
          $count++;
          continue;
        }

        $dataOut[] = $row;
        $total++;
        if($total >= $countPerStep) {
          break;
        }
      }

      return [
        'headers' => $headers,
        'data'    => $dataOut,
        'total'   => $lockData['total'],
        'step'    => $lockData['step'],
        'status'  => !empty($lockData['status']) ? true : false,
        'reindex' => !empty($lockData['reindex']) ? true : false
      ];
    }

    public function updateLockFile($revisionId, $data) {

      /*
      $data = $data ? $data : [
        'status' => 0,
        'step' => 0,
        'not_found_sku' => $this->_notFoundSkus,
        'total' => count($this->_saveDataRows)
      ];
      */

      $currentData = $this->readLockFileData($revisionId);
      if($currentData) {
        $data = array_merge($currentData, $data);
      }

      $wokingFolder = $this->getWorkingFolder();
      $lockFileName = $this->getLockingFileName($revisionId);
      $file = fopen($wokingFolder . DIRECTORY_SEPARATOR . $lockFileName, "w");
      $saveString = \Zend_Json::encode($data);
      fwrite($file, $saveString);
      fclose($file);
      return true;
    }

    public function removeWorkingFiles($revisionId) {
      $wokingFolder = $this->getWorkingFolder();
      $filePathWorkingFile = $wokingFolder . DIRECTORY_SEPARATOR . $revisionId;
      unlink($filePathWorkingFile);
      $filePathLockFile = $wokingFolder . DIRECTORY_SEPARATOR . $this->getLockingFileName($revisionId);
      unlink($filePathLockFile);
    }

    protected function readLockFileData($revisionId) {
      $wokingFolder = $this->getWorkingFolder();
      $lockFileName = $this->getLockingFileName($revisionId);
      $fullPath = $wokingFolder . DIRECTORY_SEPARATOR . $lockFileName;
      if(!is_file($fullPath)) {
        return;
      }
      $file = fopen($fullPath, "r");
      $contents = fread($file, filesize($fullPath));
      fclose($file);
      return \Zend_Json::decode( $contents );
    }

    protected function readProcessFile($revisionId) {
      $wokingFolder = $this->getWorkingFolder();
      $filePath     = $wokingFolder . DIRECTORY_SEPARATOR . $revisionId;
      return array_map('str_getcsv', file($filePath));
    }

    protected function getLockingFileName($revisionId) {
      return self::LOCKING_FILE_NAME . '_' . $revisionId;
    }

    protected function generateRevisionId() {
      return uniqid();
    }

    protected function getWorkingFolderPath() {
      return $this->_dir->getPath('pub') . DIRECTORY_SEPARATOR
                  .self::WORKING_FOLDER;
    }

    protected function getWorkingFolder() {
      $filePath = $this->getWorkingFolderPath();
      if (!is_dir($filePath)) {
        try{
          $this->_io->mkdir($filePath, self::CHMOD_DIR);
        }catch(\Exception $e) {
          return false;
        }
      }
      return $filePath;
    }

    protected function getDirectoryIterator($path) {
        return new \DirectoryIterator($path);
    }

    protected function setError($error) {
      $this->_error = $error;
    }

    public function getError() {
      return $this->_error;
    }
}
