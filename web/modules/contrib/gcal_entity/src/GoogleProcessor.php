<?php

namespace Drupal\gcal_entity;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Google\Client;
use Google\Service\Calendar\Event;
use Google_Service_Calendar;

/**
 * Class GoogleProcessor.
 */
class GoogleProcessor {

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Drupal\Core\Datetime\DateFormatterInterface definition
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface;
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a new GoogleProcessor object.
   */
  public function __construct(MessengerInterface $messenger, ConfigFactoryInterface $config_factory, DateFormatterInterface $date_formatter) {
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Fetch the gData feed and parse the XML
   *
   * @param string $calendar_id The calendars Google ID
   *
   * @return object|bool An object containing the status, request and result
   * @access private
   */
  public function load_google_calendar(string $calendar_id): ?object {

    $settings = $this->configFactory->get('gcal_entity.settings');
    $google_key = $settings->get('googleapi');
    if ($google_key == '') {
      $this->messenger->addMessage(t('GCal Entity: No googleapi key entered in settings. Go to /admin/config/gcal_entity/config to enter your API key'), $this->messenger::TYPE_ERROR);
      return FALSE;
    }

    // https://developers.google.com/google-apps/calendar/v3/reference
    $client = new Client();
    $client->setApplicationName('gcal_entity');
    $client->setDeveloperKey($google_key);

    $service = new Google_Service_Calendar($client);
    $optParams = [
      'orderBy' => 'startTime',
      'singleEvents' => 'true',
      'timeMin' => date('Y-m-d', strtotime($settings->get('start'))) . 'T00:00:00' . date('P'),
      'timeMax' => date('Y-m-d', strtotime($settings->get('end'))) . 'T00:00:00' . date('P'),
      'maxResults' => $settings->get('maxevents'),
      'timeZone' => $settings->get('timezone'),
    ];


    try {

      $events = $service->events->listEvents($calendar_id, $optParams);
    } catch (\Exception $e) {
      $this->messenger->addMessage(t('GCal Entity: Bad call to list events. Check Google API Key or Calendar Address.'), $this->messenger::TYPE_ERROR);
      $this->messenger->addMessage('<pre>' . $e . '</pre>', $this->messenger::TYPE_ERROR);
      return FALSE;
    }

    return $events;
  }


  /**
   * Read useful event information from XML chunk
   *
   * @param $google_response \Google\Service\Calendar\Event $xml An event node from a gData
   *   feed
   *
   * @return array
   * @throws \Exception
   */
  public function parse_event(Event $google_response): array {
    $settings = $this->configFactory->get('gcal_entity.settings');
    // Timezone
    $timezone = $settings->get('timezone');
    $timeformat = $settings->get('timeformat');
    $dateformat = $settings->get('dateformat');
    $tz = new \DateTimeZone($timezone);

    // Parse the timestamps
    $updated = new \DateTime($google_response->updated, $tz);
    //check if event is all day by looking from dateTime
    $start = new \DateTime((string) ($google_response->start->dateTime) ? $google_response->start->dateTime : $google_response->start->date, $tz);
    //need to minus 1 day from end date for all day events because end time is exclusive - https://developers.google.com/google-apps/calendar/v3/reference/events
    $end = new \DateTime((string) ($google_response->end->dateTime) ? $google_response->end->dateTime : date('Y-m-d', strtotime($google_response->end->date . " -1 day")), $tz);


    $event = [];
    $event['title'] = htmlspecialchars_decode((string) $google_response->summary);
    $event['where'] = htmlspecialchars_decode((string) $google_response->location);
    $event['description'] = _filter_autop(Xss::filter((string) $google_response->description));

    $event['timezone'] = $settings->get('timezone');

    $event['start_original'] = (string) $google_response->start->dateTime;
    $event['start_date'] = $this->format_date(strtotime($start->format('c')), $dateformat);
    $event['start_time'] = $this->format_date(strtotime($start->format('c')), $timeformat);
    $event['start_timestamp'] = strtotime($start->format('c')); // Use strtotime instead of getTimestamp for < PHP5.3

    $event['end_original'] = (string) $google_response->end->dateTime;
    $event['end_date'] = $this->format_date(strtotime($end->format('c')), $dateformat);
    $event['end_time'] = $this->format_date(strtotime($end->format('c')), $timeformat);
    $event['end_timestamp'] = strtotime($end->format('c'));

    // add special date display options
    $event['start_month'] = date('M', $event['start_timestamp']);
    $event['start_day'] = date('j', $event['start_timestamp']);

    // Published date and time are not available in v3.
    $event['updated'] = $this->format_date(strtotime($updated->format('c')), $dateformat);

    $event['url'] = (string) $google_response->htmlLink;

    $event['link'] = Link::fromTextAndUrl($settings->get('linktext'), Url::fromUri($event['url'] . '&ctz=' . $timezone))
      ->toString();

    // The day the event occurs on (without time) used for grouping
    $event['when'] = $start->format('Y-m-d');

    //new fields added for google api v3.
    $event['event_id'] = $google_response->id;
    $event['hangout_url'] = $google_response->hangoutLink;
    if ($event['hangout_url']) {
      $event['hangout_link'] = Link::fromTextAndUrl($settings->get('hangoutlinktext'), Url::fromUri($event['hangout_url']))
        ->toString();
    }
    else {
      $event['hangout_link'] = '';
    }
    $event['iCalUID'] = $google_response->iCalUID;
    $event['recurringEventId'] = $google_response->recurringEventId;
    $event['creator_email'] = $google_response->creator->email;
    $event['creator_displayName'] = $google_response->creator->displayName;
    $event['organizer_email'] = $google_response->organizer->email;
    $event['organizer_displayName'] = $google_response->organizer->displayName;

    if ($google_response->start->date) {
      $event['start_time'] = '';
    }
    if ($google_response->end->date) {
      $event['end_time'] = '';
    }

    if ($google_response->start->date && $google_response->end->date) {
      $event['allday'] = TRUE;
    }

    return $event;
  }

  /**
   * simplify the format date process for internal purposes.
   *
   * @param $date
   * @param $format
   *
   * @return string
   */
  private function format_date($date, $format): string {
    $type = 'custom';
    $settings = $this->configFactory->get('gcal_entity.settings');
    $timezone = $settings->get('timezone');
    return $this->dateFormatter->format($date, $type, $format, $timezone);
  }

  /**
   * strip an calendar id for just alphanumeric values
   *
   * @param $calendar_id
   *
   * @return string
   */
  public function get_cache_value($calendar_id): string {
    return preg_replace('/[^\w]/', '', $calendar_id);
  }

  /**
   * @param $key
   *
   * @return string
   */
  public function get_setting($key): string {
    $settings = $this->configFactory->get('gcal_entity.settings');
    return $settings->get($key);
  }

}
