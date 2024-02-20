<?php

namespace Drupal\contacts\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles redirections for access denied pages.
 */
class AccessDeniedSubscriber implements EventSubscriberInterface {

  use RedirectDestinationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The contacts configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new redirect subscriber.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(AccountInterface $account, ConfigFactoryInterface $config_factory) {
    $this->account = $account;
    $this->config = $config_factory->get('contacts.configuration');
  }

  /**
   * Trigger a full page redirects when access is denied to a dashboard page.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    if ($exception instanceof AccessDeniedHttpException) {
      // @todo Switch over to using the dashboard helper.
      $route = RouteMatch::createFromRequest($event->getRequest());
      if ($route->getRouteName() === 'contacts.ajax_subpage') {
        $response = new AjaxResponse();
        $url = Url::fromRoute('contacts.contact', [
          'user' => $route->getParameter('user')->id(),
          'subpage' => $route->getParameter('subpage'),
        ]);
        $response->addCommand(new RedirectCommand($url->toString()));
        $response->headers->set('X-Status-Code', 200);
        $event->setResponse($response);
      }
    }
  }

  /**
   * Redirects users to login page when access denied.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function redirectToLoginPage(ExceptionEvent $event) {
    // Only redirect if enabled.
    if (!$this->config->get('access_denied_redirect')) {
      return;
    }

    // We are only interested in access denied exceptions.
    $exception = $event->getThrowable();
    if (!$exception instanceof AccessDeniedHttpException) {
      return;
    }

    // We only want to redirect for full HTML page requests.
    $request = $event->getRequest();
    if ($request->isXmlHttpRequest() || !in_array('text/html', $request->getAcceptableContentTypes())) {
      return;
    }

    // We don't want redirect if the user is already authenticated.
    if ($this->account->isAuthenticated()) {
      return;
    }

    $options = [
      'query' => ['destination' => $request->getRequestUri()],
      'absolute' => TRUE,
    ];
    $url = Url::fromRoute('user.login', [], $options)->toString();

    // Ensure the response has appropriate cache tags.
    if ($exception instanceof CacheableAccessDeniedHttpException) {
      $response = new CacheableRedirectResponse($url);
      $response->addCacheableDependency($exception);
    }
    else {
      $response = new RedirectResponse($url);
    }

    // Clear the destination from the current request or it will attempt to
    // shortcut straight to it.
    $request->query->remove('destination');

    $event->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onException'];

    // Last to make sure other subscribers can handle first.
    $events[KernelEvents::EXCEPTION][] = ['redirectToLoginPage', -20];
    return $events;
  }

}
