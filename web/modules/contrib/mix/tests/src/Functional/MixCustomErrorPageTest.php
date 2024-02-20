<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Tests\node\Functional\NodeTestBase;

/**
 * Tests Mix custom error page..
 *
 * @group mix
 */
class MixCustomErrorPageTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mix'];

  /**
   * Test settings form.
   *
   * @covers Drupal\mix\Controller\MixUnexpectedErrorPageController
   * @covers Drupal\mix\EventSubscriber\MixExceptionHtmlSubscriber
   */
  public function testCustomErrorPage() {

    $testPage = '/system/500/mix';

    // Test default error page.
    $defaultErrorMessage = 'The website encountered an unexpected error. Please try again later.';
    $this->drupalGet($testPage);
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSession()->pageTextContains($defaultErrorMessage);

    // Enable custom error page.
    $this->config('mix.settings')->set('error_page.mode', 1)->save();
    // Test custom error page with default content.
    $this->drupalGet($testPage);
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSession()->pageTextNotContains($defaultErrorMessage);
    $this->assertSession()->pageTextContains('Oops! Unexpected error...');

    // Change custom error page content.
    $customErrorContent = 'Sorry! some thing is wrong...';
    $this->config('mix.settings')->set('error_page.content', $customErrorContent)->save();
    // Test custom error page with updated content.
    $this->drupalGet($testPage);
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSession()->pageTextContains($customErrorContent);

    // Disable custom error page.
    $this->config('mix.settings')->set('error_page.mode', 0)->save();
    // Test default error page.
    $this->drupalGet($testPage);
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSession()->pageTextContains($defaultErrorMessage);
  }

}
