<?php

class CRM_Swl_Api_SwlClient {
  private $endpoint;
  private $apiKey;
  private $timeout;
  private $logger;

  public function __construct() {
    $this->endpoint = Civi::settings()->get('swl_api_endpoint');
    $this->apiKey = Civi::settings()->get('swl_api_key');
    $this->timeout = 30;
    $this->logger = new CRM_Swl_Utils_Logger();
  }

  public function syncContact($contactData) {
    return $this->makeRequest('POST', '/contacts', $contactData);
  }

  public function updateContact($contactId, $contactData) {
    return $this->makeRequest('PUT', "/contacts/{$contactId}", $contactData);
  }

  public function deleteContact($contactId) {
    return $this->makeRequest('DELETE', "/contacts/{$contactId}");
  }

  public function syncGroup($groupData) {
    return $this->makeRequest('POST', '/groups', $groupData);
  }

  private function makeRequest($method, $path, $data = NULL) {
    $url = rtrim($this->endpoint, '/') . $path;

    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $this->apiKey,
      'User-Agent: CiviCRM-SWL-Extension/1.0'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT => $this->timeout,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_SSL_VERIFYPEER => TRUE,
      CURLOPT_FOLLOWLOCATION => TRUE,
    ]);

    if ($data) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
      $this->logger->error("cURL error: {$error}");
      throw new Exception("API request failed: {$error}");
    }

    $decodedResponse = json_decode($response, TRUE);

    if ($httpCode >= 400) {
      $this->logger->error("API error {$httpCode}: " . $response);
      throw new Exception("API returned error {$httpCode}: " . ($decodedResponse['message'] ?? 'Unknown error'));
    }

    $this->logger->info("API request successful: {$method} {$path}");
    return $decodedResponse;
  }
}
