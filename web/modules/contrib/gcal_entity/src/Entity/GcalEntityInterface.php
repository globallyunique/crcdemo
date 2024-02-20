<?php

namespace Drupal\gcal_entity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining GCal Entity entities.
 *
 * @ingroup gcal_entity
 */
interface GcalEntityInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the GCal Entity name.
   *
   * @return string
   *   Name of the GCal Entity.
   */
  public function getName(): string;

  /**
   * Sets the GCal Entity name.
   *
   * @param string $name
   *   The GCal Entity name.
   *
   * @return \Drupal\gcal_entity\Entity\GcalEntityInterface
   *   The called GCal Entity entity.
   */
  public function setName(string $name): GcalEntityInterface;

  /**
   * Gets the GCal Entity creation timestamp.
   *
   * @return int
   *   Creation timestamp of the GCal Entity.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the GCal Entity creation timestamp.
   *
   * @param int $timestamp
   *   The GCal Entity creation timestamp.
   *
   * @return \Drupal\gcal_entity\Entity\GcalEntityInterface
   *   The called GCal Entity entity.
   */
  public function setCreatedTime(int $timestamp): GcalEntityInterface;

  /**
   * Returns the GCal Entity published status indicator.
   *
   * Unpublished GCal Entity are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the GCal Entity is published.
   */
  public function isPublished(): bool;

  /**
   * Sets the published status of a GCal Entity.
   *
   * @param bool $published
   *   TRUE to set this GCal Entity to published, FALSE to set it to
   *   unpublished.
   *
   * @return \Drupal\gcal_entity\Entity\GcalEntityInterface
   *   The called GCal Entity entity.
   */
  public function setPublished(bool $published): GcalEntityInterface;

  /**
   * Gets the calendar ID of the entity.
   * @return string
   */
  public function getCalendarId(): string;

  public static function getCalendars(string $calendar_id, bool $cache = TRUE): array;

}
