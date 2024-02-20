<?php

namespace Drupal\shortcut_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for shortcut_test module.
 */
class ShortcutTestController extends ControllerBase {

  public function PageNoTitle() {
    return ['#markup' => 'Shortcut test no page title.'];
  }

}
