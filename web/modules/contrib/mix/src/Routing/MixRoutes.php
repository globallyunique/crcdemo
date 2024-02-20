<?php

namespace Drupal\mix\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class MixRoutes {

  /**
   * Provides dynamic routes.
   */
  public function routes() {
    $routes = [];

    $config = \Drupal::config('mix.settings');

    if ($config->get('standalone_password_page')) {
      // Declares a single route under the name 'mix.change_password_form'.
      $routes['mix.change_password_form'] = new Route(
        // Path to attach this route to:
        '/user/{user}/password',
        // Route defaults:
        [
          '_form' => 'Drupal\mix\Form\ChangePasswordForm',
          '_title' => 'Change password',
        ],
        // Route requirements:
        [
          '_entity_access'  => 'user.update',
          'user' => '\d+',
        ],
        // Route options:
        [
          'parameters' => [
            'user' => [
              'type' => 'entity:user',
            ],
          ],
        ]
      );
    }

    // Returns an array of Route objects.
    return $routes;
  }

}
