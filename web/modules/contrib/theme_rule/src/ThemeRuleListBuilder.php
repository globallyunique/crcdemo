<?php

declare(strict_types = 1);

namespace Drupal\theme_rule;

use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an entity list builder for theme rule config entities.
 */
class ThemeRuleListBuilder extends DraggableListBuilder {

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a new entity list builder instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, ThemeHandlerInterface $theme_handler) {
    parent::__construct($entity_type, $storage);
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('theme_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['help'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Usage:'),
      '#items' => [
        $this->t("The topmost rule whose conditions are met on a certain context (page, user role, etc) wins and the page is displayed with the rule's configured theme. Reorder rules by using drag and drop."),
        $this->t('Disabled rules and rules with no conditions are skipped'),
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'label' => $this->t('Rule name'),
      'theme' => $this->t('Theme'),
      'status' => $this->t('Status'),
      'conditions' => $this->t('Conditions'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $theme_rule): array {
    /** @var \Drupal\theme_rule\Entity\ThemeRuleInterface $theme_rule */
    return [
      'label' => $theme_rule->label(),
      'theme' => [
        'data' => [
          '#markup' => $this->themeHandler->getTheme($theme_rule->getTheme())->info['name'],
        ],
      ],
      'status' => [
        'data' => [
          '#markup' => $theme_rule->status() ? $this->t('Enabled') : $this->t('Disabled'),
        ],
      ],
      'conditions' => [
        '#theme' => 'item_list',
        '#items' => array_map(function (ConditionInterface $condition) {
          return $condition->summary();
        }, $theme_rule->getConditions()->getIterator()->getArrayCopy()),
      ],
    ] + parent::buildRow($theme_rule);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'theme_rule_collection_form';
  }

}
