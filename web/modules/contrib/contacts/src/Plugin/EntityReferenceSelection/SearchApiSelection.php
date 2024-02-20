<?php

namespace Drupal\contacts\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity reference selection for contacts, making use of search API.
 *
 * @EntityReferenceSelection(
 *   id = "search_api",
 *   label = @Translation("Contact selection"),
 *   group = "search_api",
 *   weight = 2
 * )
 */
class SearchApiSelection extends SelectionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new selection object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, EntityRepositoryInterface $entity_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);

    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->entityRepository = $entity_repository;
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
      $container->get('module_handler'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'index' => NULL,
      'conditions' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $storage = $this->entityTypeManager->getStorage('search_api_index');
    $indexes = [];
    foreach ($storage->loadMultiple() as $index) {
      /** @var \Drupal\search_api\IndexInterface $index */
      foreach ($index->getDatasources() as $datasource) {
        // Include this index if it includes the right entity type.
        if ($datasource->getEntityTypeId() == $this->configuration['target_type']) {
          $indexes[$index->id()] = $index->label();
          // Continue to the next index, we don't need to look at more sources.
          break;
        }
      }
    }

    $configuration = $this->getConfiguration();
    $form['index'] = [
      '#type' => 'select',
      '#title' => $this->t('Index'),
      '#description' => $this->t('Select the index to search.'),
      '#required' => TRUE,
      '#options' => $indexes,
      '#default_value' => $configuration['index'] ?? NULL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(SelectInterface $query) {
    throw new \Exception('Entity queries are not supported for Search API Selection handler.');
  }

  /**
   * Get the base query for finding matches.
   *
   * @param string|null $match
   *   The string to match, or NULL for any.
   * @param string $match_operator
   *   The match operator.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The base query.
   */
  protected function getQuery($match = NULL, $match_operator = 'CONTAINS') {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager
      ->getStorage('search_api_index')
      ->load($this->configuration['index']);

    $query = $index->query();
    if (isset($match)) {
      $query->keys($match);
    }

    $query->sort('search_api_relevance', 'DESC');

    // Filter on data sources that return the correct entity type.
    $datasources = [];
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == $this->configuration['target_type']) {
        $datasources[] = $datasource->getPluginId();
      }
    }
    $query->addCondition('search_api_datasource', $datasources, 'IN');

    // Add any additional conditions.
    foreach ($this->configuration['conditions'] as $condition) {
      $query->addCondition(...$condition);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $query = $this->getQuery($match, $match_operator);
    if ($limit) {
      $query->range(0, $limit);
    }

    $results = $query->execute();

    if ($results->getResultCount() == 0) {
      return [];
    }

    $options = [];
    foreach ($results->getResultItems() as $result) {
      try {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $entity = $result->getOriginalObject()->getValue();
        $translated_entity = $this->entityRepository->getTranslationFromContext($entity);
        $options[$entity->bundle()][$entity->id()] = Html::escape($translated_entity->label());
      }
      catch (SearchApiException $exception) {
        // Skip over this item.
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS') {
    $query = $this->getQuery($match, $match_operator);
    $query->range(0, 0);
    $result = $query->execute();
    return $result->getResultCount();
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids) {
    $result = [];
    if ($ids) {
      $target_type = $this->configuration['target_type'];
      $entity_type = $this->entityTypeManager->getDefinition($target_type);
      $query = $this->entityTypeManager->getStorage($target_type)->getQuery();
      $result = $query
        ->accessCheck(TRUE)
        ->condition($entity_type->getKey('id'), $ids, 'IN')
        ->execute();
    }

    return $result;
  }

}
