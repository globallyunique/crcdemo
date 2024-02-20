<?php

namespace Drupal\contacts\Form;

use Drupal\contacts\Entity\ContactTab;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to edit Contact tab entities.
 */
class ContactTabForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\contacts\Entity\ContactTabInterface $contact_tab */
    $contact_tab = $this->entity;

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add contact tab');
    }
    else {
      $form['#title'] = $this->t('Edit %label tab', ['%label' => $contact_tab->label()]);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $contact_tab->label(),
      '#description' => $this->t("Label for the tab."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $contact_tab->id(),
      '#machine_name' => [
        'exists' => '\Drupal\contacts\Entity\ContactTab::load',
      ],
      '#disabled' => !$contact_tab->isNew(),
    ];

    $form['path'] = [
      '#type' => 'textfield',
      '#default_value' => $contact_tab->getPath(),
      '#title' => $this->t('Path'),
      '#description' => $this->t('Can only contain lowercase letters, numbers and hyphens.'),
      '#required' => TRUE,
      '#pattern' => '[a-z0-9\-]+',
    ];

    // phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
    $roles = array_map(['\Drupal\Component\Utility\Html', 'escape'], user_role_names(TRUE));
    $form['account']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#description' => $this->t('Only show this tab for contacts with the following roles. If none are selected it will be shown for any contact.'),
      '#default_value' => $contact_tab->getRoles(),
      '#options' => $roles,
    ];

    // @todo Switch path to machine name when issue is fixed.
    // @see https://www.drupal.org/node/2865059.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $path_value = $form_state->getValue('path');

    $tab_using_path = ContactTab::loadByPath($path_value);
    if ($tab_using_path && $tab_using_path->id() != $this->entity->id()) {
      $form_state->setErrorByName('path', $this->t('The path must be unique. This path is already in use by %label [@id].', [
        '%label' => $tab_using_path->label(),
        '@id' => $tab_using_path->id(),
      ]));
    }

    if (preg_match('@[^a-z0-9\-]+@', $path_value)) {
      $form_state->setErrorByName('path', $this->t('The path must contain only lowercase letters, numbers, and hyphens.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $contact_tab = $this->entity;
    $status = $contact_tab->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Contact tab.', [
          '%label' => $contact_tab->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Contact tab.', [
          '%label' => $contact_tab->label(),
        ]));
    }
    $form_state->setRedirectUrl($contact_tab->toUrl('collection'));
  }

}
