/**
 * Drupal.behaviors implementation to use with Storybook.
 *
 * This needs to be added to Storybook so that it could run scripts wrapped
 * in Drupal.behaviours.
 */

window.Drupal = { behaviors: {} };

(function (Drupal, drupalSettings) {
  Drupal.throwError = function (error) {
    setTimeout(function () {
      throw error;
    }, 0);
  };

  Drupal.attachBehaviors = function (context, settings) {
    context = context || document;
    settings = settings || drupalSettings;
    const behaviors = Drupal.behaviors;

    Object.keys(behaviors).forEach(function (i) {
      if (typeof behaviors[i].attach === 'function') {
        try {
          behaviors[i].attach(context, settings);
        } catch (e) {
          Drupal.throwError(e);
        }
      }
    });
  };
})(Drupal, window.drupalSettings);
