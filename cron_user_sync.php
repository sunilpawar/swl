<?php

require_once  'cron_bootstrap.php';


class SwlCronGroupSync {
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
$obj = new SwlCronGroupSync();
$obj->sync();
$isCron = false;
echo "\ndone..........\n";

