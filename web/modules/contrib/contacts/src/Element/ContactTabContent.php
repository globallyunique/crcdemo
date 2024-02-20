<?php

namespace Drupal\contacts\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\contacts\Plugin\DashboardBlockInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

/**
 * Provides a dashboard tab content render element.
 *
 * Properties:
 * - #layout: The layout instance to assign blocks to.
 * - #tab: The tab entity being viewed.
 * - #blocks: Array of block plugins from tab.
 * - #user: The user entity being viewed.
 * - #subpage: The tab's dashboard subpage id.
 *
 * Usage example:
 * @code
 * $build['examples_tab_content'] = [
 *   '#type' => 'contact_tab_content',
 *   '#layout' => $layout,
 *   '#tab' => $tab,
 *   '#blocks' => [],
 *   '#subpage' => 'example',
 *   '#user' => $user,
 *   '#manage_mode' => TRUE,
 * ];
 * @endcode
 *
 * @RenderElement("contact_tab_content")
 */
class ContactTabContent extends RenderElement implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#attributes' => [],
      '#region_attributes' => [],
      '#not_found' => $this->t('Page not found.'),
      '#pre_render' => [
        [$class, 'preRenderTabContent'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderTabContent', 'editLinkPreRender'];
  }

  /**
   * Pre-render callback: Renders content of a tab.
   *
   * @param array $element
   *   A structured array to build the tab content.
   *
   * @return array
   *   The passed-in element containing the renderable regions in '#content'.
   */
  public static function preRenderTabContent(array $element) {
    // Get available regions from layout if not already provided.
    if (empty($element['#regions'])) {
      foreach (array_keys($element['#layout']->getPluginDefinition()->getRegions()) as $region) {
        $element['#regions'][$region] = [];
      }
    }

    // Add drag-area class.
    if ($element['#manage_mode']) {
      $element['#region_attributes']['class'][] = 'drag-area';
      $element['#region_attributes']['data-contacts-manage-update-url'] = Url::fromRoute('contacts.ajax.update_blocks')->toString();
    }

    if (!empty($element['#blocks'])) {
      foreach ($element['#blocks'] as $key => $block) {
        $block_content = NULL;
        /** @var \Drupal\Core\Block\BlockPluginInterface $block */
        if ($element['#manage_mode']) {
          $block_content = [
            '#theme' => 'contacts_manage_block',
            '#attributes' => ['class' => ['draggable']],
            '#id' => $block->getPluginId(),
            '#tab' => $element['#tab'],
            '#block' => $block,
            '#subpage' => $element['#subpage'],
          ];
        }
        else {
          $content = $block->build();
          if (count(array_diff_key($content, ['#cache' => TRUE])) > 0) {
            $block_content = [
              '#theme' => 'block',
              '#attributes' => [],
              '#configuration' => $block->getConfiguration(),
              '#plugin_id' => $block->getPluginId(),
              '#base_plugin_id' => $block->getBaseId(),
              '#derivative_plugin_id' => $block->getDerivativeId(),
              '#weight' => $block->getConfiguration()['weight'] ?? 0,
              'content' => $content,
            ];

            // Add edit link to title.
            if ($block instanceof DashboardBlockInterface) {
              $block_content['#dashboard_label_edit_link'] = $block->getEditLink(DashboardBlockInterface::EDIT_LINK_TITLE);
              // phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
              $block_content['#pre_render'][] = [self::class, 'editLinkPreRender'];
            }

            $block_content['content']['#title'] = $block->label();
          }
        }
        $element['#regions'][$block->getConfiguration()['region']][] = $block_content;
      }
    }
    elseif (!$element['#manage_mode']) {
      \Drupal::messenger()->addWarning($element['#not_found']);
    }

    $element['content'] = $element['#layout']->build($element['#regions']);

    $element['content']['#attributes'] = $element['#attributes'];
    foreach (Element::children($element['content']) as $region) {
      if ($element['#manage_mode']) {
        array_unshift($element['content'][$region], static::buildAddBlockLink($element['#tab']->id(), $region));
      }
      $element['content'][$region]['#attributes'] = $element['#region_attributes'];
    }

    return $element;
  }

  /**
   * Pre render callback for dashboard blocks to add edit link to title.
   *
   * @param array $element
   *   The block render array.
   *
   * @return array
   *   The block render array.
   */
  public static function editLinkPreRender(array $element) {
    $link = $element['#dashboard_label_edit_link'] ?? FALSE;
    if ($link) {
      if ($element['content']['#title']) {
        $element['content']['#title'] = new FormattableMarkup('@label [@link]', [
          '@label' => $element['content']['#title'],
          '@link' => $link->toString(),
        ]);
      }
      else {
        $element['content']['#title'] = $link->toString();
      }
    }
    return $element;
  }

  /**
   * Builds a link to add a new section at a given delta.
   *
   * @param string $tab
   *   The section storage.
   * @param string $region
   *   The delta of the section to splice.
   *
   * @return array
   *   A render array for a link.
   */
  protected static function buildAddBlockLink($tab, $region) {
    return [
      'link' => [
        '#type' => 'link',
        '#title' => t('Add Block'),
        '#url' => Url::fromRoute('contacts.manage.off_canvas_choose',
          [
            'tab' => $tab,
            'region' => $region,
          ],
          [
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'dialog',
              'data-dialog-renderer' => 'off_canvas',
            ],
          ]
        ),
      ],
      '#type' => 'container',
      '#attributes' => [
        'class' => ['add-section'],
      ],
    ];
  }

}
