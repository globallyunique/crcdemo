<?php

namespace Drupal\contacts_group\Plugin\Block;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\contacts\Dashboard;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides an Organisation relationship form block.
 *
 * @Block(
 *   id = "contacts_org_relationship_form",
 *   deriver = "Drupal\contacts_group\Plugin\Derivative\GroupContentEnablerDeriver",
 *   category = @Translation("Dashboard Blocks"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User")
 *     ),
 *   },
 * )
 */
class ContactOrgRelationshipFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Provides constants.
   *
   * Constants to help the block determine which end of the relationship has
   * been provided to the form.
   */
  const PROVIDES_GROUP = 'group';
  const PROVIDES_CONTENT = 'content';
  private const PROVIDES_LEGACY = [
    'member' => self::PROVIDES_CONTENT,
  ];

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
   * The Contacts Dashboard helper.
   *
   * @var \Drupal\contacts\Dashboard
   */
  protected $dashboard;

  /**
   * The group content enabler plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $groupContentEnablerManager;

  /**
   * Constructs a new ContactOrgRelationshipFormBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $form_builder
   *   The entity form builder.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\contacts\Dashboard $dashboard
   *   The contacts dashboard helper.
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $group_content_enabler_manager
   *   The group content enabler manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $form_builder, RequestStack $request_stack, Dashboard $dashboard, GroupContentEnablerManagerInterface $group_content_enabler_manager) {
    // For blocks saved prior to move to multiple enablers, copy member_roles
    // over to content_roles and trigger a deprecation.
    if (!empty($configuration['member_roles'])) {
      @trigger_error("Configuration 'member_roles' is deprecated in contacts:8.x-1.0 and is removed from contacts:2.0.0. Use 'content_roles' instead.", \E_USER_DEPRECATED);
      $configuration['content_roles'] = $configuration['member_roles'];
      unset($configuration['member_roles']);
    }
    // That change also switched from 'member' to 'content', so trigger a
    // deprecation and fix tweak the configuration.
    if (isset(self::PROVIDES_LEGACY[$configuration['provides']])) {
      $old_value = $configuration['provides'];
      $configuration['provides'] = self::PROVIDES_LEGACY[$configuration['provides']];
      @trigger_error("Configuration '{$old_value}' is deprecated in contacts:8.x-1.0 and is removed from contacts:2.0.0. Use '{$configuration['provides']}' instead.", \E_USER_DEPRECATED);
    }

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->request = $request_stack->getCurrentRequest();
    $this->dashboard = $dashboard;
    $this->groupContentEnablerManager = $group_content_enabler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('request_stack'),
      $container->get('contacts.dashboard'),
      $container->get('plugin.manager.group_content_enabler')
    );
  }

  /**
   * Get the id of the group content enabler.
   *
   * @return string
   *   The plugin id of the group content enabler.
   */
  protected function getGroupContentEnabler() {
    return $this->getPluginDefinition()['group_content_enabler'];
  }

  /**
   * Get the query key.
   *
   * This is down to allow multiple block on the same page with different
   * enablers.
   *
   * @return string
   *   The query key to distinguish this block.
   */
  protected function getQueryKey() {
    return $this->configuration['query_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'query_key' => $this->getPluginDefinition()['group_content_enabler'] . '--org',
      'provides' => static::PROVIDES_CONTENT,
      'content_roles' => [],
      'organisation_roles' => ['crm_org'],
      'show_add' => TRUE,
      'add_title' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getContextValue('user');
    if ($this->configuration['provides'] != static::PROVIDES_GROUP || $user->hasRole('crm_org')) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $content = $this->getGroupContent();
    if (!$content) {
      return '';
    }

    switch ($this->configuration['provides']) {
      case static::PROVIDES_CONTENT:
        $entity = $content->getGroup();
        break;

      case static::PROVIDES_GROUP:
        $entity = $content->getEntity();
        break;
    }

    return $entity ? $entity->label() : $this->t('Add relationship');
  }

  /**
   * Get the URL for the block.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  protected function getUrl() {
    // If this is the dashboard, get the full page URL.
    if ($this->dashboard->isDashboard()) {
      return $this->dashboard->getFullUrl();
    }

    // Otherwise use the current URL.
    return Url::fromRoute('<current>', [
      'user' => $this->getContextValue('user')->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // If we have a param, show the form.
    if ($group_content = $this->getGroupContent()) {
      return $this->buildForm($group_content);
    }

    // Otherwise show the add link.
    $build = [];

    $url = $this->getUrl();
    $query = $url->getOption('query');
    $query[$this->getQueryKey()] = 'add';
    $url->setOption('query', $query);

    if ($this->configuration['show_add']) {
      $build['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add @enabler relationship', [
          '@enabler' => $this->getGroupContentEnabler(),
        ]),
        '#url' => $url,
        '#attributes' => [
          'class' => ['button'],
        ],
      ];

      if ($this->configuration['provides'] == static::PROVIDES_GROUP) {
        if ($content_roles = $this->configuration['content_roles']) {
          $filter_indiv = in_array('crm_indiv', $content_roles);
          $filter_orgs = in_array('crm_org', $content_roles);
          if ($filter_indiv && !$filter_orgs) {
            $build['add']['#title'] = $this->t('Add member');
          }
          elseif ($filter_orgs && !$filter_indiv) {
            $build['add']['#title'] = $this->t('Add member organisation');
          }
        }
      }
      else {
        $build['add']['#title'] = $this->t('Add organisation');
      }

      if ($this->configuration['add_title']) {
        $build['add']['#title'] = $this->configuration['add_title'];
      }
    }

    return $build;
  }

  /**
   * Create a new group for the user context.
   *
   * @return \Drupal\group\Entity\GroupContentInterface|false
   *   The group content entity or FALSE if there isn't one or we were unable to
   *   load it.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   Thrown if a the relationship isn't valid for the user context.
   */
  protected function getGroupContent() {
    $relationship = $this->request->query->get($this->getQueryKey());
    if (!$relationship) {
      return FALSE;
    }

    elseif ($relationship == 'add') {
      /** @var \Drupal\group\Entity\GroupType $group_type */
      $group_type = $this->entityTypeManager
        ->getStorage('group_type')
        ->load('contacts_org');
      $plugin = $group_type->getContentPlugin($this->getGroupContentEnabler());

      $values = [
        'type' => $plugin->getContentTypeConfigId(),
      ];
      $user = $this->getContextValue('user');
      if ($this->configuration['provides'] == static::PROVIDES_CONTENT) {
        $values['entity_id'] = $user;
      }
      else {
        $values['gid'] = $user->group;
      }

      return $this->entityTypeManager
        ->getStorage('group_content')
        ->create($values);
    }
    else {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      $group_content = $this->entityTypeManager
        ->getStorage('group_content')
        ->load($relationship);

      if (!$group_content) {
        $this->messenger()->addMessage($this->t('Unable to find the relationship to edit.'), 'error');
        return FALSE;
      }

      if ($group_content->bundle() !== 'contacts_org-' . $this->getGroupContentEnabler()) {
        $this->messenger()->addError($this->t('Invalid relationship type.'));
        return FALSE;
      }

      $expected_id = $this->configuration['provides'] == static::PROVIDES_CONTENT ?
        $group_content->getEntity()->id() :
        $group_content->getGroup()->contacts_org->target_id;
      if ($this->getContextValue('user')->id() != $expected_id) {
        throw new ContextException('Invalid context for relationship.');
      }

      return $group_content;
    }
  }

  /**
   * Build the membership form.
   *
   * @param \Drupal\group\Entity\GroupContentInterface $group_content
   *   The group content entity to edit or a new one for creation.
   *
   * @return array
   *   The form render array.
   */
  protected function buildForm(GroupContentInterface $group_content) {
    // Manually build our action and redirect.
    $query = $this->request->query->all();
    // @see \Drupal\Core\Form\FormBuilder::buildFormAction.
    unset($query[FormBuilder::AJAX_FORM_REQUEST], $query[MainContentViewSubscriber::WRAPPER_FORMAT]);

    // Build our URLs.
    unset($query[$this->getQueryKey()]);
    $redirect = $this->getUrl()
      ->setOption('query', $query);

    // Get the form render array with the right redirect and action.
    $form = $this->formBuilder->getForm($group_content, 'contacts-org', [
      'redirect' => $redirect,
      'content_roles' => $this->configuration['content_roles'],
      'organisation_roles' => $this->configuration['organisation_roles'],
    ]);

    // Add a cancel to take us back to the page.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => $redirect,
      '#weight' => 99,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm(
      $form,
      $form_state
    );

    $form['provides'] = [
      '#type' => 'select',
      '#title' => $this->t('Provided End'),
      '#description' => $this->t('Which end of the relationship is provided; the group or the member?'),
      '#options' => [
        static::PROVIDES_GROUP => $this->t('Group'),
        static::PROVIDES_CONTENT => $this->t('Member'),
      ],
      '#default_value' => $this->configuration['provides'],
    ];

    $form['add_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Add Link Text'),
      '#description' => $this->t('What should the add link say?'),
      '#default_value' => $this->configuration['add_title'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm(
      $form,
      $form_state
    );

    $this->configuration['provides'] = $form_state->getValue('provides');
    $this->configuration['add_title'] = $form_state->getValue('add_title');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    /** @var \Drupal\group\Plugin\GroupContentEnablerInterface $enabler */
    $enabler = $this->groupContentEnablerManager->createInstance($this->getGroupContentEnabler());

    if ($enabler instanceof DependentPluginInterface) {
      return $enabler->calculateDependencies();
    }
    else {
      return [
        'module' => [$enabler->getProvider()],
      ];
    }
  }

}
