<?php

namespace Drupal\contacts;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityAutocompleteMatcher;

/**
 * Autocomplete matcher for handling results with multi-line labels.
 */
class MultiLineOutputAutocompleteMatcher extends EntityAutocompleteMatcher {

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {
    $matches = [];

    $options = $selection_settings + [
      'target_type' => $target_type,
      'handler' => $selection_handler,
    ];
    $handler = $this->selectionManager->getInstance($options);

    if (isset($string)) {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $match_limit = isset($selection_settings['match_limit']) ? (int) $selection_settings['match_limit'] : 10;
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, $match_limit);

      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          if (is_array($label)) {
            // If the label is an array it will likely be multi-line output.
            // In this case use the first line of the label as the key.
            $key = reset($label);
            $key = "$key ($entity_id)";
          }
          else {
            $key = "$label ($entity_id)";
          }
          // Strip things like starting/trailing white spaces, line breaks and
          // tags.
          $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
          // Names containing commas or quotes must be wrapped in quotes.
          $key = Tags::encode($key);
          $matches[] = ['value' => $key, 'label' => $label];
        }
      }
    }

    return $matches;
  }

}
