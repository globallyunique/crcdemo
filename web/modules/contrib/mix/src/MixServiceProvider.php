<?php

namespace Drupal\mix;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Defines a service provider for the Mix module.
 */
class MixServiceProvider extends ServiceProviderBase {

  /**
   * Whether the dev_mode has been enabled.
   *
   * @var bool
   */
  protected $isDevMode;

  /**
   * Construct mix service provider.
   */
  public function __construct() {
    $configStorage = BootstrapConfigStorageFactory::get();
    $mixSettings = $configStorage->read('mix.settings');
    $this->isDevMode = isset($mixSettings['dev_mode']) && $mixSettings['dev_mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Only override services when dev_mode is enabled.
    if ($this->isDevMode) {
      $cacheBins = ['cache.render', 'cache.page', 'cache.dynamic_page_cache'];
      foreach ($cacheBins as $id) {
        $container->register($id, 'Drupal\Core\Cache\NullBackend')
          ->addArgument(substr($id, strpos($id, '.') + 1))
          ->addTag('cache.bin', ['default_backend' => 'mix.cache.backend.null']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    // Only alter services when dev_mode is enabled.
    if ($this->isDevMode) {
      // Enable cacheability headers debug.
      $container->setParameter('http.response.debug_cacheability_headers', TRUE);
      // Enable twig debug.
      $twig_config = $container->getParameter('twig.config');
      $twig_config['debug'] = TRUE;
      $twig_config['auto_reload'] = TRUE;

      // Set `cache` to false will disable Twig template compilation.
      // However, this is not recommended; not even in the dev environment.
      // The `auto_reload` option ensures that cached templates
      // which have changed get compiled again.
      // So we stop set `cache` to FALSE here.
      // @see https://symfony.com/doc/current/reference/configuration/twig.html#cache
      // $twig_config['cache'] = FALSE;
      $container->setParameter('twig.config', $twig_config);
    }

  }

}
