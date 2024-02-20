<?php

namespace Drupal\contacts;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Build the breadcrumbs on the contacts dashboard.
 */
class BreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * Contacts config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * Local task manager.
   *
   * Used for generating breadcrumb link titles to ensure consistent labeling.
   *
   * @var \Drupal\Core\Menu\LocalTaskManager
   */
  protected LocalTaskManager $localTaskManager;

  /**
   * Creates the contacts breadcrumb builder.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Menu\LocalTaskManager $local_task_manager
   *   Local task plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LocalTaskManager $local_task_manager) {
    $this->config = $config_factory->get('contacts.configuration');
    $this->localTaskManager = $local_task_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'contacts.contact';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $route_match->getParameter('user');

    $breadcrumb = new Breadcrumb();

    // Legacy mode / unconfigured uses the legacy unified dashboard mode.
    // If we're in legacy mode then the Contacts link should go to
    // contacts.collection, otherwise it should go to either the Individuals
    // or Organisations page depending on the type of the contact being viewed.
    $legacy_dashboard_mode = ($this->config->get('default_contacts_dashboard_route') ?? 'contacts.collection.all') === 'contacts.collection.all';

    if ($legacy_dashboard_mode) {
      $breadcrumb->addLink(Link::createFromRoute($this->t('Contacts'), 'contacts.collection'));
    }
    elseif ($user->hasRole('crm_indiv')) {
      // Grab the link label from Local Tasks so we use the renamed title
      // if it's been altered by a hook.
      $title = $this->localTaskManager->getDefinition('contacts.collection.individual')['title'];
      $breadcrumb->addLink(Link::createFromRoute($title, 'contacts.collection.individual'));
    }
    elseif ($user->hasRole('crm_org')) {
      // Grab the link label from Local Tasks so we use the renamed title
      // if it's been altered by a hook.
      $title = $this->localTaskManager->getDefinition('contacts.collection.organisation')['title'];
      $breadcrumb->addLink(Link::createFromRoute($title, 'contacts.collection.organisation'));
    }

    $breadcrumb->addLink(Link::createFromRoute($user->label(), 'contacts.contact', [
      'user' => $user->id(),
    ]));

    $breadcrumb->addCacheableDependency($user);
    $breadcrumb->addCacheContexts(['route']);
    return $breadcrumb;
  }

}
