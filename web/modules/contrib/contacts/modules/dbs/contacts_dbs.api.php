<?php

/**
 * @file
 * Hooks provided by the Contacts DBS module.
 */

/**
 * Alter the DBS statuses.
 *
 * @param array $options
 *   The DBS status options.
 * @param array $context
 *   An arry of context containing:
 *   - definition: \Drupal\Core\Field\FieldStorageDefinitionInterface
 *     The DBS field storage definition.
 *   - entity: \Drupal\profile\Entity\ProfileInterface|null
 *     (optional) The DBS profile context if known, or NULL if the allowed
 *     values are being collected without the context of a specific entity.
 *   - cacheable: bool
 *     (optional) If an $entity is provided, the cacheable parameter should be
 *     modified by reference and set to FALSE if the set of allowed values
 *     returned was specifically adjusted for that entity and cannot not be
 *     reused for other entities. Defaults to TRUE.
 */
function hook_contacts_dbs_statuses_alter(array &$options, array &$context) {
  // Add additional DBS statuses.
}
