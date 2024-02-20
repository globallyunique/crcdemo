<?php

declare(strict_types=1);

namespace Drupal\contacts_log;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Base class for adding log messages at the end of the request.
 */
class DestructableLoggerBase implements DestructableInterface {

  /**
   * The message storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $messageStorage;

  /**
   * The message type storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $messageTemplateStorage;

  /**
   * An array of template ids already checked.
   *
   * @var array
   */
  protected $hasTemplate = [];

  /**
   * An array of updates to log.
   *
   * Each item are the entity values to store. Bundle will be added.
   *
   * @var array
   */
  protected $updates = [];

  /**
   * Construct the destructable logger.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->messageStorage = $entity_type_manager->getStorage('message');
    $this->messageTemplateStorage = $entity_type_manager->getStorage('message_template');
  }

  /**
   * Check whether we have the template.
   *
   * @param string $template
   *   The template ID to look up.
   *
   * @return bool
   *   Whether the template exists.
   */
  protected function hasTemplate(string $template): bool {
    if (!isset($this->hasTemplate[$template])) {
      $this->hasTemplate[$template] = (bool) $this->messageTemplateStorage->load($template);
    }
    return $this->hasTemplate[$template];
  }

  /**
   * Create the log item.
   *
   * @param string $key
   *   The key used for de-duping in normal requests.
   * @param array $log_entry
   *   The log entry.
   */
  protected function log(string $key, array $log_entry): void {
    // If we are in cli (e.g. cron or migrate via drush) this could be a long
    // running process and we should log every item.
    if (\PHP_SAPI === 'cli') {
      $this->createMessage($log_entry);
    }
    // Otherwise we'll create the entry on destruct to avoid duplicates.
    else {
      if (isset($this->updates[$key])) {
        $this->updates[$key] = $this->mergeLog($this->updates[$key], $log_entry);
      }
      else {
        $this->updates[$key] = $log_entry;
      }
    }
  }

  /**
   * Merge the existing and new log entry.
   *
   * @param array $existing
   *   The existing log entry.
   * @param array $new
   *   The new log entry.
   *
   * @return array
   *   The log entry as it will be inserted.
   */
  protected function mergeLog(array $existing, array $new): array {
    // Merge, preferring values on the original. More complex behaviour can be
    // handled by overriding this method.
    return $existing + $new;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    foreach ($this->updates as $update) {
      $this->createMessage($update);
    }
  }

  /**
   * Create the message.
   *
   * @param array $update
   *   The message values from the updates array.
   */
  protected function createMessage(array $update): void {
    if (isset($update['template']) && $this->hasTemplate($update['template'])) {
      $this->messageStorage
        ->create($update)
        ->save();
    }
  }

}
