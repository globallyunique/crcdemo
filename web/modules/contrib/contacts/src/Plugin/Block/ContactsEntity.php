<?php

namespace Drupal\contacts\Plugin\Block;

use Drupal\contacts\Plugin\DashboardBlockInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block the user entity and any entity implementing ownership.
 *
 * Entity classes need to implement the EntityOwnerInterface and define the
 * contacts_entity property in their definition.
 *
 * @Block(
 *   id = "contacts_entity",
 *   category = @Translation("Dashboard Blocks"),
 *   deriver = "Drupal\contacts\Plugin\Deriver\ContactsEntityBlockDeriver",
 * )
 */
class ContactsEntity extends BlockBase implements ContainerFactoryPluginInterface, DashboardBlockInterface {

  const MODE_VIEW = 'view';
  const MODE_VIEW_NEW = 'view_new';
  const MODE_FORM = 'form';

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;


  /**
   * The tab manager.
   *
   * @var \Drupal\contacts\ContactsTabManager
   */
  protected $contactsTabManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    $plugin->formBuilder = $container->get('entity.form_builder');
    $plugin->request = $container->get('request_stack')->getCurrentRequest();
    $plugin->entityDisplayRepository = $container->get('entity_display.repository');
    $plugin->currentUser = $container->get('current_user');
    $plugin->classResolver = $container->get('class_resolver');
    $plugin->moduleHandler = $container->get('module_handler');
    $plugin->contactsTabManager = $container->get('contacts.tab_manager');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'mode' => self::MODE_VIEW,
      'create' => NULL,
      'operation' => 'contacts_dashboard',
      'view_mode' => 'contacts_dashboard',
      'edit_link' => $this->pluginDefinition['_has_forms'] ? self::EDIT_LINK_CONTENT : FALSE,
      'edit_id' => 'edit',
      'view_new_text' => '',
      'custom_access' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Get our entity from the context.
    $entity = $this->getContextValue('entity');
    if (!$entity) {
      $entity = $this->createEntity();
      if (!$entity) {
        return AccessResult::forbidden();
      }
    }

    // Get our mode.
    $op = $this->getMode($entity);

    // Allow custom access callbacks.
    if ($this->configuration['custom_access']) {
      $callable = $this->configuration['custom_access'];

      // Handle class/service methods.
      if (strpos($callable, '::') !== FALSE) {
        $callable = explode('::', $callable, 2);
        $callable[0] = $this->classResolver->getInstanceFromDefinition($callable[0]);
      }

      // If it's callable, do so.
      if (is_callable($callable)) {
        return call_user_func($callable, $this, $entity, $op, $account);
      }
      // Otherwise forbid access.
      else {
        return AccessResult::forbidden('Invalid custom access callback.');
      }
    }

