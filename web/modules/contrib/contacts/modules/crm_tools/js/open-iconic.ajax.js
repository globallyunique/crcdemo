/**
 * @file
 * Enable AJAX based Open Iconic sprite loading.
 */

(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.openIconicSpriteAjaxLoad = {
    attach: function attach(context) {
      if (context !== document) {
        // We don't want this to run for evert ajax request, just run once
        // when the page loads.
        return;
      }

      if (typeof drupalSettings.contacts.openIconic.path !== 'undefined') {
        var svg = drupalSettings.contacts.openIconic.path;
        $.get(svg, function (data) {
          // Only create the sprite container if it's not been creatd already.
          if ($('#iconic-sprites').length === 0) {
            var div = document.createElement("div");
            div.setAttribute("id", "iconic-sprites");
            div.innerHTML = new XMLSerializer().serializeToString(data.documentElement);
            document.body.insertBefore(div, document.body.childNodes[0]);
          }
        });
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
