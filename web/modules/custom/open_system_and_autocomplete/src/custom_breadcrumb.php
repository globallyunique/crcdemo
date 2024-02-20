<?php

/**
 * Implements hook_system_breadcrumb_alter().
 * 
 * TODO: Rather than doing no breadcrumb caching via addCacheMaxAge to the breadcrumb we should design the pages and their routes so caching works correctly.
 * For now setting no caching is adequate for the POC. We should be using something like the following if we stick with invalidating a custom constructed breadcrumb. 
 * But this didn't work in initial tests. 
 *   \Drupal\Core\Cache\Cache::invalidateTags(["route:{$route_name}"]); 
 *   ...
 *   $breadcrumb->addCacheTags(["route:{$route_name}"]);

 */
define('LOG_CHANNEL', 'breadcrumb_alter');
define('HOME_ROUTE', '<front>');


// hook_breadcrumb_alter
//
function open_system_and_autocomplete_system_breadcrumb_alter(\Drupal\Core\Breadcrumb\Breadcrumb &$breadcrumb, \Drupal\Core\Routing\RouteMatchInterface $route_match, array $context) {
  $route_name = $route_match->getRouteName();
  // $breadcrumb->addCacheContexts(['url']);
  log_info('Route: ' . $route_name);

  if (str_starts_with($route_name, 'node.add') || $route_name === 'entity.node.edit_form') {
    handle_node_routes($breadcrumb, $route_match, $route_name);
  } elseif (str_contains($route_name, 'view.events')) {
    handle_view_events_route($breadcrumb, $route_name);
  } elseif (str_contains($route_name, 'taskconsole') || str_starts_with($route_name, 'maestro_taskconsole.taskconsoleforpatient')) {
    handle_taskconsole_route($breadcrumb, $route_name);
  } elseif (str_contains($route_name, 'entity.webform.canonical') || str_contains($route_name, 'entity.webform_submission.edit_form')) {
    handle_webform_routes($breadcrumb, $route_name);    
  } else {
    // \Drupal::logger(LOG_CHANNEL)->error('No breadcrumb handling for route: ' . $route_name);
  }
}

function handle_node_routes(&$breadcrumb, $route_match, $route_name) {
  log_info('handle_node_routes route_name:' . $route_name);
  $node_type = get_node_type($route_match, $route_name);
  if($node_type == NULL) {
    \Drupal::logger('handle_node_routes')->error('handle_node_routes Node type is NULL');
  } else {
    log_info('handle_node_routes node_type id: ' . $node_type->id());
    if ($node_type && (in_array($node_type->id(), open_system_and_autocomplete_get_content_types()) || str_contains($node_type->id(), 'task_'))) {
      $process_id = \Drupal::state()->get('sitehub.process_id');
      $patient_id = \Drupal::state()->get('sitehub.patient_id');
      $events_url = '/events/' . $patient_id;
      $task_console_url = '/taskconsole/' . $patient_id . '/' . $process_id;
      \Drupal::logger('handle_node_routes')->info('build_breadcrumb_for_node_type URL: ' . $url);
      $breadcrumb = build_breadcrumb([t('Home'), t('Patients'), t('Events'), t('Checklist')], ['/','/patients/',$events_url, $task_console_url]);
    } else if ($node_type && str_contains($node_type->id(), 'patient')) {
      $breadcrumb = build_breadcrumb([t('Home'), t('Patients')], ['/', '/patients/']);
    } else if ($node_type && str_contains($node_type->id(), 'study')) {     
      $breadcrumb = build_breadcrumb([t('Home'), t('Studies')], ['/', '/studies/']);
    }
  }
}

function handle_webform_routes(&$breadcrumb, $route_name) {
  log_info('handle_webform_routes route_name: ' . $route_name);
  $patient_id = \Drupal::state()->get('sitehub.patient_id');
  $process_id = \Drupal::state()->get('sitehub.process_id');
  log_info('handle_webform_routes  patient_id: ' . $patient_id);
  $task_console_url = '/taskconsole/' . $patient_id . '/' . $process_id;
  $breadcrumb = build_breadcrumb([t('Home'), t('Patients'), t('Events'), t('Checklist')], [ '/', '/patients/', '/events/' . $patient_id, $task_console_url]);
}

function handle_view_events_route(&$breadcrumb, $route_name) {
  log_info('handle_view_events_route route_name:' . $route_name);
  $breadcrumb = build_breadcrumb([t('Home'), t('Patients')], ['/', '/patients/']);
}

function handle_taskconsole_route(&$breadcrumb, $route_name) {
  $patient_id = \Drupal::state()->get('sitehub.patient_id');
  log_info('handle_taskconsole_route route_name: '. $route_name . ' patient_id: ' . $patient_id);
  $breadcrumb = build_breadcrumb([t('Home'), t('Patients'), t('Events')], [ '/', '/patients/', '/events/' . $patient_id]);
}

function get_node_type($route_match, $route_name) {
  $node_type = $route_match->getParameter('node_type');
  if (!$node_type && $route_name === 'entity.node.edit_form') {
    $node = $route_match->getParameter('node');
    if ($node instanceof \Drupal\node\NodeInterface) {
      $node_type = $node->getType();
    }
  }
  if (is_string($node_type)) {
    log_info('Node type is string: ' . $node_type);
    $node_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($node_type);
  }
  return $node_type;
}

function build_breadcrumb($links, $urls = []) {
  $breadcrumb = new \Drupal\Core\Breadcrumb\Breadcrumb();
  foreach ($links as $i => $link) {
    \Drupal::logger(LOG_CHANNEL)->info('build_breadcrumb i:'. $i. ' link: ' . $link. ' is set: '. isset($urls[$i]));
    $url = isset($urls[$i]) ? \Drupal\Core\Url::fromUserInput($urls[$i]) : \Drupal\Core\Url::fromRoute(HOME_ROUTE);
    $breadcrumb->addLink(\Drupal\Core\Link::fromTextAndUrl($link, $url));
  }
  return $breadcrumb;
}

function log_info($message) {
  \Drupal::logger(LOG_CHANNEL)->info($message);
}


/**
 * Implements hook_block_view_alter().
 */
function open_system_and_autocomplete_block_view_alter(array &$build, \Drupal\Core\Block\BlockPluginInterface $block) {
  \Drupal::logger('open_system_and_autocomplete_block_view_alter')->info('block plugin id:' . $block->getPluginId());
  // Check if the current block is the main navigation block.
    if ($block->getPluginId() == 'system_breadcrumb_block') {
    $build['#cache']['max-age'] = 0;
  }
  if ($block->getPluginId() == 'sitehub_material_base_main_menu') {
    // Get the current route name.
    $route_name = \Drupal::routeMatch()->getRouteName();

    // Check if the current route is a node add or edit form.
    $form_ids = open_system_and_autocomplete_get_form_ids();
    if (in_array($route_name, $form_ids)) {
      // If it is, make the block visible.
      $build['#access'] = TRUE;
    }
  }
}

/**
 * Implements hook_preprocess_block().
 */
function open_system_and_autocomplete_preprocess_block(&$variables) {
  if (isset($variables['elements']['#id']) && $variables['elements']['#id'] == 'sitehub_material_base_main_menu') {
    \Drupal::logger('open_system_and_autocomplete_preprocess_block')->info('block id matched:' . $variables['elements']['#id']);
    $route_name = \Drupal::routeMatch()->getRouteName();
    $form_ids = open_system_and_autocomplete_get_form_ids();
    if (in_array($route_name, $form_ids)) {
      $variables['content']['#access'] = TRUE;
    }
  }
}
