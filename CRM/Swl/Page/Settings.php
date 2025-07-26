<?php

class CRM_Swl_Page_Settings extends CRM_Core_Page {

  public function run() {
    if (CRM_Utils_Request::retrieve('action', 'String') == 'test') {
      $this->testConnection();
    }

    $this->assign('settings', $this->getSettings());
    $this->assign('syncStats', $this->getSyncStats());

    parent::run();
  }

  private function testConnection() {
    try {
      $client = new CRM_Swl_Api_SwlClient();
      $result = $client->makeRequest('GET', '/health');
      CRM_Core_Session::setStatus('Connection successful!', 'SWL Connection', 'success');
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus('Connection failed: ' . $e->getMessage(), 'SWL Connection', 'error');
    }
  }

  private function getSettings() {
    return [
      'api_endpoint' => Civi::settings()->get('swl_api_endpoint'),
      'sync_frequency' => Civi::settings()->get('swl_sync_frequency'),
      'last_sync' => Civi::settings()->get('swl_last_sync_time'),
    ];
  }

  private function getSyncStats() {
    // Return sync statistics
    return [
      'total_contacts' => CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_contact WHERE is_deleted = 0"),
      'synced_contacts' => CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_swl_sync_log WHERE status = 'success'"),
      'pending_contacts' => CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civicrm_swl_sync_queue"),
    ];
  }
}
