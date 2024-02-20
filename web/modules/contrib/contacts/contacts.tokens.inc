<?php

/**
 * @file
 * Token hook implementations for Contacts.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info_alter().
 */
function contacts_token_info_alter(&$data) {
  if (\Drupal::moduleHandler()->moduleExists('user_registrationpassword')) {
    $data['tokens']['user']['registrationpassword-url-with-destination'] = [
      'name' => t('Registration password URL (with destination)'),
      'description' => t('URL to confirm registration.'),
      'restricted' => TRUE,
    ];
  }
}

/**
 * Implements hook_tokens().
 */
function contacts_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'user' && !empty($data['user'])) {
    /** @var \Drupal\user\UserInterface $account */
    $account = $data['user'];
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'registrationpassword-url-with-destination':
          // We can't use a token alter to edit the existing
          // registrationpassword-url token to add in a destination as the
          // user_registrationpassword uses a token callback to overwrite any
          // modifications with the original value. We use our own token
          // instead, but make use of user_registrationpassword to do the work.
          // We also want to only output this token if the callback is set to
          // the user_passwordregistration mail tokens to avoid leaking
          // potentially sensitive tokens in other places.
          $callback = $options['callback'] ?? NULL;
          if ($callback == 'user_registrationpassword_mail_tokens' && \Drupal::moduleHandler()->moduleExists('user_registrationpassword')) {
            $url = user_registrationpassword_confirmation_url($account);

            // Use the destination from the query parameters, as if we have no
            // destination we want to let it behave as normal.
            if ($destination = \Drupal::request()->query->get('destination')) {
              $url .= '?destination=' . $destination;
            }

            $replacements[$original] = $url;
          }
          break;
      }
    }
  }

  return $replacements;
}
