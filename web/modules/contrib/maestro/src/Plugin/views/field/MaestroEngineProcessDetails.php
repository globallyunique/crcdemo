<?php

namespace Drupal\maestro\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;

/**
 * Field handler to show process details and attached views.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("maestro_process_process_details")
 */
class MaestroEngineProcessDetails extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No Query to be done.
  }

  /**
   * Define the available options.
   *
   * @return array
   *   The array of options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['views_attached'] = ['default' => ''];

    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $item = $values->_entity;
    $rows = [];
    $links = [];

    // We need the process ID no matter what we're viewing.
    $processID = 0;
    if ($item->getEntityTypeId() == 'maestro_production_assignments') {
      $queueRecord = MaestroEngine::getQueueEntryById($item->queue_id->getString());
      $processID = $queueRecord->process_id->getString();
    }
    elseif ($item->getEntityTypeId() == 'maestro_queue') {
      $processID = $item->process_id->getString();
    }
    elseif ($item->getEntityTypeId() == 'maestro_process') {
      $processID = $item->process_id->getString();
    }

    $build = [];
    $build['details'][$processID]['expand'] = [
      '#prefix' => '<div class="maestro-process-details-expand-wrapper maestro-expand-wrapper maestro-status-toggle-' . $processID . '">',
      '#suffix' => '</div><div><table class="maestro-ajax-details-view-table"><tr class="maestro-ajax-row" id="details_replace_row_' . $processID . '"><td id="details_replace_column_' . $processID . '"></td></tr></table></div>',
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

    // Css for the status bar.
    $build['#attached']['library'][] = 'maestro/maestro-engine-css';
    $build['#attached']['library'][] = 'maestro_taskconsole/maestro_taskconsole_css';
    return $build;

  }

}
