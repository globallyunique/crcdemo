<?php

namespace Drupal\Tests\mix\Functional;

/**
 * Tests advanced menu settings.
 *
 * @group mix
 */
class MixMenuTokenTest extends MixMenuTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests menu item visibility by role.
   */
  public function testMenuToken() {

    // Hierarchy
    // <$menu>
    // - item1 (menu token)
    $menu = $this->addCustomMenu();

    // Change site name.
    $this->config('system.site')->set('name', 'Drupal Test')->save();

    // A normal parent menu link.
    $this->addMenuLink('', '[current-user:name]', '/user/[current-user:uid]', $menu->id(), TRUE, 0);
    $this->addMenuLink('', '[site:name]', '/', $menu->id(), TRUE, 0);

    // Test menu token [current-user:name] and [current-user:uid].
    $this->assertSession()->linkExists('admin');
    $this->assertSession()->linkByHrefExists('/user/1');

    // Create accounts to test menu token.
    $account2_name = 'User 2';
    $account2 = $this->createUser([], $account2_name, FALSE, ['uid' => 2]);

    // Login $account2.
    $this->drupalLogin($account2);

    // Menu token test.
    // $this->drupalGet('<front>');
    // Test menu token [current-user:name] and [current-user:uid].
    $this->assertSession()->linkExists($account2_name);
    $this->assertSession()->linkByHrefExists('/user/2');
    // Test menu token [site:name].
    $this->assertSession()->linkExists('Drupal Test');

  }

}
