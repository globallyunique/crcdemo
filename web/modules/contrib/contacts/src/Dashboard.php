<?php

namespace Drupal\contacts;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper for Contacts Dashboard.
 */
class Dashboard implements TrustedCallbackInterface {

  use DependencySerializationTrait {
    DependencySerializationTrait::__sleep as traitSleep;
  }

  /**
   * A fast static cache of the service instances, keyed by service name.
   *
   * Values may be FALSE if we are not on the dashboard page.
   *
   * @var array
   */
  protected static $fastStatic;

  /**
   * Indicates we are not on the Contacts Dashboard.
   */
  const MODE_NOT = 'not';

  /**
   * Indicates we are on the Contacts Dashboard via an full page request.
   */
  const MODE_FULL = 'full';

  /**
   * Indicates we are on the Contacts Dashboard via an AJAX request.
   */
  const MODE_AJAX = 'ajax';

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The dashboard full page route name.
   *
   * @var string
   */
  protected $fullRouteName;

  /**
   * The dashboard AJAX page route name.
   *
   * @var string
   */
  protected $ajaxRouteName;

  /**
   * The dashboard mode.
   *
   * @var string
   */
  protected $mode;

  /**
   * Construct the dashboard helper.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The route match service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param string $full_route_name
   *   The route name for the dashboard full page.
   * @param string $ajax_route_name
   *   The route name for the dashboard AJAX page.
   */
  public function __construct(CurrentRouteMatch $route_match, RequestStack $request_stack, $full_route_name, $ajax_route_name) {
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
    $this->fullRouteName = $full_route_name;
    $this->ajaxRouteName = $ajax_route_name;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    return array_diff(
      $this->traitSleep(),
      ['mode'],
    );
  }

  /**
   * Get the service from a static cache including an is dashbaord check.
   *
   * @param string $service_name
   *   The service name.
   *
   * @return \Drupal\contacts\Dashboard|null
   *   The dashboard service.
   */
  protected static function getInstance(string $service_name): ?Dashboard {
    // Instantiate our fast static if it's not already set.
    if (!isset(self::$fastStatic[$service_name])) {
      $dashboard = \Drupal::service($service_name);
      self::$fastStatic[$service_name] = $dashboard->isDashboard() ? $dashboard : FALSE;
    }

    // If we have a service, return it.
    return self::$fastStatic[$service_name] ?: NULL;
  }

  /**
   * Get the dashboard mode for a Url or the current request.
   *
   * @param \Drupal\Core\Url|null $url
   *   Optionally a URL to check. Otherwise we use the current request.
   *
   * @return string
   *   One of the self::MODE_* constants.
   */
  public function getDashboardMode(Url $url = NULL) {
    // If we're not checking a specific URL, see if we can return a cache.
    if (!$url && isset($this->mode)) {
      return $this->mode;
    }

    // Get the route name we're checking.
    if ($url) {
      $route = $url->isRouted() ? $url->getRouteName() : '';

      // If the route is <current>, we can use the cache.
      if ($route == '<current>' || $route == '<none>') {
        return $this->getDashboardMode();
      }
    }
    else {
      $route = $this->routeMatch->getRouteName();
    }

    // Check the mode.
    switch ($route) {
      case $this->fullRouteName:
        $mode = self::MODE_FULL;
        break;

      case $this->ajaxRouteName:
        $mode = self::MODE_AJAX;
        break;

      default:
        $mode = self::MODE_NOT;
        break;
    }

    // If we're checking the current request, cache the value.
    if (!$url) {
      $this->mode = $mode;
    }

    return $mode;
  }

  /**
   * Check if the Url or the current request is the dashboard.
   *
   * @param \Drupal\Core\Url|null $url
   *   Optionally a URL to check. Otherwise we use the current request.
   *
   * @return bool
   *   TRUE if it is the dashboard.
   */
  public function isDashboard(Url $url = NULL) {
    return $this->getDashboardMode($url) !== self::MODE_NOT;
  }

