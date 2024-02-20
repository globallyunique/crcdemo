<?php

namespace Drupal\mix\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Mix form.
 */
class ChangePasswordForm extends FormBase {

  /**
   * The Password Hasher.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $passwordHasher;

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Is a Current password required?
   *
   * @var bool
   */
  protected $isCurrentPasswordRequired = TRUE;

  /**
   * Constructs a UserPasswordForm object.
   *
   * @param \Drupal\Core\Password\PasswordInterface $password_hasher
   *   The password service.
   */
  public function __construct(PasswordInterface $password_hasher) {
    $this->passwordHasher = $password_hasher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('password')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mix_change_password';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {

    $this->account = $user;

    // Hide 'Current password' field if the user is resetting their password,
    // or current user has 'administer users' permission.
    $request = $this->getRequest();
    $isResetPassword = FALSE;
    if ($token = $request->query->get('pass-reset-token')) {
      $session_key = 'pass_reset_' . $this->account->id();
      $session_value = $request->getSession()->get($session_key);
      $isResetPassword = isset($session_value) && hash_equals($session_value, $token);
    }
    $isAdmin = \Drupal::currentUser()->hasPermission('administer users');
    $this->isCurrentPasswordRequired = !$isAdmin && !$isResetPassword;

    $form['account']['current_pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Current password'),
      '#size' => 25,
      '#access' => $this->isCurrentPasswordRequired,
      '#required' => $this->isCurrentPasswordRequired,
      '#weight' => -5,
      // Do not let web browsers remember this password, since we are
      // trying to confirm that the person submitting the form actually
      // knows the current one.
      '#attributes' => ['autocomplete' => 'off'],
    ];

    $form['account']['pass'] = [
      '#type' => 'password_confirm',
      '#size' => 25,
      '#description' => $this->t('To change the current user password, enter the new password in both fields.'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check current password if the field is required.
    $current_pass = trim($form_state->getValue('current_pass'));
    if ($this->isCurrentPasswordRequired && !$this->passwordHasher->check($current_pass, $this->account->getPassword())) {
      $form_state->setErrorByName('current_pass', $this->t('The current password is missing or incorrect'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pass = $form_state->getValue('pass');
    $account = $this->account;
    $account->setPassword($pass);
    $account->save();
    $this->messenger()->addStatus($this->t('The password has been changed.'));
  }

}
