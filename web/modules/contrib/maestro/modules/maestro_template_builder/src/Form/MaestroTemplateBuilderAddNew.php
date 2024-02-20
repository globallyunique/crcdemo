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
 * Maestro Template Builder Add New form.
 */
class MaestroTemplateBuilderAddNew extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'template_add_new';
  }

  /**
   * {@inheritdoc}
   */
  public static function exists($submitted_value, array $element, FormStateInterface $form_state) {
    $templateMachineName = $form_state->getValue('template_machine_name');
    $template = MaestroEngine::getTemplate($templateMachineName);
    $tasks = $template->tasks;
    if (array_key_exists($submitted_value, $tasks) == TRUE) {
      return TRUE;
    }
    return FALSE;
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

    $response->addCommand(new HtmlCommand('#template-add-new-form', $form));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do we have any errors?  if so, handle them by returning the form's HTML and replacing the form.
    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response = new AjaxResponse();
      // Replaces the form HTML with the validated HTML.
      $response->addCommand(new HtmlCommand('#template-add-new-form', $form));
      return $response;
    }
    else {
      $templateMachineName = $form_state->getValue('template_machine_name');
      $id = $form_state->getValue('task_machine_name');
      $label = $form_state->getValue('task_label');
      $type = $form_state->getValue('choose_task');

      // Create the new task entry in the template.
      $template = MaestroEngine::getTemplate($templateMachineName);
      $this_task = MaestroEngine::getPluginTask($type);
      $capabilities = $this_task->getTemplateBuilderCapabilities();
      foreach ($capabilities as $key => $c) {
        $capabilities[$key] = 'maestro_template_' . $c;
      }
      $template->tasks[$id] = [
        'id' => $id,
        'label' => $label,
        'tasktype' => $type,
        'nextstep' => '',
        'nextfalsestep' => '',
        'top' => '15',
        'left' => '15',
        'assignby' => 'fixed',
        'assignto' => '',
        'raphael' => '',
        'to' => '',
        'pointedfrom' => '',
        'falsebranch' => '',
        'lines' => [],
      ];
      // We need to have this template validated now.
      $template->validated = FALSE;
      $template->save();
      $response = new AjaxResponse();
      $response->addCommand(new FireJavascriptCommand('signalValidationRequired', []));
      $response->addCommand(new FireJavascriptCommand('addNewTask', [
        'id' => $id,
        'label' => $label,
        'type' => $type,
        'capabilities' => $capabilities,
        'uilabel' => $this->t(str_replace('Maestro', '', $type)),
      ]));
      $response->addCommand(new CloseModalDialogCommand());
      return $response;
    }
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

    $form = [
      '#title' => $this->t('Add a new task'),
      '#markup' => '<div id="maestro-template-error" class="messages messages--error">dddd</div>',
    ];
    $form['#prefix'] = '<div id="template-add-new-form">';
    $form['#suffix'] = '</div>';

    // Add all the task types here.
    $manager = \Drupal::service('plugin.manager.maestro_tasks');
    $plugins = $manager->getDefinitions();

    $options = [];
    foreach ($plugins as $plugin) {
      if ($plugin['id'] != 'MaestroStart') {
        $task = $manager->createInstance($plugin['id'], [0, 0]);
        $options[$task->getPluginId()] = $task->shortDescription();
      }
    }

    $form['template_machine_name'] = [
      '#type' => 'hidden',
      '#title' => $this->t('machine name of the template'),
      '#default_value' => $templateMachineName,
      '#required' => TRUE,
    ];

    $form['task_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The Label for the new task'),
      '#required' => TRUE,
    ];

    $form['task_machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('A unique name for this task'),
      '#machine_name' => [
        'exists' => [get_class($this), 'exists'],
      ],
    ];

    $form['choose_task'] = [
      '#type' => 'radios',
      '#options' => $options,
      '#title' => $this->t('Which task would you like to create?'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Task'),
      '#required' => TRUE,
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'cancelForm'],
        'wrapper' => '',
      ],
    ];
    return $form;
  }

}
