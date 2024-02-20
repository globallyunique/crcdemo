<?php

namespace Drupal\custom_commands\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\views\Views;
use Drupal\webform\Entity\WebformSubmission;

class WorkflowController extends ControllerBase {

  public function deleteAllWorkflows() {
    
    // Get a list of all PIDs.
    \Drupal::logger('deleteAllWorkflows')->info('Get a list of all PIDs.');
    $query = \Drupal::database()->select('maestro_process', 'mp');
    $query->addField('mp', 'process_id');
    $result = $query->execute();

    if ($result instanceof \Traversable) {
      // Delete each workflow.
      foreach ($result as $record) {
        MaestroEngine::deleteProcess($record->process_id);
      }
    } else {
      $this->logger()->success(dt('No workflows to delete.'));
      // $pids = [92];
      // for ($i = 0; $i < count($pids); $i++) {
      //   deleteRecordsForAProcess($pids[$i]);
      // }
    }
    \Drupal::logger('deleteAllWorkflows')->info('content types deleted.');

    $this->deleteAllContentOfType('event');
    $this->delete_all_webform_submissions();

    return [
      '#markup' => $this->t('All workflows have been deleted.'),
    ];
    deleteLogMessages();
  }

  private function deleteAllContentOfType($content_type) {
    // Load the entity type manager service.
    $entityTypeManager = \Drupal::entityTypeManager();

    // Load all nodes of the content type.
    $query = $entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', $content_type);
    $query->accessCheck(TRUE);
    $nids = $query->execute();

    // Load all nodes.
    $nodes = $entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Delete all nodes.
    $entityTypeManager->getStorage('node')->delete($nodes);
  }

  public function deleteRecordsForAProcess($process_id) {
    // Delete from maestro_process.
    \Drupal::database()->delete('maestro_process')->condition('process_id', $process_id)->execute();

    // Delete from maestro_queue.
    \Drupal::database()->delete('maestro_queue')->condition('process_id', $process_id)->execute();

    // Delete from maestro_process_variables.
    \Drupal::database()->delete('maestro_process_variables')->condition('process_id', $process_id)->execute();
  }

  public function deleteLogMessages() {
    \Drupal::database()->delete('watchdog')->execute();
  }

  public function delete_webform_submissions($webform_id) {
    $query = \Drupal::entityQuery('webform_submission')
      ->condition('webform_id', $webform_id);
    $result = $query->execute();

    if ($result) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage('webform_submission');
      $entities = $storage_handler->loadMultiple($result);
      $storage_handler->delete($entities);
    }
  }

  public function delete_all_webform_submissions() {
    $query = \Drupal::entityQuery('webform_submission')->accessCheck(FALSE);
    $result = $query->execute();

    if ($result) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage('webform_submission');
      $entities = $storage_handler->loadMultiple($result);
      $storage_handler->delete($entities);
    }
  }


  public function resetPatients() {
    
    // Get a list of all PIDs.
    \Drupal::logger('resetPatients')->info('Get a list of all PIDs.');
    $query = \Drupal::database()->select('maestro_process', 'mp');
    $query->addField('mp', 'process_id');
    $result = $query->execute();

    if ($result instanceof \Traversable) {
      // Delete each workflow.
      foreach ($result as $record) {
        MaestroEngine::deleteProcess($record->process_id);
      }
    } else {
      $this->logger()->success(dt('No workflows to delete.'));
    }

    $this->deleteAllContentOfType('event');
    $this->delete_all_webform_submissions();
    $this->generatePatientEvents();

    return [
      '#markup' => $this->t('Patient events and workflows reset'),
    ];
    deleteLogMessages();
  }

  private function generatePatientEvents() {
    // Load the entity type manager service.
    $entityTypeManager = \Drupal::entityTypeManager();

    // Load all nodes of the content type.
    $query = $entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', 'patient');
    $query->accessCheck(TRUE);
    $nids = $query->execute();

    // Load all nodes.
    $nodes = $entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach($nodes as $patient_node) {
      $patient_id = $patient_node->id();
      \Drupal::logger('resetPatients')->info('patient node:' . $patient_id);

      $study_nodes = $patient_node->get('field_study')->referencedEntities();
      $study_node = $study_nodes[0];
      $study_id = $study_node->get('field_id')->value;
      $user_name = \Drupal::currentUser()->getAccountName();

      setupWorkflow($study_id, $patient_node, $study_node, $user_name);

    }

  }


}