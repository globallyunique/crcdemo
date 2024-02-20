<?php

namespace Drupal\redhen_contact\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Contact edit forms.
 *
 * @ingroup redhen_contact
 */
class ContactForm extends ContentEntityForm {
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\redhen_contact\Entity\Contact */
    $form = parent::buildForm($form, $form_state);
    // Check permission for "Linked user" field.
    $form['uid']['#access'] = $this->currentUser()->hasPermission('link contact entities to users');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created %label.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved %label.', [
          '%label' => $entity->label(),
        ]));
    }
    if (!$form_state->getRedirect()) {
      $form_state->setRedirect('entity.redhen_contact.canonical', ['redhen_contact' => $entity->id()]);
    }
  }

}
