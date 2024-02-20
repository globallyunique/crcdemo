<?php

namespace Drupal\Tests\decoupled_auth\Kernel;

use Drupal\Tests\decoupled_auth\DecoupledAuthUserCreationTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\SubscriberInterface;

/**
 * Test description.
 *
 * @group decoupled_auth
 */
class SimplenewsSubscriberTest extends KernelTestBase {

  use DecoupledAuthUserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'decoupled_auth',
    'simplenews',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('simplenews_subscriber');
  }

  /**
   * Test adding a new user doesn't result in unexpected changes.
   *
   * @dataProvider dataNewUser
   */
  public function testNewUser(bool $is_decoupled, bool $has_existing_user, bool $existing_is_decoupled, int $existing_status, bool $should_attach, int $expected_status) {
    // Generate the new user.
    $new_user = $this->createUnsavedUser($is_decoupled);

    // Generate the existing subscription.
    $subscriber = Subscriber::create([
      'status' => $existing_status,
      'mail' => $new_user->getEmail(),
    ]);

    // Generate and save the existing user.
    $expected_user = NULL;

    // If we're running on simplenews 1, the expected result is '0' not null.
    if (function_exists('simplenews_subscriber_load_by_mail')) {
      $expected_user = '0';
    }

    if ($has_existing_user) {
      $existing_user = $this->createUnsavedUser($existing_is_decoupled, $new_user->email_prefix);
      $existing_user->save();
      $subscriber->set('uid', $existing_user->id());
      $expected_user = $existing_user->id();
    }

    // Save the existing subscription.
    $subscriber->save();

    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('simplenews_subscriber');
    $storage->resetCache();
    $subscriber = $storage->loadUnchanged($subscriber->id());

    // Validate our pre insert state.
    $this->assertEquals($expected_user, $subscriber->getUserId(), 'Initially attached to correct user.');
    $this->assertEquals($existing_status, (int) $subscriber->getStatus(), 'Initially has correct status.');

    // Save the new user.
    $new_user->save();
    if ($should_attach) {
      $expected_user = $new_user->id();
    }

    // Load the subscriber.
    /** @var \Drupal\simplenews\SubscriberInterface $subscriber */
    $subscriber = $storage->loadUnchanged($subscriber->id());

    // Check our expectations.
    $this->assertSame($expected_user, $subscriber->getUserId(), 'Attached to correct user.');
    $this->assertSame($expected_status, (int) $subscriber->getStatus(), 'Has correct status.');
  }

  /**
   * Data provider for ::testNewUser.
   */
  public function dataNewUser() {
    yield 'active-unattached-decoupled' => [
      'is_decoupled' => TRUE,
      'has_existing_user' => FALSE,
      'existing_is_decoupled' => FALSE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => TRUE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'inactive-unattached-decoupled' => [
      'is_decoupled' => TRUE,
      'has_existing_user' => FALSE,
      'existing_is_decoupled' => FALSE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => TRUE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'active-decoupled-decoupled' => [
      'is_decoupled' => TRUE,
      'has_existing_user' => TRUE,
      'existing_is_decoupled' => TRUE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => FALSE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'inactive-decoupled-decoupled' => [
      'is_decoupled' => TRUE,
      'has_existing_user' => TRUE,
      'existing_is_decoupled' => TRUE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => FALSE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'active-coupled-decoupled' => [
      'is_decoupled' => TRUE,
      'has_existing_user' => TRUE,
      'existing_is_decoupled' => FALSE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => FALSE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'inactive-coupled-decoupled' => [
      'is_decoupled' => TRUE,
      'has_existing_user' => TRUE,
      'existing_is_decoupled' => FALSE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => FALSE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'active-unattached-coupled' => [
      'is_decoupled' => FALSE,
      'has_existing_user' => FALSE,
      'existing_is_decoupled' => FALSE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => TRUE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'inactive-unattached-coupled' => [
      'is_decoupled' => FALSE,
      'has_existing_user' => FALSE,
      'existing_is_decoupled' => FALSE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => TRUE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'active-decoupled-coupled' => [
      'is_decoupled' => FALSE,
      'has_existing_user' => TRUE,
      'existing_is_decoupled' => TRUE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => TRUE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];

    yield 'inactive-decoupled-coupled' => [
      'is_decoupled' => FALSE,
      'has_existing_user' => TRUE,
      'existing_is_decoupled' => TRUE,
      'existing_status' => SubscriberInterface::ACTIVE,
      'should_attach' => TRUE,
      'expected_status' => SubscriberInterface::ACTIVE,
    ];
  }

}
