<?php

namespace Drupal\mix\EventSubscriber;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\EventSubscriber\FinalExceptionSubscriber;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Mix exception subscriber.
 */
class MixExceptionHtmlSubscriber extends FinalExceptionSubscriber {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Set a very low priority so this handler will catch any exceptions
    // not caught elsewhere.
    // @see Drupal\Core\EventSubscriber\FinalExceptionSubscriber
    $events[KernelEvents::EXCEPTION][] = ['onException', -255];
    return $events;
  }

  /**
   * Show a better error page when exception occurs.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event) {

    if (!$this->configFactory->get('mix.settings')->get('error_page.mode')) {
      parent::onException($event);
      return;
    }

    $exception = $event->getThrowable();
    $error = Error::decodeException($exception);

    // Display the message if the current error reporting level allows this type
    // of message to be displayed, and unconditionally in update.php.
    $message = '';
    if ($this->isErrorDisplayable($error)) {
      // If error type is 'User notice' then treat it as debug information
      // instead of an error message.
      if ($error['%type'] == 'User notice') {
        $error['%type'] = 'Debug';
      }

      $error = $this->simplifyFileInError($error);

      unset($error['backtrace'], $error['exception'], $error['severity_level']);

      if (!$this->isErrorLevelVerbose()) {
        // Without verbose logging, use a simple message.
        // We use \Drupal\Component\Render\FormattableMarkup directly here,
        // rather than use t() since we are in the middle of error handling, and
        // we don't want t() to cause further errors.
        $message = new FormattableMarkup(Error::DEFAULT_ERROR_MESSAGE, $error);
      }
      else {
        // With verbose logging, we will also include a backtrace.
        $backtrace_exception = $exception;
        while ($backtrace_exception->getPrevious()) {
          $backtrace_exception = $backtrace_exception->getPrevious();
        }
        $backtrace = $backtrace_exception->getTrace();
        // First trace is the error itself, already contained in the message.
        // While the second trace is the error source and also contained in the
        // message, the message doesn't contain argument values, so we output it
        // once more in the backtrace.
        array_shift($backtrace);

        // Generate a backtrace containing only scalar argument values.
        $error['@backtrace'] = Error::formatBacktrace($backtrace);
        $message = new FormattableMarkup(Error::DEFAULT_ERROR_MESSAGE . ' <pre class="backtrace">@backtrace</pre>', $error);
      }
    }

    $content_type = $event->getRequest()->getRequestFormat() == 'html' ? 'text/html' : 'text/plain';

    // Replace default content with configurable context.
    $render_array = [
      '#type' => 'inline_template',
      '#template' => $this->configFactory->get('mix.settings')->get('error_page.content'),
      '#context' => [],
    ];

    $content = \Drupal::service('renderer')->renderRoot($render_array);

    $content .= $message ? '<br><br>' . $message : '';
    $response = new Response($content, 500, ['Content-Type' => $content_type]);

    if ($exception instanceof HttpExceptionInterface) {
      $response->setStatusCode($exception->getStatusCode());
      $response->headers->add($exception->getHeaders());
    }
    else {
      $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR, '500 Service unavailable (with message)');
    }

    $event->setResponse($response);
  }

}
