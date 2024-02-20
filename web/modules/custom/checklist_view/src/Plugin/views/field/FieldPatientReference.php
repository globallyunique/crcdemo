<?php

namespace Drupal\checklist_view\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\maestro\Engine\MaestroEngineInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to present the data found in the Maestro process variable 'field_patient_reference'.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("field_patient_reference")
 */
class FieldPatientReference extends FieldPluginBase {

  /**
   * The Maestro engine.
   *
   * @var \Drupal\maestro\Engine\MaestroEngineInterface
   */
  protected $maestroEngine;

  /**
   * Constructs a FieldPatientReference object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\maestro\Engine\MaestroEngineInterface $maestro_engine
   *   The Maestro engine.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MaestroEngineInterface $maestro_engine) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maestroEngine = $maestro_engine;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('maestro.engine')
    );
  }

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * @{inheritdoc}
   */

   /* TODO: do we need this, $process_variable not set */
  public function render(ResultRow $values) {
    $process_id = $values->_entity->id();
    \Drupal::logger('checklist_view render')->info('process_id: ' . $process_id);
    $patient_id = $this->maestroEngine->getProcessVariable('field_patient_reference', $process_id);
    \Drupal::state()->set('sitehub.process_id', $process_id);
    \Drupal::state()->set('sitehub.patient_id', $patient_id);
    \Drupal::logger('checklist_view render')->info('saving to session patient_id: ' . $patient_id. ' process_id: ' . $process_id);
    
    return [
      '#markup' => $process_variable,
    ];
  }

}