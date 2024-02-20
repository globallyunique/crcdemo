<?php

namespace Drupal\gcal_entity;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\gcal_entity\Entity\GcalEntityInterface;

/**
 * Access controller for the GCal Entity entity.
 *
 * @see \Drupal\gcal_entity\Entity\GcalEntity.
 */
class GcalEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if($entity instanceof GcalEntityInterface) {
      switch ($operation) {
        case 'view':
          if (!$entity->isPublished()) {
            return AccessResult::allowedIfHasPermission($account, 'view unpublished gcal entity entities');
          }
          return AccessResult::allowedIfHasPermission($account, 'view published gcal entity entities');

        case 'update':
          return AccessResult::allowedIfHasPermission($account, 'edit gcal entity entities');

        case 'delete':
          return AccessResult::allowedIfHasPermission($account, 'delete gcal entity entities');
      }
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'add gcal entity entities');
  }

}
