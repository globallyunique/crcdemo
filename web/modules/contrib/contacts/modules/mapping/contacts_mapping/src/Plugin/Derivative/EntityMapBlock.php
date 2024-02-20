<?php

namespace Drupal\contacts_mapping\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\geofield\Plugin\Field\FieldType\GeofieldItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for nodes.
 *
 * @see \Drupal\contacts_mapping\Plugin\Block\EntityMapBlock
 */
class EntityMapBlock extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * Creates an FieldUiLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, FieldTypePluginManagerInterface $field_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->fieldTypeManager = $field_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.field.field_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Get a list of all entity types keyed by bundle.
    $entity_bundle_info = $this->entityTypeBundleInfo->getAllBundleInfo();

    foreach ($entity_bundle_info as $entity_type_id => $entity_type) {
      // Get the entity definition.
      $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);

      // Only check content entities.
      if (!$entity_definition instanceof ContentEntityTypeInterface) {
        continue;
      }

      foreach ($entity_type as $entity_bundle_id => $entity_info) {
        // Check to see if this entity type bundle has a geofield.
        $bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $entity_bundle_id);
        $geofield = FALSE;
        foreach ($bundle_fields as $field_name => $field_definition) {
          if (!empty($field_definition->getTargetBundle())) {
            $type = $field_definition->getType();
            $definition = $this->fieldTypeManager->getDefinition($type);
            if (is_a($definition['class'], GeofieldItem::class, TRUE)) {
              $geofield = TRUE;
              break;
            }
          }
        }

        // If there is a geofield, add a block derivative for the entity bundle.
        if ($geofield) {
          $derivative_id = $entity_type_id . PluginBase::DERIVATIVE_SEPARATOR . $entity_bundle_id;
          $this->derivatives[$derivative_id] = $base_plugin_definition;
          $this->derivatives[$derivative_id]['admin_label'] = $this->t('Contacts Geofield Map:') . $entity_type_id . ' ' . $entity_bundle_id;
          $this->derivatives[$derivative_id]['_entity_type_id'] = $entity_type_id;
          $this->derivatives[$derivative_id]['_entity_bundle_id'] = $entity_bundle_id;

          $context_definition = EntityContextDefinition::fromEntityTypeId($entity_type_id);
          $context_definition->addConstraint('Bundle', [$entity_bundle_id]);
          $this->derivatives[$derivative_id]['context_definitions'] = [
            'entity' => $context_definition,
          ];
        }
      }
    }

    return $this->derivatives;
  }

}
