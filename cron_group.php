<?php

require_once  'cron_bootstrap.php';


class SwlCronGroupSync {
  public $_swlList = [];

  function sync() {
    echo "Start Syncing....";
    $config = CRM_Core_Config::singleton();

    // Import SWL User with userid and email
    //CRM_Swl_Api4_Utils::getUser();


    // We need to process one Group at a time.
    //$gids = [356];
    // $gids = [396];
    $groups = CRM_Swl_Api4_Utils::getGroupsToSync([], NULL);
    //$groups = CRM_Swl_Api4_Utils::getGroupsToSync($gids, NULL);
    echo '<pre>'; print_r($groups); echo '</pre>';
    if (!$groups) {
      // Nothing to do.
      return FALSE;
    }
    foreach ($groups as $group_id => $details) {
      echo "\n=================================\n" . $identifier = "Group " . $details['swl_group_name'] . " <=> " . $details['civigroup_title'];
      CRM_Core_Error::debug_var('group sync identifier', $identifier);
      $this->syncPushCollectSwl($details['swl_group_id']);
      $this->syncPushCollectCiviCRM($details['swl_group_id']);
      // $this->syncPushRemove($details['swl_group_id']);
      $this->syncPushAdd($details['swl_group_id']);
      echo "\n******************************\n";
    }
  }

