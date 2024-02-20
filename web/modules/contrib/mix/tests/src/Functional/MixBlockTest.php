<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Mix block class.
 *
 * @group mix
 */
class MixBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'mix'];

  /**
   * Test settings.
   */
  public function testBlockClass() {

    $this->drupalLogin($this->rootUser);

    // Place a block with custom CSS classes.
    $this->drupalGet('admin/structure/block/add/system_menu_block:account/stark', ['query' => ['region' => 'secondary_menu']]);
    $edit = [
      'id' => 'useraccountmenu',
      'region' => 'secondary_menu',
      'third_party_settings[mix][class]' => 'custom-block-class custom-block-class2',
    ];
    $this->submitForm($edit, 'Save block');

    // Test block class.
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('class="custom-block-class custom-block-class2"');

    // Test remove block class.
    $this->drupalGet('admin/structure/block/manage/useraccountmenu');
    $edit = [
      'third_party_settings[mix][class]' => '',
    ];
    $this->submitForm($edit, 'Save block');

    // Block class has been removed.
    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains('custom-block-class');
  }

}
