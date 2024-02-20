<?php

namespace Drupal\maestro\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to translate the unixtime timestamp to a human readable format if you so choose to.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("maestro_process_completed_time")
 */
class MaestroEngineProcessCompletionTime extends FieldPluginBase {

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
    $options['date_format'] = ['default' => 'medium'];

    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $options = [
      'unix' => $this->t('Database Timestamp'),
      'short' => $this->t('Short ( 12/01/1901 - 23:59 )'),
      'medium' => $this->t('Medium ( Tue, 12/01/1901 - 23:59 )'),
      'long' => $this->t('Long ( Tuesday, December 1, 1901 - 23:59 )'),
      'html_datetime' => $this->t('HTML5 Date/Time ( j-M-Y g:ia )'),
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
    $timestamp = '';
    if ($item->completed != null) {
      $timestamp = $item->completed->getString();
    }
    $format = '';
    if ($this->options['date_format'] != 'unix') {
      if ($timestamp) {
        \Drupal::logger('MaestroEngine.ProcessCompletionTime')->info(' completed:' . $timestamp);
        $format = \Drupal::service('date.formatter')->format($timestamp, $this->options['date_format']);
      } else {
        \Drupal::logger('MaestroEngine.ProcessCompletionTime')->info('no completed date');
      }
    } else {
      $format = $timestamp;
    }
    return $format;

  }

}
