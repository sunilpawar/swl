<?php


/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Swl_Form_Swluser extends CRM_Core_Form {
  public function buildQuickForm() {

    $this->add('textarea', 'swl_ids', ts('SWL IDS'), "cols=100 rows=10");

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Search'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'refresh',
        'name' => ts('Mark Deleted'),
      ],
      [
        'type' => 'next',
        'name' => ts('Mark Un-Deleted'),
      ],
    ]);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    if (!empty($values['swl_ids'])) {
      $string = $values['swl_ids'];
      $matches = explode(',', preg_replace('/\s+/', ',', $string));
      $neaterArray = array_values(array_unique($matches));
      $swlIds = implode(',', $neaterArray);
      if (isset($values['_qf_Swluser_refresh']) && !empty($swlIds)) {
        $this->markDeleted($swlIds);
      }
      $query = "select c.id, c.sort_name, d.email, uf.uf_id, d.user_id
        from civicrm_swl_user_details d
          INNER  JOIN civicrm_contact c ON (c.id = d.contact_id)
          LEFT JOIN civicrm_uf_match uf ON (uf.contact_id = c.id)
          WHERE d.user_id IN ($swlIds)
        ";
      $dao = CRM_Core_DAO::executeQuery($query);
      $htm = '<table class="selector"><tr>
        <th>Name</th>
        <th>Email</th
        ><th>SWI ID</th>
        <th>Drupal User ID</th>
        </tr>';
      while ($dao->fetch()) {
        $url = CRM_Utils_System::url("civicrm/contact/view", "reset=1&cid=" . $dao->id);
        $mainContactUrl = "<a href='{$url}' target='_blank'>" . $dao->sort_name . "</a>";
        $data[$dao->id]['name'] = $mainContactUrl;
        $data[$dao->id]['email'] = $dao->email;
        $data[$dao->id]['swl_id'] = $dao->user_id;
        $data[$dao->id]['uf_id'] = "<a href='/user/{$dao->uf_id}' target='_blank'>" . $dao->uf_id . "</a>";
      }
      foreach ($data as $contact) {
        $htm .= '<tr>';
        foreach ($contact as $value) {
          $htm .= '<td>' . $value . '</td>';
        }
        $htm .= '</tr>';
      }
      $htm .= '</table>';
      //echo $htm;
      $this->assign('html_swl', $htm);

    }
    parent::postProcess();
  }

  public function markDeleted($swlIds) {
    $query = "UPDATE civicrm_swl_user_details set mark_deleted = 1 WHERE user_id IN ($swlIds)";
    $dao = CRM_Core_DAO::executeQuery($query);
  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
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
