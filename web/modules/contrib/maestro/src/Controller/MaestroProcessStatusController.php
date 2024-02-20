<?php

namespace Drupal\maestro\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\maestro\Utility\MaestroStatus;
use Drupal\Core\Url;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\views\Views;

/**
 * Maestro Process Status Controller class.
 */
class MaestroProcessStatusController extends ControllerBase {

  /**
   * Returns response for the process status queries.
   *
   * @param int $processID
   *   The processID we wish to get details for.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response
   */
  public function getDetails($processID) {

    $build = [];
    $taskDetails = '';
    // first, we determine if the template even wants process shown.
    $template = MaestroEngine::getTemplate(MaestroEngine::getTemplateIdFromProcessId($processID));
    if (isset($template->show_details) && $template->show_details) {
      $templateName = MaestroEngine::getTemplateIdFromProcessId($processID);
      // Skip the can execute check as this is not against a queue entry.
      $status_bar = MaestroStatus::getMaestroStatusBar($processID, 0, TRUE);
      $build['status'] = [
        '#prefix' => '<div id="processid-' . $processID . '" class="maestro-block-process ' . $templateName . '">',
        '#suffix' => '</div>',
        '#markup' => $status_bar['status_bar']['#children'],
      ];

      // Lets see if there's any views attached that we should be showing.
      if (isset($template->views_attached)) {
        foreach ($template->views_attached as $machine_name => $arr) {
          $view = Views::getView($machine_name);
          if ($view) {
            $display = explode(';', $arr['view_display']);
            $display_to_use = isset($display[0]) ? $display[0] : 'default';
            $render_build = $view->buildRenderable($display_to_use, [$processID, 0], FALSE);
            if ($render_build) {
              $thisViewOutput = \Drupal::service('renderer')->renderPlain($render_build);
              if ($thisViewOutput) {
                $task_information_render_array = [
                  '#theme' => 'taskconsole_views',
                  '#task_information' => $thisViewOutput,
                  '#title' => $view->storage->label(),
                ];
                $taskDetails .= (\Drupal::service('renderer')->renderPlain($task_information_render_array));
              }
            }
          }
        }
      }
      // Anyone want to override the task details display or add to it?
      \Drupal::moduleHandler()->invokeAll('maestro_process_status_alter', 
          [&$taskDetails, $processID, $template]);

      $build['views_bar'] = [
        '#children' => '<div class="maestro-process-details">' . $taskDetails . '</div>',
      ];

    }

    // $build = MaestroStatus::getMaestroStatusBar($processID, 0, TRUE);  //skip the can execute check as this is not against a queue entry
    // we replace the down arrow with the toggle up arrow
    $replace['expand'] = [
      '#attributes' => [
        'class' => ['maestro-timeline-status', 'maestro-status-toggle-up'],
        'title' => $this->t('Close Details'),
      ],
      '#type' => 'link',
      '#id' => 'maestro-id-ajax-' . $processID,
      '#url' => Url::fromRoute('maestro.process_details_ajax_close', ['processID' => $processID]),
      '#title' => $this->t('Close Details'),
      '#ajax' => [
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    // Empty array.
    if (count($build) == 0) {
      $build['status'] = [
        '#plain_text' => $this->t('No details to show'),
      ];
    }

    $response = new AjaxResponse();
    // Row.
    $response->addCommand(new HtmlCommand('#details_replace_column_' . $processID, $build));
    // Wrapper attribute TD tag. Toggle up arrow.
    $response->addCommand(new HtmlCommand('.maestro-status-toggle-' . $processID . '', $replace['expand']));
    $response->addCommand(new CssCommand('#details_replace_row_' . $processID, ['display' => 'table-row']));
    return $response;

  }

  /**
   * Close Details method.
   */
  public function closeDetails($processID) {
    $build = [];
    // We replace the up arrow with the down arrow.
    $build['expand'] = [
      '#attributes' => [
        'class' => ['maestro-timeline-status', 'maestro-status-toggle'],
        'title' => $this->t('Open Details'),
      ],
      '#type' => 'link',
      '#id' => 'maestro-id-ajax-' . $processID,
      '#url' => Url::fromRoute('maestro.process_details_ajax_open', ['processID' => $processID]),
      '#title' => $this->t('Open Details'),
      '#ajax' => [
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],

    ];

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#maestro-ajax-' . $processID, ''));
    $response->addCommand(new HtmlCommand('.maestro-status-toggle-' . $processID, $build['expand']));
    $response->addCommand(new CssCommand('#details_replace_row_' . $processID, ['display' => 'none']));

    return $response;
  }

}
