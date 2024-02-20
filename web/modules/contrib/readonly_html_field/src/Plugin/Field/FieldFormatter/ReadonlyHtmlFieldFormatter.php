<?php

namespace Drupal\readonly_html_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a field formatter of readonly html field.
 *
 * @FieldFormatter (
 *   id = "readonly_html_field_formatter",
 *   label = @Translation("Readonly Html field Formatter"),
 *   field_types = {"readonly_html_field"}
 * )
 */
class ReadonlyHtmlFieldFormatter extends FormatterBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $langcode = empty($langcode) ? $this->languageManager->getCurrentLanguage()->getId() : $langcode;
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $default_readonly_html = check_markup($this->fieldDefinition->getSetting($default_langcode)['readonly_html']['value'], $this->fieldDefinition->getSetting($default_langcode)['readonly_html']['format']);
    $readonly_html = check_markup($this->fieldDefinition->getSetting($langcode)['readonly_html']['value'], $this->fieldDefinition->getSetting($default_langcode)['readonly_html']['format']);
    return [
      '#markup' => !empty($readonly_html) ? $readonly_html : $default_readonly_html,
    ];
  }

}
