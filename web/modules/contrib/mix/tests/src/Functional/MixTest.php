<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Tests\node\Functional\NodeTestBase;

/**
 * Tests Mix module.
 *
 * @group mix
 */
class MixTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Editor user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $editor;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mix'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create users.
    $this->editor = $this->drupalCreateUser([
      'create page content',
      'edit own page content',
    ]);
  }

  /**
   * Test settings form.
   *
   * @covers SettingsForm::buildForm
   */
  public function testSettingsForm() {

    $mixConfigPage = '/admin/config/mix';

    // Test anonymous account.
    $this->drupalGet($mixConfigPage);
    $this->assertSession()->statusCodeEquals(403);

    // Test authenticate account.
    $authUser = $this->drupalCreateUser([]);
    $this->drupalLogin($authUser);
    $this->drupalGet($mixConfigPage);
    $this->assertSession()->statusCodeEquals(403);

    // Test account with config admin permission.
    $configAdminUser = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($configAdminUser);
    $this->drupalGet($mixConfigPage);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('Enable development mode');
    $this->assertSession()->fieldExists('Remove X-Generator');
    $this->assertSession()->fieldExists('Hide revision field');
    $this->assertSession()->fieldExists('Environment Indicator');
    $this->assertSession()->fieldExists('Enable custom error page');
    $this->assertSession()->fieldExists('Error page content');

    // Assert default values.
    $this->assertSession()->fieldValueEquals('edit-dev-mode', FALSE);
    $this->assertSession()->fieldValueEquals('edit-remove-x-generator', FALSE);
    $this->assertSession()->fieldValueEquals('edit-hide-revision-field', FALSE);
    $this->assertSession()->fieldValueEquals('edit-environment-indicator', '');
    $this->assertSession()->fieldValueEquals('edit-error-page', FALSE);

    // Submit form.
    $edit = [];

    $edit['dev_mode']              = TRUE;
    $edit['remove_x_generator']    = TRUE;
    $edit['hide_revision_field']   = TRUE;
    $edit['environment_indicator'] = 'Development Environment';
    $edit['error_page']            = TRUE;
    $edit['error_page_content']    = 'Sorry! Something is wrong...';

    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    // Assert new values.
    $this->assertSession()->fieldValueEquals('edit-dev-mode', TRUE);
    $this->assertSession()->fieldValueEquals('edit-remove-x-generator', TRUE);
    $this->assertSession()->fieldValueEquals('edit-hide-revision-field', TRUE);
    $this->assertSession()->fieldValueEquals('edit-environment-indicator', 'Development Environment');
    $this->assertSession()->fieldValueEquals('edit-error-page', TRUE);
    $this->assertSession()->fieldValueEquals('edit-error-page-content', 'Sorry! Something is wrong...');

  }

  /**
   * Test hide revision field.
   *
   * @covers ::mix_form_alter
   */
  public function testHideRevisionField() {

    $addPagePath = 'node/add/page';
    $xpath = "//*[@id='edit-revision-information']";

    // Editor can see revision field by default.
    $this->drupalLogin($this->editor);
    $this->drupalGet($addPagePath);
    $this->assertSession()->elementExists('xpath', $xpath);

    // After hide_revision_field enabled, hide revision field
    // to everyone except UID 1.
    $this->config('mix.settings')->set('hide_revision_field', TRUE)->save();
    $this->drupalGet($addPagePath);
    $this->assertSession()->elementNotExists('xpath', $xpath);

    // UID 1 still can see the revision field.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet($addPagePath);
    $this->assertSession()->elementExists('xpath', $xpath);
  }

  /**
   * Test remove x generator.
   *
   * @covers ::mix_page_attachments_alter
   */
  public function testRemoveGenerator() {

    // Disable browser cache.
    $this->config('system.performance')->set('cache.page.max_age', 0)->save();

    // Default behavior.
    $this->drupalGet('');
    // X-Generator exist.
    $this->assertSession()->responseHeaderExists('X-Generator');
    $this->assertSession()->responseContains('<meta name="Generator" content="Drupal');

    // Enable "remove_x_generator".
    $this->config('mix.settings')->set('remove_x_generator', 1)->save();

    $this->drupalLogin($this->rootUser);
    // No HTTP header X-Generator.
    $this->assertSession()->responseHeaderDoesNotExist('X-Generator');
    // No meta Generator.
    $this->assertSession()->responseNotContains('<meta name="Generator" content="Drupal');
  }

}
