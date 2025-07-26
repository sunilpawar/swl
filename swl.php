<?php

require_once 'swl.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function swl_civicrm_config(&$config) {
  _swl_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function swl_civicrm_install() {
  _swl_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function swl_civicrm_enable() {
  _swl_civix_civicrm_enable();
}

/**
 * Functions below this ship commented out. Uncomment as required.
 *

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *

*/

/**
 * Implementation of hook_civicrm_buildForm
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
 */
function swl_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Group_Form_Edit' && ($form->getAction() == CRM_Core_Action::ADD OR $form->getAction() == CRM_Core_Action::UPDATE)) {
    $lists = CRM_Swl_Api4_Utils::swlGetList();
    if (!empty($lists)) {
      foreach ($form->_groupTree as $group) {
        if ($group['title'] == "Swl Settings") {
          foreach ($group['fields'] as $field) {
            if ($field['label'] == "Swl Group") {
              if (array_key_exists($field['element_name'], $form->_elementIndex)) {
                //$form->removeElement($field['element_name']);
              }
              $form->add('select', $field['element_name'], ts('Swl Group'), ['' => '- select -'] + $lists);
            }
          }
        }
      }
    }
  }
}

/**
 * Implementation of hook_civicrm_pre
 */
function swl_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
  if ($op == 'delete') {
    return;
  }
  if (in_array($objectName, ['Individual'])) {
    CRM_Swl_Api4_Utils::swl_sync($objectRef->id);
  }
  elseif ($objectName == 'Email') {
    CRM_Swl_Api4_Utils::swl_sync($objectRef->contact_id);
  }
  elseif ($objectName == 'Address') {
    CRM_Swl_Api4_Utils::swl_sync($objectRef->contact_id);
  }
  elseif ($objectName == 'Membership') {
    CRM_Swl_Api4_Utils::swl_sync($objectRef->contact_id);
  }
}

/**
 * Implementation of hook_civicrm_navigationMenu
 */
function swl_civicrm_navigationMenu( &$params ) {
  // Add menu entry for extension administration page
  _swl_civix_insert_navigation_menu($params, 'Administer/Customize Data and Screens', [
    'name' => 'SWL User Delete Process',
    'url' => 'civicrm/swl/swluser',
    'permission' => 'administer CiviCRM',
  ]);
  _swl_civix_insert_navigation_menu($params, 'Administer/Customize Data and Screens', [
    'name' => 'SWL API 4 Settings',
    'url' => 'civicrm/swl/settings/api4',
    'permission' => 'administer CiviCRM',
  ]);
  _swl_civix_insert_navigation_menu($params, 'Administer/Customize Data and Screens', [
    'name' => 'SWL API 2 Settings',
    'url' => 'civicrm/swl/settings',
    'permission' => 'administer CiviCRM',
  ]);
}
