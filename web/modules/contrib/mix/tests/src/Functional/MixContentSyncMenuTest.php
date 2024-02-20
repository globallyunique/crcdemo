<?php

namespace Drupal\Tests\mix\Functional;

/**
 * Tests content sync of the Mix module.
 *
 * @group mix
 */
class MixContentSyncMenuTest extends MixMenuTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'config',
    'serialization',
    'node',
    'mix',
  ];

  /**
   * The contents of the config export tarball, held between test methods.
   *
   * @var string
   */
  protected $tarball;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);

    // Create a custom menu programmatically.
    $this->menu = $this->addCustomMenu();
  }

  /**
   * Test callback.
   */
  public function testContentSyncMenu() {

    // Add menu links.
    $menu_id = $this->menu->id();
    $node1 = $this->drupalCreateNode(['type' => 'article']);
    $node2 = $this->drupalCreateNode(['type' => 'article']);
    $node3 = $this->drupalCreateNode(['type' => 'article']);
    $item1 = $this->addMenuLink('', 'Menu Item 1', '/node/' . $node1->id(), $menu_id, TRUE);
    $item2 = $this->addMenuLink($item1->getPluginId(), 'Menu Item 1-1', '/node/' . $node2->id(), $menu_id, FALSE);
    $item3 = $this->addMenuLink($item2->getPluginId(), 'Menu Item 1-1-1', '/node/' . $node3->id(), $menu_id);

    // Assert links in block.
    $this->assertSession()->linkByHrefExists('/node/1');
    $this->assertSession()->linkByHrefExists('/node/2');
    $this->assertSession()->linkByHrefNotExists('/node/3');

    // Assert links in menu manage page.
    $this->drupalGet('admin/structure/menu/manage/' . $menu_id);
    $this->assertSession()->linkByHrefExists('/node/3');

    // Enable content sync.
    $config = \Drupal::configFactory()->getEditable('mix.settings');
    $config->set('show_content_sync_id', TRUE)->save();
    // Rebulid all to load the serializer service.
    $this->rebuildAll();

    // Assert the sync link.
    $this->drupalGet('admin/structure/menu/manage/' . $menu_id);
    $this->assertSession()->linkExists('No');

    // Add UUID.
    $id1 = 'mix.content_sync.menu_link_content.' . $item1->uuid();
    $id2 = 'mix.content_sync.menu_link_content.' . $item2->uuid();
    $id3 = 'mix.content_sync.menu_link_content.' . $item3->uuid();
    $config->set('content_sync_ids', [$id1, $id2, $id3])->save();
    // Clear cache to update the sync link in menu item list.
    drupal_flush_all_caches();

    // Assert content_sync_ids.
    $content_sync_ids = $config->get('content_sync_ids');
    $this->assertTrue(in_array($id1, $content_sync_ids));
    $this->assertTrue(in_array($id2, $content_sync_ids));
    $this->assertTrue(in_array($id3, $content_sync_ids));

    // Assert the stop sync link.
    $this->drupalGet('admin/structure/menu/manage/' . $menu_id);
    $this->assertSession()->linkExists('Yes');

    // Export the configuration.
    // @see ConfigExportImportUITest::testExportImport().
    $this->drupalGet('admin/config/development/configuration/full/export');
    $this->submitForm([], 'Export');
    $this->tarball = $this->getSession()->getPage()->getContent();

    // Delete the block.
    $this->menu->delete();
    $this->drupalGet('admin/structure/menu');
    // Block should be removed.
    $this->assertSession()->linkByHrefNotExists('/node/1');
    // Menu management link should be removed.
    $this->assertSession()->linkByHrefNotExists('admin/structure/menu/manage/' . $menu_id);

    // Import the configuration.
    // @see ConfigExportImportUITest::testExportImport().
    $filename = 'temporary://' . $this->randomMachineName();
    file_put_contents($filename, $this->tarball);
    $this->drupalGet('admin/config/development/configuration/full/import');
    $this->submitForm(['files[import_tarball]' => $filename], 'Upload');
    $this->submitForm([], 'Import all');

    // Generate content.
    $this->drupalGet('admin/config/mix');
    $this->submitForm([], 'Generate missing contents');
    $this->assertSession()->pageTextContains('was generated successfully.');

    // Block content shows up.
    $this->drupalGet('admin/structure/menu/manage/' . $menu_id);
    $this->assertSession()->linkByHrefExists('/node/1');
    $this->assertSession()->linkByHrefExists('/node/2');
  }

}
