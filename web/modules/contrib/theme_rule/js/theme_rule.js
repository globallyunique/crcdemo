/**
 * @file
 * Drupal behaviours for Theme Negotiation by Rules module.
 */

(function ($, window, Drupal) {
  Drupal.behaviors.themeRuleConditions = {
    attach: function attach() {
      if (typeof $.fn.drupalSetSummary === 'undefined') {
        return;
      }

      function checkboxesSummary(context) {
        var vals = [];
        var $checkboxes = $(context).find('input[type="checkbox"]:checked + label');
        var il = $checkboxes.length;

        for (var i = 0; i < il; i++) {
          vals.push($($checkboxes[i]).html());
        }

        if (!vals.length) {
          vals.push(Drupal.t('Not restricted'));
        }

        return vals.join(', ');
      }

      $('[data-drupal-selector="edit-conditions-node-type"], [data-drupal-selector="edit-conditions-language"], [data-drupal-selector="edit-conditions-user-role"]').drupalSetSummary(checkboxesSummary);
      $('[data-drupal-selector="edit-conditions-request-path"]').drupalSetSummary(function (context) {
        var $pages = $(context).find('textarea[name="conditions[request_path][pages]"]');

        if (!$pages.val()) {
          return Drupal.t('Not restricted');
        }

        return Drupal.t('Restricted to certain pages');
      });
    }
  };
})(jQuery, window, Drupal);
