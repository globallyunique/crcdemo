<?php

declare(strict_types = 1);

namespace Drupal\theme_rule\Form;

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\theme_rule\Entity\ThemeRule;
use Drupal\theme_rule\Entity\ThemeRuleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form class for theme rules.
 */
class ThemeRuleForm extends EntityForm {

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The condition plugin manager service.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   * @param \Drupal\Core\Condition\ConditionManager $condition_manager
   *   The condition plugin manager service.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository service.
   */
  public function __construct(ThemeHandlerInterface $theme_handler, ConditionManager $condition_manager, ContextRepositoryInterface $context_repository) {
    $this->themeHandler = $theme_handler;
    $this->conditionManager = $condition_manager;
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('theme_handler'),
      $container->get('plugin.manager.condition'),
      $container->get('context.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ThemeRuleInterface {
    // Extending the parent method only to allow proper return typing.
    /** @var \Drupal\theme_rule\Entity\ThemeRuleInterface $rule */
    $rule = parent::getEntity();
    return $rule;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $rule = $this->getEntity();

    // Store the gathered contexts in the form state for other objects to use
    // during form building.
    $form_state->setTemporaryValue('gathered_contexts', $this->contextRepository->getAvailableContexts());

    $form['#tree'] = TRUE;
    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $rule->label(),
      '#description' => $this->t('The human-readable name of this theme rule.'),
      '#required' => TRUE,
      '#size' => 30,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $rule->id(),
      '#disabled' => !$rule->isNew(),
      '#machine_name' => [
        'exists' => [ThemeRule::class, 'load'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this theme rule. It must only contain lowercase letters, numbers, and underscores.'),
    ];
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#default_value' => !$rule->isNew() ? $rule->getTheme() : NULL,
      '#options' => array_map(function (Extension $extension) {
        return $extension->info['name'];
      }, $this->themeHandler->listInfo()),
      '#required' => TRUE,
    ];
    $form['conditions'] = $this->buildConditions([], $form_state);
    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $rule->getWeight(),
      '#delta' => 10,
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $rule->status(),
    ];

    return $form;
  }

  /**
   * Helper function for building the conditions UI form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form array with the conditions UI added in.
   */
  protected function buildConditions(array $form, FormStateInterface $form_state): array {
    $form['condition_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Conditions'),
      '#parents' => ['condition_tabs'],
      '#attached' => [
        'library' => [
          'theme_rule/conditions',
        ],
      ],
    ];

    $config = $this->getEntity()->getConditionsConfig();
    // @see theme_rule_plugin_filter_condition__theme_rule_alter()
    $definitions = $this->conditionManager->getFilteredDefinitions('theme_rule', $form_state->getTemporaryValue('gathered_contexts'), [
      'theme_rule' => $this->getEntity(),
    ]);
    foreach ($definitions as $condition_id => $definition) {
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionManager->createInstance($condition_id, $config[$condition_id] ?? []);
      $form_state->set(['conditions', $condition_id], $condition);
      $condition_form = $condition->buildConfigurationForm([], $form_state);
      $condition_form['#type'] = 'details';
      $condition_form['#title'] = $condition->getPluginDefinition()['label'];
      $condition_form['#group'] = 'condition_tabs';
      $form[$condition_id] = $condition_form;
    }

    // These tweaks were copied from BlockForm::buildVisibilityInterface(). In
    // order to alter form element provided by non-core condition plugins, you
    // should alter the form itself.
    if (isset($form['node_type'])) {
      $form['node_type']['#title'] = $this->t('Content types');
      $form['node_type']['bundles']['#title'] = $this->t('Content types');
      $form['node_type']['negate']['#type'] = 'value';
      $form['node_type']['negate']['#title_display'] = 'invisible';
      $form['node_type']['negate']['#value'] = $form['node_type']['negate']['#default_value'];
    }
    if (isset($form['user_role'])) {
      $form['user_role']['#title'] = $this->t('Roles');
      unset($form['user_role']['roles']['#description']);
      $form['user_role']['negate']['#type'] = 'value';
      $form['user_role']['negate']['#value'] = $form['user_role']['negate']['#default_value'];
    }
    if (isset($form['request_path'])) {
      $form['request_path']['#title'] = $this->t('Pages');
      $form['request_path']['negate']['#type'] = 'radios';
      $form['request_path']['negate']['#default_value'] = (int) $form['request_path']['negate']['#default_value'];
      $form['request_path']['negate']['#title_display'] = 'invisible';
      $form['request_path']['negate']['#options'] = [
        $this->t('Show for the listed pages'),
        $this->t('Hide for the listed pages'),
      ];
    }
    if (isset($form['language'])) {
      $form['language']['negate']['#type'] = 'value';
      $form['language']['negate']['#value'] = $form['language']['negate']['#default_value'];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    // Proper cast status & weight.
    $form_state->setValue('status', (bool) $form_state->getValue('status'));
    $form_state->setValue('weight', (int) $form_state->getValue('weight'));
    $this->validateConditions($form, $form_state);
  }

  /**
   * Helper function to independently validate conditions.
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function validateConditions(array $form, FormStateInterface $form_state): void {
    // Validate conditions settings.
    foreach ($form_state->getValue('conditions') as $condition_id => $values) {
      // All condition plugins use 'negate' as a boolean in their schema.
      // However, certain form elements may return it as 0/1. Cast here to
      // ensure the data is in the expected type.
      if (array_key_exists('negate', $values)) {
        $form_state->setValue(['conditions', $condition_id, 'negate'], (bool) $values['negate']);
      }

      // Allow the condition to validate the form.
      $condition = $form_state->get(['conditions', $condition_id]);
      $condition->validateConfigurationForm($form['conditions'][$condition_id], SubformState::createForSubform($form['conditions'][$condition_id], $form, $form_state));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->submitConditions($form, $form_state);
    $this->getEntity()->save();
    $this->messenger()->addStatus($this->t('The theme rule configuration has been saved.'));
    $form_state->setRedirect('entity.theme_rule.collection');
  }

  /**
   * Helper function to independently submit the conditions.
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function submitConditions(array $form, FormStateInterface $form_state): void {
    foreach ($form_state->getValue('conditions') as $condition_id => $values) {
      // Allow the condition to submit the form.
      $condition = $form_state->get(['conditions', $condition_id]);
      $condition->submitConfigurationForm($form['conditions'][$condition_id], SubformState::createForSubform($form['conditions'][$condition_id], $form, $form_state));

      $condition_configuration = $condition->getConfiguration();
      // Update the visibility conditions on the theme rule.
      $this->getEntity()->getConditions()->addInstanceId($condition_id, $condition_configuration);
    }
  }

}
