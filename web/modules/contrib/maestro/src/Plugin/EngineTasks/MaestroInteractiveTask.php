<?php

namespace Drupal\maestro\Plugin\EngineTasks;

use Drupal\Core\Plugin\PluginBase;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\MaestroTaskTrait;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Maestro Interactive Task Plugin.
 *
 * The plugin annotations below should include:
 * id: The task type ID for this task.  For Maestro tasks, this is Maestro[TaskType].
 *     So for example, the start task shipped by Maestro is MaestroStart.
 *     The Maestro End task has an id of MaestroEnd
 *     Those task IDs are what's used in the engine when a task is injected into the queue.
 *
 * @Plugin(
 *   id = "MaestroInteractive",
 *   task_description = @Translation("The Maestro Engine's interactive task."),
 * )
 */
class MaestroInteractiveTask extends PluginBase implements MaestroEngineTaskInterface {

  use MaestroTaskTrait;

  /**
   * Constructor.
   */
  public function __construct($configuration = NULL) {
    if (is_array($configuration)) {
      $this->processID = $configuration[0];
      $this->queueID = $configuration[1];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isInteractive() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription() {
    return t('Interactive Task');
  }

  /**
   * {@inheritDoc}
   */
  public function description() {
    return $this->t('Interactive Task.');
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\Component\Plugin\PluginBase::getPluginId()
   */
  public function getPluginId() {
    return 'MaestroInteractive';
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#0000ff';
  }

  /**
   * Part of the ExecutableInterface
   * Execution of the interactive task does nothing except for setting the run_once flag
   * {@inheritdoc}.
   */
  public function execute() {
    // Need to set the run_once flag here
    // as interactive tasks are executed and completed by the user using the Maestro API.
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($this->queueID);
    $queueRecord->set('run_once', 1);
    $queueRecord->save();

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getExecutableForm($modal, MaestroExecuteInteractive $parent) {
    // By default, we will provide a form that has a base queueID field and a submit/reject button set.
    // you can override this with ease in your own handler.
    // the task console should be looking at whether this requires a handler form or not.
    // our implementation forces the handler to be a mandatory field, thus causing functions like: form maestro_accept_only_form in the maestro.module
    // file to fire.
    // We are hiding this for now, as you can override all of this with your own handler.
    $form['queueID'] = [
    // This is just a placeholder form to get you under way.
      '#type' => 'hidden',
      '#title' => $this->t('The queue ID of this task'),
      '#default_value' => $this->queueID,
      '#description' => $this->t('queueID'),
    ];

    $form['information_text'] = [
      '#plain_text' => $this->t('Default Maestro Interactive Task.'),
      '#suffix' => '<br><br>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Complete'),
    ];

    $form['actions']['reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
    ];

    if ($modal == 'modal') {
      $form['actions']['submit']['#ajax'] = [
        'callback' => [$parent, 'completeForm'],
        'wrapper' => '',
      ];

      $form['actions']['reject']['#ajax'] = [
        'callback' => [$parent, 'completeForm'],
        'wrapper' => '',
      ];
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function handleExecuteSubmit(array &$form, FormStateInterface $form_state) {
    $queueID = intval($form_state->getValue('maestro_queue_id'));
    $canExecute = MaestroEngine::canUserExecuteTask($queueID, \Drupal::currentUser()->id());

    $triggeringElement = $form_state->getTriggeringElement();
    if (strstr($triggeringElement['#id'], 'edit-submit') !== FALSE && $queueID > 0 && $canExecute) {
      MaestroEngine::completeTask($queueID, \Drupal::currentUser()->id());
    }
    elseif($canExecute) {
      // we'll complete the task, but we'll also flag it as TASK_STATUS_CANCEL.
      MaestroEngine::completeTask($queueID, \Drupal::currentUser()->id());
      MaestroEngine::setTaskStatus($queueID, TASK_STATUS_CANCEL);
    }
    else {
      //Note this as an exception? The user tried to complete without being assigned.
    }

    $task = MaestroEngine::getTemplateTaskByQueueID($queueID);
    if (isset($task['data']['redirect_to'])) {
      $response = new TrustedRedirectResponse($task['data']['redirect_to']);
      $form_state->setResponse($response);
    }

  }

  /**
   * {@inheritDoc}
   */
  public function getTaskEditForm(array $task, $templateMachineName) {
    $form = [
      '#markup' => t('Interactive Task Edit'),
    ];

    // Let modules signal the handlers they wish to share.
    $handlers = \Drupal::moduleHandler()->invokeAll('maestro_interactive_handlers', []);
    $handler_desc = $this->t('The function that contains the form definition for this instance of the interactive task.');
    if (isset($task['handler']) && isset($handlers[$task['handler']])) {
      $handler_desc = $handlers[$task['handler']];
    }

    // The handler will use a lookahead.
    $form['handler'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Handler'),
      '#default_value' => isset($task['handler']) ? $task['handler'] : '',
      '#required' => FALSE,
      '#autocomplete_route_name' => 'maestro.autocomplete.interactive_handlers',
      '#ajax' => [
        'callback' => [$this, 'interactiveHandlerCallback'],
        'event' => 'autocompleteclose',
        'wrapper' => 'handler-ajax-refresh-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    $form['handler_help_text'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $handler_desc,
      '#readonly' => TRUE,
      '#attributes' => [
        'class' => ['handler-help-message'],
        'id' => ['handler-ajax-refresh-wrapper'],
      ],
    ];

    $form['redirect_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Return Path'),
      '#description' => $this->t('You can specify where your return path should go upon task completion.'),
      '#default_value' => isset($task['data']['redirect_to']) ? $task['data']['redirect_to'] : 'taskconsole',
      '#required' => TRUE,
    ];

    $form['modal'] = [
      '#type' => 'select',
      '#title' => $this->t('Task presentation'),
      '#description' => $this->t('Should this task be shown as a modal or full screen task.'),
      '#default_value' => isset($task['data']['modal']) ? $task['data']['modal'] : 'modal',
      '#options' => [
        'modal' => $this->t('Modal'),
        'notmodal' => $this->t('Full Page'),
      ],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * Implements callback for Ajax event on objective selection.
   *
   * @param array $form
   *   From render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current state of form.
   *
   * @return array
   *   Objective selection section of the form.
   */
  public function interactiveHandlerCallback(array &$form, FormStateInterface $form_state) {
    $selected_handler = $new_objective_id = $form_state->getValue('handler');

    // Let modules signal the handlers they wish to share.
    $handlers = \Drupal::moduleHandler()->invokeAll('maestro_interactive_handlers', []);
    if ($selected_handler != '' && !function_exists($selected_handler)) {
      $handler_desc = \Drupal::translation()->translate('This handler form function does not exist.');
    }
    elseif (isset($handlers[$selected_handler])) {
      $handler_desc = $handlers[$selected_handler];
    }
    else {
      $handler_desc = \Drupal::translation()->translate('The function that contains the form definition for this instance of the interactive task.');
    }

    $form['handler_help_text'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $handler_desc,
      '#readonly' => TRUE,
      '#attributes' => [
        'class' => ['handler-help-message'],
        'id' => ['handler-ajax-refresh-wrapper'],
      ],
    ];

    return $form['handler_help_text'];

  }

  /**
   * {@inheritDoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {
    $handler = $form_state->getValue('handler');
    /* Test if the interactive function name has comments in it's name
     * Defined inside [] so they can appear in the auto-complete result to the user.
     * Need to strip these comments out since they are not part of the real function name
     */
    if (strpos($handler, '[') > 0) {
      $string_parts = explode('[', $handler);
      $handler = $string_parts[0];
    }
    // Let's validate the handler here to ensure that it actually exists.
    if ($handler != '' && !function_exists($handler)) {
      $form_state->setErrorByName('handler', $this->t('This handler form function does not exist.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {
    $task['handler'] = $form_state->getValue('handler');
    /* Test if the interactive function name has comments in it's name
     * Defined inside [] so they can appear in the auto-complete result to the user.
     * Need to strip these comments out since they are not part of the real function name
     */
    if (strpos($task['handler'], '[') > 0) {
      $string_parts = explode('[', $task['handler']);
      $task['handler'] = $string_parts[0];
    }
    $task['data']['modal'] = $form_state->getValue('modal');
    $redirect = $form_state->getValue('redirect_to');
    if (isset($redirect)) {
      $task['data']['redirect_to'] = $redirect;
    }
    else {
      $task['data']['redirect_to'] = '';
    }

  }

  /**
   * {@inheritDoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    // Pretty simple -- just ensure that there's a handler and modal set.
    if ((array_key_exists('handler', $task) && $task['handler'] == '')  || !array_key_exists('handler', $task)) {
      $validation_information_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => t('The Interactive Task handler is missing and thus the engine will assign the default handler to this task.'),
      ];
    }

    if (!is_array($task['data']) || ((array_key_exists('modal', $task['data']) && $task['data']['modal'] == '')  || !array_key_exists('modal', $task['data']))) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => t('The Interactive Task has not been set up properly.  The "Task Presentation" option is missing and thus the engine will be unable to execute this task.'),
      ];
    }

    // This task should have assigned users
    // $task['assigned'] should have data.
    if ((array_key_exists('assigned', $task) && $task['assigned'] == '')  || !array_key_exists('assigned', $task)) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => t('The Interactive Task has not been set up properly.  The Interactive Task requires assignments to actors, roles or other assignment options.'),
      ];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
