<?php

namespace Drupal\contacts_dbs\Plugin\views\field;

use Drupal\contacts_dbs\Entity\DBSStatusInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Defines a DBS Status operations bulk form element.
 *
 * @ViewsField("dbs_bulk_form")
 */
class DBSBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   *
   * Provide a more useful title to improve the accessibility.
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);

    if (!empty($this->view->result)) {
      foreach ($this->view->result as $row_index => $result) {
        $status = $result->_entity;
        if ($status instanceof DBSStatusInterface) {
          $owner = $status->getOwner();
          if ($owner instanceof UserInterface) {
            $form[$this->options['id']][$row_index]['#title'] = $this->t('Update the DBS status for %name', ['%name' => $owner->label()]);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No DBS Statuses selected.');
  }

}
