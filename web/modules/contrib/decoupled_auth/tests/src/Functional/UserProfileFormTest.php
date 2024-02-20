<?php

namespace Drupal\Tests\decoupled_auth\Functional;

use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\decoupled_auth\DecoupledAuthUserCreationTrait;

/**
 * Tests the user profile form for decoupled users.
 *
 * @group decoupled_auth
 */
class UserProfileFormTest extends BrowserTestBase {

  use DecoupledAuthUserCreationTrait;
  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'decoupled_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $admin = $this->createUser(['administer users']);
    $this->drupalLogin($admin);
  }

  /**
   * Run the tests for core.
   *
   * @see \Drupal\Tests\decoupled_auth\Functional\UserPasswordFormTest::doTest
   *
   * @dataProvider dataCore
   */
  public function testCore(array $initial_values, array $form_values, array $expected_values = [], ?string $expected_error = NULL) {
    $this->doTest($initial_values, $form_values, $expected_values, $expected_error);
  }

  /**
   * Data provider for ::testCore.
   */
  public function dataCore() {
    yield 'decoupled-add-username' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => NULL,
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
        'name' => 'username',
      ],
    ];

    yield 'couple-add-username' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => NULL,
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
        'name' => 'username',
      ],
      'expected_values' => [
        'name' => 'username',
      ],
    ];

    yield 'couple-no-username' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => NULL,
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
      ],
      'expected_values' => [],
      'expected_error' => 'Error message Username field is required.',
    ];

    yield 'coupled-remove-username' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
        'name' => '',
      ],
      'expected_values' => [],
      'expected_error' => 'Error message Username field is required.',
    ];

    yield 'decouple' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
        'name' => 'username',
      ],
      'expected_values' => [
        'name' => NULL,
      ],
    ];

    yield 'decouple-remove-username' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
        'name' => '',
      ],
      'expected_values' => [
        'name' => NULL,
      ],
    ];
  }

  /**
   * Run the tests with email registration enabled.
   *
   * @see \Drupal\Tests\decoupled_auth\Functional\UserPasswordFormTest::doTest
   *
   * @dataProvider dataEmailRegistration
   */
  public function testEmailRegistration(array $initial_values, array $form_values, array $expected_values, string $expected_error = NULL) {
    try {
      $success = $this->container->get('module_installer')->install(['email_registration'], TRUE);
      $this->assertTrue($success, 'Enabled email_registration');
    }
    catch (MissingDependencyException $e) {
      // The exception message has all the details.
      $this->fail($e->getMessage());
    }

    $this->rebuildContainer();

    $this->doTest($initial_values, $form_values, $expected_values, $expected_error);
  }

  /**
   * Data provider for ::testEmailRegistration.
   */
  public function dataEmailRegistration() {
    yield 'decoupled-no-email' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => NULL,
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
      ],
      'expected_values' => [],
    ];

    yield 'decoupled-existing-email' => [
      'initial_values' => [
        'mail' => 'email@example.com',
        'name' => NULL,
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
      ],
      'expected_values' => [],
    ];

    yield 'decoupled-add-email' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => NULL,
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
        'mail' => 'email@example.com',
      ],
      'expected_values' => [
        'mail' => 'email@example.com',
      ],
    ];

    yield 'decoupled-removed-email' => [
      'initial_values' => [
        'mail' => 'email@example.com',
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
        'mail' => '',
      ],
      'expected_values' => [
        'mail' => NULL,
      ],
    ];

    yield 'coupled-with-existing-email' => [
      'initial_values' => [
        'mail' => 'email@example.com',
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
      ],
      'expected_values' => [],
    ];

    yield 'coupled-no-email' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
      ],
      'expected_values' => [],
      'expected_error' => 'Error message Email field is required.',
    ];

    yield 'coupled-add-email' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
        'mail' => 'email@example.com',
      ],
      'expected_values' => [
        'mail' => 'email@example.com',
      ],
    ];

    yield 'coupled-remove-email' => [
      'initial_values' => [
        'mail' => 'email@example.com',
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
        'mail' => '',
      ],
      'expected_values' => [],
      'expected_error' => 'Error message Email field is required.',
    ];

    yield 'couple-no-email' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => NULL,
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
      ],
      'expected_values' => [],
      'expected_error' => 'Error message Email field is required. Username field is required.',
    ];

    yield 'couple-existing-email' => [
      'initial_values' => [
        'mail' => 'email@example.com',
        'name' => NULL,
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
      ],
      'expected_values' => [],
      'expected_error' => 'Error message Username field is required.',
    ];

    yield 'couple-add-email' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
        'mail' => 'email@example.com',
      ],
      'expected_values' => [
        'mail' => 'email@example.com',
        'name' => TRUE,
      ],
    ];

    yield 'couple-remove-email' => [
      'initial_values' => [
        'mail' => 'email@example.com',
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => TRUE,
        'mail' => '',
      ],
      'expected_values' => [],
      'expected_error' => 'Error message Email field is required.',
    ];

    yield 'decouple-no-email' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
      ],
      'expected_values' => [
        'name' => NULL,
      ],
    ];

    yield 'decouple-existing-email' => [
      'initial_values' => [
        'mail' => 'email@example.com',
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
      ],
      'expected_values' => [
        'name' => NULL,
      ],
    ];

    yield 'decouple-add-email' => [
      'initial_values' => [
        'mail' => NULL,
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
        'mail' => 'email@example.com',
      ],
      'expected_values' => [
        'mail' => 'email@example.com',
        'name' => NULL,
      ],
    ];

    yield 'decouple-remove-email' => [
      'initial_values' => [
        'mail' => 'email@example.com',
        'name' => 'username',
      ],
      'form_values' => [
        'not_decoupled' => FALSE,
        'mail' => '',
      ],
      'expected_values' => [
        'mail' => NULL,
        'name' => NULL,
      ],
    ];
  }

  /**
   * Run the user edit form scenario.
   *
   * @param array $initial_values
   *   The initial values for the user entity.
   * @param array $form_values
   *   The form values to submit.
   * @param array|null $expected_values
   *   The expected values for the resulting entity. Keys not provided that
   *   exist in the initial values will be checked to see that they have not
   *   changed. Values are:
   *   - NULL: Field should be empty.
   *   - TRUE: Field should not be empty (any value is allowed).
   *   - array: A full FieldItemListInterface::getValue() to match.
   *   - anything else: A match for the main property of the first value.
   * @param string|null $expected_error
   *   The expected error message, or NULL if there should be none.
   */
  protected function doTest(array $initial_values, array $form_values, array $expected_values = [], ?string $expected_error = NULL) {
    $storage = $this->container->get('entity_type.manager')->getStorage('user');

    /** @var \Drupal\user\UserInterface $user */
    $user = $storage->create($initial_values);
    $user->save();

    // Load our edit form.
    $this->drupalGet("user/{$user->id()}/edit");
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $page = $this->getSession()->getPage();

    // Fill out our values.
    foreach ($form_values as $field => $value) {
      $input = $page->findField($field);
      $input->setValue($value);
    }

    // Submit the form.
    $page->pressButton('Save');

    // Check our resulting page.
    $page = $this->getSession()->getPage();
    $message = $page->find('css', '.messages');
    $this->assertNotEmpty($message, 'Message found');
    $this->assertTrue($message->hasClass($expected_error ? 'messages--error' : 'messages--status'), 'Message is of correct type');
    $this->assertSame($expected_error ?? 'Status message The changes have been saved.', $message->getText(), 'Message has correct text');

    $expected_values += $initial_values;
    $user = $storage->loadUnchanged($user->id());
    foreach ($expected_values as $field => $expected_value) {
      $items = $user->get($field);

      // NULL means it should be empty.
      if ($expected_value === NULL) {
        $this->assertTrue($items->isEmpty(), "{$field} is empty.");
      }
      // Otherwise we expect a value.
      else {
        $this->assertFalse($items->isEmpty(), "{$field} is not empty.");

        // An array should match FieldItemListInterface::getValue.
        if (is_array($expected_value)) {
          $this->assertSame($expected_value, $items->getValue(), "{$field} has expected value.");
        }
        // Anything else other than TRUE should match the main property of the
        // first item.
        elseif ($expected_value !== TRUE) {
          /** @var \Drupal\Core\Field\FieldItemInterface $item */
          $item = $items->first();
          $this->assertSame($expected_value, $item->{$item->mainPropertyName()}, "{$field} has expected value.");
        }
      }
    }

  }

  /**
   * Run the test for a user without any permissions editing their own profile.
   */
  public function testNoPermissionUser() {
    // Create and login as a user without permissions.
    $authenticated = $this->createUser();
    $this->drupalLogin($authenticated);

    // Load our edit form.
    $this->drupalGet("user/{$authenticated->id()}/edit");
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $page = $this->getSession()->getPage();

    // Users cannot edit their own username.
    $this->assertNull($page->findField('name'), 'Name field is hidden.');

    // Provide the current password as editing the email address requires it.
    $input = $page->findField('current_pass');
    $input->setValue($authenticated->passRaw);

    // Attempt to update the email address.
    $updated_email = 'update@example.com';
    $input = $page->findField('mail');
    $input->setValue($updated_email);

    // Submit the form.
    $page->pressButton('Save');

    // Check our resulting page.
    $page = $this->getSession()->getPage();
    $message = $page->find('css', '.messages');
    $this->assertNotEmpty($message, 'Message found');

    $this->assertTrue($message->hasClass('messages--status'), 'Message is of correct type');
    $this->assertSame('Status message The changes have been saved.', $message->getText(), 'Message has correct text');

    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $user = $storage->loadUnchanged($authenticated->id());
    $mail_value = $user->get('mail');
    $this->assertFalse($mail_value->isEmpty(), 'User email is not empty.');
    $this->assertSame('update@example.com', $mail_value->first()->value, 'User Email address was updated.');
  }

  /**
   * Runs a profile edit test for a user with edit own username permission.
   */
  public function testOwnUsernameUser() {
    // Create and login as a user with own username editing permission.
    $authenticated = $this->createUser(['change own username']);
    $this->drupalLogin($authenticated);

    // Load our edit form.
    $this->drupalGet("user/{$authenticated->id()}/edit");
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $page = $this->getSession()->getPage();

    // Users can edit their own username.
    $this->assertNotNull($page->findField('name'), 'Name field is present.');

    // Fill out our values.
    $form_values = [
      'mail' => 'update@example.com',
      'name' => 'newname',
      'current_pass' => $authenticated->passRaw,
    ];

    foreach ($form_values as $field => $value) {
      $input = $page->findField($field);
      $input->setValue($value);
    }

    // Submit the form.
    $page->pressButton('Save');

    // Check our resulting page.
    $page = $this->getSession()->getPage();
    $message = $page->find('css', '.messages');
    $this->assertNotEmpty($message, 'Message found');

    $this->assertTrue($message->hasClass('messages--status'), 'Message is of correct type');
    $this->assertSame('Status message The changes have been saved.', $message->getText(), 'Message has correct text');

    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $user = $storage->loadUnchanged($authenticated->id());

    $mail_value = $user->get('mail');
    $this->assertFalse($mail_value->isEmpty(), 'User email is not empty.');
    $this->assertSame('update@example.com', $mail_value->first()->value, 'User Email address was updated.');

    $name_value = $user->get('name');
    $this->assertFalse($name_value->isEmpty(), 'User name is not empty.');
    $this->assertSame('newname', $name_value->first()->value, 'User name was updated.');
  }

}
