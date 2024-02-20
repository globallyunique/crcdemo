<?php

namespace Drupal\contacts_mapping\Plugin\Block;

use Drupal\contacts_mapping\Form\GeolocationMapForm;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'EntityMapBlock' block plugin.
 *
 * @Block(
 *   id = "contacts_geofield_map",
 *   admin_label = @Translation("Contacts Geofield Map"),
 *   deriver = "Drupal\contacts_mapping\Plugin\Derivative\EntityMapBlock"
 * )
 */
class EntityMapBlock extends BlockBase implements ContextAwarePluginInterface, ContainerFactoryPluginInterface {

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
   * The entity form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new BlockContentBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;

    [, $bundle, $bundle_type] = explode(':', $config['id']);

    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($bundle, $bundle_type);
    $geo_list_fields = [];
    foreach ($bundle_fields as $key => $field_definition) {
      if (!empty($field_definition->getTargetBundle())) {
        if ($field_definition->getType() == 'geofield') {
          $geo_list_fields[$key] = $field_definition->getLabel();
        }
      }
    }

    $form['geo_field'] = [
      '#type' => 'select',
      '#title' => 'Select Geo field',
      '#options' => $geo_list_fields,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Invalidate the block cache to update custom block-based derivatives.
    $this->configuration['geo_field'] = $form_state->getValue('geo_field');
  }

  /**
   * Gets the entity that has the field.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity.
   */
  protected function getEntity() {
    return $this->getContextValue('entity');
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return $this->getEntity()->access('view', $account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $geofield_machine_name = $this->configuration['geo_field'];
    $entity = $this->getEntity();
    if ($entity) {
      return $this->formBuilder->getForm(GeolocationMapForm::class, $geofield_machine_name, $entity);
    }
  }

}
