<?php

require_once  'cron_bootstrap.php';


class SwlCronSync {
  public $_id = '';

  function sync() {
    echo "Start Syncing....";
    $config = CRM_Core_Config::singleton();
    $sql = "select * from civicrm_swl_contactsync_cron where status = '1'";
    $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
    $i = 1;
    while ($dao->fetch()) {
      echo $i . '<br/>';
      $i++;
      //echo '<pre>'; print_r($dao); echo '</pre>';
      $updateRecord = "update civicrm_swl_contactsync_cron set status = 2 where id =" . $dao->id;
      CRM_Core_DAO::executeQuery($updateRecord, CRM_Core_DAO::$_nullArray);

      $contactID = $dao->contact_id;
      $sql = "SELECT email FROM civicrm_email e inner join civicrm_contact c on ( c.id = e.contact_id) WHERE  e.contact_id = {$contactID} AND e.is_primary = 1 and c.is_deleted = 0";
      $email = CRM_Core_DAO::singleValueQuery($sql);
      if (empty($email)) {
        echo "<br/>No Valid Contact<br/>";
        $deleteRecord = "DELETE FROM civicrm_swl_contactsync_cron WHERE id = " . $dao->id;
        CRM_Core_DAO::executeQuery($deleteRecord, CRM_Core_DAO::$_nullArray);
        continue;
      }

      $sql = "SELECT first_name, last_name FROM civicrm_contact WHERE  id = {$contactID}  and is_deleted = 0";
      $contactDAO = CRM_Core_DAO::executeQuery($sql);
      $firstName = $lastName = '';
      while ($contactDAO->fetch()) {
        $firstName = $contactDAO->first_name;
        $lastName = $contactDAO->last_name;
      }


      $swl_group_id = '';
      $params = ['email' => $email];
      $params['first_name'] = $firstName;
      $params['last_name'] = $lastName;
      $result = CRM_Swl_Api4_Utils::createUserAddToGroup($swl_group_id, $params, $contactID);
      $deleteRecord = "DELETE FROM civicrm_swl_contactsync_cron WHERE id = " . $dao->id;
      CRM_Core_DAO::executeQuery($deleteRecord, CRM_Core_DAO::$_nullArray);

      // Check CMS Email, update it
      // $ufID = CRM_Core_BAO_UFMatch::getUFId($contactID);
      if (FALSE && $ufID) {
        $sql = "UPDATE civicrm_uf_match uf SET uf.uf_name = '{$email}' WHERE uf.uf_id = $ufID ";
        CRM_Core_DAO::executeQuery($sql);
        db_update('users')
          ->fields([
            'mail' => $email,
          ])
          ->condition('uid', $ufID)
          ->execute();
      }
    }
  }
}

$obj = new SwlCronSync();
$obj->sync();
echo "\ndone..........\n";

