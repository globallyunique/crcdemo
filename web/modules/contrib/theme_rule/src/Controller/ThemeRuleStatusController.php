<?php

declare(strict_types = 1);

namespace Drupal\theme_rule\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\theme_rule\Entity\ThemeRuleInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Theme rule status changing controller.
 */
class ThemeRuleStatusController extends ControllerBase {

  /**
   * Provides a controller for enable/disable routes.
   *
   * @param \Drupal\theme_rule\Entity\ThemeRuleInterface $theme_rule
   *   The theme rule confog entity.
   * @param string $operation
   *   Allowed values: 'enable', 'disable'.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the list of theme rule config entities.
   */
  public function toggleStatus(ThemeRuleInterface $theme_rule, string $operation): RedirectResponse {
    $status = [
      'enable' => [
        'value' => TRUE,
        'status' => $this->t('enabled'),
      ],
      'disable' => [
        'value' => FALSE,
        'status' => $this->t('disabled'),
      ],
    ];
    if (!isset($status[$operation])) {
      throw new \InvalidArgumentException("Operation '{$operation}' is invalid. Allowed values: 'enable', 'disable'.");
    }

    $arguments = [
      '%rule' => $theme_rule->label(),
      '@label' => $theme_rule->getEntityType()->getSingularLabel(),
      '@status' => $status[$operation]['status'],
    ];

    if ($theme_rule->get('status') === $status[$operation]['value']) {
      $this->messenger()->addWarning($this->t("The %rule @label is already @status.", $arguments));
    }
    else {
      $this->messenger()->addStatus($this->t("The %rule @label has been @status.", $arguments));
      $theme_rule->setStatus($status[$operation]['value'])->save();
    }

    return $this->redirect('entity.theme_rule.collection');
  }

  /**
   * Provides a title callback for the enable/disable routes.
   *
   * @param string $operation
   *   Allowed values: 'enable', 'disable'.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The title.
   */
  public function getStatusOperationTitle(string $operation): MarkupInterface {
    return [
      'enable' => $this->t('Enable'),
      'disable' => $this->t('Disable'),
    ][$operation];
  }

}
