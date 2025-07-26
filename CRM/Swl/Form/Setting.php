<?php

require_once 'CRM/Core/Form.php';

class CRM_Swl_Form_Setting extends CRM_Core_Form {
  
   /**
   * Function to pre processing
   *
   * @return None
   * @access public
   */
  function preProcess() { 
    $currentVer = CRM_Core_BAO_Domain::version(TRUE);
    //if current version is less than 4.4 dont save setting
    if (version_compare($currentVer, '4.4') < 0) {
      CRM_Core_Session::setStatus("You need to upgrade to version 4.4 or above to work with extension Hubspot","Version:");
    }
  }  

  
  /**
   * Function to actually build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    
    // Add the API Key Element
    $this->addElement('text', 'api_key', ts('API Key'), array(
      'size' => 48,
    ));
    
    $this->addElement('text', 'api_secret', ts('API Secret'), array(
      'size' => 48,
    ));
    
    $this->addElement('text', 'api_base', ts('API Base'), array(
      'size' => 48,
    ));

    $this->addElement('text', 'api_ssosecret', ts('API SSO'), array(
      'size' => 48,
    ));
    
    $this->addElement('text', 'swl_profile_block', ts('Profile Block ID'), array(
      'size' => 48,
    ));

    $this->addElement('text', 'swl_profile_block_first_name', ts('Profile First Name ID'), array(
      'size' => 48,
    ));
    $this->addElement('text', 'swl_profile_block_last_name', ts('Profile Last Name ID'), array(
      'size' => 48,
    ));
    $this->addElement('text', 'swl_profile_block_email', ts('Profile Email ID'), array(
      'size' => 48,
    ));      
    // Create the Submit Button.
    $buttons = array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
      ),
    );
    // Add the Buttons.
    $this->addButtons($buttons);
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaults = $details = array();
    $defaults['api_key']       = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_key',       NULL, FALSE);
    $defaults['api_secret']    = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_secret',    NULL, FALSE);
    $defaults['api_base']      = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_base',      NULL, FALSE);
    $defaults['api_ssosecret'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'api_ssosecret', NULL, FALSE);
    $defaults['swl_profile_block'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block', NULL, FALSE);
    $defaults['swl_profile_block_first_name'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block_first_name', NULL, FALSE);
    $defaults['swl_profile_block_last_name'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block_last_name', NULL, FALSE);
    $defaults['swl_profile_block_email'] = CRM_Core_BAO_Setting::getItem(CRM_Swl_Utils::SWL_SETTING_GROUP, 'swl_profile_block_email', NULL, FALSE);

    return $defaults;
  }

  /**
   * Function to process the form
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    // Store the submitted values in an array.
    $params = $this->controller->exportValues($this->_name);    
    // Save the API Key & Save the Security Key
    $setting = array('api_key', 'api_secret', 'api_base', 'api_ssosecret',
                    'swl_profile_block', 'swl_profile_block_first_name','swl_profile_block_last_name',
                    'swl_profile_block_email');
    foreach( $setting as $key) { 
      if (CRM_Utils_Array::value($key, $params)) {
        CRM_Core_BAO_Setting::setItem($params[$key], CRM_Swl_Utils::SWL_SETTING_GROUP, $key);
      }
    }
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }  
}

