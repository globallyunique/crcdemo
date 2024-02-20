<?php

namespace Drupal\maestro\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Ajax\RedirectCommand;

/**
 * The Maestro Interactive task form base.
 */
class MaestroInteractiveFormBase extends FormBase {
  /**
   * The ID of the queue item.
   *
   * @var int
   */
  public $queueID;

  /**
   * If this is a modal form or not.
   *
   * @var int
   */
  public $modal;

  /**
   * Return path.
   *
   * @var string
   */
  public $returnPath;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'maestro_interactive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // What if this interactive task is being executed by someone not in a modal and they're faking out that
    // it is infact a modal?  Let's check if they're even supposed to be here and if this is still a valid task.
    // We created this form element.  It must be there.
    $queueID = intval($form_state->getValue('maestro_queue_id'));
    $processID = MaestroEngine::getProcessIdFromQueueId($queueID);

    $task = MaestroEngine::getTemplateTaskByQueueID($queueID);
    if (isset($task['data']['redirect_to'])) {
      // $response = new TrustedRedirectResponse('/' . $task['data']['redirect_to']);
      // $form_state->setResponse($response);
      $url = Url::fromUserInput('/' . $task['data']['redirect_to'],
          ['query' => ['maestro' => 1, 'queueid' => $form_state->getValue('queueid', 0)]]);
      $form_state->setRedirectUrl($url);
    }

    if (MaestroEngine::canUserExecuteTask($queueID, \Drupal::currentUser()->id())) {
      $queueEntry = MaestroEngine::getQueueEntryById($queueID);
      $handler = $queueEntry->handler->getString();

      if ($handler != '') {
        // Execute our custom submit handler - assuming a standard naming convention.
        $submit_handler = $handler . '_submit';
        if (function_exists($submit_handler)) {
          call_user_func_array($submit_handler, [
            &$form,
            &$form_state,
            $queueID,
          ]);
          // Determine if the form state submission was clicked.
          // If so, complete the task!
          $triggeringElement = $form_state->getTriggeringElement();
          if (strstr($triggeringElement['#id'], 'edit-submit') !== FALSE && $queueID > 0) {
            // This is our submit button.  User wanted to complete this task.  Let's do so.
            // If you have a reject, you handle that in your own interactive submit handler code
            // to set the appropriate task status.
            MaestroEngine::completeTask($queueID, \Drupal::currentUser()->id());
          }
        }
        else {
          // Offload to the task to do any special handling.
          $task = NULL;
          $task = MaestroEngine::getPluginTask($queueEntry->task_class_name->getString(), $processID, $queueID);
          if ($task != NULL) {
            $task->handleExecuteSubmit($form, $form_state);
          }
        }
      }
      else {
        // Offload to the task to do any special handling.
        $task = NULL;
        $task = MaestroEngine::getPluginTask($queueEntry->task_class_name->getString(), $processID, $queueID);
        if ($task != NULL) {
          $task->handleExecuteSubmit($form, $form_state);
        }
      }
    }

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback helper funciton that simply helps us separate the save/complete of a task with
   * an optional ajax handler to close the dialog and/or do a redirect if the user has jumped out of the modal.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form.
   */
  public function completeForm(array &$form, FormStateInterface $form_state) {
    $this->returnPath = $form_state->getValue('return_path');
    if ($this->returnPath == '') {
      // We are making an assumption here.
      $this->returnPath = '/taskconsole';
    }

    // We created this form element.  It must be there.
    $queueID = intval($form_state->getValue('maestro_queue_id'));
    $processID = MaestroEngine::getProcessIdFromQueueId($queueID);

    if (MaestroEngine::canUserExecuteTask($queueID, \Drupal::currentUser()->id())) {
      if ($this->modal == 'modal') {
        $response = new AjaxResponse();
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new RedirectCommand($this->returnPath));
        return $response;
      }
      else {
        $response = new RedirectResponse('/' . $this->returnPath);
        $response->send();
      }
    }
    else {
      // This is the case where the task was already completed or is no longer valid for whatever reason
      // This case is also here to catch the issue where the submit form handler is firing twice.
      // When this fires twice in a modal scenario, we need to ensure that the modal dialog is actually shut down as the
      // AjaxResponse handler loses track of the origin addCommand(s) to close the modal and redirect to the redirect location.
      if ($this->modal == 'modal') {
        $response = new AjaxResponse();
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new RedirectCommand($this->returnPath));
        return $response;
      }

