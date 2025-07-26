<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once  'cron_bootstrap.php';


class MigrationSync {
  public $_swlList = [];

  function sync() {
    echo "Start Syncing....";
    $config = CRM_Core_Config::singleton();

    // Import SWL User with userid and email
    CRM_Swl_Api4_Utils::getUser2();

  }

}

global $isCron;
$isCron = TRUE;
$obj = new MigrationSync();
$obj->sync();
$isCron = false;
echo "\ndone..........\n";

