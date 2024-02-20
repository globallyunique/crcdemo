<?php

namespace Drupal\contacts_log\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the user's update times block.
 *
 * @Block(
 *   id = "contacts_log_user_update",
 *   admin_label = @Translation("User's update times"),
 *   category = @Translation("Contacts"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", label = @Translation("User"), required = TRUE)
 *   }
 * )
 */
class UserUpdateDetailsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The subscriber storage, if available.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $subscriberStorage;

  /**
   * Construct the block plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getContextValue('user');

    $build['created'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('<strong>Created:</strong> @created', [
        '@created' => $this->dateFormatter->format($user->getCreatedTime(), 'short'),
      ]),
    ];

    $update_time = $user->getChangedTime();
    $profile_fields = preg_grep('/^profile_.*$/', array_keys($user->getFields(FALSE)));
    foreach ($profile_fields as $field_name) {
      foreach ($user->get($field_name) as $profile_item) {
        /** @var \Drupal\profile\Entity\ProfileInterface $profile */
        if ($profile = $profile_item->entity) {
          $update_time = max($update_time, $profile->getChangedTime());
        }
      }
    }

    $build['updated'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('<strong>Last updated:</strong> @created', [
        '@created' => $this->dateFormatter->format($update_time, 'short'),
      ]),
    ];

    $last_login = $user->getLastLoginTime() ?
      $this->dateFormatter->format($user->getLastLoginTime(), 'short') :
      $this->t('Never');
    $build['login'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('<strong>Last login:</strong> @login', [
        '@login' => $last_login,
      ]),
    ];

    return $build;
  }

}
