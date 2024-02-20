<?php

namespace Drupal\gcal_entity;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of GCal Entity entities.
 *
 * @ingroup gcal_entity
 */
class GcalEntityListBuilder extends EntityListBuilder {

//@TODO need to check for the api in settings. If no api has been added, users should be directed to add one before adding gcal entities.

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('GCal Entity ID');
    $header['calendar_id'] = $this->t('Calendar ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /* @var $entity \Drupal\gcal_entity\Entity\GcalEntity */
    $row['id'] = $entity->id();
    $row['calendar_id'] = $entity->getCalendarId();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.gcal_entity.edit_form',
      ['gcal_entity' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }


  public function render(): array {
    $processor = Drupal::service('gcal_entity.google_processor');
    if(empty($processor->get_setting('googleapi'))){
      $empty = t('You must first enter the API at <a href="/admin/config/gcal_entity/config">Gcal Entity Admin');
    }
    else{
      $empty = $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]);
    }
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#empty' => $empty,
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];
    foreach ($this->load() as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][$entity->id()] = $row;
      }
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }
    return $build + parent::render();
  }
}
