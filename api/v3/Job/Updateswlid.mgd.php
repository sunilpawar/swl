<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'Cron:Job.Updateswlid',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Call Job.Updateswlid API',
      'description' => 'Call Job.Updateswlid API',
      'run_frequency' => 'Hourly',
      'api_entity' => 'Job',
      'api_action' => 'Updateswlid',
      'parameters' => '',
    ],
  ],
];
