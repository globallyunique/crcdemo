<?php

namespace Drupal\contacts\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * The Add Organisation form.
 *
 * @deprecated in contacts:2.0.0 and is removed from contacts:3.0.0.
 *   This has been replaced by the AddContactForm.
 *
 * @see https://www.drupal.org/project/contacts/issues/3306887
 */
class AddOrgForm extends AddContactBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contacts_add_org_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Add organisation');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state): void {
    parent::init($form_state);
    $this->user->addRole('crm_org');

    $this->profile = $this->entityTypeManager->getStorage('profile')->create([
      'type' => 'crm_org',
      'status' => TRUE,
      'is_default' => TRUE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getProfileFields(array $field_definitions): array {
    $field_definitions['crm_org_name']->setRequired(TRUE);
    return [
      'crm_org_name' => [],
    ];
  }

}
