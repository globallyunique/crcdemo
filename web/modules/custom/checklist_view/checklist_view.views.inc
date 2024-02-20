<?php

use Drupal\views\Views;

/**
 * Implements hook_views_data().
 */
function checklist_view_views_data() {
  \Drupal::logger('checklist_view_views_data')->info('altered');
  $data['maestro_queue']['field_patient_reference'] = [
    'title' => t('Patient Reference'),
    'field' => [
      'id' => 'field_patient_reference',
    ],
  ];

  return $data;
}