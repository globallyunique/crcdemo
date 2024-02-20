<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Show form id.
 *
 * @group mix
 */
class MixShowFormIdTest extends BrowserTestBase {

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
  protected static $modules = ['mix'];

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
    $this->state = $this->container->get('state');
  }

  /**
   * Test Show form id.
   *
   * @covers ::mix_form_alter
   */
  public function testShowFormId() {

    // No form id by default.
    $this->drupalGet('/user/login');
    $this->assertSession()->pageTextNotContains('hook_form_FORM_ID_alter');

    $this->state->set('mix.show_form_id', TRUE);
    $this->rebuildAll();

    // Show form id and template.
    $this->drupalGet('/user/login');
    $this->assertSession()->pageTextContains('hook_form_FORM_ID_alter');

  }

}
