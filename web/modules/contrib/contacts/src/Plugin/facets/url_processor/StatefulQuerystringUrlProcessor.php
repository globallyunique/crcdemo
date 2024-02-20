<?php

namespace Drupal\contacts\Plugin\facets\url_processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\url_processor\QueryString;

/**
 * Extends the default querystring processor to be stateful.
 *
 * Stores the filter information in session which will be used if nothing
 * is specified in the query string.
 *
 * @FacetsUrlProcessor(
 *   id = "query_string_stateful",
 *   label = @Translation("Query string + Stateful"),
 *   description = @Translation("Caches query string filters on first use and then will use the cached version if no filters are specified in the querystring.")
 * )
 */
class StatefulQuerystringUrlProcessor extends QueryString {

  /**
   * Session cache name.
   */
  const CACHE_KEY = 'search_api_filter_cache';

  /**
   * {@inheritdoc}
   */
  protected function initializeActiveFilters() {
    parent::initializeActiveFilters();

    $session = NULL;

    if ($this->request->hasSession()) {
      $session = $this->request->getSession();
    }
    if (!$session) {
      return;
    }

    $facet_source_id = $this->configuration['facet']->getFacetSourceId();
    $cache_key = self::getCacheKey($facet_source_id);

    // If no filters were found from the query string, fall back to the cache.
    if (empty($this->activeFilters) && !$this->request->query->get('clear_facet_search')) {
      if ($cached = $session->get($cache_key)) {
        foreach ($cached as $delta => $value) {
          $this->activeFilters[$delta] = $value;
        }
      }
    }
    else {
      // Filters were found in the querystring.
      // Update the cache to hold the new filters.
      $session->set($cache_key, $this->activeFilters);
    }
  }

  /**
   * Builds the cache key for a particular facet source.
   *
   * @param string $facet_source_id
   *   Facet source ID.
   *
   * @return string
   *   The cache key to use.
   */
  public static function getCacheKey($facet_source_id) {
    return self::CACHE_KEY . '|' . $facet_source_id;
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrls(FacetInterface $facet, array $results) {
    $results = parent::buildUrls($facet, $results);

    if (count($results)) {
      // Infer the view ID from the facet source ID.
      // @todo This is hacky and won't work if not using a view.
      $facet_source_id = $this->configuration['facet']->getFacetSourceId();
      [$type, $view_id, $display_id] = explode('__', $facet_source_id);
      $query_to_add = [];

      // Can't use $this->request->getSession()->get('views') because
      // views writes to $_SESSION['views'] directly, rather than to the
      // symfony sub-element, so we have to do the same.
      if (isset($_SESSION['views'][$view_id]['default'])) {
        foreach ($_SESSION['views'][$view_id]['default'] as $query_key => $value) {
          // If it's not in the querystring, then we want to add it to the link.
          if (!$this->request->query->get($query_key)) {
            $query_to_add[$query_key] = $value;
          }
        }
      }

      foreach ($results as $result) {
        // If there are cached views filters, make sure they are included
        // in the link.
        $url = $result->getUrl();
        $query = $url->getOption('query') ?? [];
        $query = array_merge($query, $query_to_add);

        // If there are no facet filters in the url, then it means that
        // clicking this link should remove the last one. We need to have a
        // marker for this though, otherwise the logic in
        // initializeActiveFilters will always try and re-add it.
        if (empty($query[$this->filterKey])) {
          $query['clear_facet_search'] = "1";
        }

        $url->setOption('query', $query);
      }
    }

    return $results;
  }

}
