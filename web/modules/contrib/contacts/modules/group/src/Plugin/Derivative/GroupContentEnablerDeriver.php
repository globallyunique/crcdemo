<?php

namespace Drupal\contacts_group\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derive plugin definitions on the basis of GroupContentEnabler plugins.
 *
 * @package Drupal\contacts_group\Plugin\Derivative
 */
class GroupContentEnablerDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The group content enabler plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $enablerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    $base_plugin_id
  ) {
    return new static(
      $container->get('plugin.manager.group_content_enabler')
    );
  }

  /**
   * GroupContentEnablerDeriver constructor.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $enabler_manager
   *   The group content enabler plugin manager.
   */
  public function __construct(GroupContentEnablerManagerInterface $enabler_manager) {
    $this->enablerManager = $enabler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $map = $this->enablerManager->getGroupTypePluginMap();
    if (!isset($map['contacts_org'])) {
      return [];
    }

    foreach ($map['contacts_org'] as $enabler_plugin_id) {
      /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $enabler */
      $enabler = $this->enablerManager->createInstance($enabler_plugin_id);
      if ($enabler->getEntityTypeId() !== 'user') {
        continue;
      }

      $this->derivatives[$enabler_plugin_id] = [
        'admin_label' => new TranslatableMarkup('Organisation @enabler form.', ['@enabler' => $enabler->getLabel()]),
        'group_content_enabler' => $enabler_plugin_id,
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
