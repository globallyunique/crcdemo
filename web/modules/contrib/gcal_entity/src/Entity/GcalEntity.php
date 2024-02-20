<?php

namespace Drupal\gcal_entity\Entity;

use Drupal;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\gcal_entity\GoogleProcessor;
use Drupal\user\UserInterface;


/**
 * Defines the GCal Entity entity.
 *
 * @ingroup gcal_entity
 *
 * @ContentEntityType(
 *   id = "gcal_entity",
 *   label = @Translation("GCal Entity"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\gcal_entity\GcalEntityListBuilder",
 *     "views_data" = "Drupal\gcal_entity\Entity\GcalEntityViewsData",
 *     "translation" = "Drupal\gcal_entity\GcalEntityTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\gcal_entity\Form\GcalEntityForm",
 *       "add" = "Drupal\gcal_entity\Form\GcalEntityForm",
 *       "edit" = "Drupal\gcal_entity\Form\GcalEntityForm",
 *       "delete" = "Drupal\gcal_entity\Form\GcalEntityDeleteForm",
 *     },
 *     "access" = "Drupal\gcal_entity\GcalEntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\gcal_entity\GcalEntityHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "gcal_entity",
 *   data_table = "gcal_entity_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer gcal entity entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/gcal/gcal_entity/{gcal_entity}",
 *     "add-form" = "/gcal/gcal_entity/add",
 *     "edit-form" = "/gcal/gcal_entity/{gcal_entity}/edit",
 *     "delete-form" = "/gcal/gcal_entity/{gcal_entity}/delete",
 *     "collection" = "/gcal/gcal_entity",
 *   },
 *   field_ui_base_route = "gcal_entity.settings"
 * )
 */
class GcalEntity extends ContentEntityBase implements GcalEntityInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values): void {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): GcalEntityInterface {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): GcalEntityInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): UserInterface {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): ?int {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid): GcalEntity {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): GcalEntity {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished(): bool {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished(bool $published): GcalEntity {
    $this->set('status', (bool) $published);
    return $this;
  }

  public function getCalendarId():string {
    return $this->get('calendar_id')->value;
  }

  /**
   * @param $calendar_id
   * @param bool $cache
   * @return array|false|object
   */
  public static function getCalendars(string $calendar_id, bool $cache = TRUE): array{
    $processor = Drupal::service('gcal_entity.google_processor');
    $request_time = Drupal::time()->getRequestTime();

    //@TODO we should be able to get a setting for the project here, but somehow...
    $cache_time = $processor->get_setting('cachetime');

    $cache_key = 'gcal_entity' . $processor->get_cache_value($calendar_id);
    // Check the cache
    if ($cache) {
      $cached_events = Drupal::cache()->get($cache_key);
      if ($cached_events && $cached_events->expire > $request_time) {
        return $cached_events->data;
      }
    }
    $calendar = $processor->load_google_calendar($calendar_id);
    $events = [];

    foreach ($calendar['items'] as $google_response) {
      $events[] = $processor->parse_event($google_response);
    }

    $events_renderable = [];
    foreach ($events as $event) {
      $events_renderable[] = [
        '#theme' => 'gcal_event',
        '#event' => $event,
      ];
    }
    // Cache our data
    if ($cache) {
      $expires = $request_time + $cache_time;
      Drupal::cache()->set($cache_key, $events_renderable, $expires);
    }

    return $events_renderable;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the GCal Entity entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the GCal Entity entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    //The calendar id is always in the form of an email address
    $fields['calendar_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Calendar ID'))
      ->setDescription(t('The calendar ID--these are always structured as email addresses'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -6,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published status'))
      ->setDescription(t('A boolean indicating whether the GCal Entity is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
