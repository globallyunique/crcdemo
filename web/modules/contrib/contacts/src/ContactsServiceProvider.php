<?php

namespace Drupal\contacts;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Service overrides for the Contacts module.
 */
class ContactsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Swap out the core autocomplete matcher for our one that can produce
    // multi-line output.
    $container->getDefinition('entity.autocomplete_matcher')
      ->setClass(MultiLineOutputAutocompleteMatcher::class);
  }

}
