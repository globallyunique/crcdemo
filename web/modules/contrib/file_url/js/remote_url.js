/**
 * @file
 * Improves the remote URL text field.
 */

(function (Drupal) {

  /**
   * Attach behaviors to the file URL auto add URL.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches triggers for the remote URL addition.
   * @prop {Drupal~behaviorDetach} detach
   *   Detaches triggers for the remote URL addition.
   */
  Drupal.behaviors.fileUrlRemoteUrlAdd = {
    attach(context) {
      once("auto-remote-url-add", "input[data-drupal-file-url-remote]", context).forEach((element) => {
        element.addEventListener('change', Drupal.file.triggerUploadButton);
      });
    },
    detach(context, setting, trigger) {
      if (trigger === 'unload') {
        once.remove("auto-remote-url-add", "input[data-drupal-file-url-remote]").forEach((element) => {
          element.removeEventListener('change', Drupal.file.triggerUploadButton);
        });
      }
    }
  };

})(Drupal);
