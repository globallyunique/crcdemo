<?php

namespace Drupal\contacts\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for the add contact forms.
 *
 * @deprecated in contacts:2.0.0 and is removed from contacts:3.0.0.
 *   This has been replaced by the AddContactForm.
 *
 * @see https://www.drupal.org/project/contacts/issues/3306887
 */
abstract class AddContactBase extends FormBase {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The widget plugin manager.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetManager;

  /**
   * The user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Validation violations for the user entity.
   *
   * @var \Drupal\Core\Entity\EntityConstraintViolationListInterface
   */
  protected $userViolations;

  /**
   * The profile entity.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $profile;

  /**
   * Validation violations for the profile entity.
   *
   * @var \Drupal\Core\Entity\EntityConstraintViolationListInterface
   */
  protected $profileViolations;

  /**
   * Construct the add contact form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\WidgetPluginManager $widget_manager
   *   The widget manager.
   */
  public function __construct(EntityTypeManager $entity_type_manager, EntityFieldManager $entity_field_manager, WidgetPluginManager $widget_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->widgetManager = $widget_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.widget'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // During the initial form build, add this form object to the form state and
    // allow for initial preparation before form building and processing.
    if (!$form_state->has('entity_form_initialized')) {
      $this->init($form_state);
    }

    $form['#parents'] = [];
    $form['#after_build'][] = '::afterBuild';
    $weight = 0;

    $user_fields = $this->entityFieldManager->getFieldDefinitions('user', 'user');
    foreach ($this->getUserFields($user_fields) as $field_name => $configuration) {
      $form[$field_name] = $this->getWidgetForm($this->user, $user_fields, $field_name, $form, $form_state, $configuration);
      $form[$field_name]['#entity_namespace'] = 'user';
      $form[$field_name]['#weight'] = $configuration['weight'] ?? $weight++;
    }

    $profile_fields = $this->entityFieldManager->getFieldDefinitions('profile', $this->profile->bundle());
    foreach ($this->getProfileFields($profile_fields) as $field_name => $configuration) {
      $form[$field_name] = $this->getWidgetForm($this->profile, $profile_fields, $field_name, $form, $form_state, $configuration);
      $form[$field_name]['#entity_namespace'] = 'profile';
      $form[$field_name]['#weight'] = $weight++;
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
    ];

    return $form;
  }

  /**
   * Initialize the form state and entities before the first form build.
   */
  protected function init(FormStateInterface $form_state): void {
    // Flag that this form has been initialized.
    $form_state->set('entity_form_initialized', TRUE);
    $this->user = $this->entityTypeManager->getStorage('user')->create();
  }

  /**
   * Get the user fields to be added to the form.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions
   *   The field definitions for the user. Modifications can be made here, such
   *   as setting fields to required.
   *
   * @return array
   *   Keys are the field name, values are the configuration for the widget.
   */
  protected function getUserFields(array $field_definitions): array {
    $field_definitions['mail']->setRequired(TRUE);
    return [
      'mail' => ['weight' => 0.5],
    ];
  }

  /**
   * Get the profile fields to be added to the form.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions
   *   The field definitions for the profile bundle. Modifications can be made
   *   here, such as setting fields to required.
   *
   * @return array
   *   Keys are the field name, values are the configuration for the widget.
   */
  abstract protected function getProfileFields(array $field_definitions): array;

