<?php

namespace Drupal\mix\EventSubscriber;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Mix config import ignore event subscriber.
 */
class MixConfigImportIgnoreEventSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The active configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * The config.storage.sync service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorageSync;

  /**
   * Constructs a MixConfigImportIgnoreEventSubscriber.php object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active configuration storage.
   * @param \Drupal\Core\Config\StorageInterface $config_storage_sync
   *   The config.storage.sync service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StorageInterface $active_storage, StorageInterface $config_storage_sync) {
    $this->configFactory = $config_factory;
    $this->activeStorage = $active_storage;
    $this->configStorageSync = $config_storage_sync;
  }

  /**
   * The storage is transformed for importing.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onImportTransform(StorageTransformEvent $event) {

    $mixSettings = $this->configFactory->get('mix.settings');

    // Only works when the "Config import ignore" is enabled.
    $isEnabled = $mixSettings->get('config_import_ignore.mode');
    if (!$isEnabled) {
      return;
    }

    $storage = $event->getStorage();
    $ignoreList = $mixSettings->get('config_import_ignore.list');

    foreach ($ignoreList as $item) {

      // Ignore entire config.
      if (strpos($item, ':') === FALSE) {
        $configName = $item;

        // Load sync config.
        $config = $storage->read($configName);
        // Load active config.
        $activeConfig = $this->activeStorage->read($configName);

        // If a config already in the active storage, then write the active
        // config to the sync storage to ignore the import.
        // （Because the config in the two storages is the same.)
        if ($activeConfig) {
          $storage->write($configName, $activeConfig);
        }
        // If a config not in the active storage, delete in the sync storage
        // to ignore the import.
        elseif (!$activeConfig && $config) {
          $storage->delete($configName);
        }

      }
      // Ignore partial configs.
      else {
        [$configName, $key] = explode(':', $item);
        $parents = explode('.', $key);

        // Load sync config.
        $config = $storage->read($configName);
        if (!$config) {
          continue;
        }
        // Load active config.
        $activeConfig = $this->activeStorage->read($configName);
        // Get target value in active storage.
        $key_exists = FALSE;
        $activeValue = NestedArray::getValue($activeConfig, $parents, $key_exists);
        // When the config and the key exist in the active storage,
        // set the active value to sync storage to ignore the import.
        if ($activeConfig && $key_exists) {
          NestedArray::setValue($config, $parents, $activeValue);
        }
        // Otherwise unset the value to ignore the import.
        else {
          NestedArray::unsetValue($config, $parents);
        }

        $storage->write($configName, $config);
      }

    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = ['onImportTransform'];
    return $events;
  }

}
