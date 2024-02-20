<?php

namespace Drupal\contacts;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerParametersInterface;

/**
 * Extends the core PagerParameters.
 */
class ContactsPagerParameters implements PagerParametersInterface {

  /**
   * Core pager parameters.
   *
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  private PagerParametersInterface $corePagerParameters;

  /**
   * Decorating constructor.
   *
   * @param \Drupal\Core\Pager\PagerParametersInterface $core_pager_parameters
   *   Core implementation of pager parameters.
   */
  public function __construct(PagerParametersInterface $core_pager_parameters) {
    $this->corePagerParameters = $core_pager_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryParameters() {
    // Resolves issue: https://www.drupal.org/node/2925598
    // Fix pagination and sorting of tables on pages with ajax.
    // We're doing this with a service override rather than the patch
    // in the linked issue in order to be able to support Core 9/10.0 and 10.1
    // which have different code in the base class, so it's not possible
    // to have a patch that targets all versions.
    $query_params = $this->corePagerParameters->getQueryParameters();

    // Remove additional parameters from query string, if present.
    return UrlHelper::filterQueryParameters($query_params, [
      MainContentViewSubscriber::WRAPPER_FORMAT,
      FormBuilderInterface::AJAX_FORM_REQUEST,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function findPage($pager_id = 0) {
    return $this->corePagerParameters->findPage($pager_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPagerQuery() {
    return $this->corePagerParameters->getPagerQuery();
  }

  /**
   * {@inheritdoc}
   */
  public function getPagerParameter() {
    return $this->corePagerParameters->getPagerParameter();
  }

}
