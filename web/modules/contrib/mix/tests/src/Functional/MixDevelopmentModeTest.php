<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the dev_mode config.
 *
 * @group mix
 */
class MixDevelopmentModeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'ajax_test', 'error_test', 'mix'];

  /**
   * Path of main test page.
   *
   * @var string
   */
  protected $testPath = '/ajax-test/insert-block-wrapper';

  /**
   * Path of error test page.
   *
   * @var string
   */
  protected $errorTestPath = '/error-test/generate-warnings';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable page caching and CSS/JS aggregation.
    $this->config('system.performance')
      ->set('cache.page.max_age', 3600)
      ->set('css.preprocess', 1)
      ->set('js.preprocess', 1)
      ->save();

    // Disable error message display.
    $this->config('system.logging')
      ->set('error_level', ERROR_REPORTING_HIDE)
      ->save();

    // Disable cacheablility header debug.
    $this->setContainerParameter('http.response.debug_cacheability_headers', FALSE);
    $this->rebuildContainer();
    $this->resetAll();
  }

  /**
   * Test development mode.
   *
   * @covers Drupal\mix\EventSubscriber\MixSubscriber
   * @covers Drupal\mix\MixServiceProvider
   */
  public function testDevelopmentMode() {

    // Before enable development mode.
    // Assert anonymous user.
    $this->assertAnonUserWithDevelopmentModeDisabled();

    // Login as root user.
    $this->drupalLogin($this->rootUser);

    // Assert authenticate user.
    $this->assertAuthUserWithDevelopmentModeDisabled();

    // Enable development mode.
    $this->setDevMode(TRUE);

    // After enable development mode.
    $this->assertAuthUserWithDevelopmentModeEnabled();

    // Disabled development mode (switch to Prod mode).
    $this->setDevMode(FALSE);

    // After disable development mode.
    $this->assertAuthUserWithDevelopmentModeDisabled();

    // Logout.
    $this->drupalLogout();

    // Assert anonymous user.
    $this->assertAnonUserWithDevelopmentModeDisabled();
  }

  /**
   * Change dev mode and rebulid container.
   */
  private function setDevMode($isDevMode) {
    // Emulate set dev_mode in UI.
    $this->drupalGet('admin/config/mix');
    $edit = [];
    $edit['dev_mode'] = $isDevMode;
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    // Rebuild container to refresh twig config.
    $this->rebuildContainer();
  }

  /**
   * Assert anonymous user with development mode disabled.
   */
  private function assertAnonUserWithDevelopmentModeDisabled() {
    // First visit.
    $pageHtml = $this->drupalGet($this->testPath);
    // No caches.
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache', 'Miss');
    $this->assertSession()->responseHeaderContains('X-Drupal-Dynamic-Cache', 'Miss');
    // No twig debug info.
    $this->assertStringNotContainsString('<!-- THEME DEBUG -->', $pageHtml, 'Twig debug markup not found in page source code when development mode is not enabled.');
    // CSS/JS aggregated.
    $this->assertSession()->elementNotExists('xpath', '//script[contains(@src, "/core/misc/drupal.js")]');
    $this->assertSession()->elementNotExists('xpath', '//link[contains(@href, "/core/modules/system/css/components/js.module.css")]');
    // No dev mode message.
    $this->assertSession()->linkNotExists('Go online.');
    // No cacheability headers.
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache-Contexts');
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache-Tags');
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache-Max-Age');
    // Second visit.
    $this->drupalGet($this->testPath);
    // Page cache hit.
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache', 'Hit');
    // No dynamic page cache for anonymous user.
    $this->assertSession()->responseHeaderContains('X-Drupal-Dynamic-Cache', 'Miss');
    // No error message.
    $this->drupalGet($this->errorTestPath);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('<pre class="backtrace">');
  }

  /**
   * Assert authenticate user with development mode disabled.
   */
  private function assertAuthUserWithDevelopmentModeDisabled() {

    // First visit.
    $pageHtml = $this->drupalGet($this->testPath);
    // Twig debug and auto_reload is disabled.
    $this->assertFalse($this->container->get('twig')->isDebug());
    $this->assertFalse($this->container->get('twig')->isAutoReload());
    // No twig debug info.
    $this->assertStringNotContainsString('<!-- THEME DEBUG -->', $pageHtml, 'Twig debug markup not found in page source code when development mode is not enabled.');
    // No page cache header for authenticated user.
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache');
    // Dynamic page cache MISS on first visit.
    $this->assertSession()->responseHeaderContains('X-Drupal-Dynamic-Cache', 'Miss');
    // CSS/JS aggregated.
    $this->assertSession()->elementNotExists('xpath', '//script[contains(@src, "/core/misc/drupal.js")]');
    $this->assertSession()->elementNotExists('xpath', '//link[contains(@href, "/core/modules/system/css/components/js.module.css")]');
    // No dev mode message.
    $this->assertSession()->linkNotExists('Go online.');
    // No cacheability headers.
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache-Contexts');
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache-Tags');
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache-Max-Age');
    // Second visit.
    $this->drupalGet($this->testPath);
    $assertSession = $this->assertSession();
    // No page cache for authenticated user.
    $assertSession->responseHeaderDoesNotExist('X-Drupal-Cache');
    // Dynamic page cache HIT on second visit.
    $assertSession->responseHeaderContains('X-Drupal-Dynamic-Cache', 'Hit');
    // No error message.
    $this->drupalGet($this->errorTestPath);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('<pre class="backtrace">');
  }

  /**
   * Assert authenticate user with development mode enabled.
   */
  private function assertAuthUserWithDevelopmentModeEnabled() {
    // First visit.
    $pageHtml = $this->drupalGet($this->testPath);
    // Twig debug and auto_reload is enabled.
    $this->assertTrue($this->container->get('twig')->isDebug());
    $this->assertTrue($this->container->get('twig')->isAutoReload());
    // Twig debug enabled.
    $this->assertStringContainsString('<!-- THEME DEBUG -->', $pageHtml, 'Twig debug markup found in page source code when development mode is enabled.');
    // No dynamic page cache.
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Dynamic-Cache');
    // No page cache.
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache');
    $this->assertSession()->responseHeaderContains('Cache-Control', 'must-revalidate, no-cache, private');
    // CSS/JS not aggregated.
    $this->assertSession()->elementExists('xpath', '//script[contains(@src, "/core/misc/drupal.js")]');
    $this->assertSession()->elementExists('xpath', '//link[contains(@href, "/core/modules/system/css/components/js.module.css")]');
    // Dev mode message.
    $this->assertSession()->linkExists('Go online.');
    // Cacheability headers exist.
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Contexts');
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Tags');
    $this->assertSession()->responseHeaderExists('X-Drupal-Cache-Max-Age');

    // Second visit.
    $pageHtml = $this->drupalGet($this->testPath);
    // Twig debug still enabled.
    $this->assertStringContainsString('<!-- THEME DEBUG -->', $pageHtml, 'Twig debug markup found in page source code when development mode is enabled.');
    // CSS/JS still not aggregated.
    $this->assertStringContainsString('/core/modules/system/css/components/js.module.css', $pageHtml, 'js.module.css not found in page source code when development mode is enabled.');
    $this->assertStringContainsString('/core/misc/drupal.js', $pageHtml, 'drupal.js not found in page source code when development mode is enabled.');
    // Still no dynamic page cache.
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Dynamic-Cache');
    // Still no page cache.
    $this->assertSession()->responseHeaderDoesNotExist('X-Drupal-Cache');
    // Error message shows.
    $this->drupalGet($this->errorTestPath);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<pre class="backtrace">');
  }

}
