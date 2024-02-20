<?php

namespace Drupal\maestro\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to generate completed timestamp.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("maestro_completed_timestamp")
 */
class MaestroEngineCompletedTimestamp extends FieldPluginBase {

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
   *   The options available.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['date_format'] = ['default' => 'medium'];

    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $options = [
      'short' => $this->t('Short ( 12/01/1901 - 23:59 )'),
      'medium' => $this->t('Medium ( Tue, 12/01/1901 - 23:59 )'),
      'long' => $this->t('Long ( Tuesday, December 1, 1901 - 23:59 )'),
      'html_datetime' => $this->t('HTML5 Date/Time ( j-M-Y g:ia )'), //'html_datetime' => $this->t('HTML5 Date/Time ( DD-MM-DDThh:mm )'),
    ];

    $form['date_format'] = [
      '#title' => $this->t('Date Format'),
      '#type' => 'select',
      '#default_value' => isset($this->options['date_format']) ? $this->options['date_format'] : 'name',
      '#options' => $options,
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $item = $values->_entity;
    //$process = $item->process_id->getString();
    $process = 'unknown';
    $timestamp = '';
    if ($item->completed != null) {
      $timestamp = $item->completed->getString();
    }
    $format = '';
    if ($timestamp) {
      \Drupal::logger('MaestroEngine.CompletedTimestamp')->info('Process:' .  $process . ' completed:' . $timestamp);
      $format = \Drupal::service('date.formatter')->format($timestamp, $this->options['date_format']);
    } else {
      \Drupal::logger('MaestroEngine.CompletedTimestamp')->info('Process:' .  $process . ' no completed date');
    }
    return $format;
  }

}
