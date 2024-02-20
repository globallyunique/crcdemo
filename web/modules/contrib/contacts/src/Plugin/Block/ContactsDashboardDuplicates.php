<?php

namespace Drupal\contacts\Plugin\Block;

use Drupal\contacts\Form\ContactsDuplicateForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to view contact duplicates.
 *
 * @Block(
 *   id = "contacts_duplicates",
 *   admin_label = @Translation("Contact Duplicates"),
 *   category = @Translation("Dashboard Blocks"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", required = TRUE, label = @Translation("User"))
 *   }
 * )
 */
class ContactsDashboardDuplicates extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder, ClassResolverInterface $class_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('form_builder'),
      $container->get('class_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'merge user entities');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->getContextValue('user');

    $form_obj = $this->classResolver->getInstanceFromDefinition(ContactsDuplicateForm::class);
    $form_obj->setContact($user);
    $form = $this->formBuilder->getForm($form_obj);

    $build = [
      'form' => $form,
    ];

    return $build;
  }

}
