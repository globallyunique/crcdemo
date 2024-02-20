<?php

namespace Drupal\mix\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\mix\EventSubscriber\MixContentSyncSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Mix routes.
 */
class MixContentSyncController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Builds the ajax response.
   */
  public function ajaxCallback($content_sync_id, $action) {

    $ajaxResponse = new AjaxResponse();

    $config = $this->configFactory->getEditable('mix.settings');
    $content_sync_ids = $config->get('content_sync_ids');

    if ($action == 'add') {
      // Add to config.
      array_push($content_sync_ids, $content_sync_id);
    }
    else {
      // Find and delete.
      foreach ($content_sync_ids as $k => $v) {
        if ($content_sync_id == $v) {
          unset($content_sync_ids[$k]);
        }
      }
    }
    // Save configuration.
    self::presave($content_sync_ids);
    $config->set('content_sync_ids', $content_sync_ids)->save();

    $uuid = substr($content_sync_id, strrpos($content_sync_id, '.') + 1);
    $selector = '#mix-content-sync-' . $uuid;
    $content = self::getAjaxLink($content_sync_id);
    $ajaxResponse->addCommand(new ReplaceCommand($selector, $content));

    // Clear block content view caches.
    $tags = [
      'config:views.view.block_content',
    ];
    Cache::invalidateTags($tags);

    return $ajaxResponse;
  }

  /**
   * Get content sync widget.
   *
   * @param string $content_sync_id
   *   Content sync ID.
   */
  public static function getWidget(string $content_sync_id) {
    $output = '<span class="form-item__description">(Export as config: ' . self::getAjaxLink($content_sync_id) . ')</span>';
    return $output;
  }

  /**
   * Get content sync ajax link to operate content sync IDs.
   *
   * @param string $content_sync_id
   *   Content sync ID.
   */
  public static function getAjaxLink(string $content_sync_id) {
    $uuid = substr($content_sync_id, strrpos($content_sync_id, '.') + 1);
    $id = 'mix-content-sync-' . $uuid;

    $content_sync_ids = \Drupal::config('mix.settings')->get('content_sync_ids');
    if (!in_array($content_sync_id, $content_sync_ids)) {
      $action = 'add';
      $linkText = t('No');
    }
    else {
      $action = 'remove';
      $linkText = t('Yes');
    }

    $ajaxLink = '<a id="' . $id . '" href="/api/mix/content-sync/' . $content_sync_id . '/' . $action . '" class="use-ajax">' . $linkText . '</a></span>';

    return $ajaxLink;
  }

  /**
   * Prepare data before it be saved.
   *
   * @param array $content_sync_ids
   *   Content sync IDs.
   */
  public static function presave(array &$content_sync_ids) {
    $supportedEntityTypes = array_keys(MixContentSyncSubscriber::$supportedEntityTypeMap);
    // Remove invalid values.
    foreach ($content_sync_ids as $key => $content_sync_id) {
      if ($content_sync_id) {
        $entityType = MixContentSyncSubscriber::parseEntityType($content_sync_id);
        if (!$entityType || !in_array($entityType, $supportedEntityTypes)) {
          \Drupal::messenger()->addWarning(t('Remove invalid content sync ID: @content_sync_id', ['@content_sync_id' => $content_sync_id]));
          unset($content_sync_ids[$key]);
        }
      }
    }

    // Remove empty values, remove duplicated values, sort IDs.
    $content_sync_ids = array_unique(array_filter($content_sync_ids));
    sort($content_sync_ids);
  }

}
