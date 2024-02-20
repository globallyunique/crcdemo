<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Mix custom error page..
 *
 * @group mix
 */
class MixRegisterPasswordTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mix'];

  /**
   * Test settings.
   */
  public function testRegisterForm() {

    // No password fields in default register form.
    $this->drupalGet('/user/register');
    $this->assertSession()->fieldNotExists('Password');
    $this->assertSession()->fieldNotExists('Confirm password');

    // Enable register password.
    $this->config('mix.settings')->set('register_password', TRUE)->save();
    Cache::invalidateTags(['config:core.entity_form_display.user.user.register']);

    // Password fields show in register form.
    $this->drupalGet('/user/register');
    $this->assertSession()->fieldExists('Password');
    $this->assertSession()->fieldExists('Confirm password');

    // Create new account.
    $edit = [
      'mail' => 'test@example.com',
      'name' => 'test',
      'mix_register_password[pass1]' => 'test',
      'mix_register_password[pass2]' => 'test',
    ];
    $this->submitForm($edit, 'Create new account');
    $this->assertSession()->statusCodeEquals(200);

    // Login new account.
    $edit = [
      'name' => 'test',
      'pass' => 'test',
    ];
    $this->submitForm($edit, 'Log in');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Member for');
  }

}
