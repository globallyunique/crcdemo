<?php

namespace Drupal\contacts\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;

/**
 * Provides the contact dashboard tabs with required context.
 */
class ContactsDashboardTabsDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives['contacts_dashboard'] = $base_plugin_definition;
    $this->derivatives['contacts_dashboard']['admin_label'] = 'Contacts Dashboard Tabs';
    $this->derivatives['contacts_dashboard']['context_definitions'] = [
      'subpage' => new ContextDefinition('string'),
      'user' => EntityContextDefinition::fromEntityTypeId('user'),
    ];

    return $this->derivatives;
  }

}
