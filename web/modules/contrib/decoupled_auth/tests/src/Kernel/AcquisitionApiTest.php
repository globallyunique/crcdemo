<?php

namespace Drupal\Tests\decoupled_auth\Kernel;

use Drupal\decoupled_auth\AcquisitionEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\Tests\decoupled_auth\DecoupledAuthUserCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\decoupled_auth\AcquisitionServiceInterface;

/**
 * Tests the Acquisition service.
 *
 * @coversDefaultClass \Drupal\decoupled_auth\AcquisitionService
 * @group decoupled_auth
 */
class AcquisitionApiTest extends KernelTestBase {

  use DecoupledAuthUserCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
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
   * Test acquiring a user via a unique email.
   *
   * @covers ::acquire
   */
  public function testAcquireBasic() {
    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');

    // Create the user we will attempt to acquire.
    $user = $this->createUser();

    // Run our acquisition.
    $values = ['mail' => $user->getEmail()];
    $acquired_user = $acquisition->acquire($values, ['name' => 'decoupled_auth_acquisition_test'], $method);

    // Check the result.
    if (!$acquired_user) {
      $this->fail('Failed to acquire user.');
    }
    else {
      $this->assertEquals('acquire', $method, 'Successfully acquired user.');
      $this->assertEquals($user->id(), $acquired_user->id(), 'Acquired correct user.');
    }
  }

  /**
   * Test the behaviour when there are multiple users with the same email.
   *
   * @covers ::acquire
   */
  public function testAcquireMultiple() {
    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');

    // Create our users.
    $user_1 = $this->createUser();
    $this->createDecoupledUser($user_1->getAccountName());

    // Set up our values for acquiring.
    $values = ['mail' => $user_1->getEmail()];

    // First try without the default behaviors - we expect $user_1 to be
    // acquired as it is the only coupled user.
    $acquired_user = $acquisition->acquire($values, ['name' => 'decoupled_auth_acquisition_test'], $method);
    if (!$acquired_user) {
      $this->fail('Failed to acquire user.');
    }
    else {
      $this->assertEquals('acquire', $method, 'Successfully acquired user.');
      $this->assertEquals($user_1->id(), $acquired_user->id(), 'Acquired correct user.');
    }

    // Next try with no behaviors. We expect no user.
    $acquired_user = $acquisition->acquire($values, ['behavior' => NULL], $method);
    $this->assertNull($acquired_user, 'Unable to acquire a user.');

    // Finally try with first match behavior. We expect either to be acquired.
    $acquired_user = $acquisition->acquire($values, ['behavior' => AcquisitionServiceInterface::BEHAVIOR_FIRST], $method);
    if (!$acquired_user) {
      $this->fail('Failed to acquire user.');
    }
    else {
      $this->assertEquals('acquire', $method, 'Successfully acquired user.');
      $this->assertEquals($user_1->getEmail(), $acquired_user->getEmail(), 'Acquired correct user.');
    }
  }

  /**
   * Test the create behavior.
   *
   * @covers ::acquire
   */
  public function testAcquireCreate() {
    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');

    $email = $this->randomMachineName() . '@example.com';
    $values = ['mail' => $email];

    // Check that user is created by default.
    $context = ['name' => 'decoupled_auth_acquisition_test'];
    $acquired_user_1 = $acquisition->acquire($values, $context, $method);

    if (!$acquired_user_1) {
      $this->fail('Failed to create user.');
    }
    else {
      $this->assertEquals('create', $method, 'Successfully created user.');
    }

    // Remove default behavior and check that no user is created.
    $context['behavior'] = NULL;
    $acquired_user_2 = $acquisition->acquire($values, $context, $method);
    $this->assertNull($method, 'Acquisition preformed no action.');
    $this->assertNull($acquired_user_2, 'No user acquired without BEHAVIOR_CREATE.');
  }

