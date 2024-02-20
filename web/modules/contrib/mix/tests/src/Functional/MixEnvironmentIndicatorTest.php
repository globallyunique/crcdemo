<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Environment indicator.
 *
 * @group mix
 */
class MixEnvironmentIndicatorTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Configuration administrator user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $configAdmin;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['test_page_test', 'mix'];

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Disable page cache.
    $this->config('system.performance')
      ->set('cache.page.max_age', 0)
      ->save();

    $this->state = $this->container->get('state');

    // Create users.
    $this->configAdmin = $this->drupalCreateUser([
      'administer site configuration',
    ]);
  }

  /**
   * Test environment indicator.
   *
   * @covers ::mix_page_top
   */
  public function testEnvironmentIndicator() {

    // No Mix environment indicator by default.
    $testPage = '/test-page';
    $xpath = "//div[@id='mix-environment-indicator']";
    $this->drupalGet($testPage);
    $this->assertSession()->elementNotExists('xpath', $xpath);

    // Set environment indicator text.
    $text = 'Development Indicator';
    $this->setEnvironmentIndicator($text);

    // Mix environment indicator shows up.
    $this->drupalGet($testPage);
    $this->assertSession()->elementExists('xpath', $xpath);
    $this->assertSession()->elementTextEquals('xpath', $xpath . '/text()', $text);
    // No edit link for account without permission.
    $this->assertSession()->linkNotExists('Edit');

    // Show edit link for configAdmin.
    $this->drupalLogin($this->configAdmin);
    $this->assertSession()->linkExists('Edit');

    // Clear text to hide the indicator.
    $this->setEnvironmentIndicator('');
    $this->drupalGet($testPage);
    $this->assertSession()->elementNotExists('xpath', $xpath);
  }

  /**
   * Help function to set value of environment indicator.
   *
   * @param string $text
   *   Environment indicator text.
   */
  private function setEnvironmentIndicator($text) {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/admin/config/mix');
    $this->submitForm(['environment_indicator' => $text], 'Save configuration');
    $this->drupalLogout();
  }

}
