<?php

namespace Drupal\mix\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mix\Controller\Mix;
use Drupal\mix\Controller\MixContentSyncController;
use Drupal\mix\EventSubscriber\MixContentSyncSubscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Mix settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Stores the state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $kernel;

  /**
   * Constructs a Drupal\mix\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param \Drupal\Core\DrupalKernelInterface $kernel
   *   The drupal kernel.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator, StateInterface $state, DrupalKernelInterface $kernel) {
    $this->setConfigFactory($config_factory);
    $this->urlGenerator = $url_generator;
    $this->state = $state;
    $this->kernel = $kernel;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('url_generator'),
      $container->get('state'),
      $container->get('kernel'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mix_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mix.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('mix.settings');

    $form['dev'] = [
      '#type' => 'details',
      '#title' => $this->t('Development'),
      '#open' => TRUE,
    ];

    // Check dev mode and give tips.
    $devMode = $config->get('dev_mode');
    $form['dev']['dev_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable development mode'),
      '#description' => $this->t('Quick switch between Dev/Prod modes to make module and theme develpment way more easier.'),
      '#default_value' => $devMode,
    ];

    // Help content for dev_mode configuration.
    $form['dev']['dev_mode_help'] = [
      '#type' => 'inline_template',
      '#template' => '<details>
        <summary>{% trans %}Dev mode vs. Prod mode{% endtrans %}</summary>
        <table>
          <tr>
            <th>{% trans %}Configuration items{% endtrans %}</th>
            <th>{% trans %}Dev mode{% endtrans %}</th>
            <th>{% trans %}Prod mode{% endtrans %}</th>
          </tr>
          <tr>
            <td>{% trans %}Twig templates debugging{% endtrans %}</td>
            <td>{% trans %}Enable twig debug{% endtrans %}<br>
                {% trans %}Enable auto reload{% endtrans %}<br>
                {% trans %}Disable cache{% endtrans %}</td>
            <td>{% trans %}Disable twig debug{% endtrans %}<br>
                {% trans %}Disable auto reload{% endtrans %}<br>
                {% trans %}Enable cache{% endtrans %}</td>
          </tr>
          <tr>
            <td>{% trans %}Backend caches (render cache, page cache, dynamic page cache, etc.){% endtrans %}</td>
            <td>{% trans %}Disable{% endtrans %}</td>
            <td>{% trans %}Enable{% endtrans %}</td>
          </tr>
          <tr>
            <td>{% trans %}CSS/JS aggregate and gzip{% endtrans %}</td>
            <td>{% trans %}Disable{% endtrans %}</td>
            <td>{% trans %}Enable{% endtrans %}</a></td>
          </tr>
          <tr>
            <td>{% trans %}Browser and proxy caches{% endtrans %}</td>
            <td>{% trans %}Disable{% endtrans %}</td>
            <td><a href="{{ performanceSettingsUrl }}" target="_blank">{% trans %}Settings{% endtrans %}</a></td>
          </tr>
          <tr>
            <td>{% trans %}Error message to display{% endtrans %}</td>
            <td>{% trans %}All messages, with backtrace information{% endtrans %}</td>
            <td><a href="{{ errorMessageSettingsUrl }}" target="_blank">{% trans %}Settings{% endtrans %}</a></td>
          </tr>
          </table>
        </details>',
      '#context' => [
        'performanceSettingsUrl' => $this->urlGenerator->generateFromRoute('system.performance_settings', [], ['fragment' => 'edit-caching']),
        'errorMessageSettingsUrl' => $this->urlGenerator->generateFromRoute('system.logging_settings'),
      ],
    ];

    $form['remove_x_generator'] = [
      '#title' => $this->t('Remove X-Generator'),
      '#type' => 'checkbox',
      '#description' => $this->t('Remove HTTP header "X-Generator" and meta @meta to obfuscate that your website is running on Drupal.', ['@meta' => '<meta name="Generator" content="Drupal 10 (https://www.drupal.org)">']),
      '#default_value' => $config->get('remove_x_generator'),
    ];

    $form['hide_revision_field'] = [
      '#title' => $this->t('Hide revision field'),
      '#type' => 'checkbox',
      '#description' => $this->t('Hide revision field to all users except UID 1 to provide a clear UI'),
      '#default_value' => $config->get('hide_revision_field'),
    ];

    $form['hide_submit'] = [
      '#title' => $this->t('Enable "Hide submit button"'),
      '#description' => $this->t('To avoid duplicate form submissions, disable the submit button after it has been clicked.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('hide_submit'),
    ];

    $form['standalone_password_page'] = [
      '#title' => $this->t('Enable "Standalone change password page"'),
      '#description' => $this->t('Move password fields in user form to a standalone password change page for better UX'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('standalone_password_page'),
    ];

    // Show form ID.
    $form['dev']['show_form_id'] = [
      '#title' => $this->t('Show form ID'),
      '#type' => 'checkbox',
      '#description' => $this->t('Show the form ID and form alter function (<a href="https://api.drupal.org/hook_form_FORM_ID_alter" target="_blank"><code>hook_form_FORM_ID_alter()</code></a>) template before a form to make form altering easier.'),
      '#default_value' => $this->state->get('mix.show_form_id'),
    ];

    // Environment indicator.
    $form['dev']['environment_indicator'] = [
      '#title' => $this->t('Environment Indicator'),
      '#type' => 'textfield',
      '#description' => $this->t('Add a simple text (e.g. Development/Dev/Stage/Test or any other text) on the top of this site to help you identify current environment.
        <br>Leave it blank in the Live environment or hide the indicator.'),
      '#default_value' => $this->state->get('mix.environment_indicator'),
    ];

    $form['content_sync'] = [
      '#title' => $this->t('Content synchronization') . '<sup>' . $this->t('(Beta)') . '</sup>',
      '#type' => 'details',
    ];

    $form['content_sync']['show_content_sync_id'] = [
      '#title' => $this->t('Enable content synchronization'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('show_content_sync_id'),
      '#description' => $this->t('Enable content synchronization to allow entity content to be sync like configurations and show links in content management pages.
        <ul>
          <li>Block - admin/structure/block/block-content</li>
          <li>Menu links - admin/structure/menu/manage/[menu-name]</li>
          <li>Taxonomy terms - admin/structure/taxonomy/manage/[taxonomy]/overview</li>
        </ul>'),
    ];

    $form['content_sync']['description_container'] = [
      '#type' => 'details',
      '#title' => $this->t('User guide'),
    ];

    $form['content_sync']['description_container']['description'] = [
      '#markup' => $this->t('By default, Drupal only synchronizes configurations between environments, not content.<br>
When we synchronize a block, only block config will be synchronized, not the block content, so you will get an error message about "This block is broken or missing."<br>
With this "Content Synchronize", we can synchronize selected content (blocks, menu links, taxonomy terms, etc.) between environments.<br>
<strong>Usage</strong>
<ul>
  <li>Enable the "Show content sync ID" below.</li>
  <li>Go to content list page, find "Export as config" and click the link to choose an item.</li>
  <li>Export configurations by Config export page or <code>drush cex</code> from Dev site.</li>
  <li>Import configurations by Config import page or <code>drush cim</code> to Prod site.</li>
  <li>Click the "Generate missing content" button below to create non-existent contents.</li>
</ul>
Note: To avoid unexpected content updates, only non-existent content will be created by now.'),
    ];

    if (!Mix::isContentSyncReady()) {
      $moduleListUrl = $this->urlGenerator->generate('system.modules_list');
      $form['content_sync']['show_content_sync_id']['#prefix'] = '<div class="mix-box mix-warning">' . $this->t('Please enable core modules <a href="@config" target="_blank">Configuration Manager</a> and <a href="@serialization" target="_blank">Serialization</a> before using this feature.', [
        '@config' => $moduleListUrl . '#module-config',
        '@serialization' => $moduleListUrl . '#module-serialization',
      ]) . '</div>';
      $form['content_sync']['show_content_sync_id']['#attached']['library'][] = 'mix/preset';
      $form['content_sync']['show_content_sync_id']['#default_value'] = FALSE;
      $form['content_sync']['show_content_sync_id']['#disabled'] = TRUE;
    }

    $form['content_sync']['advanced'] = [
      '#title' => $this->t('Advanced'),
      '#type' => 'details',
    ];

    $content_sync_ids = $config->get('content_sync_ids') ?: [];
    $form['content_sync']['advanced']['content_sync_ids'] = [
      '#title' => $this->t('Content sync IDs'),
      '#type' => 'textarea',
      '#description' => $this->t('One content sync ID per line.'),
      '#default_value' => implode(PHP_EOL, $content_sync_ids),
      '#prefix' => '<div class="form-item__description">' . $this->t('Content Sync ID will be added/removed from the following textarea automatically when you selected/unselected an item to sync in the content list pages (e.g. block, menu link and term list pages). <br>
You can also edit it manually.') . '</div>',
    ];

    $form['content_sync']['content_sync_generate_content'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate missing contents'),
      '#submit' => [[$this, 'generateContentSubmit']],
    ];
    if (!Mix::isContentSyncEnabled()) {
      $form['content_sync']['content_sync_generate_content']['#disabled'] = TRUE;
    }
    // Configuration management section.
    $form['cm'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration Management'),
      '#open' => TRUE,
    ];

    $form['cm']['config_import_ignore_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable config import ignore'),
      '#description' => $this->t('By enabling this on the Prod site, you can ignore Dev modules and configurations to be imported, and use it for configurations override.<br>
For more details please see the <a href="https://www.drupal.org/docs/contributed-modules/mix/config-import-ignore" target="_blank">online documentation</a>.'),
      '#default_value' => $config->get('config_import_ignore.mode'),
    ];

    $form['cm']['config_import_ignore_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ignored configuration items'),
      '#description' => $this->t('One item per line.<br>
# To ignore a dev-related module and configurations to be enabled on the Prod site.<br>
<code>core.extenstion:module.devel</code><br>
<code>devel.settings</code><br>
# To ignore or override a configuration item, use the format: <em>config_name:key</em><br>
<code>mix.settings:dev_mode</code><br>
<code>system.site:page.front</code><br>
# To ignore or override an entire configuration, use the format: <em>config_name</em><br>
<code>system.site</code>
'),
      '#states' => [
        'visible' => [
          ':input[name="config_import_ignore_mode"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => implode(PHP_EOL, $config->get('config_import_ignore.list') ?? []),
    ];

    $form['error_pages'] = [
      '#type' => 'details',
      '#title' => $this->t('Error pages'),
      '#open' => TRUE,
    ];

    $errorPageDesc = $this->t('Use custom content replace the default 500 (internal server error) page.') . '<br>';
    $errorPageDesc .= '<a href="' . $this->urlGenerator->generateFromRoute('mix.site_500') . '" target="_blank">' . $this->t('View current error page.') . '</a>';
    $form['error_pages']['error_page'] = [
      '#title' => $this->t('Enable custom error page'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('error_page.mode'),
      '#description' => $errorPageDesc,
    ];

    $form['error_pages']['error_page_content'] = [
      '#title' => $this->t('Error page content'),
      '#type' => 'textarea',
      '#default_value' => $config->get('error_page.content'),
      '#description' => $this->t('Custom content or HTML code of the error page.'),
      '#rows' => 26,
      '#states' => [
        'visible' => [
          ':input[name="error_page"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['seo'] = [
      '#title' => 'SEO',
      '#type' => 'details',
      '#open' => TRUE,
    ];

    $meta_settings_link = Link::createFromRoute($this->t('Meta tags'), 'mix.meta_settings', [], [
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => '80%',
        ]),
        'target' => '_blank',
      ],
    ]);
    $form['seo']['links'] = [
      '#markup' => $meta_settings_link->toString(),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $rebulidRouteCache = FALSE;
    $rebuildCache = FALSE;
    $rebuildContainer = FALSE;

    // Get configurations.
    $config = $this->config('mix.settings');

    // Get original dev_mode value, use to compare if changes later.
    $originDevMode = $config->get('dev_mode');

    // Normalize content_sync_ids.
    $content_sync_ids = array_map('trim', explode(PHP_EOL, $form_state->getValue('content_sync_ids')));
    MixContentSyncController::presave($content_sync_ids);

    // Invalidate cache when content_sync_ids changed.
    $oldContentSyncIds = $config->get('content_sync_ids');
    $contentSyncIdsChanged = $oldContentSyncIds !== $content_sync_ids;
    if ($contentSyncIdsChanged) {
      Cache::invalidateTags(['config:views.view.block_content']);
    }

    // @todo Refactor to a reusable function or method.
    $config_import_ignore_list = explode(PHP_EOL, $form_state->getValue('config_import_ignore_list'));
    $config_import_ignore_list = array_filter(array_map('trim', $config_import_ignore_list));
    sort($config_import_ignore_list);

    // Clear caches if 'show_content_sync_id' value changes.
    $oldContentSyncMode = $config->get('show_content_sync_id');
    $newContentSyncMode = $form_state->getValue('show_content_sync_id');
    if ($oldContentSyncMode != $newContentSyncMode) {
      $rebuildCache = TRUE;
    }

    // Rebuild route if 'standalone_password_page' value changes.
    $oldStandalonePasswordPage = $config->get('standalone_password_page');
    $newStandalonePasswordPage = $form_state->getValue('standalone_password_page');
    if ($oldStandalonePasswordPage != $newStandalonePasswordPage) {
      $rebulidRouteCache = TRUE;
    }

    // Save config.
    $this->config('mix.settings')
      ->set('dev_mode', $form_state->getValue('dev_mode'))
      ->set('hide_revision_field', $form_state->getValue('hide_revision_field'))
      ->set('hide_submit', $form_state->getValue('hide_submit'))
      ->set('standalone_password_page', $form_state->getValue('standalone_password_page'))
      ->set('remove_x_generator', $form_state->getValue('remove_x_generator'))
      ->set('error_page.mode', $form_state->getValue('error_page'))
      ->set('error_page.content', $form_state->getValue('error_page_content'))
      ->set('show_content_sync_id', $form_state->getValue('show_content_sync_id'))
      ->set('content_sync_ids', $content_sync_ids)
      ->set('config_import_ignore.mode', $form_state->getValue('config_import_ignore_mode'))
      ->set('config_import_ignore.list', $config_import_ignore_list)
      ->save();

    // Clear caches if 'show_form_id' value changes.
    $oldShowFormId = $this->state->get('mix.show_form_id');
    $newShowFormId = $form_state->getValue('show_form_id');
    if ($oldShowFormId != $newShowFormId) {
      $this->state->set('mix.show_form_id', $form_state->getValue('show_form_id'));
      $rebuildCache = TRUE;
    }

    // Save state value and invalidate caches when this config changes.
    $oldEnvironmentIndicator = $this->state->get('mix.environment_indicator');
    $newEnvironmentIndicator = $form_state->getValue('environment_indicator');
    if ($oldEnvironmentIndicator != $newEnvironmentIndicator) {
      $this->state->set('mix.environment_indicator', $newEnvironmentIndicator);
      // Rebuild all caches if the new value or the old value is empty.
      if (empty($oldEnvironmentIndicator) || empty($newEnvironmentIndicator)) {
        $rebuildCache = TRUE;
      }
      // Only invalidate cache tags when change between non-empty values
      // for better performance.
      else {
        Cache::invalidateTags(['mix:environment-indicator']);
      }
    }

    // Only run this when dev_mode has changed.
    $devModeChanged = $form_state->getValue('dev_mode') != $originDevMode;
    if ($devModeChanged) {
      // Clear cache to rebulid service providers and configurations
      // based on dev_mode.
      $rebuildCache = TRUE;
      $rebuildContainer = TRUE;
    }

    if ($rebulidRouteCache) {
      \Drupal::service("router.builder")->rebuild();
    }

    if ($rebuildCache) {
      drupal_flush_all_caches();
    }

    if ($rebuildContainer) {
      $this->kernel->rebuildContainer();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Generate content based on imported content-config.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function generateContentSubmit(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mix.settings');
    $content_sync_ids = $config->get('content_sync_ids');
    $activeStorage = $config->getStorage();

    // Generate non-existent content.
    $reququeCounter = [];
    while ($content_sync_ids) {
      $configName = array_shift($content_sync_ids);

      // Parse entityType.
      $entityType = MixContentSyncSubscriber::parseEntityType($configName);
      // Ignore wrong sync ID or unsupported entity types.
      if (!$entityType || !in_array($entityType, array_keys(MixContentSyncSubscriber::$supportedEntityTypeMap))) {
        continue;
      }

      // Initialize counter.
      $reququeCounter[$configName] = $reququeCounter[$configName] ?? 1;
      // Ignore generate content which already tried multiple times.
      // Show an warning message.
      if ($reququeCounter[$configName] > 5) {
        $this->messenger()->addWarning($this->t('Failed to generate @entity_type: @config_name', [
          '@entity_type' => $entityType,
          '@config_name' => $configName,
        ]));
        continue;
      }

      // Load entity.
      $uuid = substr($configName, strrpos($configName, '.') + 1);
      $existedEntity = \Drupal::service('entity.repository')->loadEntityByUuid($entityType, $uuid);
      // Load config.
      $config = $activeStorage->read($configName);
      // Get normalized content.
      // @see MixContentSyncSubscriber::onExportTransform()
      $entityArray = $config['entity'];
      // Only generate non-existent content.
      if (!$existedEntity && $entityArray) {
        try {
          $serializer = \Drupal::service('serializer');
          $entity = $serializer->denormalize($entityArray, MixContentSyncSubscriber::$supportedEntityTypeMap[$entityType], 'yaml');
          $created = $entity->save();
          if ($created === SAVED_NEW) {

            $this->messenger()->addStatus($this->t('@entity_type "@name" was generated successfully.', [
              '@entity_type' => $entityType,
              '@name' => $entity->label(),
            ]));
          }
        }
        catch (\InvalidArgumentException $e) {
          // Handle exception situation that dependency content not exist.
          if (strpos($e->getMessage(), 'entity found with UUID')) {
            // Re-queue.
            array_push($content_sync_ids, $configName);
            // Requeue counter.
            $reququeCounter[$configName]++;
          }
        }
      }
    }

    drupal_flush_all_caches();
  }

}