  function syncPushCollectSwl($swl_group_id) {
    echo "\nCollect SWL Group...\n";
    // Create a temporary table.
    // Nb. these are temporary tables but we don't use TEMPORARY table because they are
    // needed over multiple sessions because of queue.

    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS tmp_swl_group_push_s;");
    $dao = CRM_Core_DAO::executeQuery(
      "CREATE TABLE tmp_swl_group_push_s (
        user_id int(10)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;");
    // Cheekily access the database directly to obtain a prepared statement.
    $listContacts = CRM_Swl_Api4_Utils::getGroupMembersDetails($swl_group_id, TRUE);
    echo "\n\t Group Members : SWL ID ( " . $swl_group_id . " ) =>  Count : " . count($listContacts) . "\n";
    //echo '<pre>$listContacts '; print_r($listContacts); echo '</pre>';;
    $this->_swlList = $listContacts;
    $contacts = [];
    foreach ($listContacts as $userid => $userGroupIndex) {
      $insertSQL = "INSERT IGNORE INTO tmp_swl_group_push_s(user_id) VALUES( %1)";
      $paramsInsert = [1 => [$userid, 'Integer']];
      CRM_Core_DAO::executeQuery($insertSQL, $paramsInsert);
    }
  }

  function syncPushCollectCiviCRM($swl_group_id) {
    echo "\nCollect CiviCRM Group ...\n";
    // Nb. these are temporary tables but we don't use TEMPORARY table because they are
    // needed over multiple sessions because of queue.
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS tmp_swl_group_push_c;");
    $dao = CRM_Core_DAO::executeQuery("CREATE TABLE tmp_swl_group_push_c (
        contact_id INT(10) UNSIGNED NOT NULL,
        swl_id VARCHAR(200),
        PRIMARY KEY (contact_id, swl_id))
        ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;");

    // Cheekily access the database directly to obtain a prepared statement.
    $db = $dao->getDatabaseConnection();
    $insert = $db->prepare('INSERT INTO tmp_swl_group_push_c VALUES(?, ?)');

    // We need to know what groupings we have maps to.
    // We only care about CiviCRM groups that are mapped to this MC List:
    $mapped_groups = CRM_Swl_Api4_Utils::getGroupsToSync([], $swl_group_id);

    // There used to be a distinction between the handling of 'normal' groups
    // and smart groups. But now the API will take care of this.
    $grouping_group_ids = [];

    foreach ($mapped_groups as $group_id => $details) {
      CRM_Contact_BAO_GroupContactCache::loadAll($group_id);
      $grouping_group_ids[$group_id] = 1;
    }

    // Use a nice API call to get the information for tmp_swl_group_push_c.
    // The API will take care of smart groups.
    $fieldInfo = CRM_Swl_Api4_Utils::getSwlSynFieldInfo();
    $result = civicrm_api3('Contact', 'get', [
      'is_deleted' => 0,
      'on_hold' => 0,
      'contact_type' => "Individual",
      'is_opt_out' => 0,
      'do_not_email' => 0,
      'group' => $grouping_group_ids,
      'return' => ['first_name', 'last_name', 'email_id', 'email', 'group', $fieldInfo['api4_swl_id_key_name']],
      'options' => ['limit' => 0],
    ]);
    echo "\n\tCiviCRM Group Count : " . count($result['values']) . "\n";
    foreach ($result['values'] as $contact) {
      // run insert prepared statement
      $db->execute($insert, [$contact['id'], $contact[$fieldInfo['api4_swl_id_key_name']]]);
    }

    // Tidy up.
    $db->freePrepared($insert);
  }

  function syncPushRemove($swl_group_id) {
    $fieldInfo = CRM_Swl_Api4_Utils::getSwlSynFieldInfo();
    echo "\nsyncPushRemove....\n";
    // Now identify those that need removing from Swl.
    // @todo implement the delete option, here just the unsubscribe is implemented.
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT s.user_id
         FROM tmp_swl_group_push_s s
         WHERE s.user_id NOT IN (
           SELECT d.user_id FROM tmp_swl_group_push_c c inner join {$fieldInfo['table']} d on c.swl_id = d.{$fieldInfo['api4_swl_id']}
         );"
    );

    // Loop the $dao object to make a Group of emails to unsubscribe|delete from MC
    $batch = [];
    $stats[$swl_group_id]['removed'] = 0;
    while ($dao->fetch()) {
      $swlUserGroupIndex = $this->_swlList[$dao->user_id];
      $batch[$swlUserGroupIndex] = $dao->user_id;
      $stats[$swl_group_id]['removed']++;
    }
    if (!empty($batch)) {
      echo "\n\t Remove Contact form SWL Members Count : SWL Group ID: " . $swl_group_id . " => Count : " . count($batch) . "\n";
      $result = CRM_Swl_Api4_Utils::removeContactFromGroup($swl_group_id, $batch);
    }
    // Finally we can delete the emails that we just processed from the swl temp table.
    CRM_Core_DAO::executeQuery(
      "DELETE FROM tmp_swl_group_push_s
        WHERE user_id NOT IN (
        SELECT d.user_id FROM tmp_swl_group_push_c c inner join {$fieldInfo['table']} d on c.email  = d.email
      );");

    // Delete contact from civicrm table those present in swl table then only sync new contact that are not in swl
    CRM_Core_DAO::executeQuery(
      "DELETE FROM tmp_swl_group_push_c
        WHERE email IN (
        SELECT d.email FROM tmp_swl_group_push_s s inner join {$fieldInfo['table']} d on s.user_id  = d.user_id
      );");
  }

  function syncPushAdd($swl_group_id) {
    echo "\nsyncPushAdd...\n";
    // @todo take the remaining details from tmp_swl_group_push_c
    // and construct a batchUpdate (do they need to be batched into 1000s? I can't recal).

    $dao = CRM_Core_DAO::executeQuery("SELECT tc.*, c.* FROM tmp_swl_group_push_c tc inner join civicrm_contact c on c.id = tc.contact_id;");
    $stats = [];
    // Loop the $dao object to make a Group of emails to subscribe/update
    $batch = [];
    while ($dao->fetch()) {
      $batch = ['email' => $dao->email, 'first_name' => $dao->first_name, 'last_name' => $dao->last_name];
      echo "\n\tNew Contact and Add to Group (SWL Groupd id {$swl_group_id}, Civi Contact ID : {$dao->contact_id} )" . $dao->email . "\n";
      $stats[$swl_group_id]['added']++;
      $result = CRM_Swl_Api4_Utils::createUserAddToGroup($swl_group_id, $batch, $dao->contact_id, TRUE);
print_r($result);
    }

    // Finally, finish up by removing the two temporary tables
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_swl_group_push_s;");
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_swl_group_push_c;");

    return CRM_Queue_Task::TASK_SUCCESS;
  }
}

global $isCron;
$isCron = TRUE;
//$isCron = false;
$obj = new SwlCronGroupSync();
$obj->sync();
$isCron = false;
echo "\ndone..........\n";

