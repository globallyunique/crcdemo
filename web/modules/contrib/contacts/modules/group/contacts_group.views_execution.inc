<?php

/**
 * @file
 * Views execution hook implementations for Contacts Group.
 */

use Drupal\views\ViewExecutable;

/**
 * Implements hook_views_pre_build().
 */
function contacts_group_views_pre_build(ViewExecutable $view) {
  if ($view->id() == 'contacts_orgs') {
    // Include the current page in the edit link URL.

    /** @var \Drupal\Core\Pager\PagerParametersInterface $pager_parameters */
    $pager_parameters = \Drupal::service('pager.parameters');
    $page = $pager_parameters->findPage($view->getPager()->getPagerId());
    $view->field['nothing']->options['alter']['path'] .= '&page=' . $page;
  }
}
