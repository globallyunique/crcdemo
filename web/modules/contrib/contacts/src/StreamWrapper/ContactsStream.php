<?php

namespace Drupal\contacts\StreamWrapper;

use Drupal\Core\StreamWrapper\LocalReadOnlyStream;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a Contacts (contacts://) stream wrapper class.
 *
 * Provides support for accessing Contact's files with the Drupal file
 * interface.
 *
 * @todo Remove if https://www.drupal.org/node/1308152 lands.
 */
class ContactsStream extends LocalReadOnlyStream {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->t("Contact's files");
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t("Contact's files served by the webserver.");
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    return \Drupal::service('extension.list.module')->getPath('contacts');
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    $path = str_replace('\\', '/', $this->getTarget());
    return $GLOBALS['base_url'] . '/' . \Drupal::service('extension.list.module')->getPath('contacts') . '/' . $path;
  }

}
