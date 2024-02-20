<?php

namespace Drupal\contacts_user_dashboard\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the Route Subscriber.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Override the /user route to go to summary page.
    if ($route = $collection->get('user.page')) {
      $defaults = $route->getDefaults();
      $defaults['_controller'] = '\Drupal\contacts_user_dashboard\Controller\UserDashboardController::userPage';
      $route->setDefaults($defaults);
    }

    // Override the /user/{user} route to go to the summary page.
    if ($this->configFactory->get('contacts.configuration')->get('redirect_user_page')) {
      if ($route = $collection->get('entity.user.canonical')) {
        $route->setDefault('_controller', '\Drupal\contacts_user_dashboard\Controller\UserDashboardController::userPage');
      }
    }

    // Never use the admin theme for editing users.
    if ($route = $collection->get('entity.user.edit_form')) {
      $route->setOption('_admin_route', FALSE);
    }

    // Ensure user profiles accessed through the front end go via the
    // user dashboard controller so they get the correct form display.
    if ($route = $collection->get('profile.user_page.single')) {
      // Although the userProfileForm method is not overridden in the controller
      // it must still be specified in the route to ensure we go via
      // the right controller, as internally it calls editProfile/addProfile
      // which are overridden.
      $route->setDefault('_controller', '\Drupal\contacts_user_dashboard\Controller\UserDashboardProfileController::singlePage');
    }
    if ($route = $collection->get('profile.user_page.add_form')) {
      $route->setDefault('_controller', '\Drupal\contacts_user_dashboard\Controller\UserDashboardProfileController::addForm');
    }
  }

}
