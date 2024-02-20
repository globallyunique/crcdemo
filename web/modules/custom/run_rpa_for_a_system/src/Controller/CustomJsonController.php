<?php

namespace Drupal\run_rpa_for_a_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomJsonController extends ControllerBase {

  public function getJson() {
    $data = [
      'key1' => 'value1',
      'key2' => 'value2',
      // Add more data as needed
    ];

    return new JsonResponse($data);
  }

}