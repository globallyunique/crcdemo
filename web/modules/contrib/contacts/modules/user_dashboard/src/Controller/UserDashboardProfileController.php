<?php

namespace Drupal\contacts_user_dashboard\Controller;

use Drupal\profile\Controller\UserController;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\Entity\ProfileTypeInterface;
use Drupal\user\UserInterface;

/**
 * Controller for user dashboard profiles.
 *
 * Ensures that the correct form display is used.
 *
 * @package Drupal\contacts_user_dashboard\Controller
 */
class UserDashboardProfileController extends UserController {

  /**
   * {@inheritdoc}
   */
  public function addForm(UserInterface $user, ProfileTypeInterface $profile_type) {
    $profile = $this->entityTypeManager()->getStorage('profile')->create([
      'uid' => $user->id(),
      'type' => $profile_type->id(),
    ]);
    return $this->entityFormBuilder()->getForm($profile, 'user_dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function editForm(ProfileInterface $profile) {
    return $this->entityFormBuilder()->getForm($profile, 'user_dashboard');
  }

}
