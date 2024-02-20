<?php

namespace Drupal\gcal_entity\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GcalConfigForm extends ConfigFormBase
{
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->configFactory = $config_factory;
  }

  protected function getEditableConfigNames(): array {
    return [
      'gcal_entity.settings',
    ];
  }

  public function getFormId(): string {
    return 'gcal_entity_admin_form';
  }
  
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('gcal_entity.settings');

    $form['googleapi'] = array(
      '#type' => 'textfield',
      '#title' => t('Google API Key'),
      '#default_value' => $config->get('googleapi'),
      '#size' => 39,
      '#maxlength' => 39,
      '#description' => t('Key for server applications - <a href="https://console.cloud.google.com/apis/api/calendar-json.googleapis.com">https://console.cloud.google.com/apis/api/calendar-json.googleapis.com</a>'),
      '#required' => TRUE,
      '#gcal_entity_setting' => TRUE,
    );

    $form['start'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Event start'),
      '#default_value'  => $config->get('start'),
      '#description'    => t('Any events that are older than this are not displayed. For example "-1 day" or "yesterday" will include events that occured yesterday, where as "now" will only include events that have not yet started. You can use any string <a href="http://php.net/strtotime">strtotime</a> can parse.'),
      '#required'       => TRUE,
      '#gcal_entity_setting' => TRUE,
    );

    $form['end'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Event end'),
      '#default_value'  => $config->get('end'),
      '#description'    => t('Any events newer than this are not displayed. For example, "+2 months" will display any events that occur in the next 2 months. You can use any string <a href="http://php.net/strtotime">strtotime</a> can parse.'),
      '#required'       => TRUE,
      '#gcal_entity_setting' => TRUE,
    );
    $form['datelimit'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Dates to display'),
      '#default_value'  => $config->get('datelimit'),
      '#description'    => t("The maximum number of unique dates to display."),
      '#required'       => TRUE,
      '#gcal_entity_setting' => TRUE,
    );

    $form['maxevents'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Maximum number of events to fetch'),
      '#default_value'  => $config->get('maxevents'),
      '#description'    => t("The maximum number of events to fetch."),
      '#required'       => TRUE,
      '#gcal_entity_setting' => TRUE,
    );

    $form['dateformat'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Date formatting'),
      '#default_value'  => $config->get('dateformat'),
      '#description'    => t('Specify the date format.'),
      '#required'       => TRUE,
      '#gcal_entity_setting' => TRUE,
    );

    $form['timeformat'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Time formatting'),
      '#default_value'  => $config->get('timeformat'),
      '#description'    => t('Specify the time format.'),
      '#required'       => TRUE,
      '#gcal_entity_setting' => TRUE,
    );

    $form['noeventstext'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Text to display if no events are found'),
      '#default_value'  => $config->get('noeventstext'),
      '#description'    => t('Leave blank to have the block hide when no events are found.'),
      '#gcal_entity_setting' => TRUE,
    );

    $form['linktext'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Calendar link text'),
      '#default_value'  => $config->get('linktext'),
      '#description'    => t('Text to display when linking to the Google Calendar event.'),
      '#gcal_entity_setting' => TRUE,
    );

    $form['hangoutlinktext'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Calendar hangout link text'),
      '#default_value'  => $config->get('hangoutlinktext'),
      '#description'    => t('Text to display when linking to the Google Calendar event Hangout.'),
      '#gcal_entity_setting' => TRUE,
    );

    $form['cachetime'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Amount of time to cache event data'),
      '#default_value'  => $config->get('cachetime'),
      '#description'    => t("How long (in seconds) the module should hold onto the ICS data before re-requesting it from Google."),
      '#required'       => TRUE,
      '#gcal_entity_setting' => TRUE,
    );

    $form['timezone'] = array(
      '#type'           => 'textfield',
      '#title'          => t('Timezone'),
      '#default_value'  => $config->get('timezone'),
      '#description'    => t('The timezone identifier to be used for this calendar, as described in <a href="http://php.net/timezones">the PHP manual</a>.'),
      '#required'       => TRUE,
      '#gcal_entity_setting' => TRUE,
    );

    $form['#theme'] = 'system_config_form';

    return parent::buildForm( $form,  $form_state);
  }

  function submitForm(array &$form, FormStateInterface $form_state): void {

    parent::submitForm($form, $form_state);
    $values = $form_state->getValues();
    $config = $this->config('gcal_entity.settings');
    foreach ($values as $key => $value) {
      if (is_string($value)) {
        $config->set($key, $form_state->getValue($key))->save();
      }
    }
  }
}
