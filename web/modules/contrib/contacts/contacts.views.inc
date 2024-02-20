<?php

/**
 * @file
 * Views hook implementations for Contacts.
 */

/**
 * Implements hook_views_data_alter().
 */
function contacts_views_data_alter(array &$data) {
  $data['users_field_data']['_label'] = [
    'title' => t('User label'),
    'help' => t('The computed name of the user'),
    'field' => [
      'id' => 'field',
    ],
    'entity field' => '_label',
  ];
}
