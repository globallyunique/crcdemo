<?php

namespace Drupal\contacts_dbs;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for bookkeeping accounts.
 */
class WorkforceAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'view label') {
      return AccessResult::allowedIfHasPermission($account, 'view dbs workforces');
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
