<?php


class CiviSwlApi4 {
  var $baseURL;
  static $version = '4.0';
  protected $accessToken;
  protected $headers;
  protected $client;

  function __construct($token, $baseUrl) {
    $this->baseURL = $baseUrl;
    $this->accessToken = $token;
    $this->headers = [
      'Authorization' => 'Bearer ' . $token,
      'Accept' => 'application/json',
    ];
    $this->client = new GuzzleHttp\Client(['base_uri' => $baseUrl]);
  }

  /**
   * Delete Function
   * @param $component
   * @return array
   */
  function delete($component) {
    try {
      $response = $this->client->request('DELETE', '/services/4.0/' . $component,
        [
          'headers' => $this->headers,
        ]);

      $contents = $response->getBody()->getContents();
      $output = json_decode($contents, TRUE);

      return ['status' => 'ok', 'result' => $output];
    }
    catch (\GuzzleHttp\Exception\ClientException $exception) {
      return ['status' => 'failed', 'result' => [$exception->getMessage()]];
    }
    catch (Exception $exception) {
      return ['status' => 'failed', 'result' => [$exception->getMessage()]];
    }
  }

  /**
   *
   * GET Function
   * @param $component
   * @param array $data
   * @return array
   */
  function get($component, $data = []) {
    try {
      $response = $this->client->request('GET', '/services/4.0/' . $component,
        [
          'headers' => $this->headers,
          'query' => $data

        ]);

      $contents = $response->getBody()->getContents();
      $output = json_decode($contents, TRUE);

      return ['status' => 'ok', 'result' => $output];
    }
    catch (\GuzzleHttp\Exception\ClientException $exception) {
      return ['status' => 'failed', 'result' => [$exception->getMessage()]];
    }
    catch (Exception $exception) {
      return ['status' => 'failed', 'result' => [$exception->getMessage()]];
    }
  }

  /**
   * Update Function
   * @param $component
   * @param array $data
   * @return array
   */
  function update($component, $data = []) {
    try {
      $response = $this->client->request('PUT', '/services/4.0/' . $component,
        [
          'headers' => $this->headers,
          'json' => $data
        ]);

      $contents = $response->getBody()->getContents();
      $output = json_decode($contents, TRUE);

      return ['status' => 'ok', 'result' => $output];
    }
    catch (\GuzzleHttp\Exception\ClientException $exception) {
      return ['status' => 'failed', 'result' => [$exception->getMessage()]];
    }
    catch (Exception $exception) {
      return ['status' => 'failed', 'result' => [$exception->getMessage()]];
    }
  }

  /**
   * Create Function
   * @param $component
   * @param array $data
   * @return array
   */
  function create($component, $data = []) {
    try {
      $response = $this->client->request('POST', '/services/4.0/' . $component,
        [
          'headers' => $this->headers,
          'json' => $data
        ]);
      $contents = $response->getBody()->getContents();
      $output = json_decode($contents, TRUE);

      return ['status' => 'ok', 'result' => $output];
    }
    catch (\GuzzleHttp\Exception\ClientException $exception) {
      return [
        'status' => 'failed',
        'message' => $exception->getResponse()->getBody()->getContents(),
        'result' => $exception->getMessage()
      ];
    }
    catch (Exception $exception) {
      return [
        'status' => 'failed',
        'message' => $exception->getResponse()->getBody()->getContents(),
        'result' => $exception->getMessage()
      ];
    }

  }

  // User section Start

  /**
   * @param array $properties
   * @return bool|mixed
   */
  public function updateUser(array $properties) {
    $action = '/users/' . $properties['userId'];
    unset($properties['userId']);
    $result = $this->update($action, $properties);
    if ($result['status'] == 'ok') {
      return $result['result'];
    }

    return FALSE;
  }

  /**
   * @param array $properties
   * @return bool|mixed
   */
  public function createUser(array $properties) {
    $action = '/users/';
    $result = $this->create($action, $properties);
    if ($result['status'] == 'ok') {
      return $result['result'];
    }

    return FALSE;
  }

  /**
   * @param $swlUserId
   * @return bool
   */
  public function deleteUser($swlUserId) {
    $action = '/users/' . $swlUserId;
    $result = $this->delete($action);
    if ($result['status'] == 'ok') {
      return TRUE;
    }

    return FALSE;
  }

