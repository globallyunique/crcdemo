<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test content sync functions.
 *
 * @group mix
 */
class MixContentSyncConfigFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mix'];

  /**
   * Test callback.
   */
  public function testContentSyncConfigForm() {
    // Login as an admin.
    $admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/mix');

    // Assert warning message and disabled fields.
    $this->assertSession()->pageTextContains('Please enable core modules');
    $this->assertSession()->fieldDisabled('edit-show-content-sync-id');
    // The fieldDisabled() seems ot working on button, use
    // elementAttribteExists() instead.
    $this->assertSession()->elementAttributeExists('css', '#edit-content-sync-generate-content', 'disabled');

    // Enable required modules.
    \Drupal::service('module_installer')->install(['config', 'serialization']);

    // Revisit config page.
    $this->drupalGet('admin/config/mix');
    // No warning message.
    $this->assertSession()->pageTextNotContains('Please enable core modules');

    // Assert the default value and field status.
    $this->assertSession()->fieldValueEquals('edit-show-content-sync-id', FALSE);
    $this->assertSession()->fieldEnabled('edit-show-content-sync-id');
    $this->assertSession()->elementAttributeExists('css', '#edit-content-sync-generate-content', 'disabled');

    // Enable content sync.
    $edit = [];
    $edit['show_content_sync_id'] = TRUE;
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    // Assert the saved value and button status.
    $this->assertSession()->fieldValueEquals('edit-show-content-sync-id', TRUE);
    $this->assertSession()->elementAttributeNotExists('css', '#edit-content-sync-generate-content', 'disabled');
  }

}
