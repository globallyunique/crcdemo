<?php

declare(strict_types = 1);

namespace Drupal\theme_rule\Entity;

use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Interface for theme rule config entities.
 */
interface ThemeRuleInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Gets conditions for this theme rule.
   *
   * @return \Drupal\Core\Condition\ConditionPluginCollection
   *   An array of configured condition plugins.
   */
  public function getConditions(): ConditionPluginCollection;

  /**
   * Returns an array of condition configurations.
   *
   * @return array
   *   An array of condition configuration keyed by the condition ID.
   */
  public function getConditionsConfig(): array;

  /**
   * Gets a condition plugin instance.
   *
   * @param string $instance_id
   *   The condition plugin instance ID.
   *
   * @return \Drupal\Core\Condition\ConditionInterface
   *   A condition plugin.
   */
  public function getCondition(string $instance_id): ConditionInterface;

  /**
   * Returns the theme.
   *
   * @return string
   *   The theme.
   */
  public function getTheme(): string;

  /**
   * Returns the weight of this theme rule (used for sorting).
   *
   * @return int
   *   The theme rule weight.
   */
  public function getWeight(): int;

}
