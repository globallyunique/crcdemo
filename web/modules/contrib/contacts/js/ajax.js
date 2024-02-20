/**
 * @file
 * Contacts' enhancements to core's AJAX system.
 *
 * @todo: Remove if https://www.drupal.org/node/2834834 goes in.
 */

(function ($, Drupal, once) {

  'use strict';

  /**
   * Override core's .use-ajax implementation.
   *
   * Bind Ajax functionality to links that use the 'use-ajax' class.
   *
   * @param {HTMLElement} element
   *   Element to enable Ajax functionality for.
   */
  Drupal.ajax.bindAjaxLinks = function (element) {
    const elements = once('ajax', '.use-ajax', element);

    for (let linkElement of elements) {
      const elementSettings = {
        progress: { type: linkElement.getAttribute('data-ajax-progress') || 'throbber' },
        dialogType: linkElement.getAttribute('data-dialog-type'),
        dialog: linkElement.getAttribute('data-dialog-options'),
        dialogRenderer: linkElement.getAttribute('data-dialog-renderer'),
        base: linkElement.id,
        element: linkElement
      }

      const href = linkElement.getAttribute('data-ajax-url') || linkElement.getAttribute('href');

      if (href) {
        elementSettings.url = href;
        elementSettings.event = 'click';
      }

      Drupal.ajax(elementSettings);
    }
  };

})(jQuery, Drupal, once);
