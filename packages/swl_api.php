<?php //namespace Swl;

/**
 * Small World Labs API Library.
 *
 * Requires cURL (http://us2.php.net/manual/en/book.curl.php) 
 * and OAuth.php (which should have been included with this file).
 */

//require_once('OAuth.php');

/**
 * An exception class to be used when API errors happen.
 */
class CiviSwlApiException extends \Exception {};

class CiviSwlApiRequest {
  
  var $baseURL;
  protected $consumer;
  protected $accessToken;
  protected $requestToken;
  protected $format; // by default we'll take a serialized php variable, then return the unserialized version.
  protected $method;
  protected $parameters = array();
  protected $file;
    
  public $responseCode; // response code from the last http request

  /**
   * @param $api SwlApi object to get settings from
   */
  function __construct($baseURL, $consumer = NULL, $accessToken = NULL, $format = 'xml') {
    $this->baseURL = $baseURL;
    $this->consumer = $consumer;
    $this->accessToken = $token;
    $this->format = $format;
  }
  
  /**
   * Set the format of the response. If set to anything other than 'xml', any following requests will return their raw response, rather than a php object
   *
   * @param $format string the format to select. Should be one of the following: 'xml', 'json'
   */
  function setFormat($format) {
    if (in_array($format, array('xml', 'json'))) $this->format = $format;
    else throw new CiviSwlApiException(sprintf('Invalid format "%s"', $format));
  }
  
  function setMethod($name) {
    // Replace the first underscore with a dot.
    $pos = strpos($name, '_');
    if ($pos !== FALSE) $name[$pos] = '.';
    
    $this->method = $name;
  }
  
  function setParameters($parameters = array()) { 			
    $this->parameters = $parameters;
  }
  
  function setRequestToken($token) {
    $this->requestToken = $token;
  }

  /**
   * Set the file to be uploaded with the request
   *
   * @param $name string post parameter to add the file as
   * @param $file string path to the file to upload
   */
  function setFile($name, $file) {
    $this->file = array('name' => $name, 'file' => $file); 
  }

  
  function run() {

    // if the format isn't set, use the internal one
    if (!isset($this->parameters['format'])) $this->parameters['format'] = $this->format;
    
    if ($this->accessToken) $token = $this->accessToken;
    elseif ($this->requestToken && $this->method == 'auth.getAccessToken') $token = $this->requestToken;
    else $token = NULL;
    
    $fullURL = sprintf('https://%s/services/%s/%s', $this->baseURL, CiviSwlApi::$version, $this->method);
    
    $oReq = SwlOAuthRequest::from_consumer_and_token($this->consumer, $token, 'POST', $fullURL, $this->parameters);
    
    $sig = new SwlOAuthSignatureMethod_HMAC_SHA1();
    $oReq->sign_request($sig, $this->consumer, $token);
    
    $headers = explode(", ", $oReq->to_header());
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullURL);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->parameters);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_USERAGENT, "SWL PHP Library 2.0");

    try {
      $body = curl_exec($ch);
      $resCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($resCode != 200) throw new CiviSwlApiException(sprintf('Server returned response code %s"', $resCode));
      
      $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

      // if we got XML back, check for an error response
      if ($contentType == 'text/html') {
        $xml = new SimpleXMLElement($body);
        if ($xml->response['status'] == 'failure') {
          throw new CiviSwlApiException(sprintf('Server returned the error message "%s"', $xml->response->error));
        }
      }
    } catch (HttpException $e) {
      throw new CiviSwlApiException(sprintf('HttpException while making call: "%s", response code %i', $e, $resCode));
    }
    
    $this->rawResponse = $body;
    
    // if the response format is anything but 'xml' or if the call was one of the get*Token calls, return the raw value without trying to decode it.
    if (($this->parameters['format'] != 'xml') || $this->method == 'auth.getRequestToken' || $this->method == 'auth.getAccessToken') 
    {
      $this->response = $body;
    }
    else
    {// otherwise, deserialise the response
      $this->response = new SimpleXMLElement($body);
    }
  }
}

class CiviSwlApi {
  var $baseURL;
  var $lastRequest;
  static $version = '2.0';
  protected $consumer;
  protected $accessToken;
  protected $requestToken;
  
  /**
   * Initialize the API object
   *
   * @param $base_url string The base URL to use for making API calls, 
   *  this should just be the site URL without http:// or /services i.e. "community.example.com"
   * @param $consumer OAuthConsumer object with key and secret members holding the consumer key and secret
   * @param $token OAuthToken (optional if the consumer has superuser permissions) object with key and secret members holding the token and token secret.
   *  A token can also be acquired later on by calling getUserAuthorization
   */
  function __construct($baseURL, $consumer = NULL, $accessToken = NULL) {
    $this->baseURL = $baseURL;
    $this->consumer = $consumer;
    $this->accessToken = $accessToken;
  }
  
