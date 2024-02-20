<?php

declare(strict_types = 1);

namespace Drupal\Tests\theme_rule\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\theme_rule\Entity\ThemeRule;

/**
 * Tests the theme rule config entity dependencies.
 *
 * @group theme_rule
 */
class ThemeRuleDependencyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'path_alias',
    'system',
    'theme_rule',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['olivero']);
    $this->installEntitySchema('node');
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Tests dependencies.
   */
  public function testDependencies(): void {
    /** @var \Drupal\theme_rule\Entity\ThemeRuleInterface $rule */
    $rule = ThemeRule::create([
      'id' => 'rule1',
      'theme' => 'olivero',
      'conditions' => [
        'node_type' => [
          'id' => 'entity_bundle:node',
          'bundles' => [
            'article' => 'article',
          ],
          'negate' => FALSE,
          'context_mapping' => [
            'node' => '@node.node_route_context:node',
          ],
        ],
        'request_path' => [
          'id' => 'request_path',
          'pages' => '/contact',
          'negate' => FALSE,
        ],
      ],
    ]);
    $rule->save();

    $this->assertSame(['node', 'system'], $rule->getDependencies()['module']);

    // Uninstall the 'node' module.
    $this->container->get('module_installer')->uninstall(['node']);

    // Check that the node module dependency has been removed.
    $rule = ThemeRule::load($rule->id());
    $this->assertSame(['system'], $rule->getDependencies()['module']);

    // Check that removing a theme, removes the associated rules.
    $this->container->get('theme_installer')->uninstall(['olivero']);
    $this->assertNull(ThemeRule::load($rule->id()));
  }

}
