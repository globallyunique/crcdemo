<?php

namespace Drupal\contacts_log;

use Drupal\simplenews\SubscriberInterface;

/**
 * Service to handle logging for simplenews changes.
 */
class SimplenewsLogger extends DestructableLoggerBase {

  /**
   * React to a subscriber being saved.
   *
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber entity.
   * @param \Drupal\simplenews\SubscriberInterface|null $original
   *   The original subscriber entity, if there was one.
   */
  public function postSave(SubscriberInterface $subscriber, ?SubscriberInterface $original = NULL): void {
    // Check the template early to avoid unnecessary processing.
    if (!$this->hasTemplate('contacts_log_simplenews')) {
      return;
    }

    $this->log((string) $subscriber->id(), [
      'template' => 'contacts_log_simplenews',
      'contact' => $subscriber->getUserId(),
      'subscriptions' => $subscriber->getSubscribedNewsletterIds(),
      'subscriptions_original' => $original ? $original->getSubscribedNewsletterIds() : [],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function mergeLog(array $existing, array $new): array {
    // Remove the originals to preserve the original from the first instance.
    unset($new['subscriptions_original']);

    // Merge, preferring values on the newer item.
    return $new + $existing;
  }

  /**
   * {@inheritdoc}
   */
  protected function createMessage($update): void {
    // Only create if the subscriptions have changed.
    $original_ids = $update['subscriptions_original'] ?? [];
    sort($original_ids);
    $new_ids = $update['subscriptions'] ?? [];
    sort($new_ids);

    if ($original_ids != $new_ids) {
      parent::createMessage($update);
    }
  }

}