  public function getUser(array $properties) {
    $action = '/users/' . $properties['userId'];
    $result = $this->get($action, $properties);
    if ($result['status'] == 'ok') {
      return $result['result'];
    }

    return FALSE;
  }

  public function getUsers(array $properties) {
    $action = '/users';
    $result = $this->get($action, $properties);
    if ($result['status'] == 'ok') {
      return $result['result'];
    }

    return FALSE;
  }
  // User Section ends

  // Group Section Starts
  /**
   * @param array $properties
   * @param string $accessLevel
   * @return array
   */
  public function groupGetList(array $properties = [], $accessLevel = '') {
    $action = '/groups';
    $result = $this->get($action, $properties);
    $data = [];
    if ($result['status'] == 'ok') {
      foreach ($result['result'] as $group) {
        if (!empty($accessLevel) && $accessLevel != $group['access']) {
          continue;
        }
        $data[$group['id']] = urldecode($group['name']);
      }
    }

    return $data;
  }

  /**
   * @param $groupID
   * @param null $status
   * @return array
   */
  public function groupGetMembers($groupID, $properties = []) {
    $action = '/groups/' . $groupID . '/members';
    $properties['format'] = 'json';
    $result = $this->get($action, $properties);
    $data = [];
    if ($result['status'] == 'ok') {
      foreach ($result['result'] as $member) {
        if (!empty($status) && $status != $member['status']) {
          continue;
        }
        $data[$member['userId']] = $member['id'];
      }
    }

    return $data;
  }

  /**
   * @param array $properties
   * @return bool
   */
  public function groupEditMembers(array $properties) {
    $action = '/groups/' . $properties['groupId'] . '/members';
    unset($properties['groupId']);
    $result = $this->create($action, $properties);
    if ($result['status'] == 'ok') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * @param array $properties
   * @return bool
   */
  public function groupDeleteMembers(array $properties) {
    $action = '/groups/' . $properties['groupId'] . '/members/' . $properties['userMemberId'];
    $result = $this->delete($action);
    if ($result['status'] == 'ok') {
      return TRUE;
    }

    return FALSE;
  }

  // Group Section Ends

  // Profile Section Starts
  /**
   * @param array $properties
   * @return array
   */
  public function usersGetProfileBlock(array $properties) {
    $action = '/users/' . $properties['userId'] . '/profile/fields';
    $result = $this->get($action, $properties);

    $data = [];
    if ($result['status'] == 'ok') {
      foreach ($result['result'] as $field) {
        $value = NULL;
        if (!empty($field['data']) && is_array($field['data'])) {
          if (isset($field['display']) && !is_array($field['display'])) {
            $values = explode(',', urldecode($field['display']));
            if (!empty($values)) {
              $value = array_map('trim', $values);
            }
          }
          elseif (is_array($field['data'])) {
            $value = $field['data'];
          }
        }
        elseif (!empty($field['data']) && !is_array($field['data']) && !empty($field['display'])) {
          $value = urldecode($field['display']);
        }
        elseif (!empty($field['data'])) {
          $value = urldecode($field['data']);
        }
        $data[$field['id']] = $value;
      }
    }

    return $data;
  }

  public function usersGetProfileFieldOptions(array $properties) {
    $action = '/profile/fields';
    $result = $this->get($action, $properties);
    $data = [];
    foreach ($result['result'] as $field) {
      if (!empty($field['optionsCollection'])) {
        if (is_array($field['optionsCollection'])) {
          $data[$field['id']] = array_map('urldecode', $field['optionsCollection']);
        }
        else {
          $options = explode(',', $field['optionsCollection']);
          $data[$field['id']] = array_flip($options);
        }
      }
    }

    return $data;
  }

  public function userEditProfileBlock(array $properties) {
    $action = '/users/' . $properties['userId'] . '/profile/fields';
    $result = $this->update($action, $properties);
    if ($result['status'] == 'ok') {
      return TRUE;
    }

    return FALSE;
  }

  // Profile Section Ends

  // Segment Section Starts

  /**
   * @param array $properties
   * @return bool
   */
  public function getUserSegment(array $properties) {
    $result = $this->getUser($properties + ['embed' => 'tiers']);
    if ($result !== FALSE) {
      return $result['tiers'];
    }

    return FALSE;
  }

  public function updateUserSegment(array $properties) {
    $action = '/users/' . $properties['userId'] . '/tiers';
    $result = $this->update($action, $properties);
    if ($result['status'] == 'ok') {
      return TRUE;
    }

    return FALSE;
  }

}