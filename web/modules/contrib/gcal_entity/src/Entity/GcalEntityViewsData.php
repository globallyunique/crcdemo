<?php

namespace Drupal\gcal_entity\Entity;

use Drupal\views\EntityViewsData;
use Drupal\gcal_entity\Controller\GoogleProcessor;
/**
 * Provides Views data for GCal Entity entities.
 */
class GcalEntityViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.

    return $data;
  }

}
