<?php

declare(strict_types = 1);

namespace Drupal\Tests\theme_rule\Functional;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\theme_rule\Entity\ThemeRule;

/**
 * Tests theme negotiation with Theme Rules module.
 *
 * @group theme_rule
 */
class ThemeRuleTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'contact',
    'node',
    'path_alias',
    'theme_rule',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('theme_installer')->install(['claro']);
  }

  /**
   * Tests theme negotiation.
   */
  public function testThemeRule(): void {
    NodeType::create(['type' => 'page'])->save();
    $node = Node::create([
      'title' => $this->randomString(),
      'type' => 'page',
    ]);
    $node->save();

    /** @var \Drupal\theme_rule\Entity\ThemeRuleInterface $rule */
    $rule = ThemeRule::create([
      'id' => 'rule1',
      'width' => -10,
      'theme' => 'claro',
      'status' => TRUE,
      'conditions' => [
        'node_type' => [
          'id' => 'entity_bundle:node',
          'bundles' => [
            'page' => 'page',
          ],
          'negate' => FALSE,
          'context_mapping' => [
            'node' => '@node.node_route_context:node',
          ],
        ],
        'user_role' => [
          'id' => 'user_role',
          'roles' => [
            'authenticated' => 'authenticated',
          ],
          'negate' => FALSE,
          'context_mapping' => [
            'user' => '@user.current_user_context:current_user',
          ],
        ],
      ],
    ]);
    $rule->save();

    $this->drupalGet($node->toUrl());

    // Only the node_type condition is met as the user is anonymous. As an
    // effect the theme is the site's default theme.
    $this->assertTheme('olivero');

    // Login as a regular user an reload the page.
    $this->drupalLogin($this->createUser());

    // Now, all the conditions are met. The page is displayed via 'seven' theme.
    $this->drupalGet($node->toUrl());
    $this->assertTheme('claro');
  }

  /**
   * Asserts that the current page is rendered using a give theme.
   *
   * @param string $theme
   *   The theme ID.
   */
  protected function assertTheme(string $theme): void {
    $this->assertSession()->responseContains("core/themes/{$theme}");
  }

}
