<?php

require_once 'CRM/Core/Page.php';

class CRM_Swl_Page_Webhook extends CRM_Core_Page {
  function run() {
    $userID = CRM_Utils_Request::retrieve('user_id', 'Positive', CRM_Core_DAO::$_nullObject, TRUE);
    CRM_Swl_Api4_Utils::batchUsersGetProfileBlock($userID);
    echo 'Done';
    exit;
  }
}
