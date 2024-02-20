<?php
namespace Drupal\event_view\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\MaestroOrchestrator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

class MyController extends ControllerBase {

  public function storeValue($event_node_id) {
    $event_node = Node::load($event_node_id);
    if(empty($event_node)) {
      \Drupal::logger('MyController_storeValue')->info('node '. $event_node_id. ' is empty. This should not happen. Probably the drupal cache is stale.');
      return new RedirectResponse(Url::fromUri('internal:/taskconsole')->toString());
    }
    \Drupal::logger('MyController_storeValue')->info('node_id:'. $event_node_id);
    if ($event_node->get('field_pid')->isEmpty()) {
      // Start the workflow and store it's PID into the row.
      \Drupal::logger('MyController_storeValue')->info('Start the workflow and store PID into the row');
      $template_machine_name = $event_node->field_template_name->value;
      \Drupal::logger('MyController_storeValue')->info('Event specific template_name:'. $template_machine_name);
      if(empty($template_machine_name)) {
        \Drupal::logger('MyController_storeValue')->info('template_name is empty. This should not happen. Probably the Event was not properly pre-populated.');
        $template_machine_name = 'new_patient'; 
      }
      $pid = \Drupal\utility_functions\UtilityFunctions::startProcess($template_machine_name, $event_node->field_patient_reference->target_id);
      // if ($pid) {
        $event_node->set('field_pid', ['target_id' => $pid]);
        $event_node->save();
        \Drupal::logger('MyController_storeValue')->info('Saved started workflow pid:'. $pid);
      // } 
    } else {
      // Get the PID from the node if the workflow has already started.
      \Drupal::logger('storeValue')->info('Get the PID from the node if the workflow has already started. target_id:'. $event_node->get('field_pid')->target_id);
      $pid = $event_node->get('field_pid')->target_id;
    }
    $patient_node_id = $event_node->field_patient_reference->target_id;
    // Redirect the checklist view for the workflow.
    // $url = Url::fromUri('internal:/checklist/' . $pid. '/'. $event_node_id)->toString();
    $url = Url::fromUri('internal:/taskconsole/'. $patient_node_id. '/'. $pid. '/0')->toString();
    \Drupal::logger('MyController_storeValue')->info('Completed and redirecting to:'. $url);
    // Store the PID and Patient ID in the session state.
    \Drupal::state()->set('sitehub.process_id', $pid);
    \Drupal::state()->set('sitehub.patient_id', $patient_node_id);
    return new RedirectResponse($url);
  }
}

