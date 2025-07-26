<?php
use CRM_Pppnet_ExtensionUtil as E;

/**
 * Job.Swldelete API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_Swldelete_spec(&$spec) {
}

/**
 * Job.Swldelete API
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
function civicrm_api3_job_Swldelete($params) {
  $query = "SELECT c.id, c.sort_name, d.email, uf.uf_id, d.user_id
        FROM civicrm_swl_user_details d
          INNER  JOIN civicrm_contact c ON (c.id = d.contact_id)
          LEFT JOIN civicrm_uf_match uf ON (uf.contact_id = c.id)
          WHERE d.mark_deleted = 1
        ";
  $dao = CRM_Core_DAO::executeQuery($query);
  $foundRows = $dao->N;
  while ($dao->fetch()) {
    // Delete SWL User
    if (CRM_Swl_Api4_Utils::deleteUser($dao->user_id) !== FALSE) {
      CRM_Core_Error::debug_log_message('SWL user deleted :' . $dao->user_id);
      // Delete Drupal User
      if ($dao->uf_id) {
        user_cancel([], $dao->uf_id, 'user_cancel_reassign');
        // user_cancel() initiates a batch process. Run it manually.
        $batch =& batch_get();
        $batch['progressive'] = FALSE;
        batch_process();
        CRM_Core_Error::debug_log_message('Drupal user deleted :' . $dao->uf_id);
      }

      // Delete CiviCRM Contact
      try {
        civicrm_api3('contact', 'delete', ['id' => $dao->id]);
        CRM_Core_Error::debug_log_message('CiviCRM Contact deleted :' . $dao->id);
        // Delete Mapping Record
        $deleteRecord = "DELETE FROM civicrm_swl_user_details WHERE user_id = " . $dao->user_id;
        CRM_Core_DAO::executeQuery($deleteRecord);
      }
      catch (CiviCRM_API3_Exception $e) {
        CRM_Core_Error::debug('Error : swl contact delete : ' . $dao->id, $e->getMessage());
      }
    }
  }

  return civicrm_api3_create_success("Deleted {$foundRows} Contacts.");
}