  static function connect($baseURL, $key, $secret) {
    $consumer = new OAuthConsumer($key, $secret);
    $api = new CiviSwlApi($baseURL, $consumer);
    return $api;
  }
  
    
  public static function decodeToken($tokenString) {
    $res = explode('&', $tokenString);
    
    list( , $token)  = explode('=', $res[0]);
    list( , $secret)  = explode('=', $res[1]);
    return new OAuthToken($token, $secret);
  }
  
  function requestRequestToken() {
    $this->__call('auth.getRequestToken');
    $this->requestToken = self::decodeToken($this->lastRequest->response);	
  }
  
  function getRequestToken() {
    if (!isset($this->requestToken)) $this->requestRequestToken();
    return $this->requestToken;
  }
  
  function setRequestToken($token) {
    $this->requestToken = $token;
  }
  
  function requestAccessToken() {
    if (!isset($this->requestToken)) throw new CiviSwlApiException('Access Token cannot be requested without a request token.');
    $this->__call('auth.getAccessToken');
    $this->accessToken = self::decodeToken($this->lastRequest->response); 
  }
  
  function getAccessToken($request = TRUE) {
    if (request && !isset($this->accessToken)) $this->requestAccessToken();
    return $this->accessToken;
  }
  
  function setAccessToken($token) {
    $this->accessToken = $token;
  }

  /**
   * Generate and return the URL which the user needs to be sent to in order to give authorization
   *
   * @param $permissions string 'W' to request read/write permissions or 'R' for read-only permissions
   * @param $callback string a URL to redirect back to after authorized, or an empty string to give the 
   *  user a message letting them know the application is now authorized.
   */
  function getUserAuthorizationURL($permissions = 'W', $callback = '') {
    // get a request token if we don't already have one.
    if (!$this->requestToken) $this->requestRequestToken();
    
    $fullURL = sprintf('https://%s/services/%s/auth.getUserAuthorization?oauth_token=%s&oauth_callback=%s&permissions=%s', 
      $this->baseURL, self::$version, $this->requestToken->key, $callback, $permissions);
    
    return $fullURL;
  }
  
  /**
   * Creates and returns a request object with the settings defined in this CiviSwlApi object
   *
   * @param $method string (optional) name of the method to call when run() is called on this method object. Can be set later using CiviSwlApiRequest->setMethod
   * @param $format string (optional) return format of this request. Defaults to 'xml'.
   * @return a new CiviSwlApiRequest object
   */
  function createRequest($method = NULL, $format = 'xml') {
    $token = isset($this->accessToken) ? $this->accessToken : $this->requestToken;
    $req = new CiviSwlApiRequest($this->baseURL, $this->consumer, $this->requestToken, $format);
    if ($this->requestToken) $req->setRequestToken($this->requestToken);
    if ($method) $req->setMethod($method);
    return $req;
  }
  
  /**
   * Call a method by name, returning the Api's response.
   *
   * @param $name string name of the Api method to run, optionally with a '_' instead of the standard '.'
   * @param $arguments array either an empty array or an array with a single element: an associative array with the real parameters in it.
   */
  function __call($name, $arguments=array()) {
    // wait for 0.7 second to avoid per minute limit
    usleep(700000);
    $req = &$this->createRequest($name);
    $this->lastRequest = &$req;
error_log("\nName : " . print_r( $name, true), 3, "/home/pppnet/www/www/sites/all/Extension/com.civibridge.sync.swl/log");
error_log("\nParams : " . print_r( $arguments, true), 3, "/home/pppnet/www/www/sites/all/Extension/com.civibridge.sync.swl/log");

    if (!empty($arguments)) $req->setParameters($arguments[0]);
    
    $req->run();
    $result = json_decode($req->response, true);
    error_log("\nAPI Status  : " . print_r( $result['status'], true), 3, "/home/pppnet/www/www/sites/all/Extension/com.civibridge.sync.swl/log");
    if ($result['status'] == 'failure') {
      error_log("\nAPI Error Msg  : " . print_r( $result['error']['message'], true), 3, "/home/pppnet/www/www/sites/all/Extension/com.civibridge.sync.swl/log");
    }
    return $req->response;
  }	
  
  static function fromIni($filename, $base_url = NULL) {
    // Read in the key(s)
    $config = parse_ini_file($filename, TRUE);
    
    if(is_null($base_url))
    {
      // If a different site was passed in as a parameter, use that instead of the default
      $base_url = $_GET['site'] ? $_GET['site'] : $config['base_url'];
      $consumer = $config[$base_url];
    }

    if (isset($consumer['token_key'])) $token = new OAuthToken($consumer['token_key'], $consumer['token_secret']);
    else $token = NULL;
  
    $api = new CiviSwlApi($base_url, new OAuthConsumer($consumer['key'], $consumer['secret']), $token);
    return $api;
  }
  
