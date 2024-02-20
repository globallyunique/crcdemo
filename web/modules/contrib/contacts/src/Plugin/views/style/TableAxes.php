<?php

namespace Drupal\contacts\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render a table using two columns for the axes.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "table_axes",
 *   title = @Translation("Table with axes"),
 *   help = @Translation("Display a table with columns for axes."),
 *   theme = "views_view_table_axes",
 *   display_types = {"normal"}
 * )
 */
class TableAxes extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['column'] = ['default' => ''];
    $options['row'] = ['default' => ''];
    $options['empty_cell'] = ['default' => ''];
    $options['sticky'] = ['default' => TRUE];
    $options['caption'] = ['default' => ''];
    $options['summary'] = ['default' => ''];
    $options['description'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $field_options = $this->getHandlerOptions('field');
    if (empty($field_options)) {
      $form['error_markup'] = [
        '#markup' => '<div class="messages messages--error">' . $this->t('You need at least one field before you can configure your table settings') . '</div>',
        '#weight' => -99,
      ];
      return;
    }

    $form['#parents'] = ['style_options'];
    $form['#tree'] = TRUE;

    $form['column'] = [
      '#type' => 'select',
      '#title' => $this->t('Columns'),
      '#description' => $this->t('Select the field that provides our columns.'),
      '#required' => TRUE,
      '#options' => $field_options,
      '#default_value' => $this->options['column'],
      '#weight' => -1,
    ];

    $form['row'] = [
      '#type' => 'select',
      '#title' => $this->t('Rows'),
      '#description' => $this->t('Select the field that provides our rows.'),
      '#required' => TRUE,
      '#options' => $field_options,
      '#default_value' => $this->options['row'],
      '#weight' => -1,
    ];

    $form['empty_cell'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty cell'),
      '#description' => $this->t('The contents for any empty cells.'),
      '#default_value' => $this->options['empty_cell'],
    ];

    $form['sticky'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Drupal style "sticky" table headers (Javascript)'),
      '#default_value' => !empty($this->options['sticky']),
      '#description' => $this->t('(Sticky header effects will not be active for preview below, only on live output.)'),
    ];

    $form['caption'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Caption for the table'),
      '#description' => $this->t('A title semantically associated with your table for increased accessibility.'),
      '#default_value' => $this->options['caption'],
      '#maxlength' => 255,
    ];

    $form['accessibility_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Table details'),
      '#parents' => ['style_options'],
    ];

    $form['accessibility_details']['summary'] = [
      '#title' => $this->t('Summary title'),
      '#type' => 'textfield',
      '#default_value' => $this->options['summary'],
      '#fieldset' => 'accessibility_details',
    ];

    $form['accessibility_details']['description'] = [
      '#title' => $this->t('Table description'),
      '#type' => 'textarea',
      '#description' => $this->t('Provide additional details about the table to increase accessibility.'),
      '#default_value' => $this->options['description'],
      '#states' => [
        'visible' => [
          'input[name="style_options[summary]"]' => ['filled' => TRUE],
        ],
      ],
      '#fieldset' => 'accessibility_details',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    return FALSE;
  }

  /**
   * Build an options list for a set of handlers.
   *
   * @param string $type
   *   The handler type.
   * @param string $class_name
   *   Optionally a class or interface the handler must be, extend or implement.
   *
   * @return array
   *   The handler options.
   */
  protected function getHandlerOptions($type, $class_name = NULL) {
    $options = [];
    foreach ($this->displayHandler->getHandlers($type) as $handler_id => $handler) {
      if (!$class_name || is_a($handler, $class_name)) {
        $options[$handler_id] = $handler->adminLabel(TRUE);
      }
    }
    return $options;
  }

}
