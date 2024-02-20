<?php

namespace Drupal\maestro_template_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\maestro_template_builder\Ajax\FireJavascriptCommand;

/**
 * Maestro Template Editor Edit a Task Form.
 */
class MaestroTemplateBuilderEditTask extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'template_edit_task';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // TODO:  we should be passing validation off to the tasks as well.
    $templateMachineName = $form_state->getValue('template_machine_name');
    $taskID = $form_state->getValue('task_id');
    $template = MaestroEngine::getTemplate($templateMachineName);
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskID);
    $executableTask = MaestroEngine::getPluginTask($task['tasktype']);

    $executableTask->validateTaskEditForm($form, $form_state);
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
    // Remove the session variable for the task being edited.
    $_SESSION['maestro_template_builder']['maestro_editing_task'] = '';
    $response->addCommand(new HtmlCommand('#edit-task-form', $form));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If we have errors in the form, show those.
    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response = new AjaxResponse();
      // Replaces the form HTML with the validated HTML.
      $response->addCommand(new HtmlCommand('#edit-task-form', $form));
      return $response;
    }
    // otherwise, we can get on to saving the task.
    else {
      // This should be managed by the engine.  in here for the time being.
      $templateMachineName = $form_state->getValue('template_machine_name');
      $taskID = $form_state->getValue('task_id');
      $template = MaestroEngine::getTemplate($templateMachineName);
      $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskID);
      $executableTask = MaestroEngine::getPluginTask($task['tasktype']);

      // first, lets let the task do any specific or unique task preparations.
      // Prepares any specific pieces of the task for us.
      $executableTask->prepareTaskForSave($form, $form_state, $task);
      // Now the core maestro requirements like the assignments and notifications.
      $result = $executableTask->saveTask($form, $form_state, $task);
      // Oh Oh.  Some sort of error in saving the template.
      if ($result === FALSE) {
        \Drupal::messenger()->addError(t('Error saving your task.'));
        $form['status_messages'] = [
          '#type' => 'status_messages',
          '#weight' => -10,
        ];
      }
    }

    // Rebuild the form to get an updated table of assignment information.
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function saveForm(array &$form, FormStateInterface $form_state) {
    // If we have errors in the form, show those.
    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response = new AjaxResponse();
      // Replaces the form HTML with the validated HTML.
      $response->addCommand(new HtmlCommand('#edit-task-form', $form));
      return $response;
    }
    // Save of the task has already been done in the submit.  We now are only responsible for updating the UI and updating the form.
    $templateMachineName = $form_state->getValue('template_machine_name');
    $taskID = $form_state->getValue('task_id');
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskID);

    $update = [
      'label' => $task['label'],
      'taskid' => $task['id'],
      'body' => 'placeholder',
      'participate_in_workflow_status_stage' => $task['participate_in_workflow_status_stage'],
      'workflow_status_stage_number' => $task['workflow_status_stage_number'],
      'workflow_status_stage_message' => $task['workflow_status_stage_message'],
    ];

    $response = new AjaxResponse();
    $response->addCommand(new FireJavascriptCommand('maestroUpdateMetaData', $update));
    $response->addCommand(new HtmlCommand('#edit-task-form', $form));
    $response->addCommand(new FireJavascriptCommand('maestroShowSavedMessage', []));
    return $response;
  }

  /**
   * Ajax callback for add-new-form button click.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $templateMachineName = '') {
    $taskID = Xss::filter($_SESSION['maestro_template_builder']['maestro_editing_task']);
    $template = MaestroEngine::getTemplate($templateMachineName);
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskID);
    $task['form_state'] = $form_state;
    // Need to validate this taskID and template to ensure that they exist.
    if ($taskID == '' || $template == NULL || $task == NULL) {
      $form = [
        '#title' => t('Error!'),
        '#markup' => t('The task or template you are attempting to edit does not exist'),
      ];
      return $form;
    }

    $form = [
      '#title' => $this->t('Editing Task') . ': ' . $task['label'] . '(' . $taskID . ')',
      '#prefix' => '<div id="edit-task-form">',
      '#suffix' => '</div>',
    ];

    $form['save_task_notification'] = [
      '#markup' => $this->t('Task Saved'),
      '#prefix' => '<div id="save-task-notificaiton" class="messages messages--status">',
      '#suffix' => '</div>',
    ];
    // Get a handle to the task plugin.
    $executableTask = MaestroEngine::getPluginTask($task['tasktype']);

    // Get the base edit form that all tasks adhere to.
    $form += $executableTask->getBaseEditForm($task, $templateMachineName);

    // We now will pull back the edit form provided to us by the task itself.
    // this gives ultimate flexibility to developers.
    // even form alters work on this form by allowing the dev to detect what task_id is being edited
    // and get the task type and do any modifications on it from there.
    $form += $executableTask->getTaskEditForm($task, $templateMachineName);

    // Now is this thing interactive or not?
    // if so, we show the assignment and notification tabs.  If not, leave it out.
    if ($executableTask->isInteractive()) {
      $form += $executableTask->getAssignmentsAndNotificationsForm($task, $templateMachineName);
    }

    // Save button in an actions bar:
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Task'),
      '#required' => TRUE,
      '#ajax' => [
    // Use saveFrom rather than submitForm to alleviate the issue of calling a save handler twice.
        'callback' => [$this, 'saveForm'],
        'wrapper' => '',
      ],
    ];

    $form['actions']['close'] = [
      '#type' => 'button',
      '#value' => $this->t('Close'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'cancelForm'],
        'wrapper' => '',
      ],
    ];
    return $form;
  }

}
