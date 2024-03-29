<?php

namespace Drupal\Tests\contacts\Functional;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Tests CRM fields and views.
 *
 * @group contacts
 */
class ContactsDashboardTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'contacts',
  ];

  /**
   * Testing admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->adminUser = $this->createUser([], NULL, TRUE);
    // @todo update page permission requirements.
    $this->adminUser->addRole('administrator');
    $this->adminUser->save();
  }

  /**
   * Create a contact of the given type.
   *
   * @param string $type
   *   The type of contact, either 'crm_indiv' or 'crm_org'.
   * @param bool $decoupled
   *   Whether the user should be decoupled. Defaults to TRUE.
   *
   * @return \Drupal\decoupled_auth\Entity\DecoupledAuthUser
   *   The contact that was created.
   */
  protected function createContact($type, $decoupled = TRUE) {
    // Create our user.
    $name = $this->randomMachineName();
    $contact = DecoupledAuthUser::create([
      'name' => $decoupled ? NULL : $name,
      'mail' => $name . '@example.com',
      'status' => 1,
    ]);
    $contact->addRole($type);
    $contact->save();

    // Generate a random image.
    /** @var \Drupal\Core\File\FileSystemInterface $filesystem */
    $filesystem = $this->container->get('file_system');
    $tmp_file = $filesystem->tempnam('temporary://', 'contactImage_');
    $destination = $tmp_file . '.png';
    $filesystem->move($tmp_file, $destination, FileSystemInterface::CREATE_DIRECTORY);
    $path = $this->getRandomGenerator()->image($filesystem->realpath($destination), '100x100', '100x100');
    $image = File::create();
    $image->setFileUri($path);
    $image->setOwnerId($contact->id());
    $image->setMimeType($this->container->get('file.mime_type.guesser')->guessMimeType($path));
    $image->setFileName($filesystem->basename($path));
    $destination = 'public://contactImage_' . $contact->id() . '.png';
    $file = \Drupal::service('file.repository')->move($image, $destination, FileSystemInterface::CREATE_DIRECTORY);

    // Build our profile.
    switch ($type) {
      case 'crm_indiv':
        $values = [
          'type' => 'crm_indiv',
          'crm_name' => $this->randomString(20),
          'crm_gender' => 'female',
          'crm_address' => [
            'country_code' => 'GB',
            'locality' => $this->randomString(),
          ],
          'crm_photo' => $file->id(),
        ];
        break;

      case 'crm_org':
        $values = [
          'type' => 'crm_org',
          'crm_org_name' => $this->randomString(20),
          'crm_org_address' => [
            'country_code' => 'GB',
            'locality' => $this->randomString(),
          ],
          'crm_logo' => $file->id(),
        ];
        break;

      default:
        return $contact;
    }
    $values += [
      'uid' => $contact->id(),
      'status' => 1,
      'is_default' => 1,
    ];
    $profile = Profile::create($values);
    $profile->save();

    // @todo Remove when onUpdate is added.
    $contact->updateProfileFields([$type]);
    return $contact;
  }

  /**
   * Test installing contacts and accessing the contact dashboard.
   */
  public function testViewDashboard() {
    // Create some same users.
    $contacts[] = DecoupledAuthUser::load(1);
    $contacts[] = $this->adminUser;
    $contacts[] = $this->createContact('crm_indiv');
    $contacts[] = $this->createContact('crm_indiv', FALSE);
    $contacts[] = $this->createContact('crm_org');

    // Check the site has installed successfully.
    $this->drupalGet('');
    $this->assertSession()->statusCodeEquals(200);

    // Check that prior to logging in, we can't access the contacts dashboard.
    $this->drupalGet('admin/contacts/all');
    $this->assertSession()->statusCodeEquals(403);

    // Gain access to the contacts dashboard.
    $this->drupalLogin($this->adminUser);

    // Make sure our items are indexed.
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->load('contacts_index');
    $index->indexItems();

    // Check that the contacts dashboard has the expected content.
    $this->drupalGet('admin/contacts/all');
    $session = $this->assertSession();

    $session->statusCodeEquals(200);

    // Sort our contacts.
    usort($contacts, [static::class, 'sortContacts']);

    // Load our image style for building URLs.
    $style = ImageStyle::load('contacts_small');

    // Check our expected users are listed.
    $index = 1;
    foreach ($contacts as $contact) {
      // Gather our relevant values.
      $values = [];

      $roles = user_roles();
      uasort($roles, 'contacts_sort_roles');
      $roles = array_map(function ($item) {
        return $item->label();
      }, $roles);
      $values['roles'] = implode(', ', array_intersect_key($roles, array_fill_keys($contact->getRoles(), TRUE)));
      $values['email'] = $contact->getEmail();
      $values['image'] = $contact->hasField('user_picture') && $contact->user_picture[0] ? $contact->user_picture[0]->entity->getFileUri() : FALSE;
      $values['label'] = $contact->getDisplayName();

      // Only users with the individual or organisation role will be visible.
      $values['is_visible'] = FALSE;

      if ($contact->hasRole('crm_indiv')) {
        $values['is_visible'] = TRUE;
        $profile = $contact->profile_crm_indiv->entity;
        $values['city'] = $profile->crm_address->locality;

        if ($profile->crm_photo->target_id) {
          $values['image'] = $profile->crm_photo->entity->getFileUri();
        }

        if (!$values['image']) {
          $values['image'] = 'contacts://images/default-indiv.png';
        }
      }
      elseif ($contact->hasRole('crm_org')) {
        $values['is_visible'] = TRUE;
        $profile = $contact->profile_crm_org->entity;
        $values['city'] = $profile->crm_org_address->locality;
        if (!$values['image']) {
          $values['image'] = 'contacts://images/default-org.png';
        }
      }
      else {
        $values['city'] = FALSE;
        if (!$values['image']) {
          $values['image'] = 'contacts://images/default-indiv.png';
        }
      }

      // Convert the image URI to a URL.
      $values['image'] = \Drupal::service('file_url_generator')->generateString($style->buildUri($values['image']));
      $values['url'] = Url::fromRoute('contacts.contact', [
        'user' => $contact->id(),
      ])->toString();

      // Check our row is correctly rendered.
      $base_selector = "div.views-row:nth-of-type({$index}) ";

      if (!$values['is_visible']) {
        // Assert that contact is not visible and skip the rest.
        // @todo Somehow check that contact is not present on page.
        continue;
      }

      // Check our row link.
      $session->elementAttributeContains('css', $base_selector, 'data-row-link', $values['url']);

      // Check the image.
      $session->elementAttributeContains('css', $base_selector . '.contacts-row-image a', 'href', $values['url']);
      $session->elementAttributeContains('css', $base_selector . '.contacts-row-image img', 'src', $values['image']);

      // Check the label.
      if ($values['label']) {
        $session->elementAttributeContains('css', $base_selector . '.contacts-row-main .contact-label a', 'href', $values['url']);
        $session->elementTextContains('css', $base_selector . '.contacts-row-main .contact-label a', $values['label']);
      }
      else {
        $session->elementNotExists('css', $base_selector . '.contacts-row-main .contact-label');
      }

      // Check the email.
      if ($values['email']) {
        $session->elementTextContains('css', $base_selector . '.contacts-row-main .contact-email', $values['email']);
      }
      else {
        $session->elementNotExists('css', $base_selector . '.contacts-row-main .contact-email');
      }

      // Check the city.
      if ($values['city']) {
        $session->elementTextContains('css', $base_selector . '.contacts-row-main .contact-address', $values['city']);
      }
      else {
        $session->elementNotExists('css', $base_selector . '.contacts-row-main .contact-address');
      }

      // Check the roles.
      if ($values['roles']) {
        $session->elementTextContains('css', $base_selector . '.contacts-row-main .contact-roles', $values['roles']);
      }
      else {
        $session->elementNotExists('css', $base_selector . '.contacts-row-main .contact-roles');
      }

      // Check the ID.
      $session->elementTextContains('css', $base_selector . '.contacts-row-supporting small.contact-id', 'ID: ' . $contact->id());

      $index++;
    }
  }

  /**
   * Sort comparison callback for sorting contacts.
   *
   * @param \Drupal\user\UserInterface $a
   *   The first contact.
   * @param \Drupal\user\UserInterface $b
   *   The second contact.
   *
   * @return int
   *   The sort result.
   */
  public static function sortContacts(UserInterface $a, UserInterface $b) {
    // First sort by roles.
    // @todo Find a better way to sort by user role (#37).
    $a_roles = $a->getRoles();
    rsort($a_roles);
    $a_role = reset($a_roles);
    $b_roles = $b->getRoles();
    rsort($b_roles);
    $b_role = reset($b_roles);
    if ($a_role != $b_role) {
      return strnatcmp($a_role, $b_role);
    }

    // Then sort by individual name.
    $regex = "/[^A-Za-z0-9 ]/";
    $a_name = isset($a->profile_crm_indiv->entity->crm_name->value) ? strtolower(preg_replace($regex, '', $a->profile_crm_indiv->entity->crm_name->value)) : NULL;
    $b_name = isset($b->profile_crm_indiv->entity->crm_name->value) ? strtolower(preg_replace($regex, '', $b->profile_crm_indiv->entity->crm_name->value)) : NULL;

    if ($a_name != $b_name) {
      return strnatcmp($a_name, $b_name);
    }

    // Finally sort by ID.
    return strnatcmp($a->id(), $b->id());
  }

}
