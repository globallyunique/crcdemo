<?php

namespace Drupal\contacts_dbs\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edit the status of a DBS Status entity.
 *
 * @Action(
 *   id = "dbs_status_edit",
 *   label = @Translation("Edit DBS Status"),
 *   type = "dbs_status",
 *   confirm_form_route_name = "contacts_dbs.edit_status"
 * )
 */
class EditStatus extends EntityActionBase {

  /**
   * The private temporary storage factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $private_temp_store_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->privateTempStoreFactory = $private_temp_store_factory;
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
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    return $this->executeMultiple([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $entity_ids = [];
    foreach ($entities as $entity) {
      $entity_ids[] = $entity->id();
    }
    $private_store = $this->privateTempStoreFactory->get('contacts_dbs.status_edit');
    $private_store->set('entity_ids', $entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\EntityInterface $object */
    $result = $object->access('update', $account, TRUE)
      ->andIf($object->status->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}
