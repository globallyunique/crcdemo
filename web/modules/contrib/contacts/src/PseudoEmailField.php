<?php

namespace Drupal\contacts;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Encapsulates customisations related to the pseudo email field.
 *
 * The pseudo email field is an Extra Field that can be used to expose the
 * user's email address on user Form Modes (normally it always includes the
 * password too) as well as Profile Form Displays and Profile Form Modes.
 *
 * If configured to show on profile form modes, then it will validate that
 * the email is not in use and allow you to change the email address via
 * the profile. By default this is only used on the contacts_dashboard and
 * add_contact form displays.
 */
class PseudoEmailField {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Instantiates the pseudo email helper class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Defines the extra field definitions.
   *
   * @param array $fields
   *   Array to which the field definitions should be added.
   *
   * @see contacts_entity_extra_field_info
   */
  public function extraFieldDefinitions(array &$fields) {
    // Allow exposing the email on User form/display without other account info.
    $fields['user']['user']['form']['mail'] = [
      'label' => t('Email'),
      'description' => t('Contacts user mail form element.'),
      'weight' => -10,
      'visible' => FALSE,
    ];

    // Alter the original account field to indicate that it also includes email.
    $fields['user']['user']['form']['account']['label'] = $this->t('@original (includes email)', [
      '@original' => $fields['user']['user']['form']['account']['label'],
    ]);

    // Also allow exposing the email from Profile forms/display too.
    $fields['profile']['crm_org']['display']['contacts_mail'] = [
      'label' => t('Email'),
      'description' => t('Contacts user mail form element.'),
      'weight' => -10,
      'visible' => FALSE,
    ];
    $fields['profile']['crm_indiv']['display']['contacts_mail'] = [
      'label' => t('Email'),
      'description' => t('Contacts user mail form element.'),
      'weight' => -10,
      'visible' => FALSE,
    ];
    $fields['profile']['crm_org']['form']['contacts_mail'] = [
      'label' => t('Email'),
      'description' => t('Contacts user mail form element.'),
      'weight' => -10,
      'visible' => FALSE,
    ];
    $fields['profile']['crm_indiv']['form']['contacts_mail'] = [
      'label' => t('Email'),
      'description' => t('Contacts user mail form element.'),
      'weight' => -10,
      'visible' => FALSE,
    ];
  }

  /**
   * Alters the user form.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State.
   * @param string $form_id
   *   Form id.
   *
   * @see contacts_form_user_form_alter
   */
  public function userFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    $display = $this->getFormDisplay($form_state);
    // Only add mail if the component is shown and the account is not.
    if ($display->getComponent('mail') && !$display->getComponent('account') && isset($form['account'])) {
      $form['mail'] = $form['account']['mail'];
    }

