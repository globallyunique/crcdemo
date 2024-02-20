<?php

namespace Drupal\redhen_org\ContextProvider;

use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\redhen_org\Entity\Org;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Sets the current org as context on redhen org routes.
 *
 * @todo Remove once core gets a generic EntityRouteContext.
 */
class RedhenOrgRouteContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new RedhenOrgRouteContext object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $entity_context = EntityContext::fromEntityTypeId('redhen_org');
    $value = NULL;
    if ($org = $this->routeMatch->getParameter('redhen_org')) {
      $value = $org;
    }
    elseif ($this->routeMatch->getRouteName() == 'entity.redhen_org.add_form') {
      $org_type = $this->routeMatch->getParameter('redhen_org_type');
      $value = Org::create(['type' => $org_type->id()]);
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);
    $context = new Context($entity_context->getContextDefinition(), $value);
    $context->addCacheableDependency($cacheability);

    return ['redhen_org' => $context];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $context = EntityContext::fromEntityTypeId(
      'redhen_org', $this->t('Org from URL')
    );
    return ['redhen_org' => $context];
  }

}
