<?php

namespace Drupal\mix\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides local tasks for the change password route.
 */
class MixLocalTask extends DeriverBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    // Only show the "Password" tab when the
    // "standalone password change page" is checked.
    $config = \Drupal::config('mix.settings');

    if ($config->get('standalone_password_page')) {
      $this->derivatives['mix.change_password_form'] = $base_plugin_definition;
      $this->derivatives['mix.change_password_form']['route_name'] = 'mix.change_password_form';
      $this->derivatives['mix.change_password_form']['title'] = $this->t('Password');
      $this->derivatives['mix.change_password_form']['base_route'] = 'entity.user.canonical';
      $this->derivatives['mix.change_password_form']['weight'] = 10;
    }

    return $this->derivatives;
  }

}
