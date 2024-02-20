/**
 * @file
 * Defines additional behaviour for multi-line autocompletes.
 */

(function($, Drupal) {

  'use strict';

  Drupal.behaviors.autocompleteMultiLineOutput = {
    attach: function (context, settings) {
      var $context = $(context);
      $context.find(".ui-autocomplete-input").each(function () {
        $(this).autocomplete("instance")._renderItem = function (ul, item) {
          var labelMarkup = "";
          if (item.label instanceof Array) {
            labelMarkup = "<div>";
            for (var i = 0; i < item.label.length; i++) {
              labelMarkup += "<div>";
              if (i === 0) {
                labelMarkup += "<strong>" + item.label[i] + "</strong>";
                item.value = item.value.replace('Array', item.label[i]);
              }
              else {
                labelMarkup += item.label[i];
              }
              labelMarkup += "</div>";
            }
            labelMarkup += "</div>";
          }
          else {
            labelMarkup = $('<a>').html(item.label);
          }

          return $("<li>")
            .append(labelMarkup)
            .appendTo(ul);
        };
      });

    }
  }
})(jQuery, Drupal);
