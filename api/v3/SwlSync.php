<?php

function civicrm_api3_swl_sync_contacts($params) {
  try {
    $sync = new CRM_Swl_Sync_Contact();
    $result = $sync->syncContacts($params['contact_ids'] ?? NULL);

    return civicrm_api3_create_success($result, $params, 'SwlSync', 'contacts');
  }
  catch (Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }
}

function _civicrm_api3_swl_sync_contacts_spec(&$spec) {
  $spec['contact_ids'] = [
    'name' => 'contact_ids',
    'title' => 'Contact IDs',
    'description' => 'Array of contact IDs to sync (optional)',
    'type' => CRM_Utils_Type::T_INT,
    'api.multiple' => 1,
  ];
}

function civicrm_api3_swl_sync_groups($params) {
  try {
    $sync = new CRM_Swl_Sync_Group();
    $result = $sync->syncGroups($params['group_ids'] ?? NULL);

    return civicrm_api3_create_success($result, $params, 'SwlSync', 'groups');
  }
  catch (Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }
}
