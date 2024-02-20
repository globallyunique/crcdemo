<?php

namespace Drupal\contacts\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Switches theme to Dashboard theme on contact dashboard pages.
 */
class DashboardNegotiator implements ThemeNegotiatorInterface {

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    if (is_null($route)) {
      return FALSE;
    }
    // Use this theme on a certain route.
    $is_contacts_page = substr($route->getPath(), 0, 15) == '/admin/contacts';
    return $is_contacts_page && $this->currentUser->hasPermission('view contacts');
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    // Here you return the actual theme name.
    return 'contacts_theme';
  }

}
