<?php

declare(strict_types = 1);

namespace Drupal\Tests\theme_rule\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the theme negotiator service.
 *
 * @group theme_rule
 */
class ThemeRuleNegotiatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'theme_rule',
    'theme_rule_test',
  ];

  /**
   * Tests the service.
   */
  public function testRulesOrder(): void {
    $this->createTestingRules('all enabled');
    // Order matters.
    $this->assertTheme('bar', ['rule_1', 'rule_2', 'rule_3']);
    // Conditions not met.
    $this->assertTheme('baz', ['rule_1', 'rule_3']);

    $this->createTestingRules('without conditions');
    // Rule without conditions is bypassed.
    $this->assertTheme('baz', ['rule_1', 'rule_2', 'rule_3']);

    $this->createTestingRules('with disabled');
    // Disabled rule is bypassed.
    $this->assertTheme('baz', ['rule_1', 'rule_2', 'rule_3']);

    $this->createTestingRules('without conditions and with disabled');
    // Rules without conditions and/or disabled are bypassed.
    $this->assertTheme('qux', ['rule_1', 'rule_2', 'rule_3', 'rule_4']);
  }

  /**
   * Asserts that the negotiator resolves a given theme.
   *
   * @param string $expected_theme
   *   The expected theme.
   * @param string[] $winning_rules
   *   Which rules are set to win in this scenario.
   */
  protected function assertTheme(string $expected_theme, array $winning_rules = []): void {
    $this->assertThemeHelper($expected_theme, $winning_rules);
  }

  /**
   * Asserts that the negotiator doesn't resolve to any theme.
   *
   * @param string[] $winning_rules
   *   Which rules are set to win in this scenario.
   */
  protected function assertNoTheme(array $winning_rules = []): void {
    $this->assertThemeHelper(NULL, $winning_rules);
  }

  /**
   * Provides a helper for theme assertion methods.
   *
   * @param string|null $expected_theme
   *   The expected theme.
   * @param string[] $winning_rules
   *   Which rules are set to win in this scenario.
   */
  protected function assertThemeHelper(?string $expected_theme, array $winning_rules = []): void {
    $negotiator = $this->container->get('theme_rule.negotiator');
    $this->container->get('state')->set('theme_rule_test.winning_rules', $winning_rules);
    $route_match = $this->container->get('current_route_match');
    $negotiator->applies($route_match);
    $actual_theme = $negotiator->determineActiveTheme($route_match);
    $this->assertSame($expected_theme, $actual_theme);
  }

  /**
   * Creates testing rules for a given testing scenario.
   *
   * @param string $scenario
   *   The scenario identifier.
   */
  protected function createTestingRules(string $scenario): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('theme_rule');

    // Cleanup any existing rules produced by a previous usage.
    $storage->delete($storage->loadMultiple());

    $rules = Yaml::decode(file_get_contents(__DIR__ . '/../../fixtures/test_cases.yml'));
    foreach ($rules[$scenario] as $id => $values) {
      $storage->create($values + [
        'id' => $id,
        'label' => $id,
        'conditions' => [
          'theme_rule_test' => [
            'id' => 'theme_rule_test',
            'negate' => FALSE,
            'rule' => $id,
          ],
        ],
      ])->save();
    }
  }

}