  /**
   * Check if the Url or the current request is the full dashboard.
   *
   * @param \Drupal\Core\Url|null $url
   *   Optionally a URL to check. Otherwise we use the current request.
   * @param bool $same_contact_only
   *   Optionally consider a URL to another user to be a full dashboard request.
   *
   * @return bool
   *   TRUE if it is the full dashboard.
   */
  public function isDashboardFull(Url $url = NULL, $same_contact_only = TRUE) {
    // If:
    // - checking a different URL to the current request
    // - the check should account for the user being different
    // - the user for the current request is different to the URL user
    // Then this is not suitable for an AJAX request.
    if ($url && $same_contact_only && !$this->isCurrentContact($url)) {
      return FALSE;
    }
    return $this->getDashboardMode($url) === self::MODE_FULL;
  }

  /**
   * Check if the Url or the current request is the AJAX dashboard.
   *
   * @param \Drupal\Core\Url|null $url
   *   Optionally a URL to check. Otherwise we use the current request.
   * @param bool $same_user_only
   *   Optionally consider a URL to another user to be an AJAX request.
   *
   * @return bool
   *   TRUE if it is the AJAX dashboard.
   */
  public function isDashboardAjax(Url $url = NULL, $same_user_only = TRUE) {
    // If:
    // - checking a different URL to the current request
    // - the check should account for the user being different
    // - the user for the current request is different to the URL user
    // Then this is not suitable for an AJAX request.
    if ($url && $same_user_only && !$this->isCurrentContact($url)) {
      return FALSE;
    }
    return $this->getDashboardMode($url) === self::MODE_AJAX;
  }

  /**
   * Check if the Url is for the same user as the current request.
   *
   * @param \Drupal\Core\Url $url
   *   The Url to check.
   *
   * @return bool
   *   TRUE if the Url is for the same user.
   */
  public function isCurrentContact(Url $url) {
    if ($url->isRouted() && $this->isDashboard($url)) {
      $url_route_params = $url->getRouteParameters();
      if (isset($url_route_params['user'])) {
        return $url_route_params['user'] === $this->routeMatch->getRawParameter('user');
      }
    }
    return FALSE;
  }

  /**
   * Get a full page equivalent for the Url or current page.
   *
   * @param \Drupal\Core\Url|null $url
   *   The URL or NULL to use the current page.
   *
   * @return \Drupal\Core\Url
   *   The full page equivalent URL.
   */
  public function getFullUrl(Url $url = NULL) {
    if ($url) {
      $params = $url->getRouteParameters();
      $options = $url->getOptions();
    }
    else {
      $param_bag = $this->routeMatch->getParameters();
      $params = [
        'user' => $param_bag->get('user')->id(),
        'subpage' => $param_bag->get('subpage'),
      ];
      $options['query'] = $this->requestStack->getCurrentRequest()->query->all();
    }
    unset($options['query']['_wrapper_format']);
    unset($options['query']['_format']);

    return Url::fromRoute($this->fullRouteName, $params, $options);
  }

  /**
   * Get a AJAX page equivalent for the Url or current page.
   *
   * @param \Drupal\Core\Url|null $url
   *   The URL or NULL to use the current page.
   *
   * @return \Drupal\Core\Url
   *   The AJAX page equivalent URL.
   */
  public function getAjaxUrl(Url $url = NULL) {
    if ($url) {
      $params = $url->getRouteParameters();
      $options = $url->getOptions();
    }
    else {
      $param_bag = $this->routeMatch->getParameters();
      $params = [
        'user' => $param_bag->get('user')->id(),
        'subpage' => $param_bag->get('subpage'),
      ];
      $options = $this->requestStack->getCurrentRequest()->query->all();
    }
    return Url::fromRoute($this->ajaxRouteName, $params, $options);
  }

  /**
   * Fast implementation of hook_link_alter() for dashboard services.
   *
   * Implements a similar pattern to the drupal fast static to optimise the
   * speed of this method and only do processing where required.
   *
   * @param array $variables
   *   The link variables.
   * @param string $service_name
   *   The service name for the dashboard helper.
   */
  public static function fastHookLinkAlter(array &$variables, $service_name) {
    // Escape early if we are explicitly skipping AJAX conversion.
    if (!empty($variables['options']['contacts_no_ajax'])) {
      return;
    }

    if ($dashboard = self::getInstance($service_name)) {
      $dashboard->hookLinkAlter($variables);
    }
  }

