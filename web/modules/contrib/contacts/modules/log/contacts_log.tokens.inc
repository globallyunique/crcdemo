<?php

/**
 * @file
 * Token hook implementations for Contacts Log.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Implements hook_token_info_alter().
 */
function contacts_log_token_info_alter(&$data) {
  $data['tokens']['profile']['type-label'] = [
    'name' => new TranslatableMarkup('Profile bundle label'),
    'description' => new TranslatableMarkup('The type of profile.'),
  ];
}

/**
 * Implements hook_tokens().
 */
function contacts_log_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'profile' && !empty($data['profile'])) {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'type-label':
          $replacements[$original] = '';
          $bundle_label = $data['profile']->type->entity->label();
          $replacements[$original] = $bundle_label;
          break;
      }
    }
  }

  return $replacements;
}
