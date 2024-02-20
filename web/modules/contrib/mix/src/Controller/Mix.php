<?php

namespace Drupal\mix\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\StatusMessages;

/**
 * Returns responses for Mix routes.
 */
class Mix extends ControllerBase {

  /**
   * Check if is ready to use the Content sync features.
   *
   * @return bool
   *   True for ready, False for not.
   */
  public static function isContentSyncReady() {
    // Check if required modules are enabled by checking functions.
    // Can't use \Drupal::service('module_handler') here, because the container
    // wasn't ready when this is called in the MixContentSyncSubscriber.
    $isReady = function_exists('config_help') && function_exists('serialization_help');
    return $isReady;
  }

  /**
   * Check if content sync function is enabled.
   *
   * @return bool
   *   True for enabled, False for not.
   */
  public static function isContentSyncEnabled() {
    $configStorage = BootstrapConfigStorageFactory::get();
    $config = $configStorage->read('mix.settings');
    $show_content_sync_id = $config['show_content_sync_id'] ?? FALSE;
    $isEnabled = self::isContentSyncReady() && $show_content_sync_id;
    return $isEnabled;
  }

  /**
   * Parse Meta tags from a str.
   *
   * @param string $str
   *   A str contains <meta> tags.
   *
   * @return array
   *   An array of meta tags.
   *
   * @see https://www.php.net/manual/en/function.get-meta-tags.php#117766
   */
  public static function getMetaTags($str) {
    $pattern = '
    ~<\s*meta\s

    # using lookahead to capture type to $1
      (?=[^>]*?
      \b(name|property|http-equiv)\s*=\s*
      (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
      ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
    )

    # capture content to $2
    [^>]*?\bcontent\s*=\s*
      (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
      ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
    [^>]*>

    ~ix';

    if (preg_match_all($pattern, $str, $out)) {
      $metaTags = [];
      for ($i = 0; $i < count($out[0]); $i++) {
        $metaTags[] = [
          'attribute' => $out[1][$i],
          'value' => $out[2][$i],
          'content' => $out[3][$i],
        ];
      }
      return $metaTags;
    }
    return [];
  }

  /**
   * Modal form submit function.
   */
  public static function ajaxFormSubmit(array &$form, FormStateInterface $form_state) {

    // Used to display results of drupal_set_message() calls.
    $messages = StatusMessages::renderMessages(NULL);

    // Create AJAX Response object.
    $response = new AjaxResponse();

    // Display form with messages.
    $output = [];
    $output[] = $messages;
    $output[] = $form;
    $response->addCommand(new HtmlCommand(NULL, $output));

    // Close modal if no error.
    if (!$form_state->getErrors()) {
      $response->addCommand(new CloseDialogCommand());
    }

    return $response;
  }

}
