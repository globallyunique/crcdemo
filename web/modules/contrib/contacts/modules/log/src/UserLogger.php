<?php

declare(strict_types=1);

namespace Drupal\contacts_log;

use Drupal\user\UserInterface;

/**
 * User logger service.
 */
class UserLogger extends DestructableLoggerBase {

  /**
   * Check whether a certain user should be excluded from logging.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return bool
   *   Whether the user should be excluded.
   */
  protected function exclude(UserInterface $user): bool {
    // Allow a value on the profile to indicate exclusions, for example for
    // migrations.
    if (!empty($user->contacts_log_exclude)) {
      return TRUE;
    }

    // Don't log unsaved or anonymous users.
    return $user->isNew() || $user->isAnonymous();
  }

  /**
   * React to inserting a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user being inserted.
   */
  public function onInsert(UserInterface $user): void {
    if ($this->exclude($user)) {
      return;
    }

    $user_id = $user->id();
    $this->log((string) $user_id, [
      'template' => 'contacts_log_insert_user',
      'contact' => $user_id,
    ]);
  }

  /**
   * React to updating a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user being updated.
   */
  public function onUpdate(UserInterface $user): void {
    if ($this->exclude($user)) {
      return;
    }

    $user_id = $user->id();
    $this->log((string) $user_id, [
      'template' => 'contacts_log_update_user',
      'contact' => $user_id,
    ]);
  }

}
