<?php

class CRM_Swl_Connect {
  //public $api;
  public $parameters = array();
  public $time;
  
  function __construct() {
     $this->time = time();
  }
  
  function swlsso_api_init() {
    $consumer = new OAuthConsumer(SWL_KEY, SWL_SECRET);
    $api = new SwlApi(SWL_BASE, $consumer);
    return $api;
  }

  function swlsso_api_connect($method, $parameters) {
    $api = $this->swlsso_api_init();
    $parameters['format']= 'json';
    $parameters['timestamp'] = $this->time;
    $result = $api->$method($parameters);
    return json_decode($result, true);

  }
  

  function swlsso_api_login($params) {
    global $user;
    if(user_is_logged_in()) {
      // check mapping already exist
      $target_user_id = 1;
      $parameters = array();
      $parameters['hash']      = md5(SWL_SSOSECRET . $target_user_id . $this->time);
      $parameters['user_id']   =  $result['swlid'];
      // login using swl user id
      $result = $this->swlsso_api_connect('users.login', $parameters);
      if ($result['status'] == 'ok') {
        $token = $result['login']['sso_login']['token'];
        setcookie('swl_pppnet_org', $token, time()+3600, "/", '.pppnet.org');
      }
    }

  }

  function swlsso_api_logout() {
  }

  function swlsso_api_createuser($params) {
    global $user;
    if ( ! civicrm_initialize( ) ) {
      return;
    }
    require_once 'CRM/Core/BAO/UFMatch.php';
    $contactId = CRM_Core_BAO_UFMatch::getContactId( $user->uid );
    $parameters = array();
    $parameters['hash']          = md5(SWL_SSOSECRET . '1' . $this->time);
    $parameters['email_address'] = $params['email'];
    $parameters['first_name']    = $params['first_name'];
    $parameters['last_name']     = $params['last_name'];
    $parameters['password']      = '123@3Avccsdsd';
    // Create user with all details
    $result = $this->swlsso_api_connect('users.createUser', $parameters);
    if ($result['status'] == 'ok') {
    $swlid = $result['createUser']['success']['user_id'];
    db_insert('swlsso')
      ->fields(array(
        'uid' => $user->uid,
        'swlid' => $swlid,
      ))
      ->execute();
    }
    $this->swlsso_api_login($params);
  }
}