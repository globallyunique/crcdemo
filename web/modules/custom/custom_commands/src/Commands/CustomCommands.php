<?php

namespace Drupal\custom_commands\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drupal\maestro\Engine\MaestroEngine;

/**
 * A Drush command file.
 *
 * @package Drupal\custom_commands\Commands
 */
class CustomCommands extends DrushCommands {

    /**
     * Deletes all Maestro workflows.
     *
     * @command custom_commands:delete_all_workflows
     * @aliases daw
     * @usage custom_commands:delete_all_workflows
     *   Deletes all Maestro workflows.
     */
    public function deleteAllWorkflows() {
      // replaced by WorkflowController::deleteAllWorkflows() because I couldn't get Drush to recognize the class.
      \Drupal::logger('deleteAllWorkflows')->info('replaced by WorkflowController::deleteAllWorkflows() because I could not get Drush to recognize the class()');
    }

}