  /**
   * Test the behavior when status conditions.
   *
   * @covers ::acquire
   */
  public function testAcquireStatusCondition() {
    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');

    $active_user = $this->createUser();
    $email = $active_user->getEmail();

    $inactive_user = DecoupledAuthUser::create([
      'mail' => $email,
      'name' => $this->randomMachineName(),
      'status' => 0,
    ]);
    $inactive_user->save();

    // Test acquisition with no status provided.
    $values = ['mail' => $email];
    // phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
    $context = ['name' => 'decoupled_auth_acquisition_test', 'behavior' => NULL];
    $acquired_user = $acquisition->acquire($values, $context, $method);

    if ($acquired_user) {
      $this->fail('Found one user when should have been multiple.');
    }
    else {
      $this->assertNull($acquired_user, 'No user acquired.');
      $this->assertEquals($acquisition::FAIL_MULTIPLE_MATCHES, $acquisition->getFailCode(), 'Both active and inactive users found.');
    }

    // Test acquisition with active status provided.
    $values['status'] = 1;
    $acquired_user = $acquisition->acquire($values, $context, $method);

    if (!$acquired_user) {
      $this->fail('Failed to create user.');
    }
    else {
      $this->assertEquals($active_user->id(), $acquired_user->id(), 'Active user acquired.');
    }

    // Test acquisition with inactive status provided.
    $values['status'] = 0;
    $acquired_user = $acquisition->acquire($values, $context, $method);

    if (!$acquired_user) {
      $this->fail('Failed to create user.');
    }
    else {
      $this->assertEquals($inactive_user->id(), $acquired_user->id(), 'Active user acquired.');
    }
  }

  /**
   * Test acquisitions with role conditions.
   *
   * @covers ::acquire
   */
  public function testAcquireRoleConditions() {
    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');

    $user = $this->createUser();
    $rid = $this->createRole([]);

    // Test acquisition with no administrator users.
    $values = ['roles' => $rid];
    // phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
    $context = ['name' => 'decoupled_auth_acquisition_test', 'behavior' => NULL];
    $acquired_user = $acquisition->acquire($values, $context, $method);

    $this->assertNull($acquired_user, 'No user acquired.');
    $this->assertEquals($acquisition::FAIL_NO_MATCHES, $acquisition->getFailCode(), 'Both active and inactive users found.');

    // Make user an administrator.
    $user->addRole($rid);
    $user->save();

    // Test acquisition with an administrator user.
    $acquired_user = $acquisition->acquire($values, $context, $method);

    if (!$acquired_user) {
      $this->fail('Failed to acquire user.');
    }
    else {
      $this->assertTrue($user->hasRole($rid), 'Acquired user has administrator role.');
      $this->assertEquals($user->id(), $acquired_user->id(), 'Administrator user acquired.');
    }
  }

  /**
   * Test the default configuration.
   *
   * @covers ::acquire
   */
  public function testDefaultConfig() {
    // Add event listener to capture the context.
    $compiled_context = NULL;
    $this->container->get('event_dispatcher')->addListener(AcquisitionEvent::PRE, function (AcquisitionEvent $event) use (&$compiled_context) {
      $compiled_context = $event->getContext();
    }, -999);

    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');
    $acquisition->acquire(['mail' => 'foo@bar.com'], ['name' => 'first'], $method);
    $expected = AcquisitionServiceInterface::BEHAVIOR_CREATE | AcquisitionServiceInterface::BEHAVIOR_PREFER_COUPLED;
    $this->assertEquals($expected, $compiled_context['behavior'], 'Default configuration sets the correct default behavior');
    $this->assertEquals('AND', $compiled_context['conjunction']);
  }

  /**
   * Test the configuration.
   *
   * @covers ::acquire
   */
  public function testUpdatedConfig() {
    // Add event listener to capture the context.
    $compiled_context = NULL;
    $this->container->get('event_dispatcher')->addListener(AcquisitionEvent::PRE, function (AcquisitionEvent $event) use (&$compiled_context) {
      $compiled_context = $event->getContext();
    }, -999);

    $this->config('decoupled_auth.settings')
      ->set('acquisitions.behavior_first', 1)
      ->save();

    // Check our updated configuration.
    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');
    $acquisition->acquire(['mail' => 'foo@bar.com'], ['name' => 'first'], $method);
    $expected = AcquisitionServiceInterface::BEHAVIOR_CREATE | AcquisitionServiceInterface::BEHAVIOR_PREFER_COUPLED | AcquisitionServiceInterface::BEHAVIOR_FIRST;
    $this->assertEquals($expected, $compiled_context['behavior'], 'Enabling first match configuration sets the correct default behavior');
  }

