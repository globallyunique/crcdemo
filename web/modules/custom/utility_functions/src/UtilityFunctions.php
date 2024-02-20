<?php

namespace Drupal\utility_functions;

use Drupal\maestro\Controller\MaestroOrchestrator;
use Drupal\maestro\Engine\MaestroEngine;

class UtilityFunctions {

  public static function startProcess($template_machine_name,$patient_node_id) {
    $engine = new MaestroEngine();
    $template = $engine->getTemplate($template_machine_name);
    if ($template) {
      \Drupal::logger('UtilityFunctions_startProcess')->info('template:'. $template->id());
      $pid = $engine->newProcess($template_machine_name);
      $engine->setProcessVariable('patient_node_id', $patient_node_id, $pid, TRUE);   // TRUE = save to DB immediately     
      \Drupal::logger('UtilityFunctions_startProcess')->info('saving patient_node_id as variable:'. $patient_node_id. ' for pid:'. $pid);
      if ($pid) {
        UtilityFunctions::orchestrate();
        \Drupal::logger('UtilityFunctions_startProcess')->info('orchestrator started');
        return $pid;
      }
      else {
        $msg = 'Unable to start: '.$template_machine_name. ' patient id:' . $patient_node->id();
        \Drupal::logger('UtilityFunctions_startProcess')->info($msg);
        \Drupal::messenger()->addError(t($msg));
      }
    }
    else {
      \Drupal::messenger()->addError(t('Error!  No template by that name exits!'));
    }
    return NULL;
  }

   public static function orchestrate() {
    $config = \Drupal::config('maestro.settings');
    $maestro_orchestrator_token = $config->get('maestro_orchestrator_token');
    // Run the orchestrator for us once on process kickoff.
    $orchestrator = new MaestroOrchestrator(); 
    $orchestrator->orchestrate($config->get('maestro_orchestrator_token'), TRUE);
  }

}


