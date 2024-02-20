<?php

namespace Drupal\redhen_contact\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {
  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection)
  {
    if (\Drupal::config('redhen.settings')->get('redhen_admin_path')) {
      $redhen_routes[] = "entity.redhen_contact.canonical";

      foreach ($redhen_routes as $routename) {
        $route = $collection->get($routename);
        if ($route) {
          $route->setOption('_admin_route', TRUE);
        }
      }
    }
  }
}
