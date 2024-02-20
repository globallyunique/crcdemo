<?php

namespace Drupal\mix\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Mix routes.
 */
class MixUnexpectedErrorPageController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {
    throw new \Exception("Trigger custom error page.");
  }

}
