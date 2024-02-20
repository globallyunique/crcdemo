<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Tests Mix standalone change password page.
 *
 * @group mix
 */
class MixStandalonePasswordPageTest extends BrowserTestBase {

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

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
  public function testStandalonePasswordPage() {

    $this->drupalLogin($this->rootUser);

    // Test configuration field.
    $this->drupalGet('/admin/config/mix');
    $this->assertSession()->fieldExists('Enable "Standalone change password page"');

    // Enable standalone password page.
    $config = \Drupal::configFactory()->getEditable('mix.settings');
    $config->set('standalone_password_page', TRUE)->save();
    $this->resetAll();

    // Switch to a normal user.
    $authUser = User::create([
      'uid' => 2,
      'name' => 'original_username',
      'mail' => 'original_email@example.com',
      'pass' => 'original_password',
      'status' => 1,
    ]);
    $authUser->passRaw = 'original_password';
    $authUser->save();
    $this->drupalLogin($authUser);

    // Test to change other user's password.
    $this->drupalGet('/user/1/password');
    $this->assertSession()->statusCodeEquals(403);

    // Test /user/{user}/password.
    $this->drupalGet('/user/' . $authUser->id() . '/password');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('Current password');
    $this->assertSession()->fieldExists('Password');

    // Test change password without current password.
    $edit = ['pass[pass1]' => 'newpassword', 'pass[pass2]' => 'newpassword'];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('Current password field is required.');

    // Test change password with a wrong current password.
    $edit = [
      'current_pass' => 'wrong_password',
      'pass[pass1]' => 'newpassword',
      'pass[pass2]' => 'newpassword',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('The current password is missing or incorrect');

    // Test change password with current password.
    $edit = [
      'current_pass' => 'original_password',
      'pass[pass1]' => 'newpassword',
      'pass[pass2]' => 'newpassword',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('The password has been changed.');

    // Test reset destination.
    $this->drupalLogout();
    $this->drupalGet('user/password');
    $edit = ['name' => $authUser->getAccountName()];
    $this->submitForm($edit, 'Submit');
    $resetUrl = $this->getResetUrl();
    // Verify a password reset link will automatically log a user when /login is
    // appended.
    $this->drupalGet($resetUrl . '/login');
    $this->assertSession()->titleEquals('Change password | Drupal');
    $this->assertSession()->fieldNotExists('Current password');
    $this->assertSession()->fieldExists('Password');
    $this->assertSession()->fieldExists('Confirm password');
    $edit = ['pass[pass1]' => 'newpassword', 'pass[pass2]' => 'newpassword'];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('The password has been changed.');

    // Disable standalone password page.
    $config = \Drupal::configFactory()->getEditable('mix.settings');
    $config->set('standalone_password_page', FALSE)->save();
    $this->resetAll();

    // Test /user/{user}/password page, not found now.
    $this->drupalGet('/user/' . $authUser->id() . '/password');
    $this->assertSession()->statusCodeEquals(404);

    // Test reset link redirect user back to /user/{user}/edit.
    $this->drupalLogout();
    $this->drupalGet('user/password');
    $edit = ['name' => $authUser->getAccountName()];
    $this->submitForm($edit, 'Submit');
    $resetUrl = $this->getResetUrl();
    $this->drupalGet($resetUrl . '/login');
    $this->assertSession()->titleEquals('original_username | Drupal');
  }

  /**
   * Retrieves password reset email and extracts the login link.
   */
  public function getResetUrl() {
    // Assume the most recent email.
    $_emails = $this->drupalGetMails();
    $email = end($_emails);
    $urls = [];
    preg_match('#.+user/reset/.+#', $email['body'], $urls);

    return $urls[0];
  }

}
