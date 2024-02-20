<?php

namespace Drupal\Tests\mix\Functional;

/**
 * Tests advanced menu settings.
 *
 * @group mix
 */
class MixMenuItemAdvancedTest extends MixMenuTestBase {

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
  public function testMenuItemPerRole() {

    // Hierarchy
    // <$menu>
    // - item1
    // -- item1_1 (auth)
    // -- item1_2 (anon)
    // - item2 (auth)
    // -- item2_1
    // -- item2_2 (anon)
    // - item3 (anon)
    // -- item3_1
    // -- item3_2 (auth)
    $menu = $this->addCustomMenu();

    $advancedSettings_auth = [
      'mix_advanced[roles][anonymous]' => 0,
      'mix_advanced[roles][authenticated]' => 'authenticated',
    ];
    $advancedSettings_anon = [
      'mix_advanced[roles][anonymous]' => 'anonymous',
      'mix_advanced[roles][authenticated]' => 0,
    ];

    // A normal parent menu link.
    $item1   = $this->addMenuLink('', 'item1', '/', $menu->id(), TRUE, 0);
    $item1_1 = $this->addMenuLink($item1->getPluginId(), 'item1_1', '/', $menu->id(), TRUE, 0, $advancedSettings_auth);
    $item1_2 = $this->addMenuLink($item1->getPluginId(), 'item1_2', '/', $menu->id(), TRUE, 0, $advancedSettings_anon);

    // An advanced parent menu link, only visible to authenticated users.
    $item2   = $this->addMenuLink('', 'item2', '/', $menu->id(), TRUE, 1, $advancedSettings_auth);
    $item2_1 = $this->addMenuLink($item2->getPluginId(), 'item2_1', '/', $menu->id());
    $item2_2 = $this->addMenuLink($item2->getPluginId(), 'item2_2', '/', $menu->id(), TRUE, 1, $advancedSettings_anon);

    // An advanced parent menu link, only visible to anonymous users.
    $item3   = $this->addMenuLink('', 'item3', '/', $menu->id(), TRUE, 2, $advancedSettings_anon);
    $item3_1 = $this->addMenuLink($item3->getPluginId(), 'item3_1', '/', $menu->id());
    $item3_2 = $this->addMenuLink($item3->getPluginId(), 'item3_2', '/', $menu->id(), TRUE, 1, $advancedSettings_auth);

    // Test role access.
    // Authenticated users.
    $this->drupalGet('admin/structure/menu/add');
    // $item1 is has no access control, $item1_1 is for authenticated users,
    // $item1_2 is for anonymous users.
    $this->assertSession()->responseContains($item1->getTitle());
    $this->assertSession()->responseContains($item1_1->getTitle());
    $this->assertSession()->responseNotContains($item1_2->getTitle());
    // $item2 is for authenticated users, but $item2_2 is for anonymous users.
    $this->assertSession()->responseContains($item2->getTitle());
    $this->assertSession()->responseContains($item2_1->getTitle());
    $this->assertSession()->responseNotContains($item2_2->getTitle());
    // $item3 is for anonymous users, so authenticated users can't see it
    // and the children items.
    $this->assertSession()->responseNotContains($item3->getTitle());
    $this->assertSession()->responseNotContains($item3_1->getTitle());
    $this->assertSession()->responseNotContains($item3_2->getTitle());

    // Anonymous users.
    $this->drupalLogout();
    // $item1 is has no access control, $item1_1 is for authenticated users,
    // $item1_2 is for anonymous users.
    $this->assertSession()->responseContains($item1->getTitle());
    $this->assertSession()->responseNotContains($item1_1->getTitle());
    $this->assertSession()->responseContains($item1_2->getTitle());
    // $item2 is for authenticated users, so anonymous users can't see it and
    // the children items.
    $this->assertSession()->responseNotContains($item2->getTitle());
    $this->assertSession()->responseNotContains($item2_1->getTitle());
    $this->assertSession()->responseNotContains($item2_2->getTitle());
    // $item3 is for anonymous users, but $item3_2 is for authenticated users.
    $this->assertSession()->responseContains($item3->getTitle());
    $this->assertSession()->responseContains($item3_1->getTitle());
    $this->assertSession()->responseNotContains($item3_2->getTitle());
  }

  /**
   * Tests menu item and container attributes.
   */
  public function testMenuItemAttributes() {

    $menu = $this->addCustomMenu();
    $this->addMenuLink('', 'item1', '/', $menu->id());

    $advancedSettings = [
      // Link attributes.
      'mix_advanced[attributes][id]'     => 'item2',
      'mix_advanced[attributes][class]'  => 'item2 item2__link',
      'mix_advanced[attributes][target]' => '_blank',
      // Container attributes.
      'mix_advanced[container_attributes][id]'    => 'item2_container',
      'mix_advanced[container_attributes][class]' => 'item2_container item2_container_wrapper',
    ];
    $this->addMenuLink('', 'item2', '/', $menu->id(), TRUE, 0, $advancedSettings);

    // Test menu link and container's attributes.
    $this->assertSession()->elementExists('xpath', '//li[contains(@id, "item2_container")]');
    $this->assertSession()->elementExists('xpath', '//li[contains(@class, "item2_container_wrapper")]');
    $this->assertSession()->elementExists('xpath', '//a[contains(@id, "item2")]');
    $this->assertSession()->elementExists('xpath', '//a[contains(@class, "item2__link")]');
    $this->assertSession()->elementExists('css', 'li[id="item2_container"] a[id="item2"]');
    $this->assertSession()->elementExists('css', 'a[id="item2"][target="_blank"]');
  }

  /**
   * Tests the "Allow HTML" setting for menu item.
   */
  public function testMenuItemAllowHtml() {
    $menu = $this->addCustomMenu();

    // Test HTML title without "Allow HTML" enabled.
    $this->addMenuLink('', '<em>Bold Title</em>', '/', $menu->id(), TRUE, 0);
    $this->assertSession()->responseNotContains('<em>Bold Title</em>');

    // Test HTML title with "Allow HTML" enabled.
    $formValues = ['mix_allow_html' => 1];
    $this->addMenuLink('', '<strong>Bold Title</strong>', '/', $menu->id(), TRUE, 0, $formValues);
    $this->assertSession()->responseContains('<strong>Bold Title</strong>');
  }

}