    // Don't allow non-admins to make other people admins.
    if (isset($form['account']['roles']['#options']['administrator']) && !in_array('administrator', \Drupal::currentUser()->getRoles())) {
      $form['account']['roles']['administrator']['#disabled'] = TRUE;
    }
  }

  /**
   * Alters the Profile form to include the pseudo email field.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State.
   * @param string $form_id
   *   Form id.
   *
   * @see contacts_form_profile_form_alter
   */
  public function profileFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    $display = $this->getFormDisplay($form_state);
    $profile = $this->getProfile($form_state);

    // Mail isn't a field on profile directly but Contacts allows a pseudo mail
    // to be added which links to the profile owner. Generate the markup for
    // this field if the form display is configured to include it.
    if ($display->getComponent('contacts_mail')) {
      if ($user = $profile->getOwner()) {
        /** @var \Drupal\user\UserInterface $user */
        // Prevent editing the user's email in the situation where the user
        // being edited is an administrator and the current user who is
        // making the change doesn't have access to change their record. Note
        // that normally we don't care about whether we have access to
        // update the target user because we are essentially treating the email
        // as a field on the profile (not the user), so as the editor has
        // access to the profile then we allow it. But we're making a special
        // case for if the target user is an admin.
        $restrict_access = !$user->access('update') && $this->isUserAdmin($user);

        $form['contacts_mail'] = [
          '#type' => 'email',
          '#title' => $this->t('Email address'),
          '#required' => $this->isEmailRequired($profile->bundle()),
          '#default_value' => $user->getEmail(),
          '#disabled' => $restrict_access,
          '#element_validate' => [
            [Email::class, 'validateEmail'],
            [$this, 'validateProfileEmail'],
          ],
        ];

        // Add in additional submission step that saves the email change.
        $submit_handler = [$this, 'saveProfileEmail'];
        array_unshift($form['actions']['submit']['#submit'], $submit_handler);
      }
      else {
        // Existing profile with no owner - orphaned.
        $form['contacts_mail'] = [
          '#type' => 'item',
          '#title' => $this->t('Email address'),
          '#markup' => $this->t('This profile is not associated with a user.'),
        ];
      }
    }
  }

  /**
   * Validation callback for the pseudo mail field.
   *
   * @param array $element
   *   Mail element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Form.
   */
  public function validateProfileEmail(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // If our pseudo-email field is present, we need to validate that the email
    // address is not in use. Note that this only covers existing profiles.
    $display = $this->getFormDisplay($form_state);
    $profile = $this->getProfile($form_state);

    $mail = trim($element['#value']);
    $user_id = $profile->getOwnerId();

    if ($user_id) {
      /** @var \Drupal\user\UserInterface $user */
      // Load the user afresh as we need to set the email in order to validate
      // and we want to ensure this change is isolated from the user instance
      // currently stored within getOwner().
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->loadUnchanged($user_id);
    }
    else {
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->create();
    }

    $user->setEmail($mail);
    $violations = $user->validate();

    foreach ($violations->getByField('mail') as $violation) {
      /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
      $form_state->setError($element, $violation->getMessage());
    }
    $display->flagWidgetsErrorsFromViolations($violations, $complete_form, $form_state);
  }

  /**
   * Saves changed emails on the profile.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State.
   */
  public function saveProfileEmail(array &$form, FormStateInterface $form_state) {
    // If the email address has been changed (via the pseudo mail field) then
    // change this on the corresponding user too.
    $profile = $this->getProfile($form_state);
    $mail = $form_state->getValue('contacts_mail');

    /** @var \Drupal\user\UserInterface $user */
    $user = $profile->getOwner();

    if ($mail !== $user->getEmail()) {
      $user->setEmail($mail);
      $user->save();
    }
  }

  /**
   * Gets the form display.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The current form display.
   */
  private function getFormDisplay(FormStateInterface $form_state) {
    return $form_state->get('form_display');
  }

  /**
   * Checks if the email field is required for a specific profile type.
   *
   * @param string $profile_type_id
   *   Profile type.
   *
   * @return bool
   *   TRUE if the email is required, otherwise FALSE.
   */
  private function isEmailRequired($profile_type_id) {
    $required_roles = $this->configFactory
      ->get('contacts.configuration')
      ->get('email_required_roles');

    /** @var \Drupal\profile\Entity\ProfileType $profile_type */
    $profile_type = $this->entityTypeManager
      ->getStorage('profile_type')
      ->load($profile_type_id);

    foreach (array_filter($profile_type->getRoles()) as $role_id) {
      if (in_array($role_id, $required_roles)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Checks if a user has any admin roles.
   *
   * @param \Drupal\user\UserInterface $user
   *   User to check.
   *
   * @return bool
   *   TRUE if the user has at least 1 admin role.
   */
  private function isUserAdmin(UserInterface $user) {
    /** @var \Drupal\user\Entity\Role[] $roles */
    $roles = $this->entityTypeManager
      ->getStorage('user_role')
      ->loadMultiple($user->getRoles());

    foreach ($roles as $role) {
      if ($role->isAdmin()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the current profile instance being edited in the form.
   *
   * @return \Drupal\profile\Entity\Profile
   *   The profile being edited.
   */
  protected function getProfile(FormStateInterface $form_state) {
    return $form_state->getFormObject()->getEntity();
  }

}
