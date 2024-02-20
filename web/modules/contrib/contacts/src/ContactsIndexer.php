<?php

namespace Drupal\contacts;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\search_api\Utility\PostRequestIndexing;
use Drupal\search_api\Utility\Utility;
use Drupal\user\UserInterface;

/**
 * Service for indexing contacts.
 */
class ContactsIndexer {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The search_api.post_request_indexing service.
   *
   * @var \Drupal\search_api\Utility\PostRequestIndexing
   */
  protected $postRequestIndexing;

  /**
   * The Contacts Index, or FALSE if it does not exist.
   *
   * @var \Drupal\search_api\Entity\Index|false
   */
  protected $index;

  /**
   * Constructs a ContactsIndexer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\search_api\Utility\PostRequestIndexing $post_request_indexing
   *   The post request indexing service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PostRequestIndexing $post_request_indexing = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->postRequestIndexing = $post_request_indexing;
  }

  /**
   * Get the Contacts Index, if it exists.
   *
   * @return \Drupal\search_api\Entity\Index|false
   *   The index, or FALSE if it does not exist.
   */
  protected function getIndex() {
    if (!isset($this->index)) {
      $this->index = FALSE;
      if ($this->entityTypeManager->hasDefinition('search_api_index')) {
        /** @var \Drupal\search_api\IndexInterface $index */
        $index = $this->entityTypeManager
          ->getStorage('search_api_index')
          ->load('contacts_index');
        if ($index && $index->status() && !$index->isReadOnly()) {
          $this->index = $index;
        }
      }
    }
    return $this->index;
  }

  /**
   * Mark a user to be updated in the Contacts Index.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to mark for update.
   */
  public function markForUpdate(UserInterface $user) {
    // Exit as early as possible if we cannot do anything.
    $index = $this->getIndex();
    if (!$index) {
      return;
    }

    $index->trackItemsUpdated('entity:user', $this->buildItemIds($user, FALSE));
  }

  /**
   * Index a user immediately (happens post request).
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to index.
   * @param bool $skip_if_direct
   *   Skip indexing if the index is already set to index directly. Set to TRUE
   *   if Search API would have already triggered an index and we are simply
   *   ensuring it happens immediately rather than in the future.
   */
  public function indexImmediately(UserInterface $user, bool $skip_if_direct = FALSE) {
    // Exit as early as possible if we cannot do anything.
    $index = $this->getIndex();
    if (!$index) {
      return;
    }

    // If skipping direct, check if the index is already set to index directly
    // or the post request indexing is unavailable.
    if ($skip_if_direct && ($index->getOption('index_directly') || !$this->postRequestIndexing)) {
      // Nothing to do.
      return;
    }

    // If possible, register the items to be indexed post request.
    if ($this->postRequestIndexing) {
      $this->postRequestIndexing
        ->registerIndexingOperation('contacts_index', $this->buildItemIds($user, TRUE));
    }
    // Otherwise mark for update normally.
    else {
      $this->markForUpdate($user);
    }
  }

  /**
   * Build the item IDs for the index.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param bool $combined
   *   Whether to use the combined item IDs (with the datasource).
   *
   * @return array
   *   The user's item IDs.
   */
  protected function buildItemIds(UserInterface $user, bool $combined): array {
    $user_id = $user->id();
    $item_ids = [];
    foreach (array_keys($user->getTranslationLanguages()) as $langcode) {
      $item_id = "$user_id:$langcode";
      $item_ids[] = $combined ? Utility::createCombinedId('entity:user', $item_id) : $item_id;
    }
    return $item_ids;
  }

  /**
   * Update the user when a profile is modified.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The profile being inserted/updated/deleted.
   */
  public function profilePostSave(ProfileInterface $profile) {
    $user = $profile->getOwner();
    if (!$user) {
      return;
    }

    // The indiv/org profiles contains the name which is important for searches,
    // so index immediately if it's the individual profile.
    if (in_array($profile->bundle(), ['crm_indiv', 'crm_org'])) {
      $this->indexImmediately($user);
    }
    else {
      $this->markForUpdate($user);
    }
  }

}
