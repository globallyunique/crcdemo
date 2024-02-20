<?php

namespace Drupal\maestro\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for this site.
 */
class MaestroEngineSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'maestro_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'maestro.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('maestro.settings');

    $default = $config->get('maestro_redirect_location');
    $form['maestro_redirect_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URI used in notifications to redirect the recipient to.'),
      '#default_value' => isset($default) ? $default : '/taskconsole',
      '#description' => $this->t('Defaults to /taskconsole'),
      '#required' => TRUE,
    ];

    $form['maestro_send_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Send out notifications"),
      '#default_value' => $config->get('maestro_send_notifications'),
      '#description' => $this->t('When checked, this config value will enable outgoing notifications. It is important to note that this will enable or disable ALL Maestro-based messages. ') . 
        $this->t('This includes the Maestro Zero-User Notification Mechanism.'),
    ];

    $form['maestro_orchestrator_task_console'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Run the Orchestrator on Task Console Refreshes"),
      '#default_value' => $config->get('maestro_orchestrator_task_console'),
      '#description' => $this->t('When checked, a refresh of the Task Console (provided by Maestro) will run the orchestrator.'),
    ];

    $default = $config->get('maestro_orchestrator_token');
    $form['maestro_orchestrator_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The token that MUST be appended to the /orchestrator URL in order to run the orchestrator.'),
      '#default_value' => isset($default) ? $default : '',
      '#description' => $this->t('Defaults to nothing.  YOU MUST SET THIS!  Resulting URL to run the orchestrator is http://[site]/orchestrator/{token}'),
      '#required' => TRUE,
    ];

    $default = $config->get('maestro_orchestrator_lock_execution_time');
    $form['maestro_orchestrator_lock_execution_time'] = [
      '#type' => 'number',
      '#size' => 5,
      '#title' => $this->t('The number of seconds you wish to let the orchestrator lock for.'),
      '#default_value' => isset($default) ? $default : '30',
      '#description' => $this->t('Default 30 seconds. You must tune this value to however long you believe the orchestrator can potentially run for.'),
      '#required' => TRUE,
    ];

    $form['maestro_orchestrator_development_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Turn on Maestro Development Mode during orchestrator execution."),
      '#default_value' => $config->get('maestro_orchestrator_development_mode'),
      '#description' => $this->t('LEAVE THIS OFF EXCEPT WHEN TROUBLESHOOTING. When checked, Maestro Development mode is activated.') .
      $this->t('This forces the orchestrator to reset the cache on entity queries.') .
      $this->t('This will add processing time to the orchestrator. Please see the Maestro Installation Documentation on Drupal.org (https://www.drupal.org/docs/8/modules/maestro/installation).'),
    ];

    /*
     * The following settings are being phased in for future Maestro releases
     */

     $form['tokenization'] = [
       '#title' => $this->t('Maestro URL Tokens'),
       '#type' => 'fieldset',
     ];

     $form['tokenization']['maestro_sitewide_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Provide a site-wide token key for use in the URL as the key in a key-value pair'),
      '#description' => $this->t('This token key that Maestro tasks will recognize as a unique identifier for Queue and Process IDs.'),
      '#default_value' => $config->get('maestro_sitewide_token'),
     ];

     $form['tokenization']['maestro_token_zero_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Zero-User notification mechanism.'),
      '#default_value' => $config->get('maestro_token_zero_user'),
      '#description' => $this->t('Requires site-wide URL token to be set. This is a developer-based mechaism that enables outgoing task notifications.') .
        $this->t('When checked, the Maestro hook of hook_maestro_zero_user_notification is enabled and allows developers to use the hook to send notification emails even when no active users are assigned to an interactive task.'),
      '#states' => [
        'enabled' => [
          // Don't mistake :input for the type of field or for a css selector --
          // it's a jQuery selector. 
          // You can always use :input or any other jQuery selector here, no matter 
          // whether your source is a select, radio or checkbox element.
          ':input[id="edit-maestro-sitewide-token"]' => ['filled' => TRUE],
        ],
      ],
    ];

     

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('maestro.settings')
      ->set('maestro_send_notifications', $form_state->getValue('maestro_send_notifications'))
      ->save();

    $this->config('maestro.settings')
      ->set('maestro_orchestrator_task_console', $form_state->getValue('maestro_orchestrator_task_console'))
      ->save();

    $this->config('maestro.settings')
      ->set('maestro_redirect_location', $form_state->getValue('maestro_redirect_location'))
      ->save();

    $this->config('maestro.settings')
      ->set('maestro_orchestrator_token', $form_state->getValue('maestro_orchestrator_token'))
      ->save();

    $this->config('maestro.settings')
      ->set('maestro_orchestrator_lock_execution_time', $form_state->getValue('maestro_orchestrator_lock_execution_time'))
      ->save();

    $this->config('maestro.settings')
      ->set('maestro_orchestrator_development_mode', $form_state->getValue('maestro_orchestrator_development_mode'))
      ->save();

    $this->config('maestro.settings')
      ->set('maestro_sitewide_token', $form_state->getValue('maestro_sitewide_token'))
      ->save();

    if($form_state->getValue('maestro_sitewide_token') != '') {
      $this->config('maestro.settings')
        ->set('maestro_token_zero_user', $form_state->getValue('maestro_token_zero_user'))
        ->save();
    } 
    else {
      $this->config('maestro.settings')
        ->set('maestro_token_zero_user', 0)
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
