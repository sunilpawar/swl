<?php

require_once 'CRM/Core/Form.php';

class CRM_Swl_Form_Settingapi4 extends CRM_Core_Form {

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
      CRM_Core_Session::setStatus("You need to upgrade to version 4.4 or above to work with extension Hubspot", "Version:");
    }
  }


  /**
   * Function to actually build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    $civicrmFields = CRM_Swl_Utils::getCiviCRMFields();
    // Add the API Key Element
    $this->addElement('text', 'api4_key', ts('API4 Key'), ['size' => 48]);
    $this->addElement('text', 'api4_key_cron', ts('API4 Key CRON'), ['size' => 48]);

    $this->addElement('text', 'api4_secret', ts('API4 Secret'), ['size' => 48]);
    $this->addElement('text', 'api4_secret_cron', ts('API4 Secret CRON'), ['size' => 48]);

    $this->addElement('text', 'api4_base', ts('API4 Base'), ['size' => 48]);
    $this->addElement('text', 'api4_base_cron', ts('API4 Base CRON'), ['size' => 48]);

    $this->addElement('text', 'api4_sub', ts('API4 Owner Id'), ['size' => 48]);
    $this->addElement('text', 'api4_sub_cron', ts('API4 Owner Id CRON'), ['size' => 48]);
    $this->add('select', "api4_swl_id", "SWL ID",
      $civicrmFields, FALSE, ['class' => 'crm-select2', 'placeholder' => ts('- any -')]);
    $this->add('select', "api4_swl_id_update", "SWL ID Update",
      $civicrmFields, FALSE, ['class' => 'crm-select2', 'placeholder' => ts('- any -')]);
    $this->add('select', "api4_swl_is_active", "Is SWL Active?",
      $civicrmFields, FALSE, ['class' => 'crm-select2', 'placeholder' => ts('- any -')]);
    $this->add('select', "api4_swl_join_date", "SWL Join Date",
      $civicrmFields, FALSE, ['class' => 'crm-select2', 'placeholder' => ts('- any -')]);
    $this->add('select', "api4_swl_last_login", "SWL Last Login",
      $civicrmFields, FALSE, ['class' => 'crm-select2', 'placeholder' => ts('- any -')]);
    // Create the Submit Button.
    $buttons = [
      [
        'type' => 'submit',
        'name' => ts('Save'),
      ],
    ];
    // Add the Buttons.
    $this->addButtons($buttons);
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaults = [];
    $domainID = CRM_Core_Config::domainID();
    $settings = Civi::settings($domainID);
    $defaults['api4_key'] = $settings->get('api4_key');
    $defaults['api4_key_cron'] = $settings->get('api4_key_cron');
    $defaults['api4_secret'] = $settings->get('api4_secret');
    $defaults['api4_secret_cron'] = $settings->get('api4_secret_cron');
    $defaults['api4_base'] = $settings->get('api4_base');
    $defaults['api4_base_cron'] = $settings->get('api4_base_cron');
    $defaults['api4_sub'] = $settings->get('api4_sub');
    $defaults['api4_sub_cron'] = $settings->get('api4_sub_cron');
    $defaults['api4_swl_id'] = $settings->get('api4_swl_id');
    $defaults['api4_swl_id_update'] = $settings->get('api4_swl_id_update');
    $defaults['api4_swl_is_active'] = $settings->get('api4_swl_is_active');
    $defaults['api4_swl_join_date'] = $settings->get('api4_swl_join_date');
    $defaults['api4_swl_last_login'] = $settings->get('api4_swl_last_login');

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
    $setting = ['api4_key', 'api4_secret', 'api4_base', 'api4_sub', 'api4_key_cron', 'api4_secret_cron', 'api4_base_cron', 'api4_sub_cron',
      'api4_swl_id', 'api4_swl_is_active', 'api4_swl_join_date', 'api4_swl_last_login', 'api4_swl_id_update'];
    $domainID = CRM_Core_Config::domainID();
    $settings = Civi::settings($domainID);
    foreach ($setting as $key) {
      if (CRM_Utils_Array::value($key, $params)) {
        $settings->set($key, $params[$key]);
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
    $elementNames = [];
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

