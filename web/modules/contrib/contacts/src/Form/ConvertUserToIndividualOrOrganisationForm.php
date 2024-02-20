<?php

namespace Drupal\contacts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for converting users to individuals or organisations.
 *
 * This is only used if the user is neither an organisation or individual and is
 * embedded into the Summary tab.
 *
 * @see \Drupal\contacts\Plugin\Block\ContactsNoRoleBlock
 */
class ConvertUserToIndividualOrOrganisationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contacts_convert_user_to_organisation_or_individual';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['convert_to_individual'] = [
      '#type' => 'submit',
      '#value' => $this->t('Convert to Individual'),
      '#submit' => ['::convertToIndividual'],
    ];

    $form['actions']['convert_to_organisation'] = [
      '#type' => 'submit',
      '#value' => $this->t('Convert to Organisation'),
      '#submit' => ['::convertToOrganisation'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No-op.
  }

  /**
   * Converts user with no type to an Individual.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State.
   */
  public function convertToIndividual(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getRouteMatch()->getParameter('user');
    $user->addRole('crm_indiv');
    $user->save();
    $this->messenger()->addMessage($this->t('User has been marked as an individual.'));
  }

  /**
   * Converts user with no type to an Organisation.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State.
   */
  public function convertToOrganisation(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getRouteMatch()->getParameter('user');
    $user->addRole('crm_org');
    $user->save();
    $this->messenger()->addMessage($this->t('User has been marked as an organisation.'));
  }

}
