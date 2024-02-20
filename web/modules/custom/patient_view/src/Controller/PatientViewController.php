<?php

namespace Drupal\patient_view\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;

class PatientViewController extends ControllerBase {

  public function displayChecklist($pid,$nid) {
    $view = \Drupal\views\Views::getView('tasklist');
    if (is_object($view)) {
      $view->setDisplay('page_1');
      $view->setArguments([$pid,$nid]);
      $render_array = $view->render();
      return $render_array;
    }
  }

}
