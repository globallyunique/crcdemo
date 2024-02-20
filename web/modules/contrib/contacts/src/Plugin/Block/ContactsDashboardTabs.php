<?php

namespace Drupal\contacts\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\contacts\ContactsTabManager;
use Drupal\contacts\ManageDashboardHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to view contact dashboard tabs.
 *
 * @Block(
 *   id = "tabs",
 *   category = @Translation("Contacts"),
 *   deriver = "Drupal\contacts\Plugin\Deriver\ContactsDashboardTabsDeriver",
 * )
 */
class ContactsDashboardTabs extends BlockBase implements ContextAwarePluginInterface, ContainerFactoryPluginInterface {

  /**
   * The tab manager.
   *
   * @var \Drupal\contacts\ContactsTabManager
   */
  protected $tabManager;

  /**
   * Manage mode helper.
   *
   * @var \Drupal\contacts\ManageDashboardHelper
   */
  protected $manageDashboardHelper;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The layout manager service.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManager
   */
  protected $layoutManager;

  /**
   * Whether we are building tabs via AJAX.
   *
   * @var bool
   */
  protected $ajax;

  /**
   * The subpage machine name.
   *
   * @var string
   */
  protected $subpage;

  /**
   * The contact user object.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Construct the Contact Dsahboard Tabs block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\contacts\ContactsTabManager $tab_manager
   *   The tab manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user service.
   * @param \Drupal\Core\Layout\LayoutPluginManager $layout_manager
   *   The layout manager service.
   * @param \Drupal\contacts\ManageDashboardHelper $manage_dashboard_helper
   *   Manage dashboard helper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContactsTabManager $tab_manager, AccountProxy $current_user, LayoutPluginManager $layout_manager, ManageDashboardHelper $manage_dashboard_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tabManager = $tab_manager;
    $this->currentUser = $current_user;
    $this->layoutManager = $layout_manager;
    $this->ajax = TRUE;
    $this->manageDashboardHelper = $manage_dashboard_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $self = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('contacts.tab_manager'),
      $container->get('current_user'),
      $container->get('plugin.manager.core.layout'),
      $container->get('contacts.manage_dashboard_helper')
    );

    // @todo This was not added to the constructor to avoid a breaking change.
    // @todo Consolidate when moving to v3.0.
    $self->themeManager = $container->get('theme.manager');

    return $self;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $build = [];
    $this->subpage = $this->getContextValue('subpage');
    $this->user = $this->getContextValue('user');

    $this->buildTabs($build);
    $this->buildContent($build);

    $classes = [
      'contacts-tabs',
    ];

    if (!$this->isContactsThemeActive()) {
      // If we're not using the Contacts Theme, convert into drupal's
      // vertical tabs element.
      $classes[] = 'vertical-tabs';
      $classes[] = 'clearfix';
    }

    $build['#prefix'] = '<div id="contacts-tabs" class="' . implode(' ', $classes) . '">';
    $build['#suffix'] = '</div>';

    return $build;
  }

  /**
   * Adds the tabs section to the renderable array for this block plugin.
   *
   * @param array $build
   *   Drupal renderable array being added to.
   */
  protected function buildTabs(array &$build) {
    $manage_mode = $this->manageDashboardHelper->isInManageMode();
    $verify = !$manage_mode ? $this->user : NULL;

    // Get verified tabs (if verification is required).
    $tabs = $this->tabManager->getTabs($verify);
    $tab_data = [];
    foreach ($tabs as $tab) {
      $tab_data[$tab->getOriginalId()] = [
        'label' => $tab->label(),
        'path' => $tab->getPath(),
      ];
    }

    // @todo Permission check.
    $build['tabs'] = [
      '#type' => 'contact_tabs',
      '#tabs' => $tab_data,
      '#ajax' => $this->ajax,
      '#user' => $this->user,
      '#subpage' => $this->subpage,
      '#manage_mode' => $manage_mode,
      '#attributes' => [
        'class' => [
          'contacts-ajax-tabs',
          'tabs',
        ],
      ],
    ];

    if (!$this->isContactsThemeActive()) {
      // If we're not using the Contacts Theme then render our tabs
      // as the standard Drupal vertical tabs menu.
      $build['#attached']['library'][] = 'core/drupal.vertical-tabs';
      $build['tabs']['#attributes']['class'] = [
        'vertical-tabs__menu',
        'contacts-ajax-tabs',
      ];

      foreach ($build['tabs']['#tabs'] as &$tab) {
        $tab['attributes']['class'][] = 'vertical-tabs__menu-item';
        $tab['link_attributes']['class'][] = 'vertical-tabs__menu-link';

        // Drupal vertical tabs need some extra wrapper elements around
        // the tab header text.
        $tab['label'] = new FormattableMarkup('<span class="vertical-tabs__menu-link-content"><strong class="vertical-tabs__menu-item-title">@text</strong></span>', [
          '@text' => $tab['label'],
        ]);
      }
    }
  }

  /**
   * Adds the content section to the renderable array for this block plugin.
   *
   * @param array $build
   *   Drupal renderable array being added to.
   */
  protected function buildContent(array &$build) {
    $tab = $this->tabManager->getTabByPath($this->subpage);
    $manage_mode = $this->manageDashboardHelper->isInManageMode();

    $classes = [
      'contacts-tabs-content',
      'flex-fill',
    ];

    if (!$this->isContactsThemeActive()) {
      // If we're not rendering in contacts theme we need some extra css classes
      // to render as vertical tabs.
      $classes[] = 'vertical-tabs__items';
      $classes[] = 'vertical-tabs__panes';
    }

    $build['content'] = [
      '#prefix' => '<div id="contacts-tabs-content" class="' . implode(' ', $classes) . '">',
      '#suffix' => '</div>',
    ];

    // Verify tab if necessary.
    $user = !$manage_mode ? $this->user : NULL;
    if ($user && !$this->tabManager->verifyTab($tab, $user)) {
      $build['content']['#markup'] = $this->t('You do not have access to this page.');
      return $build;
    }

    $blocks = $this->tabManager->getBlocks($tab, $user);

    $layout = $tab->get('layout') ?: 'contacts_tab_content.stacked';
    $layout = $this->layoutManager->createInstance($layout, []);

    $regions = [];
    foreach (array_keys($layout->getPluginDefinition()->getRegions()) as $region) {
      $regions[$region] = [];
    }

    $build['content'] = [
      '#prefix' => '<div id="contacts-tabs-content" class="' . implode(' ', $classes) . '">',
      '#suffix' => '</div>',
      '#type' => 'contact_tab_content',
      '#tab' => $tab,
      '#layout' => $layout,
      '#regions' => $regions,
      '#user' => $this->user,
      '#subpage' => $this->subpage,
      '#blocks' => $blocks,
      '#manage_mode' => $manage_mode,
      '#attributes' => ['class' => ['dash-content']],
    ];

    if ($this->isContactsThemeActive()) {
      // Contacts theme does not render status messages by default.
      $build['messages'] = [
        '#type' => 'status_messages',
        '#weight' => -99,
      ];
    }

    $build['#attached']['drupalSettings']['contacts']['manage_mode'] = $manage_mode;
  }

  /**
   * Checks whether the contacts theme is active.
   *
   * @return bool
   *   TRUE if the contacts theme is active, otherwise FALSE.
   */
  private function isContactsThemeActive() {
    return $this->themeManager->getActiveTheme()->getName() === 'contacts_theme';
  }

}
