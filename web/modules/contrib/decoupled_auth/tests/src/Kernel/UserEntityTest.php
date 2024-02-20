<?php

namespace Drupal\Tests\decoupled_auth\Kernel;

use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\Tests\decoupled_auth\DecoupledAuthUserCreationTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\Validation\Constraint\UserNameConstraint;
use Drupal\user\UserInterface;

/**
 * Tests the user entity class and modifications made by decoupled auth.
 *
 * @group decoupled_auth
 * @see \Drupal\decoupled_auth\Entity\User
 */
class UserEntityTest extends KernelTestBase {

  use DecoupledAuthUserCreationTrait;
  use UserCreationTrait;

  /**
   * Create an unsaved decoupled user.
   *
   * @var bool
   */
  const UNSAVED_USER_DECOUPLED = TRUE;

  /**
   * Create an unsaved coupled user.
   *
   * @var bool
   */
  const UNSAVED_USER_COUPLED = FALSE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['decoupled_auth', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['decoupled_auth']);
    $this->installEntitySchema('user');
    $this->installSchema('system', 'sequences');
  }

  /**
   * Get validation messages from unsaved user object.
   *
   * @param \Drupal\decoupled_auth\Entity\DecoupledAuthUser $user
   *   User entity being validated.
   *
   * @return array
   *   Array of message template strings.
   */
  protected function getUserValidation(DecoupledAuthUser $user) {
    $violations = $user->validate();
    $messages = [];
    foreach ($violations as $violation) {
      $messages[] = $violation->getMessageTemplate();
    }
    return $messages;
  }

  /**
   * Tests some of the Classes that replace core User classes.
   */
  public function testUserClasses() {
    // Get hold of our user type definition.
    $manager = $this->container->get('entity_type.manager');
    $user_type = $manager->getDefinition('user');

    $this->assertEquals('Drupal\decoupled_auth\Entity\DecoupledAuthUser', $user_type->getClass(), 'User class is decoupled_auth class.');
    $this->assertEquals('Drupal\decoupled_auth\DecoupledAuthUserStorageSchema', $user_type->getHandlerClass('storage_schema'), 'User storage schema class is decoupled_auth class.');

    // Uninstall decoupled auth to check module removal.
    $this->disableModules(['decoupled_auth']);

    $manager = $this->container->get('entity_type.manager');
    $user_type = $manager->getDefinition('user');

    $this->assertEquals('Drupal\user\Entity\User', $user_type->getClass(), 'User class is decoupled_auth class.');
    $this->assertEquals('Drupal\user\UserStorageSchema', $user_type->getHandlerClass('storage_schema'), 'User storage schema class is decoupled_auth class.');
  }

  /**
   * Test the username validation for decoupled users.
   */
  public function testUserNameValidationDecoupled() {
    // Test that a decoupled user doesn't fail username validation.
    $user = $this->createUnsavedUser(TRUE);
    $violations = $user->validate();
    $this->assertEmpty($violations);
  }

  /**
   * Test the username validation for coupled users.
   */
  public function testUserNameValidationCoupled() {
    // Test that a coupled user is subject to all the normal validation.
    $user = $this->createUnsavedUser(TRUE);
    $constraint = new UserNameConstraint();

    // Beginning with a space.
    $user->setUsername(' space begin');
    $messages = $this->getUserValidation($user);
    $this->assertTrue(in_array($constraint->spaceBeginMessage, $messages), 'Failed spaceBeing validation.');

    // Ending with a space.
    $user->setUsername('space end ');
    $messages = $this->getUserValidation($user);
    $this->assertTrue(in_array($constraint->spaceEndMessage, $messages), 'Failed spaceEnd validation.');

    // Multiple consecutive spaces.
    $user->setUsername('multiple  spaces');
    $messages = $this->getUserValidation($user);
    $this->assertTrue(in_array($constraint->multipleSpacesMessage, $messages), 'Failed multipleSpaces validation.');

    // Illegal characters.
    $user->setUsername('\x{AD}');
    $messages = $this->getUserValidation($user);
    $this->assertTrue(in_array($constraint->illegalMessage, $messages), 'Failed illegal validation.');

    // Too long.
    $user->setUsername($this->randomString(UserInterface::USERNAME_MAX_LENGTH + 2));
    $messages = $this->getUserValidation($user);
    $this->assertTrue(in_array($constraint->tooLongMessage, $messages), 'Failed length validation.');
  }

  /**
   * Tests state getters for users.
   */
  public function testStateGetters() {
    $anonymous_hardening = version_compare(\Drupal::VERSION, '10.1', '>=');

    // Create a coupled user.
    $user = $this->createUnsavedUser(FALSE);

    $this->assertTrue($user->isCoupled());
    $this->assertFalse($user->isDecoupled());
    $this->assertFalse($user->isAuthenticated());
    $anonymous_hardening ? $this->assertFalse($user->isAnonymous()) : $this->assertTrue($user->isAnonymous());

    // Save the user.
    $user->save();

    $this->assertTrue($user->isCoupled());
    $this->assertFalse($user->isDecoupled());
    $this->assertTrue($user->isAuthenticated());
    $this->assertFalse($user->isAnonymous());

    // Create a decoupled user.
    $user = $this->createUnsavedUser(TRUE);

    $this->assertFalse($user->isCoupled());
    $this->assertTrue($user->isDecoupled());
    $this->assertFalse($user->isAuthenticated());
    $anonymous_hardening ? $this->assertFalse($user->isAnonymous()) : $this->assertTrue($user->isAnonymous());

    // Save the user.
    $user->save();

    $this->assertFalse($user->isCoupled());
    $this->assertTrue($user->isDecoupled());
    $this->assertFalse($user->isAuthenticated());
    $this->assertFalse($user->isAnonymous());

    // Get the anonymous user.
    $user = User::getAnonymousUser();

    $this->assertFalse($user->isCoupled());
    $this->assertTrue($user->isDecoupled());
    $this->assertFalse($user->isAuthenticated());
    $this->assertTrue($user->isAnonymous());
  }

}
