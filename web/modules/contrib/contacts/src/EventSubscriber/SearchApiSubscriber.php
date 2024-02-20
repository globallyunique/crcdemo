<?php

namespace Drupal\contacts\EventSubscriber;

use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Contacts event subscriber.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * The facet manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  private $facetManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The messenger.
   */
  public function __construct(DefaultFacetManager $facetManager) {
    $this->facetManager = $facetManager;
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();

    if ($query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      // If this is the 'simple' contact dashboard view, we want the facets from
      // the full source to be added.
      if ($query->getSearchId() == 'views_block:contacts_dashboard_indexed__simple') {
        $this->facetManager->alterQuery($query, 'search_api:views_block__contacts_dashboard_indexed__full');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'queryAlter',
    ];
  }

}
