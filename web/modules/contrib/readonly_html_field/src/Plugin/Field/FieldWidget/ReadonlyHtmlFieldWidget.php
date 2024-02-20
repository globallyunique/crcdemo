<?php

namespace Drupal\readonly_html_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a widget of readonly html field.
 *
 * @FieldWidget(
 *   id = "readonly_html_field_widget",
 *   label = @Translation("Readonly Html Field Widget"),
 *   field_types = {"readonly_html_field"}
 * )
 */
class ReadonlyHtmlFieldWidget extends WidgetBase {

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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $default_readonly_html = check_markup($this->fieldDefinition->getSetting($default_langcode)['readonly_html']['value'], $this->fieldDefinition->getSetting($default_langcode)['readonly_html']['format']);
    $readonly_html = check_markup($this->fieldDefinition->getSetting($langcode)['readonly_html']['value'], $this->fieldDefinition->getSetting($default_langcode)['readonly_html']['format']);
    $element = [
      '#markup' => !empty($readonly_html) ? $readonly_html : $default_readonly_html,
    ];
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $default_readonly_html = check_markup($this->fieldDefinition->getSetting($default_langcode)['readonly_html']['value'], $this->fieldDefinition->getSetting($default_langcode)['readonly_html']['format']);
    $readonly_html = check_markup($this->fieldDefinition->getSetting($langcode)['readonly_html']['value'], $this->fieldDefinition->getSetting($default_langcode)['readonly_html']['format']);
    $element = [
      '#markup' => !empty($readonly_html) ? $readonly_html : $default_readonly_html,
    ];
    return $element;
  }

}
