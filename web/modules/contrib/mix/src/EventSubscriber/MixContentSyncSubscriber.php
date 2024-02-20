<?php

namespace Drupal\mix\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\mix\Controller\Mix;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Mix content sync subscriber.
 */
class MixContentSyncSubscriber implements EventSubscriberInterface {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * Supported entity types and the classes map.
   *
   * @var array
   */
  public static $supportedEntityTypeMap = [
    'block_content'     => 'Drupal\block_content\Entity\BlockContent',
    'menu_link_content' => 'Drupal\menu_link_content\Entity\MenuLinkContent',
    'taxonomy_term'     => 'Drupal\taxonomy\Entity\Term',
  ];

  /**
   * Supported entity types.
   *
   * @var array
   */
  protected $supportedEntityTypes;

  /**
   * Constructs a ResourceResponseSubscriber object.
   */
  public function __construct() {
    // Check dependency status before assign the serializer.
    // Use \Drupal call instead of dependency injection, so we don't have to
    // add "Serialization" module as required dependency.
    if (Mix::isContentSyncEnabled()) {
      $this->serializer = \Drupal::service('serializer');
      $this->supportedEntityTypes = array_keys(self::$supportedEntityTypeMap);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    // Only react to events when the feature is enabled.
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = ['onExportTransform'];
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = ['onImportTransform'];
    return $events;
  }

  /**
   * The storage is transformed for exporting.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onExportTransform(StorageTransformEvent $event) {

    if (!Mix::isContentSyncEnabled()) {
      return;
    }

    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = $event->getStorage();

    // Get config names of allowed content.
    $content_sync_ids = \Drupal::config('mix.settings')->get('content_sync_ids');

    // Export as configs.
    foreach ($content_sync_ids as $configName) {
      $uuid = substr($configName, strrpos($configName, '.') + 1);
      // Parse entityType.
      $entityType = $this->parseEntityType($configName);
      // Ignore wrong sync ID or unsupported entity types.
      if (!$entityType || !in_array($entityType, $this->supportedEntityTypes)) {
        continue;
      }
      $contentEntity = \Drupal::service('entity.repository')->loadEntityByUuid($entityType, $uuid);
      // Ignore non-existed entity.
      if (!$contentEntity) {
        continue;
      }
      // Seems the core will ignore the numeric IDs when create new entity
      // in the import process. So we don't remove entity id and other numeric
      // IDs after the normalization.
      $normalizedContentEntity = $this->serializer->normalize($contentEntity);

      // @see block.info.yml
      // @see menu_link_content.info.yml
      // @see taxonomy.info.yml
      $dependencies = [
        'block_content'     => ['module' => ['block', 'text', 'user']],
        'menu_link_content' => ['module' => ['link']],
        'taxonomy_term' => ['module' => ['node', 'text']],
      ];

      $array = [
        'uuid' => $uuid,
        'dependencies' => $dependencies[$entityType],
        'entity' => $normalizedContentEntity,
      ];

      // Save normalized content entity into config file.
      $storage->write($configName, $array);
    }

    // Delete the config files if contents are removed.
    $items = $storage->listAll('mix.content_sync');
    foreach ($items as $item) {
      if (!in_array($item, $content_sync_ids)) {
        $storage->delete($item);
      }
    }

  }

  /**
   * The storage is transformed for importing.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onImportTransform(StorageTransformEvent $event) {

    if (!Mix::isContentSyncEnabled()) {
      return;
    }

    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = $event->getStorage();
    $content_sync_ids = \Drupal::config('mix.settings')->get('content_sync_ids');

    foreach ($content_sync_ids as $configName) {
      $array = $storage->read($configName);
      // Ignore empty config.
      if (empty($array)) {
        continue;
      }
      // Parse entityType.
      $entityType = $this->parseEntityType($configName);
      // Ignore wrong sync ID or unsupported entity types.
      if (!$entityType || !in_array($entityType, $this->supportedEntityTypes)) {
        continue;
      }

    }
  }

  /**
   * Parse entity type from config name.
   */
  public static function parseEntityType($configName) {
    $configName = str_replace('mix.content_sync.', '', $configName);
    if (strpos($configName, 'taxonomy.term.') === 0) {
      $entityType = 'taxonomy_term';
    }
    else {
      $entityType = substr($configName, 0, strpos($configName, '.'));
    }
    return $entityType;
  }

}