  /**
   * Form element #after_build callback: Updates the entity with submitted data.
   *
   * Updates the internal entity objects with submitted values when the form is
   * being rebuilt (e.g. submitted via AJAX), so that subsequent processing
   * (e.g. AJAX callbacks) can rely on it.
   */
  public function afterBuild(array $element, FormStateInterface $form_state) {
    // Rebuild the entity if #after_build is being called as part of a form
    // rebuild, i.e. if we are processing input. As element validation may not
    // have happened at this stage, we simply copy the values from form state.
    if ($form_state->isProcessingInput()) {
      // Rebuild the entity if #after_build is being called as part of a form
      // rebuild, i.e. if we are processing input. As element validation may not
      // have happened at this stage, we simply copy the values from form state.
      if ($form_state->isProcessingInput()) {
        foreach (Element::children($element) as $field_name) {
          if (!isset($element[$field_name]['#entity_namepsace'])) {
            continue;
          }

          $namespace = $element[$field_name]['#entity_namespace'];
          if (!isset($this->{$namespace})) {
            continue;
          }

          $parents = $element[$field_name]['widget']['#parents'];
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entity = $this->{$namespace};
          if ($entity->hasField($field_name)) {
            $entity->set($field_name, $form_state->getValue($parents));
          }
        }
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The built and validated entities.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): array {
    $entities = $this->buildEntities($form, $form_state);

    foreach ($entities as $namespace => $entity) {
      $violations = $entity->validate();

      // Filter the violations by the fields we expose.
      $filter_fields = array_diff($violations->getFieldNames(), $this->getViolationFields($form, $namespace));
      $violations = $violations->filterByFields($filter_fields);

      // Flag entity level violations.
      foreach ($violations->getEntityViolations() as $violation) {
        /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
        $form_state->setErrorByName('', $violation->getMessage());
      }

      // Flag field level violations.
      foreach ($violations->getFieldNames() as $field_name) {
        foreach ($violations->getByField($field_name) as $violation) {
          $form_state->setErrorByName($field_name, $violation->getMessage());
        }
      }

      // Mark entity validation required properties.
      $entity->setValidationRequired(FALSE);
    }

    // Mark the form as validated.
    $form_state->setTemporaryValue('entity_validated', TRUE);

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->buildEntities($form, $form_state) as $property => $entity) {
      $this->{$property} = $entity;
    }

    $this->user->save();
    $this->profile->setOwner($this->user);
    $this->profile->save();
    $form_state->setRedirect('contacts.contact', [
      'user' => $this->user->id(),
    ]);
  }

  /**
   * Retrieve the widget form element.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity we are working with.
   * @param \Drupal\field\Entity\FieldConfig[] $field_definitions
   *   The field definitions for the given entity.
   * @param string $field_name
   *   The field name to get the widget for.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   (Optional) The widget plugin configuration.
   *
   * @return array
   *   The widget form array.
   */
  protected function getWidgetForm(FieldableEntityInterface $entity, array $field_definitions, $field_name, array $form, FormStateInterface $form_state, array $configuration = []) {
    $widget = $this->getWidget($field_definitions, $field_name, $configuration);
    $items = $entity->get($field_name);
    $items->filterEmptyItems();
    $element = $widget->form($items, $form, $form_state);
    $element['#widget_configuration'] = $configuration;
    return $element;
  }

  /**
   * Get the widget plugin for an entity field.
   *
   * @param \Drupal\field\Entity\FieldConfig[] $field_definitions
   *   The field definitions for the given entity.
   * @param string $field_name
   *   The field name to get the widget for.
   * @param array $configuration
   *   (Optional) The widget plugin configuration.
   *
   * @return \Drupal\Core\Field\WidgetInterface|null
   *   The field widget plugin.
   */
  protected function getWidget(array $field_definitions, $field_name, array $configuration = []) {
    $widget = $this->widgetManager->getInstance([
      'field_definition' => $field_definitions[$field_name],
      'configuration' => $configuration,
    ]);
    return $widget;
  }

  /**
   * Get fields in the form that require entity field validation.
   *
   * @param array $form
   *   The form array.
   * @param string $entity
   *   The entity to validate the field against.
   *
   * @return array
   *   Array of field names to validate.
   */
  protected function getViolationFields(array $form, string $entity) {
    $field_names = [];
    foreach (Element::children($form) as $field_name) {
      if ($entity === ($form[$field_name]['#entity_namespace'] ?? NULL)) {
        $field_names[] = $field_name;
      }
    }
    return $field_names;
  }

  /**
   * Copy form values onto our entities.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The built entities.
   */
  protected function buildEntities(array $form, FormStateInterface $form_state): array {
    $entities = [];

    foreach (['user', 'profile'] as $namespace) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = clone $this->{$namespace};
      $entity->setValidationRequired(!$form_state->getTemporaryValue('entity_validated'));

      $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

      foreach (Element::children($form) as $field_name) {
        if ($namespace !== ($form[$field_name]['#entity_namespace'] ?? NULL)) {
          continue;
        }

        $widget = $this->getWidget($fields, $field_name, $form[$field_name]['#widget_configuration']);
        $widget->extractFormValues($entity->get($field_name), $form, $form_state);
      }

      $entities[$namespace] = $entity;
    }

    return $entities;
  }

}