  /**
   * Test event subscribers.
   *
   * @see \Drupal\decoupled_auth_event_test\EventSubscriber\DecoupledAuthEventTestSubscriber
   *
   * @covers ::acquire
   */
  public function testAcquireEventSubscribers() {
    $this->enableModules(['decoupled_auth_event_test']);

    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');

    // Add event listener to capture the context.
    $compiled_context = NULL;
    $this->container->get('event_dispatcher')->addListener(AcquisitionEvent::POST, function (AcquisitionEvent $event) use (&$compiled_context) {
      $compiled_context = $event->getContext();
    }, -999);

    $context = ['name' => 'decoupled_auth_acquisition_test'];
    $acquisition->acquire([], $context, $method);

    $this->assertTrue($compiled_context['testEventPre']);
    $this->assertTrue($compiled_context['testEventPost']);
  }

  /**
   * Test altering of acquisition queries.
   *
   * @covers ::findMatch
   */
  public function testAcquireQueryAlter() {
    $this->enableModules(['decoupled_auth_event_test']);

    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');

    $user = $this->createUser();

    // Run without expecting a query alter (to verify the following tests are
    // true failures to acquire).
    $context = ['name' => 'decoupled_auth_acquisition_test'];
    $acquired_user = $acquisition->acquire(['mail' => $user->getEmail()], $context, $method);
    if (!$acquired_user) {
      $this->fail('Failed to acquire user.');
    }
    else {
      $this->assertEquals($user->id(), $acquired_user->id(), 'Successfully acquired correct user.');
    }

    // Run with a generic query alter.
    $context = $context + ['_query_alter' => 'generic'];
    $acquisition->acquire(['mail' => $user->getEmail()], $context, $method);
    $this->assertEquals($acquisition::FAIL_NO_MATCHES, $acquisition->getFailCode(), 'Correctly found no matches with a generic query alter.');

    // Run with a specific query alter.
    $context = $context + ['_query_alter' => 'specific'];
    $acquisition->acquire(['mail' => $user->getEmail()], $context, $method);
    $this->assertEquals($acquisition::FAIL_NO_MATCHES, $acquisition->getFailCode(), 'Correctly found no matches with a specifc query alter.');
  }

