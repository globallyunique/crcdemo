<?php

namespace Drupal\contacts_mapping\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\user\UserInterface;

/**
 * Represents a list of geofield item field values.
 */
class GeofieldItemList extends FieldItemList {

  /**
   * Fetch geolocation data from source fields.
   *
   * @return bool
   *   Whether the entity was updated and needs saving.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function updateGeoFromSource(): bool {

    // If geolocation is computed, do nothing.
    if ($this->computed) {
      return FALSE;
    }

    /** @var \Drupal\user\UserInterface $entity */
    $entity = $this->getEntity();

    if (!$entity instanceof UserInterface) {
      return FALSE;
    }

    // @todo Make sources configurable plugins.
    if (in_array('crm_indiv', $entity->getRoles())) {
      $field = 'profile_crm_indiv';
    }
    elseif (in_array('crm_org', $entity->getRoles())) {
      $field = 'profile_crm_org';
    }
    else {
      return FALSE;
    }

    // Using the magic method to access the profile loads a cached version of
    // the profile, which if this is the first time the address has been
    // geocoded can have an empty value. Instead, we'll fully load the profile.
    if ($profiles = $entity->get($field)->referencedEntities()) {
      if ($profile = reset($profiles)) {
        if ($profile->hasField('geolocation_geocoded')) {
          $this->setValue($profile->get('geolocation_geocoded')->getValue());

          return TRUE;
        }
      }
    }

    // If we get here, nothing needs to be saved.
    return FALSE;
  }

}
