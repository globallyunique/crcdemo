<?php

namespace Drupal\contacts\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed item list for the label of an entity.
 *
 * @package Drupal\contacts_events\Plugin\Field
 */
class EntityLabelItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $this->list = [];

    // This could cause issues with unsaved entities (e.g. FieldCollectionItem
    // which attempts to get a host that is not set), so skip new entities.
    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      $this->list[0] = $this->createItem(0, $entity->label());
    }

  }

}
