<?php

namespace Drupal\gcal_entity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Site\Settings;

/**
 * Class GcalController.
 */
class GcalController extends ControllerBase {

  /**
   * Drupal\Core\Site\Settings definition.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected Settings $settings;

  /**
   * Constructs a new GcalController object.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('settings')
    );
  }

}
