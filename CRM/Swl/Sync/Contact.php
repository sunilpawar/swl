<?php

class CRM_Swl_Sync_Contact {
  private $client;
  private $logger;
  private $batchSize = 100;

  public function __construct() {
    $this->client = new CRM_Swl_Api_SwlClient();
    $this->logger = new CRM_Swl_Utils_Logger();
  }

  public function syncContacts($contactIds = NULL) {
    try {
      $contacts = $this->getContactsToSync($contactIds);
      $this->logger->info("Starting sync of " . count($contacts) . " contacts");

      $batches = array_chunk($contacts, $this->batchSize);
      $successCount = 0;
      $errorCount = 0;

      foreach ($batches as $batch) {
        foreach ($batch as $contact) {
          try {
            $this->syncSingleContact($contact);
            $successCount++;
            $this->markContactSynced($contact['id']);
          }
          catch (Exception $e) {
            $errorCount++;
            $this->logger->error("Failed to sync contact {$contact['id']}: " . $e->getMessage());
            $this->markContactSyncError($contact['id'], $e->getMessage());
          }
        }

        // Add small delay between batches to avoid overwhelming the API
        usleep(100000); // 0.1 seconds
      }

      $this->logger->info("Sync completed: {$successCount} success, {$errorCount} errors");
      return ['success' => $successCount, 'errors' => $errorCount];

    }
    catch (Exception $e) {
      $this->logger->error("Contact sync failed: " . $e->getMessage());
      throw $e;
    }
  }

  private function getContactsToSync($contactIds = NULL) {
    $whereClause = "c.is_deleted = 0 AND c.is_deceased = 0";
    $params = [];

    if ($contactIds) {
      $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
      $whereClause .= " AND c.id IN ({$placeholders})";
      $params = $contactIds;
    }
    else {
      // Only sync contacts modified since last sync
      $lastSync = Civi::settings()->get('swl_last_sync_time');
      if ($lastSync) {
        $whereClause .= " AND c.modified_date > ?";
        $params[] = $lastSync;
      }
    }

    $sql = "
      SELECT c.id, c.contact_type, c.first_name, c.last_name, c.organization_name,
             e.email, p.phone, a.street_address, a.city, a.postal_code, a.country_id,
             c.modified_date
      FROM civicrm_contact c
      LEFT JOIN civicrm_email e ON c.id = e.contact_id AND e.is_primary = 1
      LEFT JOIN civicrm_phone p ON c.id = p.contact_id AND p.is_primary = 1
      LEFT JOIN civicrm_address a ON c.id = a.contact_id AND a.is_primary = 1
      WHERE {$whereClause}
      ORDER BY c.modified_date ASC
    ";

    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $contacts = [];

    while ($dao->fetch()) {
      $contacts[] = [
        'id' => $dao->id,
        'contact_type' => $dao->contact_type,
        'first_name' => $dao->first_name,
        'last_name' => $dao->last_name,
        'organization_name' => $dao->organization_name,
        'email' => $dao->email,
        'phone' => $dao->phone,
        'address' => [
          'street_address' => $dao->street_address,
          'city' => $dao->city,
          'postal_code' => $dao->postal_code,
          'country_id' => $dao->country_id,
        ],
        'modified_date' => $dao->modified_date,
      ];
    }

    return $contacts;
  }

  private function syncSingleContact($contact) {
    // Transform CiviCRM contact data to SWL format
    $swlContact = $this->transformContactData($contact);

    // Check if contact already exists in SWL
    $existingContact = $this->findExistingContact($contact['id']);

    if ($existingContact) {
      return $this->client->updateContact($existingContact['swl_id'], $swlContact);
    }
    else {
      $result = $this->client->syncContact($swlContact);
      // Store the SWL ID for future updates
      $this->storeSwlContactId($contact['id'], $result['id']);
      return $result;
    }
  }

  private function transformContactData($contact) {
    return [
      'civicrm_id' => $contact['id'],
      'type' => strtolower($contact['contact_type']),
      'first_name' => $contact['first_name'],
      'last_name' => $contact['last_name'],
      'organization_name' => $contact['organization_name'],
      'email' => $contact['email'],
      'phone' => $contact['phone'],
      'address' => $contact['address'],
      'last_modified' => $contact['modified_date'],
    ];
  }

  private function markContactSynced($contactId) {
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_contact SET modified_date = NOW() WHERE id = ?",
      [$contactId]
    );
  }

  private function markContactSyncError($contactId, $error) {
    // Log error in custom table or activity
    // Implementation depends on your error tracking strategy
  }
}
