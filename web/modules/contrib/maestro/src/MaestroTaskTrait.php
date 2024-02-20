<?php

namespace Drupal\maestro;

use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\user\Entity\User;

/**
 * MaestroTaskTrait.
 *
 * Provides base task parameters and methods.
 * Includes the processID and queueID properties and methods for base task implementation.
 *
 * @ingroup maestro
 */
trait MaestroTaskTrait {

  /**
   * The Maestro Process ID.
   *
   * @var int
   */
  protected $processID = 0;

  /**
   * The Maestro queue ID.
   *
   * @var int
   */
  protected $queueID = 0;

  /**
   * The default will be success for the execution status.
   *
   * @var int
   */
  protected $executionStatus = TASK_STATUS_SUCCESS;

  /**
   * Default will be that the task completed normally.
   *
   * @var int
   */
  protected $completionStatus = MAESTRO_TASK_COMPLETION_NORMAL;

  /**
   * Returns the value of the execution status protected variable denoting if the execution of this task is complete.
   */
  public function getExecutionStatus() {
    return $this->executionStatus;
  }

  /**
   * Returns the value of the completion status protected variable denoting any special completion status condition the task wishes to pass along.
   */
  public function getCompletionStatus() {
    return $this->completionStatus;
  }

  /**
   * Retrieve the core Maestro form edit elements that all tasks MUST adhere to.
   *
   * @param array $task
   *   The loaded task from the template.
   * @param string $templateMachineName
   *   The Maestro template machine name.
   */
  public function getBaseEditForm(array $task, $templateMachineName) {
    $form['template_machine_name'] = [
      '#type' => 'hidden',
      '#title' => $this->t('machine name of the template.'),
      '#default_value' => $templateMachineName,
      '#required' => TRUE,
    ];

    $form['task_id'] = [
      '#type' => 'hidden',
      '#title' => $this->t('the ID in the template of the task being edited.'),
      '#default_value' => $task['id'],
      '#required' => TRUE,
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The label of the task.'),
      '#default_value' => $task['label'],
      '#required' => TRUE,
    ];

    $form['participate_in_workflow_status_stage'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Participate in Setting the Workflow Stage and Status Message?'),
      '#default_value' => isset($task['participate_in_workflow_status_stage']) ? $task['participate_in_workflow_status_stage'] : 0,
    ];

    $form['status_and_stage_fieldset'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#description' => $this->t('The Maestro task console has the ability to display a progression stage/status bar to the end user. Set the status stage number and message below.'),
      '#title' => $this->t('Stage and Status Message Settings'),
      '#states' => [
        'visible' => [
          ':input[name="participate_in_workflow_status_stage"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['status_and_stage_fieldset']['workflow_status_stage_number'] = [
      '#type' => 'number',
      '#title' => $this->t('The workflow stage number you wish to relate the Stage Status Message to.'),
      '#description' => $this->t('When set to 0, the status message will NOT be set in the workflow.'),
      '#default_value' => isset($task['workflow_status_stage_number']) ? $task['workflow_status_stage_number'] : '0',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="participate_in_workflow_status_stage"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['status_and_stage_fieldset']['workflow_status_stage_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stage Status message.'),
      '#default_value' => isset($task['workflow_status_stage_message']) ? $task['workflow_status_stage_message'] : '',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="participate_in_workflow_status_stage"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
  }

  /**
   * Retrieve the core Maestro form edit elements for Assignments and Notifications.
   *
   * @param array $task
   *   The task loaded from the template.
   * @param string $templateMachineName
   *   The Maestro template's machine name.
   */
  public function getAssignmentsAndNotificationsForm(array $task, $templateMachineName) {
    $variables = MaestroEngine::getTemplateVariables($templateMachineName);
    $options = [];
    foreach ($variables as $variableName => $arr) {
      $options[$variableName] = $variableName;
    }

    // Assignments section.
    $form['assignments'] = [
      '#title' => $this->t('Assignments'),
    ];

    $form['edit_task_assignments'] = [
      '#tree' => TRUE,
      '#type' => 'details',
      '#group' => 'assignments',
      '#title' => 'Assignment Details',
    ];

    // The following are the assignment mechanisms.
    $form['edit_task_assignments']['select_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Assign by'),
      '#options' => [
        'fixed' => $this->t('Fixed Value'),
        'variable' => $this->t('Variable'),
      ],
      '#default_value' => 'fixed',
      '#attributes' =>
       [
         'onchange' => 'maestro_task_editor_assignments_assignby(this.value);',
       ],
    ];

    /*
     * Developers:  You can add to the onchange for this as you see fit to allow for other types
     */
    $form['edit_task_assignments']['select_assign_to'] = [
      '#type' => 'select',
      '#title' => $this->t('Assign to'),
      '#options' => [
        'user' => $this->t('User'),
        'role' => $this->t('Role'),
      ],
      '#default_value' => 'user',
      '#attributes' =>
        [
          'onchange' => 'maestro_task_editor_assignments_assignto(this.value);',
        ],
    ];

    $form['edit_task_assignments']['select_assigned_user'] = [
      '#id' => 'select_assigned_user',
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#default_value' => '',
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#title' => $this->t('User'),
      '#required' => FALSE,
      '#prefix' => '<div class="maestro-engine-user-and-role"><div class="maestro-engine-assignments-hidden-user">',
      '#suffix' => '</div>',
    ];

    $form['edit_task_assignments']['select_assigned_role'] = [
      '#id' => 'select_assigned_role',
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user_role',
      '#default_value' => '',
      '#title' => $this->t('Role'),
      '#required' => FALSE,
      '#prefix' => '<div class="maestro-engine-assignments-hidden-role">',
      '#suffix' => '</div></div>',
    ];

    $form['edit_task_assignments']['variable'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the variable'),
      '#required' => FALSE,
      '#default_value' => '',
      '#options' => $options,
      '#prefix' => '<div class="maestro-engine-assignments-hidden-variable">',
      '#suffix' => '</div>',
    ];

    // Now to list the existing assignments here:
    $form['edit_task_assignments']['task_assignment_table'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [$this->t('Delete'), $this->t('To What'), $this->t('By'), $this->t('Assignee')],
      '#empty' => t('There are no assignments.'),
    ];

    isset($task['assigned']) ? $assignments = explode(',', $task['assigned']) : $assignments = [];
    $cntr = 0;
    foreach ($assignments as $assignment) {
      if ($assignment != '') {
        // [0]=to what, [1]=by fixed or variable, [2]=who or varname
        $howAssigned = explode(':', $assignment);

        $form['edit_task_assignments']['task_assignment_table'][$cntr]['delete'] = [
          '#type' => 'checkbox',
          '#default_value' => 0,
        ];
        $form['edit_task_assignments']['task_assignment_table'][$cntr]['to_what'] = [
          '#plain_text' => $howAssigned[0],
        ];
        $form['edit_task_assignments']['task_assignment_table'][$cntr]['by'] = [
          '#plain_text' => $howAssigned[1],
        ];
        $form['edit_task_assignments']['task_assignment_table'][$cntr]['asignee'] = [
          '#plain_text' => $howAssigned[2],
        ];

        $cntr++;
      }
    }

    // End of assignments section.
    // Notifications section.
    $form['notifications'] = [
      '#title' => $this->t('Notifications'),
    ];

    $form['edit_task_notifications'] = [
      '#tree' => TRUE,
      '#type' => 'details',
      '#group' => 'notifications',
      '#title' => 'Notification Details',
    ];

    $form['edit_task_notifications']['select_notification_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Notify by'),
      '#options' => [
        'fixed' => $this->t('Fixed Value'),
        'variable' => $this->t('Variable'),
      ],
      '#default_value' => 'fixed',
      '#attributes' =>
      [
        'onchange' => 'maestro_task_editor_notifications_assignby(this.value);',
      ],
    ];

    $form['edit_task_notifications']['select_notification_to'] = [
      '#type' => 'select',
      '#title' => $this->t('Notification to'),
      '#options' => [
        'user' => $this->t('User'),
        'role' => $this->t('Role'),
      ],
      '#default_value' => 'user',
      '#attributes' =>
      [
        'onchange' => 'maestro_task_editor_notifications_assignto(this.value);',
      ],
    ];

    $form['edit_task_notifications']['select_notification_user'] = [
      '#id' => 'select_notification_user',
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#default_value' => '',
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#title' => $this->t('User'),
      '#required' => FALSE,
      '#prefix' => '<div class="maestro-engine-user-and-role-notifications"><div class="maestro-engine-notifications-hidden-user">',
      '#suffix' => '</div>',
    ];

    $form['edit_task_notifications']['select_notification_role'] = [
      '#id' => 'select_notification_role',
      '#type' => 'textfield',
      '#default_value' => '',
      '#title' => $this->t('Role'),
      '#autocomplete_route_name' => 'maestro.autocomplete.roles',
      '#required' => FALSE,
      '#prefix' => '<div class="maestro-engine-notifications-hidden-role">',
      '#suffix' => '</div></div>',
    ];

    $form['edit_task_notifications']['variable'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the variable'),
      '#required' => FALSE,
      '#default_value' => '',
      '#options' => $options,
      '#prefix' => '<div class="maestro-engine-notifications-hidden-variable">',
      '#suffix' => '</div>',
    ];

    $whichNotification = [
      'assignment' => $this->t('Assignment'),
      'reminder' => $this->t('Reminder'),
      'escalation' => $this->t('Escalation'),
    ];
    $form['edit_task_notifications']['which_notification'] = [
      '#type' => 'radios',
      '#title' => $this->t('Which notification'),
      '#required' => FALSE,
      '#default_value' => 'assignment',
      '#options' => $whichNotification,
      '#prefix' => '<div class="">',
      '#suffix' => '</div>',
    ];

    $form['edit_task_notifications']['reminder_after'] = [
      '#type' => 'textfield',
      '#default_value' => $task['notifications']['reminder_after'] ?? '0',
      '#title' => $this->t('Reminder After (days)'),
      '#required' => FALSE,
      '#size' => 2,
      '#prefix' => '<div class="maestro-engine-reminder-escalation-values"><div class="maestro-reminder-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['edit_task_notifications']['escalation_after'] = [
      '#type' => 'textfield',
      '#default_value' => $task['notifications']['escalation_after'] ?? '0',
      '#title' => $this->t('Escalation After (days)'),
      '#required' => FALSE,
      '#size' => 2,
      '#prefix' => '<div class="maestro-escalation-wrapper">',
      '#suffix' => '</div></div>',
    ];

    // Now to list the existing assignments here:
    $form['edit_task_notifications']['task_notifications_table'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [$this->t('Delete'), $this->t('To What'), $this->t('By'), $this->t('Assignee'), $this->t('Notification Type')],
      '#empty' => t('There are no notifications.'),
    ];

    if (array_key_exists('notifications', $task) && array_key_exists('notification_assignments', $task['notifications'])) {
      $notifications = explode(',', $task['notifications']['notification_assignments']);
      $cntr = 0;
      foreach ($notifications as $notification) {
        if ($notification != '') {
          // [0]=to what, [1]=by fixed or variable, [2]=who or varname, [3] which notification
          $howAssigned = explode(':', $notification);

          $form['edit_task_notifications']['task_notifications_table'][$cntr]['delete'] = [
            '#type' => 'checkbox',
            '#default_value' => 0,
          ];
          $form['edit_task_notifications']['task_notifications_table'][$cntr]['to_what'] = [
            '#plain_text' => $howAssigned[0],
          ];
          $form['edit_task_notifications']['task_notifications_table'][$cntr]['by'] = [
            '#plain_text' => $howAssigned[1],
          ];
          $form['edit_task_notifications']['task_notifications_table'][$cntr]['asignee'] = [
            '#plain_text' => $howAssigned[2],
          ];

          $form['edit_task_notifications']['task_notifications_table'][$cntr]['type'] = [
            '#plain_text' => $howAssigned[3],
          ];
          $cntr++;
        }
      }
    }

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['edit_task_notifications']['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['maestro'],
      ];
    }
    else {
      $form['edit_task_notifications']['token_tree'] = [
        '#plain_text' => $this->t('Enabling the Token module will reveal the replacable tokens available for custom notifications.'),
      ];
    }
    $form['edit_task_notifications']['notification_assignment_subject'] = [
      '#type' => 'textarea',
      '#default_value' => isset($task['notifications']['notification_assignment_subject']) ? $task['notifications']['notification_assignment_subject'] : '',
      '#title' => $this->t('Custom Assignment Subject'),
      '#required' => FALSE,
      '#rows' => 1,
      '#prefix' => '<div class="maestro-engine-assignments-hidden-notification">',
      '#suffix' => '</div>',
    ];
    $form['edit_task_notifications']['notification_assignment'] = [
      '#id' => 'notification_assignment',
      '#type' => 'textarea',
      '#default_value' => isset($task['notifications']['notification_assignment']) ? $task['notifications']['notification_assignment'] : '',
      '#title' => $this->t('Custom Assignment Message'),
      '#required' => FALSE,
      '#rows' => 2,
      '#prefix' => '<div class="maestro-engine-assignments-hidden-notification">',
      '#suffix' => '</div>',
    ];
    $form['edit_task_notifications']['notification_reminder_subject'] = [
      '#type' => 'textarea',
      '#default_value' => isset($task['notifications']['notification_reminder_subject']) ? $task['notifications']['notification_reminder_subject'] : '',
      '#title' => $this->t('Custom Reminder Subject'),
      '#required' => FALSE,
      '#rows' => 1,
      '#prefix' => '<div class="maestro-engine-assignments-hidden-notification">',
      '#suffix' => '</div>',
    ];
    $form['edit_task_notifications']['notification_reminder'] = [
      '#id' => 'notification_reminder',
      '#type' => 'textarea',
      '#default_value' => isset($task['notifications']['notification_reminder']) ? $task['notifications']['notification_reminder'] : '',
      '#title' => $this->t('Custom Reminder Message'),
      '#required' => FALSE,
      '#rows' => 2,
      '#prefix' => '<div class="maestro-engine-assignments-hidden-escalation">',
      '#suffix' => '</div>',
    ];
    $form['edit_task_notifications']['notification_escalation_subject'] = [
      '#type' => 'textarea',
      '#default_value' => isset($task['notifications']['notification_escalation_subject']) ? $task['notifications']['notification_escalation_subject'] : '',
      '#title' => $this->t('Custom Escalation Subject'),
      '#required' => FALSE,
      '#rows' => 1,
      '#prefix' => '<div class="maestro-engine-assignments-hidden-notification">',
      '#suffix' => '</div>',
    ];
    $form['edit_task_notifications']['notification_escalation'] = [
      '#id' => 'notification_escalation',
      '#type' => 'textarea',
      '#default_value' => isset($task['notifications']['notification_escalation']) ? $task['notifications']['notification_escalation'] : '',
      '#title' => $this->t('Custom Escalation Message'),
      '#required' => FALSE,
      '#rows' => 2,
      '#prefix' => '<div class="maestro-engine-assignments-hidden-escalation">',
      '#suffix' => '</div>',
    ];

    $form['#attached']['library'][] = 'maestro/maestro-engine-css';
    $form['#attached']['library'][] = 'maestro/maestro-engine-task-edit';

    return $form;
  }

