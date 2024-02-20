<?php

namespace Drupal\contacts\Plugin\EntityReferenceSelection;

use Drupal\search_api\SearchApiException;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Entity reference selection for contacts with multi-line output.
 *
 * Note that the ID of this plugin is "search_api:user", meaning that consumers
 * will automatically end up using this handler if they set their selection
 * handler to just "search_api" and the entity type is set to "user" (ie they
 * don't have to explicitly set their handler to "search_api:user",
 * just "search_api"). Because of this, we don't want to risk accidentally
 * leaking user data (because the multi-line output includes addresses), so
 * there is also a setting to explicitly state which roles cause the multi-line
 * outputs to display.
 *
 * @EntityReferenceSelection(
 *   id = "search_api_user_multiline",
 *   label = @Translation("Contact selection (Multi-line user output)"),
 *   group = "search_api_user_multiline",
 *   entity_types = {"user"},
 *   weight = 99
 * )
 */
class MultilineUserSearchApiSelection extends SearchApiSelection {

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $query = $this->getQuery($match, $match_operator);

    if ($limit) {
      $query->range(0, $limit);
    }

    $results = $query->execute();

    if ($results->getResultCount() == 0) {
      return [];
    }

    $options = [];
    foreach ($results->getResultItems() as $result) {
      try {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $entity = $result->getOriginalObject()->getValue();
        $options[$entity->bundle()][$entity->id()] = $this->buildOutputLines($entity);
      }
      catch (SearchApiException $exception) {
        // This indicates an item is in the index but not the database, so
        // ignore it.
      }
    }
    return $options;
  }

  /**
   * Builds the autocomplete output for an entity.
   *
   * The default implemenetation will include the entity's label as the first
   * line, and if the entity has a crm_org profile will include the org address
   * as the second line.
   *
   * @param \Drupal\user\UserInterface $entity
   *   The entity to build the output for.
   *
   * @return array
   *   Array where each element is an output line to display.
   */
  protected function buildOutputLines(UserInterface $entity) : array {
    $output = [];

    $translated_entity = $this->entityRepository->getTranslationFromContext($entity);
    $label = (string) $translated_entity->label();

    if ($email = $entity->getEmail()) {
      $label .= " ($email)";
    }

    $output[] = $label;

    $address = $this->formatAddress($entity);

    if (!empty($address)) {
      $output[] = $address;
    }

    $this->moduleHandler->alter('contacts_multiline_autocomplete_output', $output, $entity);

    return $output;
  }

  /**
   * Builds the address line.
   *
   * @param \Drupal\user\Entity\User $entity
   *   The organisation.
   *
   * @return string
   *   The address string.
   */
  protected function formatAddress(User $entity) {
    if ($entity->hasRole('crm_org') && ($profile = $entity->profile_crm_org->entity)) {
      $address = $profile->crm_org_address;
    }
    elseif ($entity->hasRole('crm_indiv') && ($profile = $entity->profile_crm_indiv->entity)) {
      $address = $profile->crm_address;
    }

    if (isset($address)) {
      $to_format = array_filter([
        $address->address_line1,
        $address->address_line2,
        $address->locality,
        $address->administrative_area,
        $address->postal_code,
      ]);

      return implode(', ', $to_format);
    }

    return '';
  }

}
