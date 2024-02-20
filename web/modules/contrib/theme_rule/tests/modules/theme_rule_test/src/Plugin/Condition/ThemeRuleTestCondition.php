<?php

declare(strict_types = 1);

namespace Drupal\theme_rule_test\Plugin\Condition;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Condition\ConditionPluginBase;

/**
 * Provides a condition to be used in tests.
 *
 * @Condition(
 *   id = "theme_rule_test",
 * )
 */
class ThemeRuleTestCondition extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'rule' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $winning_rules = \Drupal::state()->get('theme_rule_test.winning_rules');
    return in_array($this->getConfig()['rule'], $winning_rules);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): MarkupInterface {
    return $this->t('Rule @rule', ['@rule' => $this->getConfig()['rule']]);
  }

}
