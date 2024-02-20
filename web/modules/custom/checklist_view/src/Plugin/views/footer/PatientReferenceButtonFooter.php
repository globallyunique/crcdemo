<?php

namespace Drupal\checklist_view\Plugin\views\footer;

use Drupal\views\Plugin\views\footer\FooterPluginBase;
use Drupal\views\ResultRow;
use Drupal\maestro\Engine\MaestroEngine;

/**
 * Footer plugin to present a button linked with field_patient_reference.
 *
 * @ingroup views_footer_plugins
 *
 * @ViewsFooter("patient_reference_button_footer")
 */

 // THIS HAS NOT BEEN TESTED. IT IS A EXPERIMENTAL ATTEMPT TO CREATE A BUTTON IN THE FOOTER OF THE CHECKLIST VIEW.
class PatientReferenceButtonFooter extends FooterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if (!$empty || !empty($this->options['empty'])) {
      // Get the last row's field_patient_reference value.
      $last_row = end($this->view->result);
      $process_id = $last_row->_entity->id();
      $engine = new MaestroEngine();
      $process_variable = $engine->getProcessVariable('field_patient_reference', $process_id);

      // Create a button linked with field_patient_reference.
      $button = [
        '#type' => 'button',
        '#value' => $this->t('Button'),
        '#attributes' => [
          'onclick' => 'location.href="' . $process_variable . '";return false;',
          'class' => 'btn btn-primary',
        ],
      ];

      return $button;
    }
  }

}