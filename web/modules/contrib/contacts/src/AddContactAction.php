<?php

namespace Drupal\contacts;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implementation of the 'Add Individual' and 'Add Organisation' menu actions.
 */
class AddContactAction extends LocalActionDefault {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $self = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $self->entityTypeManager = $container->get('entity_type.manager');
    return $self;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    $role_id = $this->pluginDefinition['options']['load_title_from_role'] ?? NULL;

    if ($role_id) {
      $role = $this->entityTypeManager
        ->getStorage('user_role')
        ->load($role_id);

      return new TranslatableMarkup('Add @role', [
        '@role' => $role->label(),
      ]);
    }

    return parent::getTitle($request);
  }

}
