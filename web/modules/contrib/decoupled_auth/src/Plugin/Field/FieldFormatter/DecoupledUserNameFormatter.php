<?php

namespace Drupal\decoupled_auth\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\user\Plugin\Field\FieldFormatter\UserNameFormatter;

/**
 * Replacement user name formatter that allows formatting decoupled usernames.
 *
 * @see \Drupal\user\Plugin\Field\FieldFormatter\UserNameFormatter
 */
class DecoupledUserNameFormatter extends UserNameFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // The core handler only works if there is a name set as it loops over the
    // items. That doesn't work for decoupled users, so pull the user directly
    // from the items and assume only ever one delta.
    /** @var \Drupal\user\UserInterface $user */
    if ($user = $items->getEntity()) {
      if ($this->getSetting('link_to_entity')) {
        $elements[0] = [
          '#theme' => 'username',
          '#account' => $user,
          '#link_options' => ['attributes' => ['rel' => 'user']],
          '#cache' => [
            'tags' => $user->getCacheTags(),
          ],
        ];
      }
      else {
        $elements[0] = [
          '#markup' => $user->getDisplayName(),
          '#cache' => [
            'tags' => $user->getCacheTags(),
          ],
        ];
      }
    }

    return $elements;
  }

}
