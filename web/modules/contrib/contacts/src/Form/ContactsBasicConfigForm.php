<?php

namespace Drupal\contacts\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Basic config for contacts.
 *
 * @package Drupal\contacts\Form
 */
class ContactsBasicConfigForm extends ConfigFormBase {

  /**
   * The route builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandler
   */
  protected ThemeHandler $themeHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $form_instance */
    $form_instance = parent::create($container);
    $form_instance->routeBuilder = $container->get('router.builder');
    $form_instance->themeHandler = $container->get('theme_handler');
    $form_instance->entityTypeManager = $container->get('entity_type.manager');

    return $form_instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['contacts.configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contacts_basic_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('contacts.configuration');

    $form['access_denied_redirect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect to the login page when access denied'),
      '#default_value' => $config->get('access_denied_redirect'),
      '#description' => $this->t('When checked, requests for anonymous users who get an access denied response will be redirected to the login page.'),
    ];

    $form['redirect_user_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect user page'),
      '#default_value' => $config->get('redirect_user_page'),
      '#description' => $this->t('When checked, will redirect requests from /user/{user} to the user dashboard.'),
    ];

    $form['unified_login_register'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the unified login/registration page'),
      '#default_value' => $config->get('unified_login_register') ?? TRUE,
      '#description' => $this->t('When checked, the login and register forms will be displayed side by side for both login and register routes.'),
    ];

    $form['coupled_real_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the real name for registerd users'),
      '#default_value' => $config->get('coupled_real_name') ?? TRUE,
      '#description' => $this->t("This will override the chosen login name when displaying the user's name."),
    ];

    $form['add_contact_form_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Add Contact form mode'),
      '#required' => TRUE,
      '#options' => [
        'current' => 'Current',
        'legacy' => 'Legacy',
      ],
      // Default to legacy if not configured, for backwards compatibility.
      '#default_value' => $config->get('add_contact_form_type') ?? 'legacy',
      '#description' => $this->t('Selecting "legacy" will use the old modal forms when adding a new contact. Selecting "current" will use the more modern standalone "add new contact" page.'),
    ];

    $form['default_contacts_dashboard_route'] = [
      '#type' => 'select',
      '#title' => $this->t('Contacts Default Search Screen'),
      '#required' => TRUE,
      '#options' => [
        'contacts.collection.individual' => $this->t('Individuals screen'),
        'contacts.collection.organisation' => $this->t('Organisations screen'),
        'contacts.collection.all' => $this->t('Unified Organisation/Individuals screen (Legacy & Deprecated)'),
      ],
      '#description' => $this->t('Selecting "Unified (Legacy)" will used the old, unified Contacts search screen. Selecting "Separate" will have separate, individually customizable Individuals & Organisations screens.'),
      // Default to the legacy implementation for backwards compat.
      '#default_value' => $config->get('default_contacts_dashboard_route') ?? 'contacts.collection.all',
    ];

    $form['email_required_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Email required roles'),
      '#description' => $this->t('Selects which CRM roles should set the email address as required'),
      '#options' => [],
      '#default_value' => $config->get('email_required_roles') ?? [],
    ];

    foreach ($this->getCrmRoles() as $role) {
      $form['email_required_roles']['#options'][$role->id()] = $role->label();
    }

    // This functionality is part of the Contacts Theme, so we'll hide it if the
    // theme is not in use.
    if ($this->themeHandler->themeExists('contacts_theme')) {

      // For backwards compatibility where there is no existing configuration
      // default to expanding crm_roles and crm_type facets.
      $expand_facet_blocks = $config->get('expanded_facet_blocks') ?? [
        'facet_block:crm_roles',
        'facet_block:crm_type',
      ];

      $form['expanded_facet_blocks'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Blocks that should be expanded in the contacts dashboard search'),
        '#description' => $this->t('One block ID per line'),
        '#default_value' => implode("\n", $expand_facet_blocks) ?? '',
      ];
    }

    $form['warning'] = [
      'heading' => ['#markup' => $this->t('<h3>Important Note</h3>')],
      'text' => ['#markup' => $this->t('Saving changes on this page will cause the routing table to be rebuilt and cache bins to be cleared.')],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('contacts.configuration')
      ->set('access_denied_redirect', $form_state->getValue('access_denied_redirect'))
      ->set('redirect_user_page', $form_state->getValue('redirect_user_page'))
      ->set('unified_login_register', $form_state->getValue('unified_login_register'))
      ->set('coupled_real_name', $form_state->getValue('coupled_real_name'))
      ->set('expanded_facet_blocks', explode("\r\n", $form_state->getValue('expanded_facet_blocks')))
      ->set('add_contact_form_type', $form_state->getValue('add_contact_form_type'))
      ->set('email_required_roles', array_filter($form_state->getValue('email_required_roles')))
      ->set('default_contacts_dashboard_route', $form_state->getValue('default_contacts_dashboard_route'))
      ->save();

    $this->routeBuilder->rebuild();

    foreach (Cache::getBins() as $cache_backend) {
      $cache_backend->deleteAll();
    }
  }

  /**
   * Gets CRM roles.
   *
   * @return \Drupal\user\Entity\Role[]
   *   CRM roles.
   */
  private function getCrmRoles() {
    /** @var \Drupal\user\Entity\Role[] $roles */
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    foreach ($roles as $delta => $role) {
      $is_crm_role = $role->getThirdPartySetting('crm_tools', 'crm_tools_is_hat');
      if (!$is_crm_role) {
        unset($roles[$delta]);
      }
    }

    return $roles;
  }

}
