<?php

namespace Drupal\gcal_entity\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

class GcalEntityLocalAction extends LocalActionDefault {

  //@TODO create an override that forces users to add API key before adding calendar entities
  /*first check for gcal_entity.settings googleapi setting. If it is empty, then redirect the user to the settings page or post a message warning.
    if not then provide the user with the add gcal_entity link. */

  public function getTitle(Request $request = NULL): bool|string {
    $config = \Drupal::config('gcal_entity.settings');
    if ($config->get('googleapi')) {
      return "Add Gcal Entity";
    }
    else {
      return FALSE;
    }
  }

}
