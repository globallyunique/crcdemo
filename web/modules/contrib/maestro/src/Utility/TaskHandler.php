<?php

namespace Drupal\maestro\Utility;

use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use Drupal\maestro\Engine\MaestroEngine;

/**
 * Internal function that helps determine the handler type that is
 * used for interactive tasks.
 *
 * Returns either unknown, external, internal or function.
 */
class TaskHandler {

  /**
   * Get the type of task handler.
   */
  public static function getType($handler) {
    global $base_url;

    $handler = str_replace($base_url, '', $handler);

    $handler_type = 'unknown';
    $is_externalRoute = UrlHelper::isExternal($handler);
    if ($is_externalRoute === FALSE) {
      try {
        if (function_exists($handler)) {
          $handler_type = 'function';
        }
        else {
          $url = Url::fromUri("internal:" . $handler);
          if ($url && $url->isRouted()) {
            $handler_type = 'internal';
          }
        }
      }
      catch (\InvalidArgumentException $e) {
        $handler_type = 'unknown';
      }
    }
    else {
      $handler_type = 'external';
    }

    return $handler_type;
  }

  /**
   * Get the handler's URL.
   */
  public static function getHandlerURL($queueID) {
    global $base_url;
    $url = FALSE;
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
    $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($queueRecord->process_id->getString());
    //Do We have a token?  If so, let's use that in place of the direct QueueID.
    $queueToken = MaestroEngine::getTokenFromQueueId($queueID);
    \Drupal::logger('MaestroEngine')->info('getHandlerURL queueToken:' . $queueToken); // MV
    if($queueToken !== FALSE && $queueToken !== '') {
      $query_options = ['queueid_or_token' => $queueToken];
    }
    else {
      $query_options = ['queueid_or_token' => $queueID];
    }
  
    $handler = $queueRecord->handler->getString();
    if ($handler && !empty($handler) && $queueRecord->is_interactive->getString() == '1') {
      $handler = str_replace($base_url, '', $handler);
      $handler_type = TaskHandler::getType($handler);

      $handler_url_parts = UrlHelper::parse($handler);
      $query_options += $handler_url_parts['query'];

    }
    elseif ($queueRecord->is_interactive->getString() == '1' && empty($handler)) {
      // Handler is empty.  If this is an interactive task and has no handler, we're still OK.  This is an interactive function that uses a default handler then.
      $handler_type = 'function';
    }
    else {
      // This doesn't match, so return nothing.
      return FALSE;
    }
    $query_options += ['modal' => 'notmodal'];
    switch ($handler_type) {
      case 'external':
        $url = Url::fromUri($handler, ['query' => $query_options])->toString();
        break;

      case 'internal':
        $url = Url::fromUserInput($handler, ['query' => $query_options])->toString();
        break;

      case 'function':
        $url = Url::fromRoute('maestro.execute', $query_options, ['absolute' => TRUE])->toString();
        break;

      default:
        $url = FALSE;
        break;
    }
    \Drupal::logger('MaestroEngine')->info('getHandlerURL handler_type:' . $handler_type); // MV  
    \Drupal::logger('MaestroEngine')->info('getHandlerURL query_options:' . print_r($query_options, TRUE)); // MV

    return $url;
  }

}