  /**
   * Tests AcquisitionServiceInterface::BEHAVIOR_INCLUDE_PROTECTED_ROLES.
   *
   * @covers ::findMatch
   */
  public function testProtectedRoles() {
    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');

    $user = $this->createUser();
    $admin_user = $this->createUser();
    $this->createAdminRole('administrator');
    $admin_user->addRole('administrator');
    $admin_user->save();

    $values = [
      'user' => ['mail' => $user->getEmail()],
      'admin_user' => ['mail' => $admin_user->getEmail()],
    ];

    // Default config - administrator protected.
    // Expected to acquire user and not admin user.
    $context = [
      'behavior' => AcquisitionServiceInterface::BEHAVIOR_FIRST,
    ];

    $acquired = [];
    foreach ($values as $key => $value) {
      $acquired[$key] = $acquisition->acquire($value, $context);
    }

    if (!$acquired['user']) {
      $this->fail('Failed to acquire user.');
    }
    else {
      $this->assertEquals($user->id(), $acquired['user']->id(), 'Successfully acquired correct user.');
    }
    $this->assertNull($acquired['admin_user']);

    // Default config + BEHAVIOR_INCLUDE_PROTECTED_ROLES behavior.
    // Expected to acquire user and admin user.
    $context = [
      'behavior' => AcquisitionServiceInterface::BEHAVIOR_FIRST | AcquisitionServiceInterface::BEHAVIOR_INCLUDE_PROTECTED_ROLES,
    ];

    $acquired = [];
    foreach ($values as $key => $value) {
      $acquired[$key] = $acquisition->acquire($value, $context);
    }

    if (!$acquired['user']) {
      $this->fail('Failed to acquire user.');
    }
    else {
      $this->assertEquals($user->id(), $acquired['user']->id(), 'Successfully acquired correct user.');
    }
    if (!$acquired['admin_user']) {
      $this->fail('Failed to acquire admin user.');
    }
    else {
      $this->assertEquals($admin_user->id(), $acquired['admin_user']->id(), 'Successfully acquired correct user.');
    }

    // Empty config - no protected roles.
    // Expected to acquire user and admin user.
    $context = [
      'behavior' => AcquisitionServiceInterface::BEHAVIOR_FIRST,
    ];

    // Remove administrator role from protected roles.
    $settings = \Drupal::configFactory()->getEditable('decoupled_auth.settings');
    $settings->set('acquisitions.protected_roles', []);
    $settings->save();

    $acquired = [];
    foreach ($values as $key => $value) {
      $acquired[$key] = $acquisition->acquire($value, $context);
    }

    if (!$acquired['user']) {
      $this->fail('Failed to acquire user.');
    }
    else {
      $this->assertEquals($user->id(), $acquired['user']->id(), 'Successfully acquired correct user.');
    }
    if (!$acquired['admin_user']) {
      $this->fail('Failed to acquire admin user.');
    }
    else {
      $this->assertEquals($admin_user->id(), $acquired['admin_user']->id(), 'Successfully acquired correct user.');
    }
  }

  /**
   * Ensures there is no shared state to corrupt the next run.
   */
  public function testNoSharedState() {
    // Add event listener to capture the context.
    $compiled_context = NULL;
    $this->container->get('event_dispatcher')->addListener(AcquisitionEvent::PRE, function (AcquisitionEvent $event) use (&$compiled_context) {
      $compiled_context = $event->getContext();
    }, -999);

    /** @var \Drupal\decoupled_auth\AcquisitionServiceInterface $acquisition */
    $acquisition = $this->container->get('decoupled_auth.acquisition');
    $values = ['mail' => 'foo@bar.com', 'status' => NULL];

    $first = $acquisition->acquire($values, ['name' => 'first'], $method);
    $this->assertEquals('create', $method);
    $this->assertTrue($first != NULL);
    $this->assertEquals('first', $compiled_context['name']);
    $this->assertEquals('AND', $compiled_context['conjunction']);
    $this->assertEquals(AcquisitionServiceInterface::BEHAVIOR_CREATE | AcquisitionServiceInterface::BEHAVIOR_PREFER_COUPLED, $compiled_context['behavior']);

    $second = $acquisition->acquire($values, [
      'name' => 'second',
      'behavior' => AcquisitionServiceInterface::BEHAVIOR_FIRST | AcquisitionServiceInterface::BEHAVIOR_INCLUDE_PROTECTED_ROLES,
    ], $method);
    $this->assertEquals('second', $compiled_context['name']);
    $this->assertEquals('AND', $compiled_context['conjunction']);
    $this->assertEquals(AcquisitionServiceInterface::BEHAVIOR_FIRST | AcquisitionServiceInterface::BEHAVIOR_INCLUDE_PROTECTED_ROLES, $compiled_context['behavior']);

    // And re-run the first one.
    $third = $acquisition->acquire($values, ['name' => 'first'], $method);
    $this->assertEquals('create', $method);
    $this->assertTrue($third != NULL);
    $this->assertEquals('first', $compiled_context['name']);
    $this->assertEquals('AND', $compiled_context['conjunction']);
    $this->assertEquals(AcquisitionServiceInterface::BEHAVIOR_CREATE | AcquisitionServiceInterface::BEHAVIOR_PREFER_COUPLED, $compiled_context['behavior']);
  }

}
