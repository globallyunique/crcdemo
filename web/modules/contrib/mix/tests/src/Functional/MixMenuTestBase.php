<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\system\Entity\Menu;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests advanced menu settings.
 *
 * @group mix
 */
abstract class MixMenuTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'menu_ui', 'menu_link_content', 'mix'];

  /**
   * Array of placed menu blocks keyed by block ID.
   *
   * @var array
   */
  protected $blockPlacements;

  /**
   * Creates a custom menu.
   *
   * @return \Drupal\system\Entity\Menu
   *   The custom menu that has been created.
   */
  public function addCustomMenu() {
    // Add a custom menu.
    $this->drupalGet('admin/structure/menu/add');
    $menu_name = strtolower($this->randomMachineName());
    $label = $this->randomMachineName();
    $edit = [
      'id' => $menu_name,
      'description' => '',
      'label' => $label,
    ];
    $this->submitForm($edit, 'Save');

    // Enable the block.
    $block = $this->drupalPlaceBlock('system_menu_block:' . $menu_name);
    $this->blockPlacements[$menu_name] = $block->id();

    return Menu::load($menu_name);
  }

  /**
   * Add a menu link.
   */
  public function addMenuLink($parent = '', $title = 'link', $path = '/', $menu_name = 'custom', $expanded = FALSE, $weight = 0, $form_values = []) {
    // Go to add menu link page.
    $this->drupalGet("admin/structure/menu/manage/$menu_name/add");
    $edit = [
      'link[0][uri]' => $path,
      'title[0][value]' => $title,
      'description[0][value]' => '',
      'enabled[value]' => 1,
      'expanded[value]' => $expanded,
      'menu_parent' => $menu_name . ':' . $parent,
      'weight[0][value]' => $weight,
    ];
    $edit += $form_values;

    $this->submitForm($edit, 'Save');

    $menu_links = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['title' => $title]);
    $menu_link = reset($menu_links);
    return $menu_link;
  }

}
