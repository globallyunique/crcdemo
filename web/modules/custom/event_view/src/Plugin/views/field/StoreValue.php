<?php
namespace Drupal\event_view\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to present a button to store a value.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("store_value")
 */
class StoreValue extends FieldPluginBase {

  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  public function render(ResultRow $values) {
    \Drupal\utility_functions\UtilityFunctions::orchestrate(); // MV
    $node = $values->_entity;
    $url = Url::fromRoute('event_view.store_value', ['event_node_id' => $node->id()]);
    \Drupal::logger('render_storeValue')->info('in storeValue render url:'. $url->toString());
    return [
      '#type' => 'link',
      '#title' => $this->t('View Checklist'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['button'],
      ],
    ];
  }
}

