<?php

namespace Drupal\readonly_html_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinition;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a field type of readonly html field.
 *
 * @FieldType(
 *   id = "readonly_html_field",
 *   label = @Translation("Readonly Html field"),
 *   default_formatter = "readonly_html_field_formatter",
 *   default_widget = "readonly_html_field_widget",
 *   category=@Translation("Text")
 * )
 */
class ReadonlyHtmlFieldItem extends FieldItemBase {

  /**
   * {@inheritDoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['value'] = FieldDefinition::create('string');
    return $properties;
  }

  /**
   * {@inheritDoc}
   */
  public static function defaultFieldSettings() {
    $languages = \Drupal::languageManager()->getLanguages();
    $element = [];
    foreach ($languages as $langcode => $language) {
      $element[$langcode] = [
        'readonly_html' => [
          'value' => '',
          'format' => 'basic_html',
        ],
      ];
    }
    return $element + parent::defaultFieldSettings();
  }

  /**
   * {@inheritDoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $languages = \Drupal::languageManager()->getLanguages();
    $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    // Render default language text field firstly.
    $default_language = $languages[$default_langcode];
    unset($languages[$default_langcode]);
    $languages = [$default_langcode => $default_language] + $languages;
    foreach ($languages as $langcode => $language) {
      $form[$langcode] = [
        '#type' => 'details',
        '#title' => $language->getName() . ' ' . ($language->isDefault() ? $this->t('(Default)') : '(Translation)'),
        '#tree' => TRUE,
        '#open' => $language->isDefault(),
        '#required' => $language->isDefault(),
      ];
      $form[$langcode]['readonly_html'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Readonly Html'),
        '#default_value' => $this->getSetting($langcode)['readonly_html']['value'],
        '#format' => $this->getSetting($langcode)['readonly_html']['format'],
      ];
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'text',
          'size' => 'tiny',
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function isEmpty() {
    return TRUE;
  }

}
