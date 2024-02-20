<?php

namespace Drupal\file_url\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\file_url\FileUrlHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'file_default' formatter.
 *
 * @FieldFormatter(
 *   id = "file_url_default",
 *   label = @Translation("Generic file"),
 *   field_types = {
 *     "file_url",
 *   },
 * )
 */
class FileUrlFormatter extends FileFormatterBase {

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a new formatter plugin instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'mode' => 'link',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode'),
      '#options' => [
        'link' => $this->t('Link (file and extension as link text)'),
        'plain' => $this->t('Plain URL'),
      ],
      '#default_value' => $this->getSetting('mode'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    switch ($this->getSetting('mode')) {
      case 'link':
        $summary[] = $this->t('Link to file with file name and extension as text');
        break;

      case 'plain':
        $summary[] = $this->t('Plain URL');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $item = $file->_referringItem;
      if ($this->getSetting('mode') === 'plain') {
        $elements['delta'] = [
          $elements[$delta] = [
            '#markup' => $this->fileUrlGenerator->generateString($file->getFileUri()),
            '#cache' => [
              'tags' => $file->getCacheTags(),
            ],
          ],
        ];
      }
      else {
        $elements[$delta] = [
          '#theme' => 'file_link',
          '#file' => $file,
          '#description' => $item->description,
          '#cache' => [
            'tags' => $file->getCacheTags(),
          ],
        ];
      }

      // Pass field item attributes to the theme function.
      if (isset($item->_attributes)) {
        $elements[$delta] += ['#attributes' => []];
        $elements[$delta]['#attributes'] += $item->_attributes;
        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }

      // Allow showing the full URI as tip.
      // @todo Probably the UX/UI team should decide if the full URL should be
      //   permanently displayed when showing distributions.
      $elements[$delta]['#attributes']['title'] = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items): void {
    // Collect entity IDs to load. For performance, we want to use a single
    // "multiple entity load" to load all the entities for the multiple
    // "entity reference item lists" being displayed. We thus cannot use
    // \Drupal\Core\Field\EntityReferenceFieldItemList::referencedEntities().
    foreach ($entities_items as $items) {
      foreach ($items as $item) {
        // To avoid trying to reload non-existent entities in
        // getEntitiesToView(), explicitly mark the items where $item->entity
        // contains a valid entity ready for display. All items are initialized
        // at FALSE.
        $item->_loaded = FALSE;
        if ($this->needsEntityLoad($item)) {
          $file = FileUrlHandler::urlToFile($item->target_id);
          $entities[$item->target_id] = $file;
        }
      }
    }

    // For each item, pre-populate the loaded entity in $item->entity, and set
    // the 'loaded' flag.
    foreach ($entities_items as $items) {
      foreach ($items as $item) {
        if (isset($entities[$item->target_id])) {
          $item->entity = $entities[$item->target_id];
          $item->_loaded = TRUE;
        }
        elseif ($item->hasNewEntity()) {
          $item->_loaded = TRUE;
        }
      }
    }
  }

}
