<?php

namespace Drupal\maestro_template_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\maestro_template_builder\Ajax\FireJavascriptCommand;
use Drupal\maestro\Engine\MaestroEngine;

/**
 *
 */
class MaestroValidityCheck extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'template_validity_check';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Everything in the base form is mandatory.  nothing really to check here.
  }

  /**
   * {@inheritdoc}
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    // We cancel the modal dialog by first sending down the form's error state as the cancel is a submit.
    // we then close the modal.
    $response = new AjaxResponse();
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $items = MaestroEngine::performTemplateValidityCheck($form_state->getValue('template_machine_name'));
    if (count($items['failures']) > 0) {
      $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
    }
    else {
      $response->addCommand(new FireJavascriptCommand('turnOffValidationRequired', []));
    }
    $response->addCommand(new HtmlCommand('#template-validity-check', $form));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * Ajax callback for add-new-form button click.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $templateMachineName = '') {
    $template = MaestroEngine::getTemplate($templateMachineName);
    // Need to validate this template to ensure that it exists.
    if ($template == NULL) {
      $form = [
        '#title' => $this->t('Error!'),
        '#markup' => $this->t("The template you are attempting to add a task to doesn't exist"),
      ];
      return $form;
    }

    $items = MaestroEngine::performTemplateValidityCheck($templateMachineName);

    $form = [
      '#title' => $this->t('Validity Check'),
    ];

    // Failures.
    if (count($items['failures'])) {
      $form['#prefix'] = '<div id="template-validity-check" class="messages messages--error">';
      foreach ($items['failures'] as $item) {
        $form['#prefix'] .= '<div class="template-validity-check-issue">';
        $form['#prefix'] .= '<span class="template-validity-check-label">' . $this->t('Task ID') . ': </span>' . $item['taskID'] . "<br>";
        $form['#prefix'] .= '<span class="template-validity-check-label">' . $this->t('Task Label') . ': </span>' . $item['taskLabel'] . "<br>";
        $form['#prefix'] .= '<span class="template-validity-check-label">' . $this->t('Failure Note') . ': </span>' . $item['reason'] . "<br>";
        $form['#prefix'] .= '</div>';
      }
      $form['#prefix'] .= '</div>';
    }
    else {
      $form['#prefix'] = '<div id="template-validity-check" class="messages messages--status">' . $this->t('Validity Check Passed') . "</div>";
    }

    // Information.
    if (count($items['information'])) {
      $form['#prefix'] .= '<div id="template-validity-check-information" class="messages messages--warning">';
      foreach ($items['information'] as $item) {
        $form['#prefix'] .= '<div class="template-validity-check-issue">';
        $form['#prefix'] .= '<span class="template-validity-check-label">' . $this->t('Task ID') . ': </span>' . $item['taskID'] . "<br>";
        $form['#prefix'] .= '<span class="template-validity-check-label">' . $this->t('Task Label') . ': </span>' . $item['taskLabel'] . "<br>";
        $form['#prefix'] .= '<span class="template-validity-check-label">' . $this->t('Information Note') . ': </span>' . $item['reason'] . "<br>";
        $form['#prefix'] .= '</div>';
      }
      $form['#prefix'] .= '</div>';
    }

    $form['template_machine_name'] = [
      '#type' => 'hidden',
      '#default_value' => $templateMachineName,
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Save Template Validity'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'cancelForm'],
        'wrapper' => '',
      ],
    ];
    return $form;
  }

}
