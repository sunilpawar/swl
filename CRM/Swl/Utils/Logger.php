<?php

class CRM_Swl_Utils_Logger {
  private $logFile;

  public function __construct() {
    $this->logFile = CRM_Core_Config::singleton()->configAndLogDir . 'swl_sync.log';
  }

  public function info($message) {
    $this->log('INFO', $message);
  }

  public function error($message) {
    $this->log('ERROR', $message);
  }

  public function warning($message) {
    $this->log('WARNING', $message);
  }

  private function log($level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$level}: {$message}" . PHP_EOL;

    file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // Also log to CiviCRM's system log
    CRM_Core_Error::debug_log_message($logEntry, FALSE, 'swl_sync');
  }
}
