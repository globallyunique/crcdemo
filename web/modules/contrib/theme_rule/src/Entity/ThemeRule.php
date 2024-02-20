<?php

declare(strict_types = 1);

namespace Drupal\theme_rule\Entity;

use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Executable\ExecutableManagerInterface;

/**
 * Defines the theme rule config entity.
 *
 * @ConfigEntityType(
 *   id = "theme_rule",
 *   label = @Translation("Theme rule"),
 *   label_collection = @Translation("Theme rules"),
 *   label_singular = @Translation("theme rule"),
 *   label_plural = @Translation("theme rules"),
 *   label_count = @PluralTranslation(
 *     singular = "@count theme rule",
 *     plural = "@count theme rules",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\theme_rule\ThemeRuleListBuilder",
 *     "form" = {
 *       "add" = "Drupal\theme_rule\Form\ThemeRuleForm",
 *       "default" = "Drupal\theme_rule\Form\ThemeRuleForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer themes",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status",
 *     "weight" = "weight",
 *   },
 *   links = {
 *     "collection" = "/admin/appearance/theme-rules",
 *     "add-form" = "/admin/appearance/theme-rules/add",
 *     "edit-form" = "/admin/appearance/theme-rules/manage/{theme_rule}",
 *     "delete-form" =
 *   "/admin/appearance/theme-rules/manage/{theme_rule}/delete",
 *     "enable" = "/admin/appearance/theme-rules/manage/{theme_rule}/enable",
 *     "disable" = "/admin/appearance/theme-rules/manage/{theme_rule}/disable",
 *   },
 *   config_prefix = "rule",
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "theme",
 *     "weight",
 *     "conditions",
 *   },
 * )
 */
class ThemeRule extends ConfigEntityBase implements ThemeRuleInterface {

  /**
   * The ID of the theme rule.
   *
   * @var string
   */
  protected $id;

  /**
   * The theme rule weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The theme.
   *
   * @var string
   */
  protected $theme;

  /**
   * The condition plugin configurations.
   *
   * @var array
   */
  protected $conditions = [];

  /**
   * The available contexts for this theme rule and its conditions.
   *
   * @var array
   */
  protected $contexts = [];

  /**
   * The condition collection.
   *
   * @var \Drupal\Core\Condition\ConditionPluginCollection
   */
  protected $conditionCollection;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected $conditionPluginManager;

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return [
      'conditions' => $this->getConditions(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions(): ConditionPluginCollection {
    if (!isset($this->conditionCollection)) {
      $this->conditionCollection = new ConditionPluginCollection($this->conditionPluginManager(), $this->get('conditions'));
    }
    return $this->conditionCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditionsConfig(): array {
    return $this->getConditions()->getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getCondition(string $instance_id): ConditionInterface {
    return $this->getConditions()->get($instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTheme(): string {
    return $this->theme;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): ThemeRuleInterface {
    parent::calculateDependencies();

    $this->addDependency('theme', $this->getTheme());
    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    foreach ($this->getConditions() as $condition) {
      $this->addDependency('module', $condition->getPluginDefinition()['provider']);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);

    if (isset($dependencies['module'])) {
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      foreach ($this->getConditions() as $condition_id => $condition) {
        if (in_array($condition->getPluginDefinition()['provider'], $dependencies['module'], TRUE)) {
          $this->getConditions()->removeInstanceId($condition_id);
          $changed = TRUE;
        }
      }
    }

    return $changed;
  }

  /**
   * Gets the condition plugin manager.
   *
   * @return \Drupal\Core\Executable\ExecutableManagerInterface
   *   The condition plugin manager.
   */
  protected function conditionPluginManager(): ExecutableManagerInterface {
    if (!isset($this->conditionPluginManager)) {
      $this->conditionPluginManager = \Drupal::service('plugin.manager.condition');
    }
    return $this->conditionPluginManager;
  }

}
