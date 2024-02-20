<?php

namespace Drupal\contacts_dbs;

use Drupal\views\EntityViewsData;

/**
 * Provides the Views data for the contacts dbs entity type.
 */
class DBSStatusViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Add relationship from DBS Status revision to DBS Status.
    $data['dbs_status_field_revision']['id']['relationship']['id'] = 'standard';
    $data['dbs_status_field_revision']['id']['relationship']['base'] = 'dbs_status_field_data';
    $data['dbs_status_field_revision']['id']['relationship']['base field'] = 'id';
    $data['dbs_status_field_revision']['id']['relationship']['title'] = $this->t('DBS Status');
    $data['dbs_status_field_revision']['id']['relationship']['label'] = $this->t('DBS Status');
    $data['dbs_status_field_revision']['id']['relationship']['help'] = $this->t('Get the actual status item from a revision.');

    // Add bulk update option for DBS Status.
    $data['dbs_status_field_revision']['dbs_bulk_form'] = [
      'title' => $this->t('Bulk update'),
      'help' => $this->t('Add a form element that lets you run operations on multiple DBS Statuses.'),
      'field' => [
        'id' => 'dbs_bulk_form',
      ],
    ];

    // Add DBS status filter for status field.
    $data['dbs_status_field_data']['status']['filter'] = [
      'id' => 'list_field',
      'field' => 'status',
      'field_name' => 'status',
      'entity_type' => 'dbs_status',
      'allow empty' => TRUE,
    ];

    return $data;
  }

}
