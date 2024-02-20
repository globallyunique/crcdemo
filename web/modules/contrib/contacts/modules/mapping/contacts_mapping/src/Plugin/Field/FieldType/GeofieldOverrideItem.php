<?php

namespace Drupal\contacts_mapping\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\geofield\Plugin\Field\FieldType\GeofieldItem;

/**
 * Plugin implementation of the 'geofield_override' field type.
 *
 * @FieldType(
 *   id = "geofield_override",
 *   label = @Translation("Geofield Override"),
 *   description = @Translation("This field stores geospatial information and can be overridden."),
 *   default_widget = "geofield_latlon",
 *   list_class = "\Drupal\contacts_mapping\Plugin\Field\FieldType\GeofieldItemList",
 *   default_formatter = "geofield_default"
 * )
 */
class GeofieldOverrideItem extends GeofieldItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field) {
    $schema = parent::schema($field);

    $schema['columns']['computed'] = [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'size' => 'tiny',
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['computed'] = DataDefinition::create('boolean')
      ->setLabel(t('Computed'));

    return $properties;
  }

}
