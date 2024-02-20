<?php

declare(strict_types = 1);

namespace Drupal\theme_rule\Theme;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\theme_rule\Entity\ThemeRuleInterface;

/**
 * Provides a theme negotiator based on the theme rule config entities.
 */
class ThemeRuleNegotiator implements ThemeNegotiatorInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The context handler service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * Static cache of eligible theme rule entities.
   *
   * @var \Drupal\theme_rule\Entity\ThemeRuleInterface[]
   */
  protected $themeRules;

  /**
   * Static cache the theme rule config entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $themeRuleStorage;

  /**
   * Constructs a new theme negotiator instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository service.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ContextRepositoryInterface $context_repository, ContextHandlerInterface $context_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->contextRepository = $context_repository;
    $this->contextHandler = $context_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    $storage = $this->entityTypeManager->getStorage('theme_rule');
    $rule_ids = $storage->getQuery()
      ->condition('status', TRUE)
      ->sort('weight')
      ->execute();

    $this->themeRules = array_filter(
      $storage->loadMultiple($rule_ids),
      // Filter out rules without conditions.
      function (ThemeRuleInterface $theme_rule): bool {
        return (bool) $theme_rule->getConditions()->count();
      }
    );

    return !empty($this->themeRules);
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    foreach ($this->themeRules as $theme_rule) {
      $rule_conditions = $theme_rule->getConditions();

      $conditions = [];
      foreach ($rule_conditions as $condition_id => $condition) {
        if ($condition instanceof ContextAwarePluginInterface) {
          try {
            $contexts = $this->contextRepository->getRuntimeContexts(array_values($condition->getContextMapping()));
            $this->contextHandler->applyContextMapping($condition, $contexts);
          }
          catch (MissingValueContextException $exception) {
            // If any context is missing or has no value, we cannot evaluate
            // this rule. Jump to the next rule.
            continue 2;
          }
        }
        $conditions[$condition_id] = $condition;
      }

      // Check that the all conditions are met.
      if ($this->resolveConditions($conditions)) {
        return $theme_rule->getTheme();
      }
    }

    // No enabled rule met their conditions.
    return NULL;
  }

  /**
   * Resolves the given conditions.
   *
   * Note: All conditions (AND logic) must pass in order to apply this rule.
   *
   * @param \Drupal\Core\Condition\ConditionInterface[] $conditions
   *   A set of conditions.
   *
   * @return bool
   *   Whether these conditions are met.
   */
  protected function resolveConditions(array $conditions): bool {
    foreach ($conditions as $condition) {
      try {
        $pass = $condition->execute();
      }
      catch (ContextException $e) {
        // A condition is missing context and is not negated, then it's a fail.
        $pass = $condition->isNegated();
      }

      // If a condition fails, the rule failed.
      if (!$pass) {
        return FALSE;
      }
    }

    // All conditions passed.
    return TRUE;
  }

}
