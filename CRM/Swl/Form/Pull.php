<?php
/**
 * @file
 * This provides the Sync Pull from SWL to CiviCRM form.
 */

class CRM_Swl_Form_Pull extends CRM_Core_Form {

  const QUEUE_NAME = 'mc-pull';
  const END_URL    = 'civicrm/swl/pull';
  const END_PARAMS = 'state=done';

  function preProcess() {
    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');
    if ($state == 'done') {
      $stats = CRM_Core_BAO_Setting::getItem(CRM_Swl_Api4_Utils::SWL_SETTING_GROUP, 'pull_stats');

      $groups = CRM_Swl_Api4_Utils::getGroupsToSync(array(), null);
      if (!$groups) {
        return;
      }

      $output_stats = array();
      foreach ($groups as $group_id => $details) {
        $list_stats = $stats[$details['list_id']];
        $output_stats[] = array(
          'name' => $details['civigroup_title'],
          'stats' => $list_stats,
        );
      }
      $this->assign('stats', $output_stats);
    }
  }

  public function buildQuickForm() {
    // Create the Submit Button.
    $buttons = array(
      array(
        'type' => 'submit',
        'name' => ts('Import'),
      ),
    );
    // Add the Buttons.
    $this->addButtons($buttons);
  }

  public function postProcess() {
    $setting_url = CRM_Utils_System::url('civicrm/swl/settings', 'reset=1',  TRUE, NULL, FALSE, TRUE);
    $runner = self::getRunner();
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to pull. Make sure swl settings are configured in the <a href='.$setting_url.'>setting page</a>.'));
    }
  }

  static function getRunner($skipEndUrl = FALSE) {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));

    // reset pull stats.
    CRM_Core_BAO_Setting::setItem(Array(), CRM_Swl_Api4_Utils::SWL_SETTING_GROUP, 'pull_stats');
    $stats = array();

    // We need to process one list at a time.
    $groups = CRM_Swl_Api4_Utils::getGroupsToSync(array(), null);
    if (!$groups) {
      // Nothing to do.
      return FALSE;
    }
    // Each list is a task.
    $listCount = 1;
    foreach ($groups as $group_id => $details) {
      $stats[$details['list_id']] = array(
        'hs_count' => 0,
        'c_count' => 0,
        'in_sync' => 0,
        'added' => 0,
        'removed' => 0,
      ) ;

      $identifier = "List " . $listCount++ . " " . $details['civigroup_title'];

      $task  = new CRM_Queue_Task(
        array ('CRM_Swl_Form_Pull', 'syncPullList'),
        array($details['list_id'], $identifier),
        "Preparing queue for $identifier"
      );

      // Add the Task to the Queue
      $queue->createItem($task);
    }
    // Setup the Runner
		$runnerParams = array(
      'title' => ts('Import From SWL'),
      'queue' => $queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
    );
		// Skip End URL to prevent redirect
		// if calling from cron job
		if ($skipEndUrl == TRUE) {
			unset($runnerParams['onEndUrl']);
		}
    $runner = new CRM_Queue_Runner($runnerParams);
    static::updatePullStats($stats);
    return $runner;
  }

  static function syncPullList(CRM_Queue_TaskContext $ctx, $listID, $identifier) {
    // Add the swl collect data task to the queue
    $ctx->queue->createItem( new CRM_Queue_Task(
      array('CRM_Swl_Form_Pull', 'syncPullCollectSwl'),
      array($listID),
      "$identifier: Fetching data from Swl (can take a mo)"
    ));

    // Add the CiviCRM collect data task to the queue
    $ctx->queue->createItem( new CRM_Queue_Task(
      array('CRM_Swl_Form_Pull', 'syncPullCollectCiviCRM'),
      array($listID),
      "$identifier: Fetching data from CiviCRM"
    ));

    // Remaining people need something updating.
    $ctx->queue->createItem( new CRM_Queue_Task(
      array('CRM_Swl_Form_Pull', 'syncPullUpdates'),
      array($listID),
      "$identifier: Updating contacts in CiviCRM"
    ));

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect Swl data into temporary working table.
   */
  static function syncPullCollectSwl(CRM_Queue_TaskContext $ctx, $listID) {

    // Shared process.
    $count = CRM_Swl_Form_Sync::syncCollectSwl($listID);
    static::updatePullStats(array( $listID => array('hs_count'=>$count)));

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   */
  static function syncPullCollectCiviCRM(CRM_Queue_TaskContext $ctx, $listID) {

    // Shared process.
    $stats[$listID]['c_count'] =  CRM_Swl_Form_Sync::syncCollectCiviCRM($listID);

    // Remove identicals
    $stats[$listID]['in_sync'] = CRM_Swl_Form_Sync::syncIdentical();

    static::updatePullStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * New contacts from Swl need bringing into CiviCRM.
   */
  static function syncPullUpdates(CRM_Queue_TaskContext $ctx, $listID) {
    // Prepare the groups that we need to update
    $stats[$listID]['added'] = $stats[$listID]['removed'] = 0;

    // We need the membership group and any groups mapped to interest groupings with the allow MC updates option set.
    $membership_group_id = FALSE;
    $updatable_grouping_groups = array();
    foreach (CRM_Swl_Api4_Utils::getGroupsToSync(array(), $listID) as $groupID => $details) {
      // This group is one that we allow Swl to update CiviCRM with.
      $updatable_grouping_groups[$groupID] = $details;
    }

    // First update the first name and last name of the contacts we
    // already matched. See issue #188.
    CRM_Swl_Api4_Utils::updateGuessedContactDetails();

    // all swl table
    $dao = CRM_Core_DAO::executeQuery( "SELECT m.*, c.contact_id
      FROM tmp_swl_push_s m
      LEFT JOIN tmp_swl_push_c c ON m.email = c.email
      ;");

    // Loop the $dao object creating/finding contacts in CiviCRM.
    $groupContactRemoves = $groupContact = array();
    while ($dao->fetch()) {
      $params = array(
        'FNAME' => $dao->first_name,
        'LNAME' => $dao->last_name,
        'EMAIL' => $dao->email,
      );
      
      // We don't know yet who this is.
      // Update/create contact.
      $contact_id = CRM_Swl_Api4_Utils::updateContactDetails($params);
      
      
      if($contact_id) {

        // Ensure the contact is in the membership group.
        if (!$dao->contact_id) {
          // This contact was not found in the CiviCRM table.
          // Therefore they are not in the membership group.
          // (actually they could have an email problem as well, but that's OK).
          // Add them into the membership group.
          $groupContact[$membership_group_id][] = $contact_id;
          $civi_groupings = array();
          $stats[$listID]['added']++;
        }
        
        // unpack the group membership reported by MC
        $mc_groupings = unserialize($dao->groupings);

        // Now sort out the grouping_groups for those we are supposed to allow updates for
        foreach ($updatable_grouping_groups as $groupID => $details) {
          // Should this person be in this grouping:group according to MC?
          if (!empty($mc_groupings[ $details['grouping_id'] ][ $details['group_id'] ])) {
            // They should be in this group.
            if (empty($civi_groupings[ $details['grouping_id'] ][ $details['group_id'] ])) {
              // But they're not! Plan to add them in.
              $groupContact[$groupID][] = $contact_id;
            }
          }
          else {
            // They should NOT be in this group.
            if (!empty($civi_groupings[ $details['grouping_id'] ][ $details['group_id'] ])) {
              // But they ARE. Plan to remove them.
              $groupContactRemoves[$groupID][] = $contact_id;
            }
          }
        }
      }
    }


    // And now, what if a contact is not in the swl list? We must remove them from the membership group.
    // I changed the query below; I replaced a 'WHERE NOT EXISTS' construct
    // by an outer join, in the hope that it will be faster (#188).
    $dao = CRM_Core_DAO::executeQuery( "SELECT c.contact_id
      FROM tmp_swl_push_c c
      LEFT OUTER JOIN tmp_swl_push_s m ON m.email = c.email
      WHERE m.email IS NULL;");
    // Loop the $dao object creating/finding contacts in CiviCRM.
    while ($dao->fetch()) {
      $groupContactRemoves[$membership_group_id][] =$dao->contact_id;
      $stats[$listID]['removed']++;
    }
    // Log group contacts which are going to be added to CiviCRM
    CRM_Core_Error::debug_var( 'SWL $groupContact= ', $groupContact);

    // FIXME: dirty hack setting a variable in session to skip post hook
		require_once 'CRM/Core/Session.php';
    $session = CRM_Core_Session::singleton();
    $session->set('skipPostHook', 'yes');
    
    if ($groupContact) {
      // We have some contacts to add into groups...
      foreach($groupContact as $groupID => $contactIDs ) {
        CRM_Contact_BAO_GroupContact::addContactsToGroup($contactIDs, $groupID, 'Admin', 'Added');
      }
    }

    // Log group contacts which are going to be removed from CiviCRM
    CRM_Core_Error::debug_var( 'SWL $groupContactRemoves= ', $groupContactRemoves);
    
    if ($groupContactRemoves) {
      // We have some contacts to add into groups...
      foreach($groupContactRemoves as $groupID => $contactIDs ) {
        CRM_Contact_BAO_GroupContact::removeContactsFromGroup($contactIDs, $groupID, 'Admin', 'Removed');
      }
    }
    
    // FIXME: unset variable in session
		$session->set('skipPostHook', '');

    static::updatePullStats($stats);
    // Finally, finish up by removing the two temporary tables
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_swl_push_s;");
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_swl_push_c;");

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Update the pull stats setting.
   */
  static function updatePullStats($updates) {
    $stats = CRM_Core_BAO_Setting::getItem(CRM_Swl_Api4_Utils::SWL_SETTING_GROUP, 'pull_stats');
    foreach ($updates as $listId=>$settings) {
      foreach ($settings as $key=>$val) {
        $stats[$listId][$key] = $val;
      }
    }
    CRM_Core_BAO_Setting::setItem($stats, CRM_Swl_Api4_Utils::SWL_SETTING_GROUP, 'pull_stats');
  }

}

