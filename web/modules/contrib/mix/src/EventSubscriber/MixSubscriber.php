<?php

namespace Drupal\mix\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Mix event subscriber.
 */
class MixSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * A config object for the mix settings configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * Constructs a MixSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The kill switch.
   */
  public function __construct(
      ConfigFactoryInterface $config_factory,
      AccountInterface $currentUser,
      UrlGeneratorInterface $url_generator,
      MessengerInterface $messenger,
      KillSwitch $killSwitch
    ) {
    $this->config = $config_factory->get('mix.settings');
    $this->currentUser = $currentUser;
    $this->urlGenerator = $url_generator;
    $this->messenger = $messenger;
    $this->killSwitch = $killSwitch;
  }

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Response event.
   */
  public function onKernelRequest(RequestEvent $event) {
    $isDevMode = $this->config->get('dev_mode');
    if ($isDevMode) {
      if ($this->currentUser->hasPermission('administer site configuration')) {
        $message = $this->t('In development mode. <a href=":url">Go online.</a>', [':url' => $this->urlGenerator->generateFromRoute('mix.settings')]);
        $this->messenger->addStatus($message, FALSE);
      }
      else {
        $this->messenger->addStatus($this->t('In development mode.'), FALSE);
      }
      // Prevent page to be cached.
      $this->killSwitch->trigger();
    }
  }

  /**
   * Kernel response event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Response event.
   */
  public function onKernelResponse(ResponseEvent $event) {
    $removeXGenerator = $this->config->get('remove_x_generator');
    if ($removeXGenerator) {
      $response = $event->getResponse();
      $response->headers->remove('X-Generator');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 30];
    $events[KernelEvents::RESPONSE][] = ['onKernelResponse', -10];
    return $events;
  }

}