  /*
   * API user end fuctionality
   *
   *
   */

  public function groupGetList(array $properties, $accessLevel = '') {
    $action = 'groups.getList';
    $properties['format'] = 'json';
    $output = $this->$action($properties);
    
    $result = json_decode($output, true);
//error_log("\ngroupGetList : " . print_r( $result, true), 3, "/home/pppnet/www/www/sites/all/Extension/com.civibridge.sync.swl/log");
    $data = array();
    if ($result['status'] == 'ok') {
      if ($result['groups']['@attributes']['count'] == 1) {
        $result['groups']['group']  = array ($result['groups']['group']);
      }
      foreach ($result['groups']['group'] as $group) {
        if (!empty($accessLevel) && $accessLevel != $group['access']) {
          continue;
        }
        $data[$group['group_id']] = $group['name'];
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
      if ( !array_key_exists('0', $result['groups']['group']['members']['member'])) {
        $result['groups']['group']['members']['member'] = array($result['groups']['group']['members']['member']);
      }
      foreach ($result['groups']['group']['members']['member'] as $member) {
        if (!empty($status) && $status != $member['user_status']) {
          continue;
        }
        $data[$member['user_id']]= $member['user_id'];
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
  
  public function usersGetProfileBlock(array $properties) {
    $action = 'users.getProfileBlock';
    $properties['format'] = 'json';
    $output = $this->$action($properties);
    $result = json_decode($output, true);
    $fields = $result['profile_blocks']['profile_block']['fields']['field'];
    $data = array();
    foreach ($fields as $k => $field) {
      if (!empty($field['options_list'])) {
        if (is_array($field['options_list'])) {
          //
          $value = '';
        } else {
          $options = explode(',', $field['options_list']);
          $value = array();
          if (isset($field['userdata']) && !is_array($field['userdata'])) {
            $values = explode(',', $field['userdata']);
            if (!empty($values)) {
              foreach ($values as $v) {
               $value[] = $options[$v];
              }
            }
          }
        }
      } else if (!empty($field['userdata'])) {
        $value = $field['userdata'];
      }
      $data[$field['field_id']] = $value;
    }
    return $data;
  }

  public function usersGetProfileFieldOptions(array $properties) {
    $action = 'users.getProfileBlock';
    $properties['format'] = 'json';
    $output = $this->$action($properties);
    $result = json_decode($output, true);
    $fields = $result['profile_blocks']['profile_block']['fields']['field'];
    $data = array();
    foreach ($fields as $k => $field) {
      if (!empty($field['options_list'])) {
        if (is_array($field['options_list'])) {
          //
        } else {
          $options = explode(',', $field['options_list']);
          $data[$field['field_id']] = array_flip($options);
        }
      }
    }
    return $data;
  }

  public function userEditProfileBlock(array $properties) {
    $action = 'users.editProfileBlock';
    $properties['format'] = 'json';
    $output =  $this->$action($properties);
    $result = json_decode($output, true);
    if ($result['status'] == 'ok') {
      return true;
    }
    return false;
  }

  public function updateUser(array $properties) {
    $action = 'users.editUser';
    $properties['format'] = 'json';
    $output =  $this->$action($properties);
    $result = json_decode($output, true);
    if ($result['status'] == 'ok') {
      return $result['createUser']['success']['user_id'];
    }
    return false;
  }
  
  public function createUser(array $properties) {
    $action = 'users.createUser';
    $properties['format'] = 'json';
    $output =  $this->$action($properties);
    $result = json_decode($output, true);
    if ($result['status'] == 'ok') {
      return $result['createUser']['success']['user_id'];
    }
    return false;
  }
  
  public function getUserSegment(array $properties) {
    $action = 'users.getList';
    $properties['format'] = 'json';
    $output =  $this->$action($properties);
    $result = json_decode($output, true);
    $data = array();
    if ($result['status'] == 'ok') {
      $tiers = $result['users']['user']['tiers']['tier'];
      if( !is_array($tiers)) {
        $tiers = (array) $tiers;
      }
      return $tiers;
    }
    return false;
  }
  
  public function getUser(array $properties) {
    $action = 'users.getList';
    $properties['format'] = 'json';
    $output =  $this->$action($properties);
    $result = json_decode($output, true);
    if ($result['users']['@attributes']['count'] == '0') {
      return false;
    }
    $data = array();
    if ($result['status'] == 'ok') {
      if ($result['users']['@attributes']['count'] == '1') {
        $swlid = $result['users']['user']['user_id'];
        $email = $result['users']['user']['email_address'];
        $data[$swlid]= $email;
      } else {
        foreach ($result['users']['user'] as $user) {
          $data[$user['user_id']] = $user['email_address'];
        }
      }
      return $data;
    }
    return false;
  }
}

?>
