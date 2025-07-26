<?php
use CRM_Swl_ExtensionUtil as E;

/**
 * Job.Updateswlid API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_Updateswlid_spec(&$spec) {
}

/**
 * Job.Updateswlid API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_job_Updateswlid($params) {
  $fieldInfo = CRM_Swl_Api4_Utils::getSwlSynFieldInfo();
  $sql = "select entity_id, {$fieldInfo['api4_swl_id']} as currrent_swlid, {$fieldInfo['api4_swl_id_update']} as new_swlid from {$fieldInfo['table']} where {$fieldInfo['api4_swl_id']} != {$fieldInfo['api4_swl_id_update']} and {$fieldInfo['api4_swl_id_update']} is not null";
  $dao = CRM_Core_DAO::executeQuery($sql);
  $count = 0;
  while ($dao->fetch()) {
    $contactParams = [];
    $contactParams['id'] = $dao->entity_id;
    $contactParams[$fieldInfo['api4_swl_id_key_name']] = $dao->new_swlid;
    $contactResult = civicrm_api3('Contact', 'create', $contactParams);
    $count++;
  }
  $returnValues = "Updated $count Contact for SWL ID update.";

  return civicrm_api3_create_success($returnValues, $params, 'Job', 'Updateswlid');
}
