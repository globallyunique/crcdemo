<?php

namespace Drupal\gcal_entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger;

/**
 * Form controller for GCal Entity edit forms.
 *
 * @ingroup gcal_entity
 */
class GcalEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);
    $messenger = \Drupal::messenger();

    switch ($status) {
      case SAVED_NEW:
        $messenger->addMessage($this->t('Created the %label GCal Entity.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $messenger->addMessage($this->t('Saved the %label GCal Entity.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.gcal_entity.canonical', ['gcal_entity' => $entity->id()]);
  }

}
