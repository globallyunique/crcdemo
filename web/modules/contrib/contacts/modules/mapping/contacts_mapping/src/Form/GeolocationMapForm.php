<?php

namespace Drupal\contacts_mapping\Form;

use Drupal\contacts_mapping\Plugin\Field\FieldType\GeofieldItemList;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\geofield\WktGeneratorInterface;
use Drupal\Leaflet\LeafletService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for geo-location map.
 */
class GeolocationMapForm extends FormBase {

  use StringTranslationTrait;

  /**
   * Leaflet service.
   *
   * @var \Drupal\Leaflet\LeafletService
   */
  protected $leafletService;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The WKT format Generator service.
   *
   * @var \Drupal\geofield\WktGeneratorInterface
   */
  protected $wktGenerator;

  /**
   * Constructs a new BlockContentBlock.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Leaflet\LeafletService $leaflet_service
   *   The Leaflet service.
   * @param \Drupal\geofield\WktGeneratorInterface $wkt_generator
   *   The WKT format Generator service.
   */
  public function __construct(RendererInterface $renderer, LeafletService $leaflet_service, WktGeneratorInterface $wkt_generator) {
    $this->renderer = $renderer;
    $this->leafletService = $leaflet_service;
    $this->wktGenerator = $wkt_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('leaflet.service'),
      $container->get('geofield.wkt_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contacts_geolocation_map';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $geo_latitude = NULL;
    $geo_longitude = NULL;

    // Get form arguments from form state.
    $geofield_machine_name = $form_state->getBuildInfo()['args'][0];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getBuildInfo()['args'][1];

    if ($entity && $entity->hasField($geofield_machine_name)) {
      $geofield = $entity->get($geofield_machine_name);

      $geo_latitude = $geofield->lat;
      $geo_longitude = $geofield->lon;
    }

    if ($this->validateLatLong($geo_latitude, $geo_longitude)) {

      // Define map features from lat/lng.
      $features = [
        [
          'type' => 'point',
          'lat' => $geo_latitude,
          'lon' => $geo_longitude,
        ],
      ];

      // Set map type (default leaflet OSM)
      $settings['leaflet_map'] = 'OSM Mapnik';

      // Set $map array with leafletMapGetInfo.
      $map = $this->leafletService->leafletMapGetInfo($settings['leaflet_map']);

      // Make some settings alterations.
      $map['settings']['zoom'] = 6;
      $map['settings']['scrollWheelZoom'] = FALSE;

      // Render the map.
      $result = $this->leafletService->leafletRenderMap($map, $features, $height = '200px');

      $form['contacts_geolocation_map'] = [
        '#markup' => $this->renderer->render($result),
      ];
    }

    // Only allow overriding if field is correct type.
    if ($geofield instanceof GeofieldItemList) {
      $form['geolocation'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Geolocation'),
      ];

      $form['geolocation']['override'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override geolocation'),
        '#default_value' => isset($geofield) && $geofield->computed,
      ];

      $form['geolocation']['contact_geolocation'] = [
        '#type' => 'container',
        '#states' => [
          // Show the settings if 'bar' has been selected for 'foo'.
          'visible' => [
            ':input[name="override"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['geolocation']['contact_geolocation']['contact_geo_lat'] = [
        '#type' => 'number',
        '#title' => $this->t('Latitude'),
        '#min' => '-90',
        '#max' => '90',
        '#step' => '0.000000000001',
        '#required' => TRUE,
        '#default_value' => $geo_latitude,
      ];
      $form['geolocation']['contact_geolocation']['contact_geo_long'] = [
        '#type' => 'number',
        '#title' => $this->t('Longitude'),
        '#min' => '-180',
        '#max' => '180',
        '#step' => '0.000000000001',
        '#required' => TRUE,
        '#default_value' => $geo_longitude,
      ];

      $form['geolocation']['actions']['#type'] = 'actions';
      $form['geolocation']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get form arguments from form state.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getBuildInfo()['args'][1];
    $geofield_machine_name = $form_state->getBuildInfo()['args'][0];

    if (!$entity->hasField($geofield_machine_name)) {
      return;
    }

    $values = $form_state->getValues();
    $geofield = $entity->get($geofield_machine_name);

    // Check if field is overridden.
    if (!empty($values['override'])) {
      $arr_geo_lat_long = [
        'long' => $values['contact_geo_long'],
        'lat' => $values['contact_geo_lat'],
      ];

      $geo_location_wkt = $this->wktGenerator->wktBuildPoint($arr_geo_lat_long);
      $geofield_point = [
        'value' => $geo_location_wkt,
        'computed' => TRUE,
      ];
      $geofield->setValue([$geofield_point]);
    }
    else {
      /** @var \Drupal\contacts_mapping\Plugin\Field\FieldType\GeofieldItemList $geofield */
      // If not overridden recalculate from source.
      $geofield->computed = FALSE;
      $geofield->updateGeoFromSource();
    }

    $entity->save();
  }

  /**
   * Validates a given coordinate.
   *
   * @param float|int|string $lat
   *   Latitude.
   * @param float|int|string $long
   *   Longitude.
   *
   * @return bool
   *   `true` if the coordinate is valid, `false` if not
   */
  public function validateLatLong($lat = NULL, $long = NULL) {
    if (!isset($lat) || !isset($long)) {
      return FALSE;
    }

    return preg_match('/(^[-+]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?)),\s*([-+]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))$/', $lat . ',' . $long);
  }

}