  /**
   * Available for all tasks -- this does the general task construction for us, ensuring we have sanity in the saved
   * Config Entity for the task.  Assignments and Notifications are the two main elements this method worries about.
   *
   * @param array $form
   *   The form submission from the submit handler.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormStateInterface from the submit handler.
   * @param array $task
   *   The array representation of loading a task from the template via the MaestroEngine::getTemplateTaskByID method.
   */
  public function saveTask(array &$form, FormStateInterface $form_state, array &$task) {
    $result = FALSE;
    $templateMachineName = $form_state->getValue('template_machine_name');
    $taskID = $form_state->getValue('task_id');
    $taskAssignments = $form_state->getValue('edit_task_assignments');
    $taskNotifications = $form_state->getValue('edit_task_notifications');

    // $task holds the loaded task from the template.  We can now perform our general operations on the task
    // to ensure that it contains all of the proper elements in it for use by the engine.
    // the elements of our task that are minimally required are as follows:
    // id, tasktype, label, nextstep, nextfalsestep, top, left, assignby, assignto, assigned
    // first, do a validity check on the task structure
    // These are the keys that SHOULD exist in our task.
    $requiredKeys = [
      'id' => $taskID,
      'tasktype' => '',
      'label' => $form_state->getValue('label'),
      'nextstep' => '',
      'nextfalsestep' => '',
      'top' => 25,
      'left' => 25,
      'assignby' => '',
      'assignto' => '',
      'assigned' => '',
      'runonce' => 0,
      'handler' => '',
      'showindetail' => 1,
      'participate_in_workflow_status_stage' => 0,
      'workflow_status_stage_number' => 0,
      'workflow_status_stage_message' => '',
    ];
    $missingKeys = array_diff_key($requiredKeys, $task);
    foreach ($missingKeys as $key => $val) {
      // Seed the key properly with default values.
      $task[$key] = $val;
    }
    // Now the core fields.
    $task['label'] = $form_state->getValue('label');
    $task['participate_in_workflow_status_stage'] = $form_state->getValue('participate_in_workflow_status_stage');
    $task['workflow_status_stage_number'] = $form_state->getValue('workflow_status_stage_number');
    $task['workflow_status_stage_message'] = $form_state->getValue('workflow_status_stage_message');

    // Now the assignments.
    $executableTask = MaestroEngine::getPluginTask($task['tasktype']);
    if ($executableTask->isInteractive()) {
      // ok, we now manipulate the assignments if we're in here
      // first to detect if we're deleting anything
      // break out the current assignments.
      isset($task['assigned']) ? $currentAssignments = explode(',', $task['assigned']) : $currentAssignments = [];
      isset($taskAssignments['task_assignment_table']) ? $deleteAssignmentsList = $taskAssignments['task_assignment_table'] : $deleteAssignmentsList = [];

      if (isset($deleteAssignmentsList) && is_array($deleteAssignmentsList)) {
        foreach ($deleteAssignmentsList as $key => $arr) {
          // The deleteAssignmentsList is a key-for-key alignment with the currentAssignments.
          if ($arr['delete'] == 1) {
            unset($currentAssignments[$key]);
          }
        }
      }
      $task['assigned'] = implode(',', $currentAssignments);

      if (($taskAssignments['select_assigned_role'] != '' || $taskAssignments['select_assigned_user'] != '') && $taskAssignments['select_method'] == 'fixed') {
        // alright, formulate the assignment.
        if ($taskAssignments['select_assigned_user'] != '' && $taskAssignments['select_assign_to'] == 'user') {
          // Need to get the username.
          $account = User::load($taskAssignments['select_assigned_user']);
          $assignee = $account->getAccountName();
        }
        elseif ($taskAssignments['select_assigned_role'] != '' && $taskAssignments['select_assign_to'] == 'role') {
          $assignee = $taskAssignments['select_assigned_role'];
        }
        $assignment = $taskAssignments['select_assign_to'] . ':' . $taskAssignments['select_method'] . ':' . $assignee;
      }
      elseif ($taskAssignments['select_method'] == 'variable') {
        $assignment = $taskAssignments['select_assign_to'] . ':' . $taskAssignments['select_method'] . ':' . $taskAssignments['variable'];
      }
      if (isset($assignment) && $assignment != '') {
        if ($task['assigned'] != '') {
          $task['assigned'] .= ',';
        }
        $task['assigned'] .= $assignment;
      }

      // And now notifications
      // we need to parse out the notification form to determine what this person is trying to add in a similar fashion to that of the assignments.
      if (!array_key_exists('notifications', $task)) {
        // Lets just seed the main array key.
        $task['notifications'] = [];
      }
      if (array_key_exists('notification_assignments', $task['notifications'])) {
        $currentNotifications = explode(',', $task['notifications']['notification_assignments']);
        $deleteNotificationsList = $taskNotifications['task_notifications_table'];
        foreach ($deleteNotificationsList as $key => $arr) {
          // The $deleteNotificationsList is a key-for-key alignment with the currentNotifications.
          if ($arr['delete'] == 1) {
            unset($currentNotifications[$key]);
          }
        }
        $task['notifications']['notification_assignments'] = implode(',', $currentNotifications);
      }
      $notifications = '';
      if (($taskNotifications['select_notification_role'] != '' || $taskNotifications['select_notification_user'] != '') && $taskNotifications['select_notification_method'] == 'fixed') {
        // alright, formulate the assignment.
        if ($taskNotifications['select_notification_user'] != '' && $taskNotifications['select_notification_to'] == 'user') {
          // Need to get the username.
          $account = User::load($taskNotifications['select_notification_user']);
          $assignee = $account->getAccountName();
        }
        elseif ($taskNotifications['select_notification_role'] != '' && $taskNotifications['select_notification_to'] == 'role') {
          // Need to strip out the text surrounding the bracketed values.
          preg_match('#\((.*?)\)#', $taskNotifications['select_notification_role'], $match);
          $assignee = $match[1];
        }
        $notifications = $taskNotifications['select_notification_to'] . ':' . $taskNotifications['select_notification_method'] . ':' . $assignee . ':' . $taskNotifications['which_notification'];
      }
      elseif ($taskNotifications['select_notification_method'] == 'variable') {
        $notifications = $taskNotifications['select_notification_to'] . ':' . $taskNotifications['select_notification_method'] . ':' . $taskNotifications['variable'] . ':' . $taskNotifications['which_notification'];
      }

      if ($notifications != '') {
        if ($task['notifications']['notification_assignments'] != '') {
          $task['notifications']['notification_assignments'] .= ',';
        }
        $task['notifications']['notification_assignments'] .= $notifications;
      }
    }
    $task['notifications']['notification_assignment_subject'] = $taskNotifications['notification_assignment_subject'];
    $task['notifications']['notification_assignment'] = $taskNotifications['notification_assignment'];
    $task['notifications']['notification_reminder_subject'] = $taskNotifications['notification_reminder_subject'];
    $task['notifications']['notification_reminder'] = $taskNotifications['notification_reminder'];
    $task['notifications']['notification_escalation_subject'] = $taskNotifications['notification_escalation_subject'];
    $task['notifications']['notification_escalation'] = $taskNotifications['notification_escalation'];

    $task['notifications']['reminder_after'] = $taskNotifications['reminder_after'];
    $task['notifications']['escalation_after'] = $taskNotifications['escalation_after'];

    // Let other modules do their own assignments and notifications and any other task mods they want.
    \Drupal::moduleHandler()->invokeAll('maestro_pre_task_save', [$templateMachineName, $taskID, &$task, $taskAssignments, $taskNotifications]);

    // Finally save the task.
    $result = MaestroEngine::saveTemplateTask($templateMachineName, $taskID, $task);
    // TODO: What to do with the result if an error exists?
    // we now clear out the form values for a few specific fields we have control over.
    $arr = $form_state->getUserInput();
    $arr['edit_task_assignments']['select_assigned_role'] = '';
    $arr['edit_task_assignments']['select_assigned_user'] = '';
    $arr['edit_task_assignments']['select_assign_to'] = 'user';
    $arr['edit_task_assignments']['select_method'] = 'fixed';
    $arr['edit_task_notifications']['select_notification_role'] = '';
    $arr['edit_task_notifications']['select_notification_user'] = '';
    $arr['edit_task_notifications']['select_notification_to'] = 'user';
    $arr['edit_task_notifications']['select_notification_method'] = 'fixed';
    $arr['edit_task_notifications']['which_notification'] = 'assignment';

    $form_state->setUserInput($arr);

    return $result;
  }

}
