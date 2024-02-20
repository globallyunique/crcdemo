<?php

namespace Drupal\decoupled_auth\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\decoupled_auth\DecoupledAuthUserInterface;
use Drupal\user\Plugin\Validation\Constraint\UserNameConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Validates the DecoupledAuthUserName constraint.
 */
class DecoupledAuthUserNameConstraintValidator extends UserNameConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    // Skip username validation for decoupled users.
    if ($items instanceof FieldItemListInterface) {
      $entity = $items->getEntity();
      if ($entity instanceof DecoupledAuthUserInterface && $entity->isDecoupled()) {
        return;
      }
    }

    // Otherwise pass on for normal validation.
    parent::validate($items, $constraint);
  }

}
