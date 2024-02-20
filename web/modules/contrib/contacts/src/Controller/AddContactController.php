<?php

namespace Drupal\contacts\Controller;

use Drupal\contacts\Form\AddIndivForm;
use Drupal\contacts\Form\AddOrgForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Controller for adding new contacts.
 */
class AddContactController extends ControllerBase {

  /**
   * Displays the 'Add New Individual' screen.
   *
   * @return array
   *   Render array.
   */
  public function addIndividual() {
    if ($this->useLegacyForms()) {
      return $this->formBuilder()->getForm(AddIndivForm::class);
    }

    // By default the profile will be owned by the administrator account
    // so note we need to explicitly set uid to null.
    /** @var \Drupal\user\UserInterface $new_user */
    $new_user = $this->entityTypeManager()
      ->getStorage('user')
      ->create();

    $new_user->addRole('crm_indiv');

    $profile = $this->entityTypeManager()
      ->getStorage('profile')
      ->create(['type' => 'crm_indiv', 'uid' => $new_user]);

    return $this->entityFormBuilder()->getForm($profile, 'add_contact');
  }

  /**
   * Displays the 'Add Organisation' screen.
   *
   * @return array
   *   Render array.
   */
  public function addOrganisation() {
    if ($this->useLegacyForms()) {
      return $this->formBuilder()->getForm(AddOrgForm::class);
    }

    /** @var \Drupal\user\UserInterface $new_user */
    $new_user = $this->entityTypeManager()
      ->getStorage('user')
      ->create();

    $new_user->addRole('crm_org');

    // By default the profile will be owned by the administrator account
    // so note we need to explicitly set uid to null.
    $profile = $this->entityTypeManager()
      ->getStorage('profile')
      ->create(['type' => 'crm_org', 'uid' => $new_user]);

    return $this->entityFormBuilder()->getForm($profile, 'add_contact');
  }

  /**
   * Builds the add contact form title based on the role name.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Current route.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Title.
   */
  public function addContactTitle(RouteMatchInterface $route_match) {
    if ($route_match->getRouteName() == 'contacts.add_org_form') {
      $role_id = 'crm_org';
    }
    elseif ($route_match->getRouteName() == 'contacts.add_indiv_form') {
      $role_id = 'crm_indiv';
    }
    else {
      return $this->t('Add Contact');
    }

    $role_id = $this->entityTypeManager()
      ->getStorage('user_role')
      ->load($role_id);

    return $this->t('Add @role', [
      '@role' => $role_id->label(),
    ]);
  }

  /**
   * Determines whether to use the legacy or new version of Add Contacts.
   *
   * @return bool
   *   TRUE if the legacy version should be used, otherwise FALSE.
   */
  private function useLegacyForms() {
    $config = $this->config('contacts.configuration');
    $contact_form_type = $config->get('add_contact_form_type') ?? 'legacy';
    return $contact_form_type === 'legacy';
  }

}
