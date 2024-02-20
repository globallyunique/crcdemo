<?php
namespace Drupal\open_system_and_autocomplete\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

class OpenSystemAndAutocompleteThemeNegotiator implements ThemeNegotiatorInterface {
  public function applies(RouteMatchInterface $route_match) {
    $route_name = $route_match->getRouteName();

    // Hack to make webform submissions use the correct theme
    if(isset($route_name) && strpos($route_name, 'webform_submission.edit_form') != false) {
      \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('applies route match keys:'. print_r(array_keys($route_match->getParameters()->all()), TRUE));
      // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('applies node parameter:'. print_r($route_match->getParameter('webform'), TRUE));
      // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('applies all params:'. print_r($route_match->getParameters()->all(), TRUE));
      return true;
    }

    // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('applies checking route:'. $route_name);
    if ($route_name === null) {
      return false;
    }
    if (strpos($route_name, 'contacts.collection.individual') === 0 ||
      strpos($route_name, 'contacts.collection.organisation') === 0 ||
      strpos($route_name, 'contacts') === 0) {
      return true;
    }

    if (strpos($route_name, 'node.add') === 0 ||
        strpos($route_name, 'node.edit_form') === 0 ||
        strpos($route_name, 'entity.node.edit_form') === 0) {
      $node = $route_match->getParameter('node');
      if (strpos($route_name, 'node.add') === 0) {
        $node = $route_match->getParameter('node_type');
      }

      if ($node instanceof \Drupal\node\NodeInterface) {
        // Check if the node type is in your list of types.
        // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('applies checking node:'. $node->getType());
        return $this->matchesRouteName($node->getType());
      } elseif ($node instanceof \Drupal\node\Entity\NodeType) {
        // For node.add routes, the 'node' parameter is the node type.
        // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('applies checking node:'. $node->id());
        return $this->matchesRouteName($node->id());
      } elseif (is_string($node)) {
        // For node.add routes, the 'node' parameter is the content type.
        // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('applies checking node:'. $node);
        return $this->matchesRouteName($node);
      } elseif ($node === null) {
        // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->error('node is null, route parameters: ' . print_r($route_match->getParameters()->all(), TRUE));
      }
    }
    // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('applies returning false');
    return false;
    // $routes = ['contacts.collection.individual', 'contacts.collection.organisation'];
    // return in_array($route_match->getRouteName(), $routes);
  }

  public function matchesRouteName($route_name) {
    // Force styling to default for the following
    $types = [
      // Tasks
      'system_a_',
      'informed_consent_via_medable', 
      'vital_signs', 
      'vital_signs_edit',
      'patient_number_via_ivrs',
      'hemoglobin_a1c',
      'simple_informed_consent', 
      'add_patient_to_ehr',
      'hachinski__4',
      'mmse_10_23',
      'placeholder_task',
      'physical_examination',
      'ambulatory_ecg_placed',
      'randomize_patient',
      'placeholder_task',
      // Admin pages for adding/editing/deleting
      'patient',
      'study',
      // Docs
      'ethics_committee_submission',
      'feasibility_assessment',
      'article',
      'page',
      //Formum
      'forum'
      
    ];
    return (in_array($route_name, $types) || strpos($route_name, 'task_') !== false);
  }

  public function determineActiveTheme(RouteMatchInterface $route_match) {
    // \Drupal::logger('OpenSystemAndAutocompleteThemeNegotiator')->info('determineActiveTheme');
    return 'sitehub_material_base';
  }
}