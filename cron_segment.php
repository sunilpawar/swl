<?php

require_once  'cron_bootstrap.php';

class SwlCronSegmentSync {
  public $_id = '';

  function sync() {
    $config = CRM_Core_Config::singleton();
    echo "Start Syncing....\n";
    echo "Start Pulling swl user...\n";
    //CRM_Swl_Api4_Utils::getUser();
    echo "completed Pulling swl user.\n";
    $fieldInfo = CRM_Swl_Api4_Utils::getSwlSynFieldInfo();
    $sql = "select t.* from {$fieldInfo['table']} t inner join civicrm_contact c on c.id = t.{$fieldInfo['api4_swl_id']}";
    //$sql = "select * from civicrm_swl_user_details WHERE `contact_id` = 55075";

    $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
    $i = 1;
    while ($dao->fetch()) {
      $params = $dao->toArray();
      echo $i . '/' . $dao->N . "\n";
      $i++;

      $contactID = $dao->entity_id;
      $swlUserId = $params[$fieldInfo['api4_swl_id']];
      if (!empty($swlUserId) && !empty($contactID)) {
        $swlSegment = CRM_Swl_Api4_Utils::getUserSegment(['userId' => $swlUserId]);

        $crmSegment = CRM_Swl_Api4_Utils::getSegmentByMembership($contactID);
        $validateTiers = [1, 2, 9, 10, 11, 15];
        foreach ($swlSegment as $swlKey => $swlSegID) {
          if (in_array($swlSegID, $validateTiers)) {
            unset($swlSegment[$swlKey]); // remove segment which is going to update using api
          }
        }
        $swlSegment = array_merge($swlSegment, $crmSegment);
        $swlSegment = array_filter($swlSegment);
        if (empty($swlSegment)) {
          $swlSegment = ['9'];
        }
        if (!empty($swlSegment)) {
          $parameters = [];
          $parameters['userId'] = $swlUserId;
          $parameters['tiers'] = $swlSegment;
          CRM_Swl_Api4_Utils::updateUserSegment($parameters);
        }
      }
    }
  }
}

global $isCron;
$isCron = true;
$obj = new SwlCronSegmentSync();
$obj->sync();
$isCron = false;
echo "\ndone..........\n";

