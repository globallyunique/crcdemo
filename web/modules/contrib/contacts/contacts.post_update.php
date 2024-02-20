<?php

/**
 * @file
 * Post update hooks for Contacts.
 */

/**
 * Enable access denied redirects for existing installs.
 */
function contacts_post_update_access_denied_bc() {
  $config = \Drupal::configFactory()->getEditable('contacts.configuration');
  if ($config->get('access_denied_redirect') === NULL) {
    $config->set('access_denied_redirect', TRUE)->save();
  }
}

/**
 * Enable access denied redirects for existing installs.
 */
function contacts_post_update_coupled_real_name_bc() {
  $config = \Drupal::configFactory()->getEditable('contacts.configuration');
  if ($config->get('coupled_real_name') === NULL) {
    $config->set('coupled_real_name', TRUE)->save();
  }
}
