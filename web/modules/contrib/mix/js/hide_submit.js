(function ($, Drupal) {
  Drupal.behaviors.hide_submit = {
    attach: function (context, settings) {
      // Disable the submit button when the form is submitted.
      $('form', context).on('submit', function(event) {
        // Bail out if the form contains validation errors.
        if ($.validator && !$(this).valid()) return;
        var form = $(this);
        // Create a disabled clone of the submit button, then hide the original one.
        // If we disable the button, the form won't know which button was clicked.
        $(this).find('input[type="submit"], button[type="submit"]').each(function (index) {
            // Create a disabled clone of the submit button
            $(this).clone(false).removeAttr('id').prop('disabled', true).insertBefore($(this));
            // Hide the actual submit button and move it to the beginning of the form
            $(this).hide();
            form.prepend($(this));
        });
      });
    }
  };
})(jQuery, Drupal);
