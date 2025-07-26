<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Swl Contact Sync',
    'entity' => 'Job',
    'params' => array(
    	'version' => 3,
    	'api_entity' => "job",
      'sequential' => 1,
      'run_frequency' => "Daily",
      'name' => "Swl Contact Sync",
      'api_action' => "swl_sync",
    ),
  ),
  0 => array(
    'name' => 'Swl User Data',
    'entity' => 'Job',
    'params' => array(
      'version' => 3,
      'api_entity' => "job",
      'sequential' => 1,
      'run_frequency' => "Daily",
      'name' => "Swl User Pull",
      'api_action' => "swl_user_pull",
    ),
  ),
);
