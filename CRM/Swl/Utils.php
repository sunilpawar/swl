<?php
//use \Swl;
require_once 'packages/OAuth.php';
require_once 'packages/swl_api.php';

class CRM_Swl_Utils {
  private static $_singleton = NULL;
  private static $_getListAll = NULL;
  static $_profileBlock = NULL;
  static $_customValue = NULL;
  static $_profileLabel = NULL;
  const SWL_SETTING_GROUP = 'Swl Preferences';

  /**
   * Construct a HelpTab
   */
  private function __construct() {
  }

  static function swl() {
    global $isCron;
    $apiKey = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_key');
    $secret = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_secret');
    $base = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_base');
    $ssosecret = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_ssosecret');
    if ($isCron) {
      $apiKey = 'acd81730d014c7f8704febdacb9ae0c7';
      $secret = 'afb0ae5fa227e12e091da36d0e1b990e';
    }
    $consumer = new SwlOAuthConsumer($apiKey, $secret);
    $api = new CiviSwlApi($base, $consumer);

    return $api;
  }

  static function swlparams($parameters = [], $user_id = 1) {
    date_default_timezone_set('America/San_Francisco');
    //ini_set('date.timezone', 'America/San_Francisco');
    $ssosecret = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_ssosecret');
    $time = time();
    //if (!empty($parameters['member_user_id'])) {
    //$user_id = $parameters['member_user_id'];
    //}
    $sso_hash = md5($ssosecret . $user_id . $time);
    $parameters['hash'] = $sso_hash;
    $parameters['timestamp'] = $time;

    return $parameters;
  }

  static function swlGetList() {
    $swl = CRM_Swl_Utils::swl();
    $listAll = $swl->groupGetList(CRM_Swl_Utils::swlparams());

    return $listAll;
  }

  static function groupGetMembers($groupId) {
    $swl = CRM_Swl_Utils::swl();
    $parameters = [];
    $parameters['group_id'] = $groupId;
    $parameters['authed_user_id'] = 1;
    $listAll = $swl->groupGetMembers(CRM_Swl_Utils::swlparams($parameters));

    return $listAll;
  }

  static function usersGetProfileBlock($userid) {
    $swl = CRM_Swl_Utils::swl();
    $parameters = [];
    $parameters['user_id'] = $userid;
    $parameters['block_id'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);
    $listAll = $swl->usersGetProfileBlock(CRM_Swl_Utils::swlparams($parameters));

    $swl_first_name_id = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block_first_name', NULL, FALSE);
    $swl_last_name_id = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block_last_name', NULL, FALSE);
    $swl_email_id = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block_email', NULL, FALSE);
    $profile = [];
    $profile['first_name'] = $listAll[$swl_first_name_id];
    $profile['last_name'] = $listAll[$swl_last_name_id];
    $profile['email'] = $listAll[$swl_email_id];
    if (empty($profile['email'])) {
      $sql = "SELECT email FROM civicrm_swl_user_details WHERE user_id = {$userid}";
      $profile['email'] = CRM_Core_DAO::singleValueQuery($sql);
    }
    $profile['userid'] = $userid;

    return $profile;
  }

  static function addUpdateContactFromGroup($groupId, $userId) {
    if (empty($groupId) || empty($userId)) {
      return;
    }
    $swl = CRM_Swl_Utils::swl();
    $parameters['group_id'] = $groupId;
    $parameters['member_user_id'] = $userId;
    $parameters['authed_user_id'] = 1;
    $result = $swl->groupEditMembers(CRM_Swl_Utils::swlparams($parameters));

    return $result;
  }

  static function removeContactFromGroup($groupId, $userIds) {
    $swl = CRM_Swl_Utils::swl();
    $parameters['group_id'] = $groupId;
    foreach ($userIds as $userId) {
      $parameters['member_user_id'] = $userId;
      $parameters['authed_user_id'] = 1;
      $result = $swl->groupDeleteMembers(CRM_Swl_Utils::swlparams($parameters));
    }
  }

  static function getUserSegment($parameters) {
    $swl = CRM_Swl_Utils::swl();
    $result = $swl->getUserSegment(CRM_Swl_Utils::swlparams($parameters));

    return $result;
  }

  static function updateUser($parameters) {
    $swl = CRM_Swl_Utils::swl();
    $result = $swl->updateUser(CRM_Swl_Utils::swlparams($parameters));

    return $result;
  }


  static function createUser($parameters) {
    $swl = CRM_Swl_Utils::swl();
    $parameters['email_address'] = $parameters['email'];
    $result = $swl->createUser(CRM_Swl_Utils::swlparams($parameters));

    return $result;
  }

