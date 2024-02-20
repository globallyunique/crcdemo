<?php

namespace Drupal\contacts_dbs;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\contacts_dbs\Entity\DBSStatus;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Manages starting and processing of DBS application statuses.
 */
class DBSManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The DBS status storage service.
   *
   * @var \Drupal\contacts_dbs\DBSStatusStorage
   */
  protected $dbsStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Construct the price calculator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dbsStorage = $entity_type_manager->getStorage('dbs_status');
    $this->currentUser = $current_user;
  }

  /**
   * Gets the active dbs status for a user.
   *
   * @param int $user_id
   *   The user to fetch dbs status item for.
   * @param string $workforce
   *   The workforce of dbs status item to fetch.
   * @param bool $include_acceptable
   *   (Optional) Include other acceptable workforces, defaults to TRUE.
   *
   * @return \Drupal\contacts_dbs\Entity\DBSStatusInterface|null
   *   DBS status item of null if no active statuses found.
   */
  public function getDbs($user_id, $workforce, $include_acceptable = TRUE) {
    $dbs_statuses = $this->getAllDbs($user_id, $workforce, $include_acceptable, TRUE);
    return reset($dbs_statuses) ?: NULL;
  }

  /**
   * Gets all dbs statuses for a user.
   *
   * @param int $user_id
   *   The user to fetch dbs statuses for.
   * @param string $workforce
   *   The workforce of dbs statuses to fetch.
   * @param bool $include_acceptable
   *   (Optional) Include other acceptable workforces, defaults to TRUE.
   * @param bool $active
   *   (Optional) Only include active statuses, defaults to FALSE.
   *
   * @return \Drupal\contacts_dbs\Entity\DBSStatusInterface[]
   *   DBS status item of null if no active statuses found.
   */
  public function getAllDbs($user_id, $workforce, $include_acceptable = TRUE, $active = FALSE) : array {
    $query = $this->dbsStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', $user_id)
      ->sort('changed', 'DESC');

    if ($active === TRUE) {
      $query->addTag('dbs_status_active');
    }

    if ($include_acceptable) {
      /** @var \Drupal\contacts_dbs\Entity\DBSWorkforceInterface $workforce_entity */
      $workforce_entity = $this->entityTypeManager->getStorage('dbs_workforce')->load($workforce);
      // Allow other modules to handle altering acceptable workforces.
      $acceptable_workforces = array_merge([$workforce], $workforce_entity->getAlternatives());
      $query->condition('workforce', $acceptable_workforces, 'IN');
    }
    else {
      $query->condition('workforce', $workforce, '=');
    }

    $items = $query->execute();

    if (!empty($items)) {
      return $this->dbsStorage->loadMultiple($items);
    }

    return [];
  }

  /**
   * Start a DBS workforce for a user.
   *
   * @param int $user_id
   *   The user to start dbs for.
   * @param string $workforce
   *   The workforce to start.
   * @param \DateTimeInterface|null $valid_at
   *   A timestamp for when we want to check the cleared status. If not given,
   *   it will check for currently valid.
   * @param string $log_message
   *   The revision log message.
   *
   * @return bool
   *   TRUE if a process was started, FALSE if none was required.
   */
  public function start($user_id, $workforce = NULL, DateTimePlus $valid_at = NULL, string $log_message = '') {
    if (!$workforce) {
      $workforce = 'default';
    }

    $status = $this->getDbs($user_id, $workforce);

    if (!$status) {
      $status = $this->dbsStorage->create([
        'uid' => $user_id,
        'workforce' => $workforce,
      ]);
    }

    // If the status is cleared, we don't need to continue.
    $current_status = $status->get('status')->value;
    if (in_array($current_status, DBSStatus::getClearedStatuses())) {
      if ($status->isValid($valid_at)) {
        return FALSE;
      }
    }

    // @todo Just because a status is not valid in the future, it may be valid
    // now. Do we want to clear it immediately?
    // Update status and track a new revision.
    $status->setNewRevision();
    $status->setRevisionUserId($this->currentUser->id());
    $status->setRevisionLogMessage($log_message);
    $new_status = $current_status == 'update_service_checked' ? 'update_service_check_required' : 'letter_required';
    $status->set('status', $new_status);
    $status->set('certificate_no', NULL);
    $status->save();
    return TRUE;
  }

}
