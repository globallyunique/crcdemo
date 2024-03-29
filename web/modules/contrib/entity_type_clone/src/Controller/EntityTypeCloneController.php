<?php

namespace Drupal\entity_type_clone\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for EntityTypeClone operations.
 *
 * @package Drupal\entity_type_clone\Controller
 */
class EntityTypeCloneController extends ControllerBase {

  /**
   * Store validated displays with third-party settings.
   */
  protected static $checkThirdPartySettings = [];

  /**
   * Replaces string values recursively in an array.
   */
  public static function arrayReplace(string $find, string $replace, array $arr): array {
    $newArray = [];
    foreach ($arr as $key => $value) {
      // Validate the fields like "status" and "weight" first.
      if (is_bool($value) || is_numeric($value)) {
        $newArray[$key] = $value;
      }
      if (is_array($value)) {
        $newArray[$key] = self::arrayReplace($find, $replace, $value);
      }
      elseif (is_string($value)) {
        $newArray[$key] = str_replace($find, $replace, $value);
      }
    }
    return $newArray;
  }

  /**
   * Implements to copy field display.
   */
  public static function copyFieldDisplay($display, $mode, $data) {
    // Prepare the storage string.
    $storage = 'entity_' . $display . '_display';
    // Get the source field name.
    $sourceFieldName = $data['field']->getName();
    // Get the source form display.
    $sourceDisplay = \Drupal::entityTypeManager()
      ->getStorage($storage)
      ->load($data['values']['show']['entity_type'] . '.' . $data['values']['show']['type'] . '.' . $mode)
      ->toArray();
    // Just get enabled form and view display.
    if ($sourceDisplay['status']) {
      // Prepare the target form display.
      $targetDisplay = EntityTypeCloneController::arrayReplace(
        $data['values']['show']['type'], $data['values']['clone_bundle_machine'], $sourceDisplay
      );
      unset($targetDisplay['_core']);
      // Generate new uuid.
      $targetDisplay['uuid'] = \Drupal::service('uuid')->generate();
      // Save the target display.
      if ($display === 'form') {
        // Save the form display.
        \Drupal::configFactory()
          ->getEditable('core.' . $storage . '.' . $data['values']['show']['entity_type'] . '.' . $data['values']['clone_bundle_machine'] . '.' . $mode)
          ->setData($targetDisplay)
          ->save();
      }
      elseif ($display === 'view') {
        // Save the view display.
        $entityDisplay = \Drupal::service('entity_display.repository')
          ->getViewDisplay($data['values']['show']['entity_type'], $data['values']['clone_bundle_machine'], $mode);

        // Add support for third party configuration in view modes like
        // field_group, etc.
        if (!isset(self::$checkThirdPartySettings[$mode])) {
          $third_party_settings = $sourceDisplay['third_party_settings'];
          if ($third_party_settings) {
            // Avoid future checks.
            self::$checkThirdPartySettings[$mode] = true;
            foreach ($third_party_settings as $module => $third_data) {
              if (is_array($third_data)) {
                foreach ($third_data as $key => $third_value) {
                  // Add to destination display.
                  $entityDisplay->setThirdPartySetting($module, $key, $third_value);
                }
              }
            }
          }
        }

        if (isset($targetDisplay['content'][$sourceFieldName])) {
          $entityDisplay->setComponent($sourceFieldName, $targetDisplay['content'][$sourceFieldName]);
        }
        // Hide the field if needed.
        if (isset($targetDisplay['hidden'][$sourceFieldName]) && (int) $targetDisplay['hidden'][$sourceFieldName] === 1) {
          $entityDisplay->removeComponent($sourceFieldName);
        }
        // Save the display.
        $entityDisplay->save();
      }
      return new JsonResponse(t('Success'));
    }
  }

}
