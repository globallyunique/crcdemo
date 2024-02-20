<?php

namespace Drupal\contacts_dbs\Form;

use Drupal\contacts_dbs\Entity\DBSStatus;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for only editing the status of a DBS Status entity.
 */
class DBSStatusEditStatusForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The private temporary storage factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * DBSStatusEditStatusForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   An entity type manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp_store_factory
   *   A private temporary store factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A messenger service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $private_temp_store_factory, MessengerInterface $messenger, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStoreFactory = $private_temp_store_factory;
    $this->messenger = $messenger;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('messenger'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contacts_dbs_status.edit_status';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $private_store = $this->privateTempStoreFactory->get('contacts_dbs.status_edit');
    $status_count = count($private_store->get('entity_ids'));
    $this->messenger->addWarning($this->formatPlural($status_count, 'You are about to update the status of one DBS Status.', 'You are about to update the status of @count DBS Statuses.'));

    $definitions = $this->entityFieldManager->getFieldStorageDefinitions('dbs_status');
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('New status'),
      '#required' => TRUE,
      '#description' => $this->t('The status to set selected DBS Statuses to.'),
      '#options' => options_allowed_values($definitions['status']),
    ];

    // Add a submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->formatPlural($status_count, 'Update one DBS Status.', 'Update @count DBS Statuses.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $new_status = $form_state->getValue('status');

    $private_store = $this->privateTempStoreFactory->get('contacts_dbs.status_edit');
    $entity_ids = $private_store->get('entity_ids');

    /** @var \Drupal\contacts_dbs\Entity\DBSStatusInterface[] $entities */
    $entities = DBSStatus::loadMultiple($entity_ids);
    foreach ($entities as $entity) {
      $entity->set('status', $new_status);
      $entity->save();
    }

    $private_store->delete('entity_ids');
  }

}
