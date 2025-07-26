<?php

class Group extends SwlApi {

  public function groupGetList(array $properties, $accessLevel = '') {
    $action = 'groups.getList';
    $properties['format'] = 'json';
    $output = $this->$action($properties);
    $result = json_decode($output, true);
    $data = array();
    if ($result['status'] == 'ok') {
      foreach ($result['group'] as $group) {
        if (!empty($accessLevel) && $accessLevel != $group['access']) {
          continue;
        }
        $data[$group['group_id']]['group_id'] = $group['group_id'];
        $data[$group['group_id']]['name'] = $group['name'];
        $data[$group['group_id']]['access'] = $group['access'];
        $data[$group['group_id']]['group_id'] = $group['group_id'];
      }
    }
    return $data;
  }


  public function groupGetMembers(array $properties, $status = '') {
    $action = 'groups.getMembers';
    $properties['format'] = 'json';
    $output = $this->$action($properties);
    $result = json_decode($output, true);
    $data = array();
    if ($result['status'] == 'ok') {
      if ($result['group']['@attributes']['count'] == 1) {
        foreach ($result['group']['members']['member'] as $member) {
          if (!empty($status) && $status != $member['user_status']) {
            continue;
          }
          $data[$result['group']['members']['member']['user_id']]= $result['group']['members']['member']['user_id'];
        }
      } else {
        foreach ($result['group']['members']['member'] as $member) {
          if (!empty($status) && $status != $member['user_status']) {
            continue;
          }
          $data[$member['user_id']]= $member['user_id'];
        }
      }
    }
    return $data;
  }

  public function groupEditMembers(array $properties) {
    $action = 'groups.editMembers';
    $properties['format'] = 'json';
    $output =  $this->$action($properties);
    $result = json_decode($output, true);
    if ($result['status'] == 'ok') {
      return true;
    }
    return false;
  }
  
  public function groupDeleteMembers(array $properties) {
    $action = 'groups.deleteMembers';
    $properties['format'] = 'json';
    $output =  $this->$action($properties);
    $result = json_decode($output, true);
    if ($result['status'] == 'ok') {
      return true;
    }
    return false;
  }  
  
}