      $response = new RedirectResponse('/' . $this->returnPath);
      $response->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $queueid_or_token = NULL, $modal = NULL) {
    //Determine if this is an integer or not.  If Integer, it's a queueID, if not, it's probably a token or fake value.

    if(intval($queueid_or_token) == $queueid_or_token) {
      $this->queueID = $queueid_or_token;
    }
    else {
      //need to fetch off the queueID based on token
      $qID = MaestroEngine::getQueueIdFromToken($queueid_or_token);
      if($qID !== FALSE) {
        $this->queueID = $qID;
      }
    }
    
    $this->modal = $modal;
    // Get the return path from the task definition.
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($this->queueID);
    if ($queueRecord) {
      $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($queueRecord->process_id->getString());
      $taskTemplate = MaestroEngine::getTemplateTaskByID($templateMachineName, $queueRecord->task_id->getString());

      if (isset($taskTemplate['data']['redirect_to'])) {
        $this->returnPath = $taskTemplate['data']['redirect_to'];
      }
      else {
        $config = \Drupal::config('maestro.settings');
        // Before we do anything, determine where to redirect the user after execution:
        if ($config->get('maestro_redirect_location')) {
          $this->returnPath = 'taskconsole';
        }
        else {
          // we're just going out on a limb here and returning the person back to our task console.
          $this->returnPath = 'taskconsole';
        }
      }

      $form = [];
      // Determine if this user should even be seeing this page.
      if (!MaestroEngine::canUserExecuteTask($this->queueID, \Drupal::currentUser()->id())) {
        $form['error'] = [
          '#markup' => $this->t('You do not have access to this task.  The task has either been reassigned or is no longer valid.'),
        ];
        // Throw new AccessDeniedHttpException(); //doing this in a modal just makes the modal hang.
      }

      // Devs can override/add to this form declaration as they see fit.
      return $form;
    }
    else {
      // There is no queue record.
      $form['error'] = [
        '#markup' => $this->t('The task is no longer valid.'),
      ];
    }
  }

  /**
   * Returns the executable form fields for this interactive task.
   *
   * Need a different form? no problem, specify the form in the UI and we'll fetch it instead.
   */
  public function getExecutableFormFields() {
    $processID = MaestroEngine::getProcessIdFromQueueId($this->queueID);
    // Lets load the actual task for this queue item and return its form fields.
    $queueEntry = MaestroEngine::getQueueEntryById($this->queueID);
    if ($queueEntry) {
      $started_date = intval($queueEntry->started_date->getString());
      $created_date = intval($queueEntry->created->getString());
      // We will set the started date to the FIRST time someone clicks on the execute of the task.
      // when we create a task, we set the started_date to the time the entity is created.
      if ($started_date - $created_date < 5) {
        // There could be some slack between the started date and the created date just due to latency in task and entity creation.
        // giving it 5s should be enough time.
        $queueEntry->set('started_date', time());
        $queueEntry->save();
      }

      // Do you have a handler?  if so, use that function's form elements.
      if ($queueEntry->handler->getString() != '') {
        // We must execute the handler here.  This is a simple function declaration in a .module file traditionally.
        $handler = $queueEntry->handler->getString();
        $form = [];
        // You can override this weight in your own handler code!
        $form['actions']['#weight'] = 100;
        // We force down a submit button.  You need to have a complete task somewhere.
        $form['actions']['submit'] = [
          '#type' => 'submit',
        // You can override the #value in your own handler.
          '#value' => t('Complete'),
        ];
        call_user_func_array($handler, [&$form, $this->queueID, $this]);
        // Non-array return types are possible, e.g. RedirectResponse objects.
        if (is_array($form)) {
          // Not overridable in your handler!  we do this on purpose here if this is a known modal based on the task option.
          if ($this->modal == 'modal') {
            $form['actions']['submit']['#ajax'] = [
            // We use our helper method of completeForm to close the modal.
              'callback' => [$this, 'completeForm'],
              'wrapper' => '',
            ];
          }

          $form['return_path'] = [
            '#type' => 'hidden',
            '#default_value' => $this->returnPath,
          ];
        }
      }
      else {
        $task = NULL;
        $task = MaestroEngine::getPluginTask($queueEntry->task_class_name->getString(), $processID, $this->queueID);
        if ($task != NULL) {
          $form = $task->getExecutableForm($this->modal, $this);
        }
      }
      // Non-array return types are possible, e.g. RedirectResponse objects.
      if (!is_array($form)) {
        return $form;
      }
      // We add our own queue ID to the form mix to be absolutely sure we have a queue ID submitted to us.
      $form['maestro_queue_id'] = [
        '#type' => 'hidden',
        '#default_value' => $this->queueID,
      ];
      /*
       * We are using the $form[actions] to house our button commands.  For the submit form, if this ia a modal
       * dialog we're using, we'll add the submit form modal command
       */
    }
    else {
      // There is no queue record.
      $form['error'] = [
        '#markup' => $this->t('The task is no longer valid.'),
      ];
    }
    return $form;
  }

}
