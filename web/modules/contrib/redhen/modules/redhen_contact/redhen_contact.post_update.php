<?php

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Implements hook_post_update_NAME().
 */
function redhen_contact_post_update_update_base_fields(&$sandbox) {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('redhen_contact');
  $fields = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('redhen_contact');

  $config = \Drupal::config('redhen_contact.settings');

  $required_names = $config->get('required_properties');
  $fields['first_name'] = BaseFieldDefinition::create('string')
    ->setName('first_name')
    ->setTargetEntityTypeId('redhen_contact')
    ->setLabel(t('First Name'))
    ->setSettings([
      'max_length' => 255,
      'text_processing' => 0,
    ])
    ->setDefaultValue('')
    ->setDisplayOptions('form', [
      'type' => 'string_textfield',
      'weight' => -10,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE)
    ->setRequired($required_names['first_name'])
    ->setRevisionable(TRUE);

  $fields['middle_name'] = BaseFieldDefinition::create('string')
    ->setName('middle_name')
    ->setTargetEntityTypeId('redhen_contact')
    ->setLabel(t('Middle Name'))
    ->setSettings([
      'max_length' => 255,
      'text_processing' => 0,
    ])
    ->setDefaultValue('')
    ->setDisplayOptions('form', [
      'type' => 'string_textfield',
      'weight' => -9,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE)
    ->setRequired($required_names['middle_name'])
    ->setRevisionable(TRUE);

  $fields['last_name'] = BaseFieldDefinition::create('string')
    ->setName('last_name')
    ->setTargetEntityTypeId('redhen_contact')
    ->setLabel(t('Last Name'))
    ->setSettings([
      'max_length' => 255,
      'text_processing' => 0,
    ])
    ->setDefaultValue('')
    ->setDisplayOptions('form', [
      'type' => 'string_textfield',
      'weight' => -8,
    ])
    ->setDisplayConfigurable('form', TRUE)
    ->setDisplayConfigurable('view', TRUE)
    ->setRequired($required_names['last_name'])
    ->setRevisionable(TRUE);

  $definition_update_manager->updateFieldableEntityType($entity_type, $fields, $sandbox);

  return t('redhen_contact have been updated.');
}