    // Default to entity access controls.
    return $entity->access($op, $account, TRUE);
  }

  /**
   * Whether we should use edit links.
   *
   * @return bool
   *   Whether we should use edit links.
   */
  protected function useEditLink() {
    return $this->pluginDefinition['_has_forms'] && $this->configuration['edit_link'] && !empty($this->configuration['edit_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function getEditLink($mode) {
    // Check that we support and want edit links.
    if (!$this->useEditLink()) {
      return FALSE;
    }

    // Check we should show an edit link in this place.
    if ($this->configuration['edit_link'] != $mode) {
      return FALSE;
    }

    // If we are already in edit mode, don't show a link.
    if ($this->request->query->has('edit')) {
      return FALSE;
    }

    // If we are already in edit mode, don't show a link.
    if ($this->getMode() == 'update') {
      return FALSE;
    }

    if (empty($this->getContextValue('user')) || empty($this->getContextValue('subpage'))) {
      return FALSE;
    }

    $params = [
      'user' => $this->getContextValue('user')->id(),
      'subpage' => $this->getContextValue('subpage'),
    ];

    $options = ['query' => ['edit' => $this->configuration['edit_id']]];
    $link = Link::createFromRoute('Edit',
      'contacts.contact',
      $params,
      $options);

    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function processManageMode(array &$variables) {
    $definition = $this->getPluginDefinition();

    $bundle = $definition['_bundle_key'] ? $definition['_bundle_id'] : $definition['_entity_type_id'];
    $variables['attributes']['data-contacts-manage-entity-type'] = $variables['entity'] = $definition['_entity_type_id'];
    $variables['attributes']['data-contacts-manage-entity-bundle'] = $variables['bundle'] = $bundle;

    $variables['content']['links'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $this->getManageLinks(),
    ];

    if (!empty($definition['_required_hats'])) {
      $hats = [];
      // @todo Show hat icons instead of labels.
      foreach ($definition['_required_hats'] as $hat) {
        $hats[] = [
          '#theme' => 'crm_tools_hat',
          '#role' => $hat,
        ];
      }
      $variables['footer']['visible_hats'] = $hats;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // @todo Can we set the default mode to MODE_VIEW_NEW?
    $form['mode'] = [
      '#type' => 'select',
      '#options' => [
        self::MODE_FORM => $this->t('Form'),
        self::MODE_VIEW => $this->t('View existing'),
        self::MODE_VIEW_NEW => $this->t('View always'),
      ],
      '#title' => $this->t('Show as'),
      '#default_value' => $this->configuration['mode'],
    ];

    // If entity has bundles get bundle specific display modes.
    if ($this->pluginDefinition['_bundle_key']) {
      $view_mode_options = $this->entityDisplayRepository->getViewModeOptionsByBundle($this->pluginDefinition['_entity_type_id'], $this->pluginDefinition['_bundle_id']);
      $form_mode_options = $this->entityDisplayRepository->getFormModeOptionsByBundle($this->pluginDefinition['_entity_type_id'], $this->pluginDefinition['_bundle_id']);
    }
    else {
      $view_mode_options = $this->entityDisplayRepository->getViewModeOptions($this->pluginDefinition['_entity_type_id']);
      $form_mode_options = $this->entityDisplayRepository->getFormModeOptions($this->pluginDefinition['_entity_type_id']);
    }

    // @todo figure out how we can get the parent form structure.
    $form['view_mode'] = [
      '#type' => 'select',
      '#options' => $view_mode_options,
      '#title' => $this->t('View mode'),
      '#default_value' => $this->configuration['view_mode'],
      '#states' => [
        'visible' => [
          ':input[name="settings[mode]"]' => [
            '!value' => self::MODE_FORM,
          ],
        ],
      ],
    ];

    $form['operation'] = [
      '#type' => 'select',
      '#options' => $form_mode_options,
      '#title' => $this->t('Form mode'),
      '#default_value' => $this->configuration['operation'],
    ];

    $entity_type = $this->entityTypeManager->getDefinition($this->pluginDefinition['_entity_type_id']);
    $form['view_new_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('No existing entity text'),
      '#desciption' => $this->t("Optional text to display if viewing and there isn't already a @entity_type.", [
        '@entity_type' => $entity_type->getSingularLabel(),
      ]),
      '#default_value' => $this->configuration['view_new_text'],
      '#states' => [
        'visible' => [
          ':input[name="settings[mode]"]' => [
            'value' => self::MODE_VIEW_NEW,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['mode'] = $form_state->getValue('mode');
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
    $this->configuration['operation'] = $form_state->getValue('operation');
    $this->configuration['view_new_text'] = $form_state->getValue('view_new_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getContextValue('entity');

    // If we don't have an entity, attempt creation.
    if (!$entity) {
      $entity = $this->createEntity();

      // If we still have no entity, return an empty render array.
      if (!$entity) {
        return [
          'no_entity' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#markup' => $this->configuration['view_new_text'],
          ],
        ];
      }
    }

    if ($this->getMode($entity) == 'update') {
      return $this->buildForm($entity);
    }
    return $this->buildView($entity);
  }

  /**
   * Get the mode to show for the entity.
   *
   * @param null|\Drupal\Core\Entity\EntityInterface $entity
   *   The entity. If not provided, we will get it from the context.
   *
   * @return string
   *   The mode to use, either 'view' or 'update'.
   */
  protected function getMode(EntityInterface $entity = NULL) {
    $definition = $this->getPluginDefinition();
    $config = $this->getConfiguration();
    if (!$entity) {
      $entity = $this->getContextValue('entity');
    }

    // If there are no forms, always show a view.
    if (!$definition['_has_forms']) {
      return 'view';
    }

    // Show a form if we are form only.
    if ($config['mode'] == self::MODE_FORM) {
      return 'update';
    }

    // If we have requested this to be editable, show the form.
    if ($this->useEditLink() && $this->request->query->get('edit') == $this->configuration['edit_id']) {
      return 'update';
    }

    // View if the entity is not new or we want to view new entities.
    if (!$entity || !$entity->isNew() || $config['mode'] == self::MODE_VIEW_NEW) {
      return 'view';
    }

    return 'update';
  }

  /**
   * Create an entity, if the definition and config allow it.
   *
   * @return false|\Drupal\Core\Entity\EntityInterface
   *   The created entity or FALSE if the definition or config do not allow it.
   */
  protected function createEntity() {
    // Check our definition allows creation.
    $definition = $this->getPluginDefinition();
    if (!$definition['_allow_create']) {
      return FALSE;
    }

    // Check our config allows creation.
    $config = $this->getConfiguration();
    if (!$config['create']) {
      return FALSE;
    }

    // Check create access.
    $bundle = $definition['_bundle_key'] ? $definition['_bundle_id'] : NULL;
    $context = [];
    if (is_a($this->entityTypeManager->getDefinition($definition['_entity_type_id'])->getClass(), EntityOwnerInterface::class, TRUE)) {
      $user = $this->getContextValue('user');
      $context['owner'] = $user;
    }
    if (!$this->entityTypeManager->getAccessControlHandler($definition['_entity_type_id'])->createAccess($bundle, NULL, $context)) {
      return FALSE;
    }

    // Build our values.
    $values = [];

    // If this entity type has bundles, set the appropriate key.
    if ($bundle) {
      $values[$definition['_bundle_key']] = $bundle;
    }

    // Create the entity.
    $entity = $this->entityTypeManager->getStorage($definition['_entity_type_id'])->create($values);

    // If this has an owner, set it.
    if ($entity instanceof EntityOwnerInterface) {
      $entity->setOwner($this->getContextValue('user'));
    }

    return $entity;
  }

  /**
   * Build the view mode render array for the block.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   A render array containing the rendered entity.
   */
  protected function buildView(EntityInterface $entity) {
    $build = [];

    if ($entity->isNew()) {
      $build['no_entity'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->configuration['view_new_text'],
      ];
    }

    // Output an edit link if relevant.
    if ($link = $this->getEditLink(self::EDIT_LINK_CONTENT)) {
      $build['edit'] = $link->toRenderable();
    }
    elseif ($link = $this->getEditLink(self::EDIT_LINK_TITLE)) {
      $build['#title_edit'] = $link;
    }

    // Get the view builder.
    $definition = $this->getPluginDefinition();
    $config = $this->getConfiguration();
    $view_builder = $this->entityTypeManager->getViewBuilder($definition['_entity_type_id']);
    $build['view'] = $view_builder->view($entity, $config['view_mode']);

    return $build;
  }

  /**
   * Build the form mode render array for the block.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   A render array containing the form.
   */
  protected function buildForm(EntityInterface $entity) {
    // Manually build our action and redirect.
    $route_name = 'contacts.contact';
    $route_params = [
      'user' => $this->getContextValue('user')->id(),
      'subpage' => $this->getContextValue('subpage'),
    ];

    $options = ['query' => $this->request->query->all()];
    // @see \Drupal\Core\Form\FormBuilder::buildFormAction.
    unset($options['query'][FormBuilder::AJAX_FORM_REQUEST], $options['query'][MainContentViewSubscriber::WRAPPER_FORMAT]);

    // Build our redirect URL.
    unset($options['query']['edit']);
    $redirect = Url::fromRoute($route_name, $route_params, $options);

    // Get the form.
    $config = $this->getConfiguration();

    // Fall back to the default form if the requested one doesn't exist.
    if ($this->entityTypeManager->getDefinition($this->pluginDefinition['_entity_type_id'], TRUE)->getFormClass($config['operation'])) {
      $operation = $config['operation'];
    }
    else {
      $operation = 'default';
    }

    $form = $this->formBuilder->getForm($entity, $operation, [
      'redirect' => $redirect,
    ]);

    return ['form' => $form];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $definition = $this->getPluginDefinition();

    $entity_type = $this->entityTypeManager->getDefinition($this->pluginDefinition['_entity_type_id']);

    // Add the module which defines the entity type.
    $dependencies['module'][] = $entity_type->getProvider();

    // If we have a bundle for creating, add it's config dependencies.
    if ($definition['_bundle_key']) {
      $dependency = $entity_type->getBundleConfigDependency($definition['_bundle_id']);
      $dependencies[$dependency['type']][] = $dependency['name'];
    }

    return $dependencies;
  }

  /**
   * Get list of links to display on manage block.
   *
   * @return array
   *   Array of links to be based to an 'item_list' render array.
   */
  protected function getManageLinks() {
    $entity_id = $this->pluginDefinition['_entity_type_id'];
    $bundle_id = $this->pluginDefinition['_bundle_id'];
    $entity_definition = $this->entityTypeManager->getDefinition($entity_id);
    $bundle_type = $entity_definition->getBundleEntityType();
    $operations = [];
    // Add manage fields and display links if this entity type is the bundle
    // of another and that type has field UI enabled.
    if ($bundle_type) {
      $link_options = [
        'attributes' => ['target' => '_blank'],
      ];

      // @todo Solve generic entity access permission issue.
      $operations['manage-entity'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit profile type'),
        '#weight' => 10,
        '#url' => Url::fromRoute("entity.{$bundle_type}.edit_form", [
          $bundle_type => $bundle_id,
        ], $link_options),
      ];

      if ($entity_definition->get('field_ui_base_route') && $this->moduleHandler->moduleExists('field_ui')) {
        if ($this->currentUser->hasPermission('administer ' . $entity_id . ' fields')) {
          $operations['manage-fields'] = [
            '#type' => 'link',
            '#title' => $this->t('Manage fields'),
            '#weight' => 15,
            '#url' => Url::fromRoute("entity.{$entity_id}.field_ui_fields", [
              $bundle_type => $bundle_id,
            ], $link_options),
          ];
        }
        if ($this->currentUser->hasPermission('administer ' . $entity_id . ' form display')) {
          $operations['manage-form-display'] = [
            '#type' => 'link',
            '#title' => $this->t('Manage form display'),
            '#weight' => 20,
            '#url' => Url::fromRoute("entity.entity_form_display.{$entity_id}.default", [
              $bundle_type => $bundle_id,
            ], $link_options),
          ];
        }
        if ($this->currentUser->hasPermission('administer ' . $entity_id . ' display')) {
          $operations['manage-display'] = [
            '#type' => 'link',
            '#title' => $this->t('Manage display'),
            '#weight' => 25,
            '#url' => Url::fromRoute("entity.entity_view_display.{$entity_id}.default", [
              $bundle_type => $bundle_id,
            ], $link_options),
          ];
        }
      }
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function getManageMeta() {
    $meta = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'About this block',
    ];

    $definition = $this->getPluginDefinition();
    $entity_type_definition = $this->entityTypeManager->getDefinition($definition['_entity_type_id']);
    $entity_bundle_type = $entity_type_definition->getBundleEntityType();
    if ($entity_bundle_type && $definition['_bundle_id']) {
      $bundle_entity = $this->entityTypeManager->getStorage($entity_bundle_type)->load($definition['_bundle_id']);
    }

    $hats = [];

    if (isset($bundle_entity)) {
      $roles = user_roles();
      uasort($roles, 'contacts_sort_roles');
      $roles = array_intersect(array_keys($roles), $bundle_entity->getRoles());
      // @todo Show hat icons instead of labels.
      foreach ($roles as $role) {
        $hats[] = [
          '#theme' => 'crm_tools_hat',
          '#role' => $role,
        ];
      }
    }

    $meta['needed_hats'] = [
      '#theme' => 'item_list',
      '#items' => $hats,
      '#title' => 'Allowed for users with hats:',
    ];

    $tabs = $this->contactsTabManager->getTabsWithBlock($this->getPluginId());
    $meta['placed_tabs'] = [
      '#theme' => 'item_list',
      '#items' => $tabs,
      '#title' => 'Currently placed on tabs:',
    ];

    $meta['manage_links'] = [
      '#theme' => 'item_list',
      '#items' => $this->getManageLinks(),
      '#title' => 'Manage:',
    ];

    return $meta;
  }

}
