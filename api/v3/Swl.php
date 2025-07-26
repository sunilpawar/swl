<?php

/**
  * Hubspot Get CiviCRM Group Hubspot settings (Hubspot List Id and Group)
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */ 
function civicrm_api3_job_swl_user_pull($params) {
  echo '1';
  $groups  = CRM_Swl_Api4_Utils::getUser();
  return civicrm_api3_create_success($groups);
}

/**
 * CiviCRM to Hubspot Sync
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */ 
function civicrm_api3_job_swl_sync($params) {
  $groups = CRM_Swl_Api4_Utils::getGroupsToSync([], NULL);

  $result = $pullResult = [];
  /*
  // Do pull first from mailchimp to CiviCRM
  $pullRunner = CRM_Swl_Form_Pull::getRunner($skipEndUrl = TRUE);
  if ($pullRunner) {
    $pullResult = $pullRunner->runAll();
  }
  */
  // Do push from CiviCRM to mailchimp
  $runner = CRM_Swl_Form_Sync::getRunner($skipEndUrl = TRUE);
  if ($runner) {
    $result = $runner->runAll();
  }

  if ($pullResult['is_error'] == 0 && $result['is_error'] == 0) {
    return civicrm_api3_create_success();
  }
  else {
    return civicrm_api3_create_error();
  }
}

