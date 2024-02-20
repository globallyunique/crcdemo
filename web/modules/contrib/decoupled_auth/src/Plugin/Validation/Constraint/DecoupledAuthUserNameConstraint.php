<?php

namespace Drupal\decoupled_auth\Plugin\Validation\Constraint;

use Drupal\user\Plugin\Validation\Constraint\UserNameConstraint;

/**
 * Checks if a value is a valid user name, but only if the user is coupled.
 *
 * @Constraint(
 *   id = "DecoupledAuthUserName",
 *   label = @Translation("User name", context = "Validation"),
 * )
 */
class DecoupledAuthUserNameConstraint extends UserNameConstraint {}
