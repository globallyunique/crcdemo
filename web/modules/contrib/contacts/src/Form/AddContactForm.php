<?php

namespace Drupal\contacts\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\profile\Plugin\Field\FieldWidget\ProfileFormWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form class used for the Add Contact screen.
 *
 * Used by the 'add_contact' form mode on the Profile entity.
 */
class AddContactForm extends ContactsProfileForm {

  /**
   * Theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManager
   */
  protected $themeManager;

  /**
   * Profile to create.
   *
   * @var \Drupal\profile\Entity\Profile
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $self = parent::create($container);
    $self->themeManager = $container->get('theme.manager');
    return $self;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    if ($this->themeManager->getActiveTheme()->getName() === 'contacts_theme') {
      // The contacts theme doesn't display status messages by default
      // (it only includes them on certain pages on the contacts
      // dashboard), so we need to explicitly render status messages if we're
      // inside the contacts theme.
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
    }

    // If we're creating an organisation, org name should be required.
    if (isset($form['crm_org_name'])) {
      $form['crm_org_name']['widget'][0]['value']['#required'] = TRUE;
    }

    // If we're creating a person, name should be required.
    if (isset($form['crm_name'])) {
      $form['crm_name']['widget'][0]['#required'] = TRUE;
      $form['crm_name']['widget'][0]['#show_component_required_marker'] = TRUE;
    }

    // Note the contacts_mail pseudo/surrogate field is injected
    // into all profile forms including this one if it's configured in the
    // form mode. See Drupal\contacts\PsuedoEmailField::alterProfileForm.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    // Remove the Cancel action added by the ContactsProfileForm.
    unset($actions['cancel']);

    // Add handler for 'save and add another'.
    $submit_handlers = $actions['submit']['#submit'];
    $submit_handlers[] = [ProfileFormWidget::class, 'saveProfiles'];
    $submit_handlers[] = '::addAnother';

    $actions['submit_and_add_another'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and add another'),
      '#button_type' => 'primary',
      '#submit' => $submit_handlers,
    ];

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    $form_state->setRedirect('contacts.contact', [
      'user' => $this->entity->getOwnerId(),
    ]);
  }

  /**
   * Submit handler when 'Save & Add Another' is clicked.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State.
   */
  public function addAnother(array &$form, FormStateInterface $form_state) {
    // Redirect back to self.
    $form_state->setRedirect(\Drupal::routeMatch()->getRouteName());
  }

}
