<?php

namespace Drupal\mix\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Example configuration override.
 */
class ConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ConfigOverrider object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    // Only override configs in development mode is enabled.
    $devMode = $this->configFactory->getEditable('mix.settings')->getOriginal('dev_mode', FALSE);
    if (!$devMode) {
      return $overrides;
    }

    // Override browser cache, css/js aggregate configs.
    if (in_array('system.performance', $names)) {
      $overrides['system.performance'] = [
        'cache' => [
          'page' => [
            'max_age' => 0,
          ],
        ],
        'css' => [
          'preprocess' => 0,
          'gzip' => 0,
        ],
        'js' => [
          'preprocess' => 0,
          'gzip' => 0,
        ],
      ];
    }

    // Override "Error message to display" config.
    if (in_array('system.logging', $names)) {
      $overrides['system.logging'] = [
        'error_level' => 'verbose',
      ];
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'ConfigOverrider';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
