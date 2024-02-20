<?php

namespace Drupal\contacts\Plugin\Block;

use Drupal\contacts\Form\ConvertUserToIndividualOrOrganisationForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contacts Summary tab shown if the contact is neither an individual nor org.
 *
 * @Block(
 *   id = "contacts_unknown_contact_type",
 *   admin_label = @Translation("Unknown Contact Type"),
 *   category = @Translation("Dashboard Blocks"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", required = TRUE, label = @Translation("User"))
 *   }
 * )
 */
class ContactsNoRoleBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  private FormBuilderInterface $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $self = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $self->formBuilder = $container->get('form_builder');

    return $self;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      'intro' => [
        '#markup' => $this->t('This user is neither an individual nor an organisation.'),
      ],
      'form' => $this->formBuilder->getForm(ConvertUserToIndividualOrOrganisationForm::class),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getContextValue('user');
    // If the user being viewed is neither an individual or organisation
    // then show this block.
    return AccessResult::allowedIf(!$user->hasRole('crm_indiv') && !$user->hasRole('crm_org'))
      ->addCacheableDependency($user);
  }

}