  static function userEditProfileBlock($userid, $params, $contactID) {
    //$swl = CRM_Swl_Utils::swl();
    $parameters = [];
    //============
    $result = civicrm_api3('Contact', 'getsingle', [
      'sequential' => 1,
      'return' => "id,first_name,last_name,middle_name,current_employer,prefix_id,suffix_id,job_title,custom_8,custom_6,custom_10,custom_34,custom_38,custom_39,custom_40,custom_41,custom_11,custom_12,custom_13,custom_104,custom_105",
      'id' => $contactID,
      'api.Email.getsingle' => ['is_primary' => 1],
    ]);

    $websiteResult = civicrm_api3('Website', 'get', [
      'sequential' => 1,
      'contact_id' => $contactID,
      'options' => ['limit' => 1],
    ]);
    if (!empty($websiteResult['values'])) {
      $result['website'] = $websiteResult['values']['0']['url'];
    }
    else {
      $result['website'] = '';
    }
    $swlProfileLabel = self::getSWLProfileLabel();
    $civiCustomLabel = self::getCustomFieldOptions();
    $prefix = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'prefix_id');
    $suffix = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'suffix_id');
    $prefix = array_flip($prefix);
    $suffix = array_flip($suffix);
    $blockID = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);
    $blockMapping = self::getProfleBlockMapping($blockID);
    $finalData = [];
    foreach ($blockMapping as $fID => $cName) {
      if (!empty($result[$cName]) && !empty($civiCustomLabel[$cName])) {
        if (is_array($result[$cName])) {
          $cOption = $swlLabel = [];
          foreach ($result[$cName] as $cValue) {
            $cLabel = $civiCustomLabel[$cName][$cValue];
            $cOption[] = $cLabel;
            $swlLabel[] = $swlProfileLabel[$fID][$cLabel];
          }
          //$finalData[$fID. '_label'] = $cOption;
          if (!empty($swlLabel)) {
            $finalData[$fID] = implode(',', $swlLabel);
          }
        }
        else {
          $cLabel = $civiCustomLabel[$cName][$result[$cName]];
          //$finalData[$fID .'_label'] = $cLabel;
          $finalData[$fID] = $swlProfileLabel[$fID][$cLabel];
        }
      }
      else {
        if ($cName == 'prefix_id') {
          $cName = 'individual_prefix';
          if (!empty($result[$cName])) {
            $finalData[$fID] = $swlProfileLabel[$fID][$result[$cName]];
          }
          else {
            $finalData[$fID] = '0';
          }
        }
        elseif ($cName == 'suffix_id') {
          $cName = 'individual_suffix';
          if (!empty($result[$cName])) {
            $finalData[$fID] = $swlProfileLabel[$fID][$result[$cName]];
          }
          else {
            $finalData[$fID] = '0';
          }
        }
        elseif ($cName == 'email') {
          $cName = 'api.Email.getsingle';
          if (!empty($result[$cName])) {
            $finalData[$fID] = $result[$cName]['email'];
          }
        }
        else {
          $finalData[$fID] = $result[$cName];
        }
      }
    }
    //$finalData = array_filter($finalData);
    $finalData = array_filter($finalData, function ($value) {
      return ($value !== NULL && $value !== FALSE);
    });


    $addressGet = "SELECT street_address, supplemental_address_1,city, postal_code,civicrm_country.name as 'country',civicrm_state_province.abbreviation as 'state', geo_code_1, geo_code_2 FROM civicrm_address
    left join civicrm_country on (civicrm_country.id = civicrm_address.country_id)
    left join civicrm_state_province on (civicrm_state_province.id = civicrm_address.state_province_id)
    where civicrm_address.is_primary = 1 and civicrm_address.contact_id = $contactID";

    $dao = CRM_Core_DAO::executeQuery($addressGet);
    $address = [];
    while ($dao->fetch()) {
      $address['street_address'] = $dao->street_address;
      $address['supplemental_address_1'] = $dao->supplemental_address_1;
      $address['city'] = $dao->city;
      $address['state'] = $dao->state;
      $address['postal_code'] = $dao->postal_code;
      $address['country'] = self::getNearestMatch('country', $dao->country);
    }

    $address = array_map('urlencode', $address);
    if (!empty($address['geo_code'])) {
      $address['geo_code'] = urldecode($address['geo_code']);
    }
    //$finalData = array();
    if (!empty($address)) {
      $finalData['238'] = implode(',', $address);
    }

    // Membership Details

    // if user not found in UFMatch then get it from membership type
    $membershipStatus = CRM_Member_PseudoConstant::membershipStatus(NULL, "(is_current_member = 1 )", 'id');
    $membershipTypes = [];
    if ($contactID && !empty($membershipStatus)) {
      // check membership available for logged in user
      $sql = 'SELECT mt.id, mt.name FROM civicrm_membership m inner join civicrm_membership_type mt on (mt.id = m.membership_type_id)
             WHERE m.is_test= 0
            AND m.status_id IN (' . implode(',', array_keys($membershipStatus)) . ') AND m.contact_id = ' . $contactID;
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $membershipTypes[$dao->id] = $dao->name;
      }
      if (!empty($membershipTypes)) {
        $finalData['270'] = implode(',', $membershipTypes);
      }
      if (!empty($membershipTypes) && array_key_exists('1', $membershipTypes)) {
        $finalData['273'] = 'Active';
      }
      else {
        $finalData['273'] = 'InActive';
      }
    }
    else {
      $finalData['273'] = 'InActive';
    }


    $parameters['block_id'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);

    $parameters['target_user_id'] = $userid;
    foreach ($finalData as $key => $value) {
      $parameters['userdata'] = $value;
      $parameters['field_id'] = $key;
      if (!empty($parameters['field_id'])) {
        $swl = CRM_Swl_Utils::swl();
        $result = $swl->userEditProfileBlock(CRM_Swl_Utils::swlparams($parameters));
      }
    }

    return TRUE;
  }

  static function batchUsersGetProfileBlock($userID) {
    $sql = "select swl.user_id as swlid, swl.email, e.contact_id
      from civicrm_swl_user_details swl inner join civicrm_email e on ( e.email = swl.email)
      inner join civicrm_contact c on (c.id = e.contact_id and c.is_deleted = 0)
      and e.is_primary = 1 and swl.user_id = {$userID}";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $block = [];
    $parameters = [];
    $parameters['block_id'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);
    $blockMapping = self::getProfleBlockMapping($parameters['block_id']);
    [$optionvalues, $customfields] = CRM_Swl_Utils::getCustomValueLabel();
    $pseudoconstants = [
      'prefix_id' => 'contact',
      'suffix_id' => 'contact',
      'country_id' => 'address',
      'country_code' => CRM_Core_PseudoConstant::countryIsoCode(), // no need to flip this one.
      'state_province_id' => array_merge(array_flip(CRM_Core_PseudoConstant::stateProvince(FALSE, FALSE)),
        array_flip(CRM_Core_PseudoConstant::stateProvinceAbbreviation())),
    ];
    foreach ($pseudoconstants as $key => $source) {
      if (!is_string($source))
        continue;
      $result = civicrm_api3($source, 'getoptions', ['field' => $key]);
      if (!$result['is_error']) {
        $pseudoconstants[$key] = array_flip($result['values']);
      }
    }
    $pseudoconstants['country_code']['USA'] = 1228; // US
    //echo '<pre>'; print_r($customfields);
    //echo '<pre>$optionvalues'; print_r($optionvalues); exit;
    while ($dao->fetch()) {
      $profile = [];
      $swl = CRM_Swl_Utils::swl();
      $parameters['user_id'] = $dao->swlid;
      $listAll = $swl->usersGetProfileBlock(CRM_Swl_Utils::swlparams($parameters));
      foreach ($blockMapping as $fieldid => $field_name) {
        $profile[$field_name] = $listAll[$fieldid];
        if (!in_array($field_name, ['country', 'state_province']) && array_key_exists($field_name, $pseudoconstants)) {
          $value = $listAll[$fieldid];
          if (is_array($value)) {
            $value = reset($value);
          }
          if (array_key_exists($value, $pseudoconstants[$field_name])) {
            $profile[$field_name] = $pseudoconstants[$field_name][$value];
          }
          else {
            $profile[$field_name] = '';
          }
        }
        if (array_key_exists($fieldid, $listAll)) {
          // check custom field types
          if (substr($field_name, 0, 7) == 'custom_') {
            $field_id = substr($field_name, 7);
            $value = $listAll[$fieldid];
            if (!isset($customfields[$field_id]))
              continue;
            // if boolean, reformat accordingly
            if ($customfields[$field_id]['data_type'] == 'Boolean') {
              if (in_array(strtolower($value), ['y', 'yes', 'true']))
                $profile[$field_name] = 1;
              elseif (in_array(strtolower($value), ['n', 'no', 'false']))
                $profile[$field_name] = 0;
            }

            // if enum, check value is OK
            if ($customfields[$field_id]['option_group_id']) {
              $group_id = $customfields[$field_id]['option_group_id'];
              if (array_key_exists($group_id, $optionvalues)) {
                $multi = is_array($value);
                if (!$multi)
                  $value = [$value];
                foreach ($value as &$val) {
                  if (!array_key_exists($val, $optionvalues[$group_id])) {
                    $option = array_search($val, $optionvalues[$group_id]);
                    if ($option !== FALSE) {
                      $val = $option;
                    }
                  }
                }
                if (!$multi)
                  $profile[$field_name] = $value = reset($value);
              }
            }
            if ($customfields[$field_id]['html_type'] == 'Radio' && $customfields[$field_id]['data_type'] == 'String') {
              if (is_array($profile[$field_name])) {
                $profile[$field_name] = reset($profile[$field_name]);
              }
            }
            if (!empty($profile[$field_name]) && is_array($profile[$field_name])) {
              $profile[$field_name] = array_filter($profile[$field_name]);
            }

            // fold multivalued fields with civicrm separator
            // why do it here: after reformat of custom fields
            //if (is_array($value))
            //$profile[$field_name] = "\001" . implode( "\001", $value ) . "\001";
          }
          else {
            //$profile[$field_name] = $listAll[$fieldid];
          }
        }
      }
      //echo '<pre> ';  echo 'ContactID : '. $dao->contact_id . ' , SWL ID  : ' . $dao->swlid;  echo '</pre>';
      if ($dao->contact_id) {
        $address = $profile['address'];
        unset($profile['address']);
        $address = explode(',', $address);

        array_pop($address);
        $add = ['street_address', 'supplemental_address_1', 'city', 'state_province', 'postal_code', 'country_id'];
        $final = array_combine($add, $address);
        $final = array_map('urldecode', $final);
        if (($final['country_id']) && (array_key_exists($final['country_id'], $pseudoconstants['country_code']))) {
          $final['country_id'] = $pseudoconstants['country_code'][$final['country_id']];
        }

        $addressType = self::getAddress($dao->contact_id);
        if (!empty($final)) {
          $final = $final + $addressType;
          $param_address = ['api.address.create' => $final];
          $profile['id'] = $profile['contact_id'] = $dao->contact_id;
          $profile = array_merge($profile, $param_address);
        }
        $profile['id'] = $profile['contact_id'] = $dao->contact_id;
        //echo '<pre> ';print_r($profile);echo '</pre>';
        $result = civicrm_api3('Contact', 'create', $profile);
        //$crmDat = self::pppnet_member_profile($dao->contact_id);
        //echo '<pre>CRM DATA $result';print_r($result);echo '</pre>';
      }
    }
    //return $profile;
  }

  static function batchUserEditProfileBlock($userid) {
    $swl = CRM_Swl_Utils::swl();
    $parameters = [];
    $parameters['block_id'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);
    if ($parameters['block_id']) {
      $blockMapping = self::getProfleBlockMapping($parameters['block_id']);
      $result = civicrm_api3('Contact', 'getsingle', [
        'sequential' => 1,
        'id' => $contactID,
      ]);
    }
    $parameters['target_user_id'] = $userid;
    foreach ($params as $key => $value) {
      $parameters['userdata'] = $value;
      if ($key == 'first_name') {
        $parameters['field_id'] = $swl_first_name_id;
      }
      elseif ($key == 'last_name') {
        $parameters['field_id'] = $swl_last_name_id;
      }
      elseif ($key == 'email') {
        $parameters['field_id'] = $swl_email_id;
      }
      if (!empty($parameters['field_id'])) {
        $result = $swl->userEditProfileBlock(CRM_Swl_Utils::swlparams($parameters));
      }
    }

    return TRUE;
  }

  static function getProfleBlockMapping($blockID) {
    if (empty(self::$_profileBlock)) {
      $sql = "select field_id, name from civicrm_swl_profile_field_mapping where is_active = 1 and block_id = " . $blockID;
      $dao = CRM_Core_DAO::executeQuery($sql);
      $block = [];
      while ($dao->fetch()) {
        $block[$dao->field_id] = $dao->name;
      }
      self::$_profileBlock = $block;
    }
    else {
      $block = self::$_profileBlock;
    }

    return $block;
  }

  static function getCustomFieldOptions() {
    if (empty(self::$_customValue)) {
      $blockID = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);
      $query = "SELECT name, option_group_id FROM civicrm_swl_profile_field_mapping where option_group_id is NOT NULL AND is_active = 1 and block_id = " . $blockID;
      $dao = CRM_Core_DAO::executeQuery($query);
      $block = [];
      while ($dao->fetch()) {
        $options = civicrm_api('OptionValue', 'get', ['version' => 3, 'option_group_id' => $dao->option_group_id, 'option.limit' => 1000]);
        if ($options['is_error'])
          continue;
        foreach ($options['values'] as $option) {
          $block[$dao->name][$option['value']] = $option['label'];
        }
      }
      self::$_customValue = $block;
    }
    else {
      $block = self::$_customValue;
    }

    return $block;
  }

  static function getSWLProfileLabel() {
    if (empty(self::$_profileLabel)) {
      $swl = CRM_Swl_Utils::swl();
      $parameters = [];
      $parameters['block_id'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);
      $block = $swl->usersGetProfileFieldOptions(CRM_Swl_Utils::swlparams($parameters));
      self::$_profileLabel = $block;
    }
    else {
      $block = self::$_profileLabel;
    }

    return $block;
  }

  static function createUserAddToGroup($groupid = '', $params, $contactID, $only_group_sync = FALSE) {
    // Check user already present in SWL
    // get swl id using civicrm contact id
    $sql = "select user_id from civicrm_swl_user_details where contact_id = {$contactID}";
    $swlUserId = CRM_Core_DAO::singleValueQuery($sql);
    if (!empty($swlUserId)) {
      // keep email in sync
      $updatedEmail = $params['email'];
      $sql = "update IGNORE civicrm_swl_user_details set email = '{$updatedEmail}' where contact_id = {$contactID}";
      CRM_Core_DAO::executeQuery($sql);
    }

    // if not present try using email
    if (empty($swlUserId)) {
      $sql = "select user_id from civicrm_swl_user_details where email = %1";
      $emailParams = [1 => [$params['email'], 'String']];
      $swlUserId = CRM_Core_DAO::singleValueQuery($sql, $emailParams);

      // add contact id if not present
      $sql = "update civicrm_swl_user_details set contact_id = $contactID where email = %1";
      CRM_Core_DAO::executeQuery($sql, $emailParams);
    }

    if (empty($swlUserId)) {
      $parameters = [];
      $swl = CRM_Swl_Utils::swl();
      $parameters['email_address'] = $params['email'];
      $parameters['first_name'] = $params['first_name'];
      $parameters['last_name'] = $params['last_name'];
      $result = $swl->getUser(CRM_Swl_Utils::swlparams($parameters));
      foreach ($result as $sid => $semail) {
        if ($semail == $params['email']) {
          $emailParams = [1 => [$semail, 'String']];
          $insert = "INSERT IGNORE INTO civicrm_swl_user_details(user_id, email, contact_id) VALUES($sid, %1, $contactID )";
          CRM_Core_DAO::executeQuery($insert, $emailParams);
          $swlUserId = $sid;
          break;
        }
      }
    }
    if (!empty($swlUserId)) {
      //update Email address of user for just to be in sync with CRM
      $parameters = [];
      $parameters['target_user_id'] = $swlUserId;
      $parameters['authed_user_id'] = 1;
      $parameters['email_address'] = $params['email'];
      CRM_Swl_Utils::updateUser($parameters);


      // Update First name , Last name
      $parameters = [];
      $parameters['target_user_id'] = $swlUserId;
      $parameters['authed_user_id'] = 1;
      if (empty($params['first_name']) && empty($params['last_name'])) {
        $parameters['first_name'] = $params['email'];
      }

      if (!empty($params['first_name'])) {
        $parameters['first_name'] = $params['first_name'];
      }
      if (!empty($params['last_name'])) {
        $parameters['last_name'] = $params['last_name'];
      }

      CRM_Swl_Utils::updateUser($parameters);

      CRM_Swl_Utils::addUpdateContactFromGroup($groupid, $swlUserId);
    }
    else {
      $params['password'] = '123@3Avccsdsd';
      $swlUserId = CRM_Swl_Utils::createUser($params);
      if (!empty($swlUserId)) {
        CRM_Swl_Utils::addUpdateContactFromGroup($groupid, $swlUserId);
      }
    }

    if ($only_group_sync) {
      return TRUE;
    }
    if (!empty($swlUserId)) {
      $swlSegment = CRM_Swl_Utils::getUserSegment(['user_id' => $swlUserId]);
      $crmSegment = CRM_Swl_Utils::getSegmentByMembership($contactID);
      $validateTiers = [1, 2, 9, 10, 11, 15];
      foreach ($swlSegment as $swlKey => $swlSegID) {
        if (in_array($swlSegID, $validateTiers)) {
          unset($swlSegment[$swlKey]); // remove segment which is going to update using api
        }
      }
      $swlSegment = array_merge($swlSegment, $crmSegment);
      $swlSegment = array_filter($swlSegment);
      if (empty($swlSegment)) {
        $swlSegment = ['9'];
      }
      if (!empty($swlSegment)) {
        $parameters = [];
        $parameters['target_user_id'] = $swlUserId;
        $parameters['authed_user_id'] = 1;
        $parameters['tier'] = implode(',', $swlSegment);
        CRM_Swl_Utils::updateUser($parameters);
      }
      CRM_Swl_Utils::userEditProfileBlock($swlUserId, $params, $contactID);
    }

    return $contacts;
  }


  static function getGroupMembersDetails($groupid, $memberListOnly = FALSE) {
    $memberLists = CRM_Swl_Utils::groupGetMembers($groupid);
    if ($memberListOnly) {
      return $memberLists;
    }
    $contacts = [];
    foreach ($memberLists as $userid => $dont) {
      $contacts[$userid] = CRM_Swl_Utils::usersGetProfileBlock($userid);
    }

    return $contacts;
  }

  static function getUser() {
    $swl = CRM_Swl_Utils::swl();
    $parameters = [];
    $parameters['limit'] = '500';
    $offset = 0;
    $counter = TRUE;
    while ($counter) {
      $parameters['offset'] = $offset;
      $result = $swl->getUser(CRM_Swl_Utils::swlparams($parameters));
      if ($result == FALSE) {
        $counter = FALSE;
      }
      else {
        foreach ($result as $userid => $email) {
          $insert = "INSERT IGNORE INTO civicrm_swl_user_details(user_id, email) VALUES($userid,'$email')";
          CRM_Core_DAO::executeQuery($insert);
        }
        $offset += 500;
      }
    }
  }


  static function getGroupsToSync($groupIDs = [], $swl_group = NULL) {

    $params = $groups = $temp = [];
    foreach ($groupIDs as $value) {
      if ($value) {
        $temp[] = $value;
      }
    }

    $groupIDs = $temp;

    if (!empty($groupIDs)) {
      $groupIDs = implode(',', $groupIDs);
      $whereClause = "entity_id IN ($groupIDs)";
    }
    else {
      $whereClause = "1 = 1";
    }

    $whereClause .= " AND swl_group IS NOT NULL AND swl_group <> ''";

    if ($swl_group) {
      // just want results for a particular MC list.
      $whereClause .= " AND swl_group = %1 ";
      $params[1] = [$swl_group, 'String'];
    }

    $query = "
      SELECT  entity_id, swl_group, cg.title as civigroup_title, cg.saved_search_id, cg.children
      FROM    civicrm_value_swl_settings swl
      INNER JOIN civicrm_group cg ON swl.entity_id = cg.id
      WHERE $whereClause";
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    $lists = CRM_Swl_Utils::swlGetList();
    while ($dao->fetch()) {
      $groups[$dao->entity_id] =
        [
          'swl_group_id' => $dao->swl_group,
          'swl_group_name' => $lists[$dao->swl_group],
          'civigroup_title' => $dao->civigroup_title,
          'civigroup_uses_cache' => (bool)(($dao->saved_search_id > 0) || (bool)$dao->children),
        ];
    }

    return $groups;
  }

  /*
   * Create/Update contact details in CiviCRM, based on the data from Swl webhook
   */
  static function updateContactDetails(&$params, $delay = FALSE) {

    if (empty($params)) {
      return NULL;
    }
    $params['status'] = ['Added' => 0, 'Updated' => 0];
    $contactParams =
      [
        'version' => 3,
        'contact_type' => 'Individual',
        'first_name' => $params['FNAME'],
        'last_name' => $params['LNAME'],
        'email' => $params['EMAIL'],
      ];

    if ($delay) {
      //To avoid a new duplicate contact to be created as both profile and upemail events are happening at the same time
      sleep(20);
    }
    $contactids = CRM_Swl_Utils::getContactFromEmail($params['EMAIL']);

    if (count($contactids) > 1) {
      return NULL;
    }
    if (count($contactids) == 1) {
      $contactParams = CRM_Swl_Utils::updateParamsExactMatch($contactids, $params);
      $params['status']['Updated'] = 1;
    }
    if (empty($contactids)) {
      //check for contacts with no primary email address
      $id = CRM_Swl_Utils::getContactFromEmail($params['EMAIL'], FALSE);

      if (count($id) > 1) {
        return NULL;
      }
      if (count($id) == 1) {
        $contactParams = CRM_Swl_Utils::updateParamsExactMatch($id, $params);
        $params['status']['Updated'] = 1;
      }
      // Else create new contact
      if (empty($id)) {
        $params['status']['Added'] = 1;
      }

    }
    // Create/Update Contact details
    $contactResult = civicrm_api('Contact', 'create', $contactParams);

    return $contactResult['id'];
  }

  static function getContactFromEmail($email, $primary = TRUE) {
    $primaryEmail = 1;
    if (!$primary) {
      $primaryEmail = 0;
    }
    $contactids = [];
    $query = "
      SELECT `contact_id` FROM civicrm_email ce
      INNER JOIN civicrm_contact cc ON ce.`contact_id` = cc.id
      WHERE ce.email = %1 AND ce.is_primary = {$primaryEmail} AND cc.is_deleted = 0 ";
    $dao = CRM_Core_DAO::executeQuery($query, ['1' => [$email, 'String']]);
    while ($dao->fetch()) {
      $contactids[] = $dao->contact_id;
    }

    return $contactids;
  }

  static function updateParamsExactMatch($contactids = [], $params) {
    $contactParams =
      [
        'version' => 3,
        'contact_type' => 'Individual',
        'first_name' => $params['FNAME'],
        'last_name' => $params['LNAME'],
        'email' => $params['EMAIL'],
      ];
    if (count($contactids) == 1) {
      $contactParams['id'] = $contactids[0];
      unset($contactParams['contact_type']);
      // Don't update firstname/lastname if it was empty
      if (empty($params['FNAME']))
        unset($contactParams['first_name']);
      if (empty($params['LNAME']))
        unset ($contactParams['last_name']);
    }

    return $contactParams;
  }

  static function pppnet_member_profile($contactId) {
    $contactData = [];
    if ($contactId) {
      $params = [];
      $defaults = [];
      $params['id'] = $params['contact_id'] = $contactId;
      $params['noRelationships'] = $params['noNotes'] = $params['noGroups'] = TRUE;
      $contactInfo = CRM_Contact_BAO_Contact::retrieve($params, $defaults, FALSE);
      $entityBlock = ['contact_id' => $contactId];
      $contactInfo->address = CRM_Core_BAO_Address::getValues($entityBlock);
      $helpOptions = new stdClass();
      $helpOptions->locationTypes = CRM_Core_PseudoConstant::get('CRM_Core_DAO_Address', 'location_type_id');
      $helpOptions->country = CRM_Core_PseudoConstant::get('CRM_Core_DAO_Address', 'country_id');
      $helpOptions->stateProvince = CRM_Core_PseudoConstant::get('CRM_Core_DAO_Address', 'state_province_id');
      $helpOptions->phone = CRM_Core_PseudoConstant::get('CRM_Core_DAO_Phone', 'phone_type_id');
      $helpOptions->prefix = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'prefix_id');
      $helpOptions->suffix = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'suffix_id');
      $helpOptions->website = CRM_Core_PseudoConstant::get('CRM_Core_DAO_Website', 'website_type_id');

      $contactData = [];
      $contactData['first_name'] = $contactInfo->first_name ? $contactInfo->first_name : '';
      $contactData['middle_name'] = $contactInfo->middle_name ? $contactInfo->middle_name : '';
      $contactData['last_name'] = $contactInfo->last_name ? $contactInfo->last_name : '';
      $contactData['job_title'] = $contactInfo->job_title ? $contactInfo->job_title : '';
      $contactData['individual_prefix'] = $contactInfo->prefix_id ? $helpOptions->prefix[$contactInfo->prefix_id] : '';
      $contactData['individual_suffix'] = $contactInfo->suffix_id ? $helpOptions->suffix[$contactInfo->suffix_id] : '';
      $contactData['current_employer'] = $contactInfo->organization_name ? $contactInfo->organization_name : '';
      if (!empty($contactInfo->address)) {
        $contactData['address'] = self::_pppnet_filter_address($contactInfo->address, 'address', $helpOptions, $contactData);
      }
      if (!empty($contactInfo->phone)) {
        $contactData['phone'] = self::_pppnet_filter_address($contactInfo->phone, 'phone', $helpOptions, $contactData);
      }
      if (!empty($contactInfo->email)) {
        $contactData['email'] = self::_pppnet_filter_address($contactInfo->email, 'email', $helpOptions, $contactData);
      }
      if (!empty($contactInfo->website)) {
        self::_pppnet_filter_address($contactInfo->website, 'website', $helpOptions, $contactData);
      }
    }

    return $contactData;
  }

  static function _pppnet_filter_address($params, $type, $helpOptions, & $contactData) {
    $v = [];
    foreach ($params as $key => $values) {
      if ($type == 'email') {
        $v = $values['email'];
      }
      elseif ($type == 'phone') {
        $v = $values['phone'];
      }
      elseif ($type == 'address') {
        $v[] = @ $values['street_address'];
        $v[] = @ $values['supplemental_address_1'];
        $v[] = @ $values['supplemental_address_2'];
        $v[] = @ $values['city'];
        $v[] = @ $values['postal_code'];
        $v[] = @ $values['state_province_id'] ? $helpOptions->stateProvince[$values['state_province_id']] : '';
        $v[] = @ $values['country_id'] ? $helpOptions->country[$values['country_id']] : '';
        $v = implode(',', $v);
      }
      elseif ($type == 'website') {
        $contactData[$helpOptions->website[$values['website_type_id']]] = $values['url'];
      }
    }

    return $v;
  }

  static function getCustomValueLabel() {
    $customfields = $optionvalues = [];
    $result = civicrm_api('CustomField', 'get', ['version' => 3, 'option.limit' => 10000]);
    $customGroups = [4, 5];
    if ($result['is_error'])
      exit('Unable to load custom fields');
    foreach ($result['values'] as $value) {
      $customfields[$value['id']] = $value;
      if (!empty($value['option_group_id']) && in_array($value['custom_group_id'], $customGroups)) {
        $group_id = $value['option_group_id'];
        if (!array_key_exists($group_id, $optionvalues)) {
          $options = civicrm_api('OptionValue', 'get', ['version' => 3, 'option_group_id' => $group_id, 'option.limit' => 1000]);
          if ($options['is_error'])
            exit('Unable to load option group ' . $group_id);
          foreach ($options['values'] as $option)
            $optionvalues[$group_id][$option['value']] = $option['label'];
        }
      }
    }

    return [$optionvalues, $customfields];;
  }

  static function getAddress($contactID) {
    $sql = "SELECT id, contact_id, location_type_id FROM `civicrm_address` WHERE contact_id = {$contactID} AND is_primary = 1";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $block = [];
    while ($dao->fetch()) {
      $block['id'] = $dao->id;
      $block['contact_id'] = $dao->contact_id;
      $block['location_type_id'] = $dao->location_type_id;
    }
    if (empty($block)) {
      $block['location_type_id'] = 5;
      $block['contact_id'] = $contactID;
      $block['is_primary'] = 1;
    }

    return $block;
  }

  static function getSegmentByMembership($contactId) {
    // Check first UF Match table to get roles
    $ufID = CRM_Core_BAO_UFMatch::getUFId($contactId);
    if (!empty($ufID)) {
      $user = user_load($ufID);
      $user_roles = array_values($user->roles);
      $tiers = [];
      if (in_array('administrator', array_values($user->roles))
        || in_array('council admin', array_values($user->roles))
        || in_array('national and council', array_values($user->roles))
        || in_array('national', array_values($user->roles))
      ) {
        $tiers[] = '1'; // National Member
      }
      if (in_array('council admin', array_values($user->roles))) {
        $tiers[] = '15'; // Council Admin
      }
      if (in_array('council', array_values($user->roles))) {
        $tiers[] = '10'; // Council Member (nonmember)
      }
      if (in_array('staff', $user->roles)) {
        $tiers[] = '2'; // PPPNET Staff
      }
      /*
       IF no national membership available (current), then check any expired membership exist for same contact
      */
      if (!in_array('1', $tiers)) {
        $expiredStatusId = array_search('Expired', CRM_Member_PseudoConstant::membershipStatus());
        $sql = "SELECT count(*) FROM civicrm_membership m
          WHERE m.is_test= 0 AND m.status_id = {$expiredStatusId} AND m.contact_id = {$contactId} AND m.membership_type_id = 1";
        if (CRM_Core_DAO::singleValueQuery($sql)) {
          $tiers[] = '11'; //Expired (nonmember)- Had a National membership but expired
        }
      }
      // No membership trace
      if (empty($tiers)) {
        $tiers[] = '9'; // Nonmember- Do not have any membership in Civi
      }

      return $tiers;
    }
    // if user not found in UFMatch then get it from membership type
    $membershipStatus = CRM_Member_PseudoConstant::membershipStatus(NULL, "(is_current_member = 1 )", 'id');
    $result = [];
    $tiers = [];
    if ($contactId && !empty($membershipStatus)) {
      // check membership available for logged in user
      $sql = 'SELECT m.id, m.membership_type_id FROM civicrm_membership m WHERE m.is_test= 0
            AND m.status_id IN (' . implode(',', array_keys($membershipStatus)) . ') AND m.contact_id = ' . $contactId;
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $result[$dao->id] = $dao->membership_type_id;
      }
    }
    // get list of council membership type from result
    $councilMembership = array_filter($result,
      function ($key) {
        if ($key <= 157 && $key >= 2) {
          return TRUE;
        }
      }
    );

    if (!empty($result)) {
      if (in_array('1', $result)) { // National
        $tiers[] = '1';
      }
      elseif (!empty($councilMembership)) {
        $tiers[] = '10'; //Council Member (nonmember)
      }
    }
    else {
      $expiredStatusId = array_search('Expired', CRM_Member_PseudoConstant::membershipStatus());
      $sql = "SELECT count(*) FROM civicrm_membership m
        WHERE m.is_test= 0 AND m.status_id = {$expiredStatusId} AND m.contact_id = {$contactId} AND m.membership_type_id = 1";
      if (CRM_Core_DAO::singleValueQuery($sql)) {
        $tiers[] = '11'; //Expired (nonmember)- Had a National membership but expired
      }
      // No membership trace
      if (empty($tiers)) {
        $tiers[] = '9'; // Nonmember- Do not have any membership in Civi
      }
    }

    return $tiers;
  }


  function getNearestMatch($inputType, $input = '') {
    $swl_country = [
      "USA" => "United States", "GBR" => "United Kingdom", "AFG" => "Afghanistan", "ALA" => "Åland Islands",
      "ALB" => "Albania", "DZA" => "Algeria", "ASM" => "American Samoa", "AND" => "Andorra", "AGO" => "Angola",
      "AIA" => "Anguilla", "ATA" => "Antarctica", "ATG" => "Antigua and Barbuda", "ARG" => "Argentina", "ARM" => "Armenia",
      "ABW" => "Aruba", "AUS" => "Australia", "AUT" => "Austria", "AZE" => "Azerbaijan", "BHS" => "Bahamas", "BHR" => "Bahrain",
      "BGD" => "Bangladesh", "BRB" => "Barbados", "BLR" => "Belarus", "BEL" => "Belgium", "BLZ" => "Belize", "BEN" => "Benin",
      "BMU" => "Bermuda", "BTN" => "Bhutan", "BOL" => "Bolivia, Plurinational State of", "BES" => "Bonaire, Sint Eustatius and Saba",
      "BIH" => "Bosnia and Herzegovina", "BWA" => "Botswana", "BVT" => "Bouvet Island", "BRA" => "Brazil", "IOT" => "British Indian Ocean Territory",
      "BRN" => "Brunei Darussalam", "BGR" => "Bulgaria", "BFA" => "Burkina Faso", "BDI" => "Burundi", "KHM" => "Cambodia", "CMR" => "Cameroon",
      "CAN" => "Canada", "CPV" => "Cape Verde", "CYM" => "Cayman Islands", "CAF" => "Central African Republic", "TCD" => "Chad",
      "CHL" => "Chile", "CHN" => "China", "CXR" => "Christmas Island", "CCK" => "Cocos (Keeling) Islands", "COL" => "Colombia",
      "COM" => "Comoros", "COG" => "Congo", "COD" => "Congo, the Democratic Republic of the", "COK" => "Cook Islands", "CRI" => "Costa Rica",
      "CIV" => "Côte d'Ivoire", "HRV" => "Croatia", "CUB" => "Cuba", "CUW" => "Curaçao", "CYP" => "Cyprus", "CZE" => "Czech Republic",
      "DNK" => "Denmark", "DJI" => "Djibouti", "DMA" => "Dominica", "DOM" => "Dominican Republic", "ECU" => "Ecuador", "EGY" => "Egypt",
      "SLV" => "El Salvador", "GNQ" => "Equatorial Guinea", "ERI" => "Eritrea", "EST" => "Estonia", "ETH" => "Ethiopia",
      "FLK" => "Falkland Islands (Malvinas)", "FRO" => "Faroe Islands", "FJI" => "Fiji", "FIN" => "Finland", "FRA" => "France",
      "GUF" => "French Guiana", "PYF" => "French Polynesia", "ATF" => "French Southern Territories", "GAB" => "Gabon",
      "GMB" => "Gambia", "GEO" => "Georgia", "DEU" => "Germany", "GHA" => "Ghana", "GIB" => "Gibraltar", "GRC" => "Greece",
      "GRL" => "Greenland", "GRD" => "Grenada", "GLP" => "Guadeloupe", "GUM" => "Guam", "GTM" => "Guatemala", "GGY" => "Guernsey",
      "GIN" => "Guinea", "GNB" => "Guinea-Bissau", "GUY" => "Guyana", "HTI" => "Haiti", "HMD" => "Heard Island and McDonald Islands",
      "VAT" => "Holy See (Vatican City State)", "HND" => "Honduras", "HKG" => "Hong Kong", "HUN" => "Hungary", "ISL" => "Iceland",
      "IND" => "India", "IDN" => "Indonesia", "IRN" => "Iran, Islamic Republic of", "IRQ" => "Iraq", "IRL" => "Ireland",
      "IMN" => "Isle of Man", "ISR" => "Israel", "ITA" => "Italy", "JAM" => "Jamaica", "JPN" => "Japan", "JEY" => "Jersey",
      "JOR" => "Jordan", "KAZ" => "Kazakhstan", "KEN" => "Kenya", "KIR" => "Kiribati", "PRK" => "Korea, Democratic People's Republic of",
      "KOR" => "Korea, Republic of", "KWT" => "Kuwait", "KGZ" => "Kyrgyzstan", "LAO" => "Lao People's Democratic Republic", "LVA" => "Latvia",
      "LBN" => "Lebanon", "LSO" => "Lesotho", "LBR" => "Liberia", "LBY" => "Libyan Arab Jamahiriya", "LIE" => "Liechtenstein",
      "LTU" => "Lithuania", "LUX" => "Luxembourg", "MAC" => "Macao", "MKD" => "Macedonia, the former Yugoslav Republic of",
      "MDG" => "Madagascar", "MWI" => "Malawi", "MYS" => "Malaysia", "MDV" => "Maldives", "MLI" => "Mali", "MLT" => "Malta",
      "MHL" => "Marshall Islands", "MTQ" => "Martinique", "MRT" => "Mauritania", "MUS" => "Mauritius", "MYT" => "Mayotte",
      "MEX" => "Mexico", "FSM" => "Micronesia, Federated States of", "MDA" => "Moldova, Republic of", "MCO" => "Monaco", "MNG" => "Mongolia",
      "MNE" => "Montenegro", "MSR" => "Montserrat", "MAR" => "Morocco", "MOZ" => "Mozambique",
      "MMR" => "Myanmar", "NAM" => "Namibia", "NRU" => "Nauru", "NPL" => "Nepal", "NLD" => "Netherlands", "NCL" => "New Caledonia",
      "NZL" => "New Zealand", "NIC" => "Nicaragua", "NER" => "Niger", "NGA" => "Nigeria", "NIU" => "Niue", "NFK" => "Norfolk Island",
      "MNP" => "Northern Mariana Islands", "NOR" => "Norway", "OMN" => "Oman", "PAK" => "Pakistan", "PLW" => "Palau", "PSE" => "Palestinian Territory, Occupied",
      "PAN" => "Panama", "PNG" => "Papua New Guinea", "PRY" => "Paraguay", "PER" => "Peru", "PHL" => "Philippines", "PCN" => "Pitcairn",
      "POL" => "Poland", "PRT" => "Portugal", "PRI" => "Puerto Rico", "QAT" => "Qatar", "REU" => "Réunion", "ROU" => "Romania", "RUS" => "Russian Federation",
      "RWA" => "Rwanda", "BLM" => "Saint Barthélemy", "SHN" => "Saint Helena, Ascension and Tristan da Cunha", "KNA" => "Saint Kitts and Nevis",
      "LCA" => "Saint Lucia", "MAF" => "Saint Martin (French part)", "SPM" => "Saint Pierre and Miquelon", "VCT" => "Saint Vincent and the Grenadines",
      "WSM" => "Samoa", "SMR" => "San Marino", "STP" => "Sao Tome and Principe", "SAU" => "Saudi Arabia", "SEN" => "Senegal", "SRB" => "Serbia",
      "SYC" => "Seychelles", "SLE" => "Sierra Leone", "SGP" => "Singapore",
      "SXM" => "Sint Maarten (Dutch part)", "SVK" => "Slovakia", "SVN" => "Slovenia", "SLB" => "Solomon Islands", "SOM" => "Somalia",
      "ZAF" => "South Africa", "SGS" => "South Georgia and the South Sandwich Islands", "SSD" => "South Sudan", "ESP" => "Spain", "LKA" => "Sri Lanka",
      "SDN" => "Sudan", "SUR" => "Suriname", "SJM" => "Svalbard and Jan Mayen", "SWZ" => "Swaziland", "SWE" => "Sweden",
      "CHE" => "Switzerland", "SYR" => "Syrian Arab Republic", "TWN" => "Taiwan, Province of China", "TJK" => "Tajikistan", "TZA" => "Tanzania, United Republic of",
      "THA" => "Thailand", "TLS" => "Timor-Leste", "TGO" => "Togo", "TKL" => "Tokelau", "TON" => "Tonga", "TTO" => "Trinidad and Tobago",
      "TUN" => "Tunisia", "TUR" => "Turkey", "TKM" => "Turkmenistan", "TCA" => "Turks and Caicos Islands", "TUV" => "Tuvalu", "UGA" => "Uganda",
      "UKR" => "Ukraine", "ARE" => "United Arab Emirates", "UMI" => "United States Minor Outlying Islands", "URY" => "Uruguay", "UZB" => "Uzbekistan",
      "VUT" => "Vanuatu", "VEN" => "Venezuela, Bolivarian Republic of", "VNM" => "Vietnam", "VGB" => "Virgin Islands, British", "VIR" => "Virgin Islands, U.S.",
      "WLF" => "Wallis and Futuna", "ESH" => "Western Sahara", "YEM" => "Yemen", "ZMB" => "Zambia", "ZWE" => "Zimbabwe"
    ];
    $inputCombination = $closest = '';
    if ($inputType == 'country') {
      $input = str_replace(['.', ','], '', $input);
      $inputCombination = explode(" ", $input);
    }
    elseif (!empty($input)) {
      $inputCombination = (array)$input;
    }
    if ($inputType == 'country' && count($inputCombination) > 1) {
      $inputCombination = self::getAllCombos($inputCombination);
      array_unshift($inputCombination, $input);
    }
    // no shortest distance found, yet
    $shortest = -1;
    $swl_country = array_flip($swl_country);
    if (!empty($inputCombination)) {
      // loop through words to find the closest
      foreach ($inputCombination as $input) {
        foreach ($swl_country as $word => $id) {

          // calculate the distance between the input word,
          // and the current word
          $lev = levenshtein($input, $word);
          // check for an exact match
          if ($lev == 0) {
            // closest word is this one (exact match)
            $closest = $word;
            $shortest = 0;
            // break out of the loop; we've found an exact match
            break 2;
          }
          elseif (FALSE && $lev <= 8) {
            // closest word is this one (exact match)
            $closest = $word;

            $shortest = $lev;
            // break out of the loop; we've found an exact match
            break 2;
          }

          // if this distance is less than the next found shortest
          // distance, OR if a next shortest word has not yet been found
          if ($lev <= $shortest || $shortest < 0) {
            // set the closest match, and shortest distance
            $closest = $word;
            $shortest = $lev;
          }
        }
      }
    }

    if (!empty($closest) && !empty($swl_country[$closest])) {
      return $swl_country[$closest];
    }

    if (($inputType == 'country' || $inputType == 'state_province') && empty($closest)) {
      return [];
    }
  }


  function getAllCombos($arr) {
    $combinations = [];
    $words = sizeof($arr);
    $combos = 1;
    for ($i = $words; $i > 0; $i--) {
      $combos *= $i;
    }
    while (sizeof($combinations) < $combos) {
      shuffle($arr);
      $combo = implode(" ", $arr);
      if (!in_array($combo, $combinations)) {
        $combinations[] = $combo;
      }
    }

    return $combinations;
  }

  static function swl_sync($contactID, $email = '') {
    if (!empty($contactID)) {
      $contactType = CRM_Contact_BAO_Contact::getContactType($contactID);
      if ($contactType == 'Individual') {
        $insert = "INSERT IGNORE INTO civicrm_swl_contactsync_cron(contact_id, status) VALUES($contactID, '1' )";
        CRM_Core_DAO::executeQuery($insert);
      }
    }

    /*
    if ($contactID && empty($email)) {
      $sql = "SELECT email FROM civicrm_email WHERE  civicrm_email.contact_id = {$contactID} AND civicrm_email.is_primary = 1";
      $email = CRM_Core_DAO::singleValueQuery($sql);
    }
    if (!empty($contactID)) {
      $contactType = CRM_Contact_BAO_Contact::getContactType($contactID);
      if ($contactType != 'Individual') {
        return;
      }
      $swl_group_id= '';
      $params = array('email' => $email);
      $result = CRM_Swl_Utils::createUserAddToGroup($swl_group_id, $params, $contactID);
    }
    */
  }

  static function userEditProfileBlockStatus($userid, $params, $contactID) {
    //$swl = CRM_Swl_Utils::swl();
    $parameters = [];
    // Membership Details
    $finalData = [];
    // if user not found in UFMatch then get it from membership type
    $membershipStatus = CRM_Member_PseudoConstant::membershipStatus(NULL, "(is_current_member = 1 )", 'id');
    $membershipTypes = [];
    if ($contactID && !empty($membershipStatus)) {
      // check membership available for logged in user
      $sql = 'SELECT mt.id, mt.name FROM civicrm_membership m inner join civicrm_membership_type mt on (mt.id = m.membership_type_id)
             WHERE m.is_test= 0
            AND m.status_id IN (' . implode(',', array_keys($membershipStatus)) . ') AND m.contact_id = ' . $contactID;
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $membershipTypes[$dao->id] = $dao->name;
      }
      if (!empty($membershipTypes)) {
        $finalData['270'] = implode(',', $membershipTypes);
      }
      if (!empty($membershipTypes) && array_key_exists('1', $membershipTypes)) {
        $finalData['273'] = 'Active';
      }
      else {
        $finalData['273'] = 'InActive';
      }
    }
    else {
      $finalData['273'] = 'InActive';
    }


    $parameters['block_id'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);

    $parameters['target_user_id'] = $userid;
    foreach ($finalData as $key => $value) {
      $parameters['userdata'] = $value;
      $parameters['field_id'] = $key;
      if (!empty($parameters['field_id'])) {
        $swl = CRM_Swl_Utils::swl();
        $result = $swl->userEditProfileBlock(CRM_Swl_Utils::swlparams($parameters));
      }
    }

    return TRUE;
  }

  public static function getCiviCRMFields() {
    $civicrmFields = CRM_Contact_Form_Search_Builder::fields();
    $cleanFields = [];
    foreach ($civicrmFields as $fieldName => $fieldDetail) {
      $cleanFields[$fieldName] = $fieldDetail['title'];
    }

    return $cleanFields;
  }
}

