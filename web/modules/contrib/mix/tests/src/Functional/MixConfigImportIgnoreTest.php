<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests config import ignore.
 *
 * @group mix
 */
class MixConfigImportIgnoreTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config', 'mix', 'help'];

  /**
   * The contents of the config export tarball, held between test methods.
   *
   * @var string
   */
  protected $tarball;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->rootUser);
    // Export current configurations.
    $this->drupalGet('admin/config/development/configuration/full/export');
    $this->submitForm([], 'Export');
    $this->tarball = $this->getSession()->getPage()->getContent();
    // Import the configuration to the site then the active configuration
    // has a compare target.
    $filename = 'temporary://' . $this->randomMachineName();
    file_put_contents($filename, $this->tarball);
    $this->drupalGet('admin/config/development/configuration/full/import');
    $this->submitForm(['files[import_tarball]' => $filename], 'Upload');
  }

  /**
   * Test config import ignore.
   *
   * @covers Drupal\mix\EventSubscriber\MixConfigImportIgnoreEventSubscriber
   */
  public function testConfigImportIgnore() {

    // No staged configration at first.
    $configPage = 'admin/config/development/configuration';
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextContains('The staged configuration is identical to the active configuration.');

    // Change site name, system.site show in the list.
    $systemSite = $this->config('system.site');
    $systemSite->set('name', $this->randomString(16))->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextContains('system.site');

    // Test ignore by [name].[key] format.
    // Enable config import ignore and ignore system.site:name. No diff.
    $mixSettings = $this->config('mix.settings');
    $mixSettings->set('config_import_ignore.mode', TRUE)->save();
    $mixSettings->set('config_import_ignore.list', ['system.site:name'])->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextNotContains('system.site');

    // Change system.site:page.front. system.site shows in list.
    $systemSite->set('page.front', '/admin')->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextContains('system.site');

    // Test ignore multi-level config.
    // Add system.site:page.front to the ignore list.
    // No diff in system.site.
    $mixSettings->set('config_import_ignore.list', [
      'system.site:name',
      'system.site:page.front',
    ])->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextNotContains('system.site');

    // Change system.site:slogan. system.site show in list.
    $systemSite->set('slogan', $this->randomString(16))->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextContains('system.site');

    // Test ignore by config name.
    // Use system.site to ignore entire system.site config.
    // No diff in system.site.
    $mixSettings->set('config_import_ignore.list', ['system.site'])->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextNotContains('system.site');

    // Enabled a module.
    $this->installModule('block');
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextContains('core.extension');

    // Test ignored module will not be disabled in the import process.
    $mixSettings->set('config_import_ignore.list', ['core.extension:module.block'])->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextNotContains('core.extension');

    // Disable a module.
    $this->uninstallModule('help');
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextContains('core.extension');

    // Test ignored module will not be enabled in the import process.
    $mixSettings->set('config_import_ignore.list', [
      'core.extension:module.block',
      'core.extension:module.help',
    ])->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextNotContains('core.extension');

    // Disable config import ignore. system.site shows.
    $mixSettings->set('config_import_ignore.mode', FALSE)->save();
    $this->drupalGet($configPage);
    $this->assertSession()->pageTextContains('system.site');

  }

  /**
   * Installs a module.
   *
   * @param string $module
   *   The module name.
   */
  protected function installModule($module) {
    $this->container->get('module_installer')->install([$module]);
    $this->container = \Drupal::getContainer();
  }

  /**
   * Uninstalls a module.
   *
   * @param string $module
   *   The module name.
   */
  protected function uninstallModule($module) {
    $this->container->get('module_installer')->uninstall([$module]);
    $this->container = \Drupal::getContainer();
  }

}