  /**
   * Service implementation of hook_link_alter().
   *
   * @param array $variables
   *   The link variables.
   */
  public function hookLinkAlter(array &$variables) {
    /** @var \Drupal\Core\Url $url */
    $url = &$variables['url'];

    // Change links within the dashboard.
    if ($this->isDashboardFull($url)) {
      $variables['options']['attributes'] += [
        'data-ajax-progress' => 'fullscreen',
        'data-ajax-url' => $this->getAjaxUrl($url)->toString(),
      ];
      if (!isset($variables['options']['attributes']['class']) || !in_array('use-ajax', $variables['options']['attributes']['class'])) {
        $variables['options']['attributes']['class'][] = 'use-ajax';
      }
    }

    // Update links indicated to use a modal. Check the target as a workaround
    // for views, which doesn't allow more specific options to be set.
    $use_modal = !empty($variables['options']['contacts_modal'])
      || ($variables['options']['attributes']['target'] ?? NULL) == '_contacts_modal';
    if ($use_modal) {
      unset($variables['options']['attributes']['target']);
      $variables['options']['attributes'] += [
        'data-ajax-progress' => 'fullscreen',
        'data-dialog-type' => 'modal',
      ];
      $variables['options']['attributes']['class'][] = 'use-ajax';

      // Set a redirect URL if there isn't one.
      if (empty($variables['options']['query']['destination'])) {
        $variables['options']['query']['destination'] = $this->getFullUrl()->toString();
      }
    }

    // Update destinations on links to always use the non-AJAX links. We only
    // need to do this if we're currently on the AJAX dashboard.
    if (isset($variables['options']['query']['destination']) && $this->isDashboardAjax()) {
      $destination = Url::fromUserInput($variables['options']['query']['destination']);
      if ($this->isDashboardAjax($destination)) {
        $variables['options']['query']['destination'] = $this->getFullUrl($destination)->toString();
      }
    }

  }

  /**
   * Fast implementation of hook_form_alter().
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The form ID.
   * @param string $service_name
   *   The service name for the dashboard helper.
   */
  public static function fastHookFormAlter(array &$form, FormStateInterface $form_state, string $form_id, string $service_name): void {
    if (!self::isDefaultFormAction($form)) {
      return;
    }

    if ($dashboard = self::getInstance($service_name)) {
      $dashboard->hookFormAlter($form, $form_state, $form_id, TRUE);
    }
  }

  /**
   * Implementation of hook_form_alter().
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The form ID.
   * @param bool $default_checked
   *   Whether the is default check has already been run to avoid doing it
   *   again.
   */
  public function hookFormAlter(array &$form, FormStateInterface $form_state, string $form_id, bool $default_checked = FALSE): void {
    if (!$default_checked && !self::isDefaultFormAction($form)) {
      return;
    }

    // If we are in an AJAX request, explicitly set the action to the non AJAX
    // variation.
    if ($this->isDashboardAjax()) {
      $form['#attached']['placeholders'][$form['#action']]['#lazy_builder'][0] = [
        $this,
        'buildFormAction',
      ];
    }
  }

  /**
   * Check whether a form is using the default form action (the current page).
   *
   * @param array $form
   *   The form array.
   *
   * @return bool
   *   Whether the default form action is being used.
   */
  public static function isDefaultFormAction(array $form): bool {
    // Check whether the action is the default placeholder.
    if (!is_string($form['#action'])) {
      return FALSE;
    }
    if (!isset($form['#attached']['placeholders'][$form['#action']])) {
      return FALSE;
    }
    return $form['#attached']['placeholders'][$form['#action']]['#lazy_builder'][0] === 'form_builder:renderPlaceholderFormAction';
  }

  /**
   * Builds the $form['#action'].
   *
   * @return array
   *   The form action render array.
   */
  public function buildFormAction() {
    $url = $this->getFullUrl();
    $uri = $url->toString();

    // Prevent cross site requests via the Form API by using an absolute URL
    // when the request uri starts with multiple slashes..
    if (strpos($uri, '//') === 0) {
      $uri = $url->setAbsolute()->toString();
    }

    // @todo Remove this parsing once these are removed from the request in
    //   https://www.drupal.org/node/2504709.
    $parsed = UrlHelper::parse($uri);
    unset($parsed['query'][FormBuilder::AJAX_FORM_REQUEST], $parsed['query'][MainContentViewSubscriber::WRAPPER_FORMAT]);
    $action = $parsed['path'] . ($parsed['query'] ? ('?' . UrlHelper::buildQuery($parsed['query'])) : '');

    return [
      '#type' => 'markup',
      '#markup' => UrlHelper::filterBadProtocol($action),
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['buildFormAction'];
  }

}
