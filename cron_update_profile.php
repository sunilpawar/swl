<?php

require_once  'cron_bootstrap.php';

class SwlCronProfileSync {
  public $_id = '';

  function sync() {
    $config = CRM_Core_Config::singleton();
    echo "Start Syncing....";
    echo "Start Pulling swl user...";
    //CRM_Swl_Api4_Utils::getUser();
    echo "completed Pulling swl user.";
    $sql = "select * from civicrm_swl_user_details";
    //$sql = "select * from civicrm_swl_user_details where `contact_id` = 27649";
    //$sql = "SELECT * FROM `civicrm_swl_user_details` LIMIT 20000 OFFSET 1900";
    $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
    $i = 1;
    $params = [];
    while ($dao->fetch()) {
      echo $i . "\n";
      $i++;

      $contactID = $dao->contact_id; // civicrm id
      $swlUserId = $dao->user_id; // swl id
      if (empty($contactID)) {
        $contactIDs = CRM_Swl_Api4_Utils::getContactFromEmail($dao->email);
        $contactID = reset($contactIDs);
        if (empty($contactID)) {
          echo "delete record\n";
          $deleteRecord = "DELETE FROM civicrm_swl_user_details WHERE user_id = " . $dao->user_id;
          CRM_Core_DAO::executeQuery($deleteRecord, CRM_Core_DAO::$_nullArray);
          continue;
        }
        // add contact id if not present
        echo "update record for contact id\n";
        $sql = "update civicrm_swl_user_details set contact_id = $contactID where email = %1";
        $emailParams = [1 => [$dao->email, 'String']];
        CRM_Core_DAO::executeQuery($sql, $emailParams);
      }
      if (!empty($swlUserId) && !empty($contactID)) {
        //$contacts = CRM_Swl_Api4_Utils::usersGetProfileBlock($swlUserId);
        CRM_Swl_Api4_Utils::userEditProfileBlockStatus($swlUserId, $params, $contactID);

      }
    }
  }
}

global $isCron;
$isCron = true;
$obj = new SwlCronProfileSync();
$obj->sync();
$isCron = false;
echo "\ndone..........\n";

