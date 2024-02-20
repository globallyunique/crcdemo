<?php

namespace Drupal\crm_tools\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\crm_tools\Controller\LoginController;
use Drupal\user\UserInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Construct the Contacts CRM Tools Route Subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Unify the login/register pages. Contacts provides configuration to
    // control this. If NULL, contacts is not enabled and we'll use it.
    $use_unified_login_register = $this->configFactory
      ->get('contacts.configuration')
      ->get('unified_login_register') ?? TRUE;
    $has_visitor_registration = $this->configFactory
      ->get('user.settings')
      ->get('register') != UserInterface::REGISTER_ADMINISTRATORS_ONLY;
    if ($use_unified_login_register && $has_visitor_registration) {
      $login_route = $collection->get('user.login');
      $register_route = $collection->get('user.register');
      if ($login_route && $register_route) {
        $login_route->setDefault('_controller', LoginController::class . '::page');
        $login_route->setDefault('_login_form', $login_route->getDefault('_form'));
        $login_route->setDefault('_register_form', $register_route->getDefault('_entity_form'));

        $register_route->setDefault('_controller', LoginController::class . '::page');
        $register_route->setDefault('_login_form', $login_route->getDefault('_form'));
        $register_route->setDefault('_register_form', $register_route->getDefault('_entity_form'));
      }
    }

    // Override the core roles listing with our form.
    if ($route = $collection->get('entity.user_role.collection')) {
      $defaults = $route->getDefaults();
      unset($defaults['_entity_form']);
      $defaults['_form'] = 'Drupal\crm_tools\Form\OverviewRoles';
      $route->setDefaults($defaults);
    }
  }

}
