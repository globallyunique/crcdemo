<?php

//  drush php:eval "require '/Users/mike.vogel/sop-engine-poc/permissions_check.php';"
// 

$role_id = 'authenticated';  // Replace with the machine name of the role
$permission = 'start template visit_1';

$role = \Drupal\user\Entity\Role::load($role_id);
if ($role && $role->hasPermission($permission)) {
  $role->revokePermission($permission);
  $role->save();
}