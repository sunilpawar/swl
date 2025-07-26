<?php
/**
 * @file
 * This provides the Sync Push from CiviCRM to Swl form.
 */

class CRM_Swl_Form_Sync extends CRM_Core_Form {

  const QUEUE_NAME = 'swl-sync';
  const END_URL = 'civicrm/swl/sync';
  const END_PARAMS = 'state=done';

  /**
   * Function to pre processing
   *
   * @return None
   * @access public
   */
  function preProcess() {
    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');
    if ($state == 'done') {
      $stats = CRM_Core_BAO_Setting::getItem(CRM_Swl_Api4_Utils::SWL_SETTING_GROUP, 'push_stats');
      $groups = CRM_Swl_Api4_Utils::getGroupsToSync([], NULL);
      if (!$groups) {
        return;
      }
      $output_stats = [];
      foreach ($groups as $group_id => $details) {
        $swl_group_stats = $stats[$details['swl_group_id']];
        $output_stats[] = [
          'name' => $details['civigroup_title'],
          'stats' => $swl_group_stats,
        ];
      }
      $this->assign('stats', $output_stats);
    }
  }

  /**
   * Function to actually build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    // Create the Submit Button.
    $buttons = [
      [
        'type' => 'submit',
        'name' => ts('Sync Contacts'),
      ],
    ];

    // Add the Buttons.
    $this->addButtons($buttons);
  }

  /**
   * Function to process the form
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $runner = self::getRunner();
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    }
    else {
      CRM_Core_Session::setStatus(ts('Nothing to sync. Make sure swl settings are configured for the groups with enough members.'));
    }
  }

  static function getRunner($skipEndUrl = FALSE) {

    // Import SWL User with userid and email
    // CRM_Swl_Api4_Utils::getUser();

    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create([
      'name' => self::QUEUE_NAME,
      'type' => 'Sql',
      'reset' => TRUE,
    ]);

    // reset push stats
    CRM_Core_BAO_Setting::setItem([], CRM_Swl_Api4_Utils::SWL_SETTING_GROUP, 'push_stats');
    $stats = [];

    // We need to process one Group at a time.
    $groups = CRM_Swl_Api4_Utils::getGroupsToSync([], NULL);
    if (!$groups) {
      // Nothing to do.
      return FALSE;
    }

    // Each Group is a task.
    $groupCount = 1;
    foreach ($groups as $group_id => $details) {
      //if ($details['swl_group_id'] != 91) continue;
      $stats[$details['swl_group_id']] = [
        'hs_count' => 0,
        'c_count' => 0,
        'in_sync' => 0,
        'added' => 0,
        'removed' => 0,
        'group_id' => 0,
        'error_count' => 0
      ];

      $identifier = "Group " . $details['swl_group_name'] . " " . $details['civigroup_title'];

      $task = new CRM_Queue_Task(
        ['CRM_Swl_Form_Sync', 'syncPushGroup'],
        [$details['swl_group_id'], $identifier],
        "Preparing queue for $identifier"
      );

      // Add the Task to the Queue
      $queue->createItem($task);
    }

    // Setup the Runner
    $runnerParams = [
      'title' => ts('SWL Sync: CiviCRM to SWL'),
      'queue' => $queue,
      'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
    ];
    // Skip End URL to prevent redirect
    // if calling from cron job
    if ($skipEndUrl == TRUE) {
      unset($runnerParams['onEndUrl']);
    }
    $runner = new CRM_Queue_Runner($runnerParams);

    static::updatePushStats($stats);

    return $runner;
  }

  /**
   * Set up (sub)queue for syncing a SWL Group.
   */
  static function syncPushGroup(CRM_Queue_TaskContext $ctx, $swl_group_id, $identifier) {
    // Split the work into parts:
    // @todo 'force' method not implemented here.

    // Add the Swl collect data task to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Swl_Form_Sync', 'syncPushCollectSwl'],
      [$swl_group_id],
      "$identifier: Fetched data from Swl"
    ));
    // Add the CiviCRM collect data task to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Swl_Form_Sync', 'syncPushCollectCiviCRM'],
      [$swl_group_id],
      "$identifier: Fetched data from CiviCRM"
    ));

    // Add the removals task to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Swl_Form_Sync', 'syncPushRemove'],
      [$swl_group_id],
      "$identifier: Removed those who should no longer be subscribed"
    ));

    // Add the batchUpdate to the queue
    $ctx->queue->createItem(new CRM_Queue_Task(
      ['CRM_Swl_Form_Sync', 'syncPushAdd'],
      [$swl_group_id],
      "$identifier: Added new subscribers and updating existing data changes"
    ));

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect Swl data into temporary working table.
   */
  static function syncPushCollectSwl(CRM_Queue_TaskContext $ctx, $swl_group_id) {

    $stats[$swl_group_id]['hs_count'] = static::syncCollectSwl($swl_group_id);
    static::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   */
  static function syncPushCollectCiviCRM(CRM_Queue_TaskContext $ctx, $swl_group_id) {
    $stats[$swl_group_id]['c_count'] = static::syncCollectCiviCRM($swl_group_id);
    static::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Unsubscribe contacts that are subscribed at Swl but not in our Group.
   */
  static function syncPushRemove(CRM_Queue_TaskContext $ctx, $swl_group_id) {
    // Delete records have the same hash - these do not need an update.
    static::updatePushStats([$swl_group_id => ['in_sync' => static::syncIdentical()]]);

    // Now identify those that need removing from Swl.
    // @todo implement the delete option, here just the unsubscribe is implemented.
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT m.email, m.user_id
       FROM tmp_swl_push_s m
       WHERE NOT EXISTS (
         SELECT email FROM tmp_swl_push_c c WHERE c.email = m.email
       );");

    // Loop the $dao object to make a Group of emails to unsubscribe|delete from MC
    $batch = [];
    $stats[$swl_group_id]['removed'] = 0;
    while ($dao->fetch()) {
      $batch[$dao->user_id] = $dao->user_id;
      $stats[$swl_group_id]['removed']++;
    }
    if (!$batch) {
      // Nothing to do
      return CRM_Queue_Task::TASK_SUCCESS;
    }

    $result = CRM_Swl_Api4_Utils::removeContactFromGroup($swl_group_id, $batch);

    // Finally we can delete the emails that we just processed from the swl temp table.
    CRM_Core_DAO::executeQuery(
      "DELETE FROM tmp_swl_push_s
       WHERE NOT EXISTS (
         SELECT email FROM tmp_swl_push_c c WHERE c.email = tmp_swl_push_s.email
       );");

    static::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Batch update Swl with new contacts that need to be subscribed, or have changed data.
   *
   * This also does the clean-up tasks of removing the temporary tables.
   */
  static function syncPushAdd(CRM_Queue_TaskContext $ctx, $swl_group_id) {

    // @todo take the remaining details from tmp_swl_push_c
    // and construct a batchUpdate (do they need to be batched into 1000s? I can't recal).

    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM tmp_swl_push_c;");
    $stats = [];
    // Loop the $dao object to make a Group of emails to subscribe/update
    $batch = [];
    while ($dao->fetch()) {
      $batch = ['email' => $dao->email, 'first_name' => $dao->first_name, 'last_name' => $dao->last_name];
      $stats[$swl_group_id]['added']++;
      $result = CRM_Swl_Api4_Utils::createUserAddToGroup($swl_group_id, $batch, $dao->contact_id, TRUE);
    }


    $get_GroupId = CRM_Swl_Api4_Utils::getGroupsToSync([], $swl_group_id);
    $stats[$swl_group_id]['group_id'] = array_keys($get_GroupId);

    static::updatePushStats($stats);

    // Finally, finish up by removing the two temporary tables
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_swl_push_s;");
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_swl_push_c;");

    return CRM_Queue_Task::TASK_SUCCESS;
  }


  /**
   * Collect SWL data into temporary working table.
   */
  static function syncCollectSwl($swl_group_id) {
    // Create a temporary table.
    // Nb. these are temporary tables but we don't use TEMPORARY table because they are
    // needed over multiple sessions because of queue.

    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS tmp_swl_push_s;");
    $dao = CRM_Core_DAO::executeQuery(
      "CREATE TABLE tmp_swl_push_s (
        email VARCHAR(200),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        user_id int(10),
        hash CHAR(32),
        PRIMARY KEY (email, user_id))
        ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;");

    // Cheekily access the database directly to obtain a prepared statement.
    $listContacts = CRM_Swl_Api4_Utils::getGroupMembersDetails($swl_group_id, TRUE);
    $contacts = [];
    foreach ($listContacts as $userid => $dont) {
      $contact = CRM_Swl_Api4_Utils::usersGetProfileBlock($userid);
      $hash = md5($contact['email'] . $contact['first_name'] . $contact['last_name']);
      $insertSQL = "INSERT IGNORE INTO tmp_swl_push_s(email, first_name, last_name, user_id, hash) 
        VALUES( %1, %2, %3, %4, %5)";
      $paramsInsert = [
        1 => [$contact['email'], 'String'],
        2 => [$contact['first_name'], 'String'],
        3 => [$contact['last_name'], 'String'],
        4 => [$userid, 'Integer'],
        5 => [$hash, 'String'],
      ];
      CRM_Core_DAO::executeQuery($insertSQL, $paramsInsert);
    }

    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) c  FROM tmp_swl_push_s");
    $dao->fetch();

    return $dao->c;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   */
  static function syncCollectCiviCRM($swl_group_id) {
    // Nb. these are temporary tables but we don't use TEMPORARY table because they are
    // needed over multiple sessions because of queue.
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS tmp_swl_push_c;");
    $dao = CRM_Core_DAO::executeQuery("CREATE TABLE tmp_swl_push_c (
        contact_id INT(10) UNSIGNED NOT NULL,
        email_id INT(10) UNSIGNED NOT NULL,
        email VARCHAR(200),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        hash CHAR(32),
        PRIMARY KEY (email_id, email, hash))
        ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;");

    // Cheekily access the database directly to obtain a prepared statement.
    $db = $dao->getDatabaseConnection();
    $insert = $db->prepare('INSERT INTO tmp_swl_push_c VALUES(?, ?, ?, ?, ?, ?)');

    //create table for swl civicrm syn errors
    $dao = CRM_Core_DAO::executeQuery("CREATE TABLE IF NOT EXISTS swl_civicrm_syn_errors (
        id int(11) NOT NULL AUTO_INCREMENT,
        email VARCHAR(200),
        error VARCHAR(200),
        error_count int(10),
        group_id int(20),
        list_id VARCHAR(20),
        PRIMARY KEY (id)
        );");

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

    // Use a nice API call to get the information for tmp_swl_push_c.
    // The API will take care of smart groups.
    $result = civicrm_api3('Contact', 'get', [
      'is_deleted' => 0,
      'on_hold' => 0,
      'contact_type' => "Individual",
      'is_opt_out' => 0,
      'do_not_email' => 0,
      'group' => $grouping_group_ids,
      'return' => ['first_name', 'last_name', 'email_id', 'email', 'group'],
      'options' => ['limit' => 0],
    ]);

    foreach ($result['values'] as $contact) {
      $hash = md5($contact['email'] . $contact['first_name'] . $contact['last_name']);
      // run insert prepared statement
      $db->execute($insert, [$contact['id'], $contact['email_id'], $contact['email'], $contact['first_name'], $contact['last_name'], $hash]);
    }

    // Tidy up.
    $db->freePrepared($insert);
    // count
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) c  FROM tmp_swl_push_c");
    $dao->fetch();

    return $dao->c;
  }

  /**
   * Update the push stats setting.
   */
  static function updatePushStats($updates) {
    $stats = CRM_Core_BAO_Setting::getItem(CRM_Swl_Api4_Utils::SWL_SETTING_GROUP, 'push_stats');

    foreach ($updates as $swl_group_id => $settings) {
      foreach ($settings as $key => $val) {
        // avoid error details to store in civicrm_settings table
        // create sql error "Data too long for column 'value'" (for long array)
        if ($key == 'error_details') {
          continue;
        }
        $stats[$swl_group_id][$key] = $val;
      }
    }
    CRM_Core_BAO_Setting::setItem($stats, CRM_Swl_Api4_Utils::SWL_SETTING_GROUP, 'push_stats');
  }

  /**
   * Removes from the temporary tables those records that do not need processing.
   */
  static function syncIdentical() {
    // Delete records have the same hash - these do not need an update.
    // count
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(c.email) co FROM tmp_swl_push_s m
      INNER JOIN tmp_swl_push_c c ON m.email = c.email AND m.hash = c.hash;");
    $dao->fetch();
    $count = $dao->co;
    CRM_Core_DAO::executeQuery(
      "DELETE m, c
       FROM tmp_swl_push_s m
       INNER JOIN tmp_swl_push_c c ON m.email = c.email AND m.hash = c.hash;");

    return $count;
  }
}

