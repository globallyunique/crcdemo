<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Tests\block_content\Functional\BlockContentTestBase;

/**
 * Tests content sync of the Mix module.
 *
 * @group mix
 */
class MixContentSyncBlockTest extends BlockContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config', 'serialization', 'views', 'mix'];

  /**
   * The submitted block values used by this test.
   *
   * @var array
   */
  protected $blockValues;

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

    // Create block types programmatically.
    $this->createBlockContentType('other', TRUE);
  }

  /**
   * Test callback.
   */
  public function testContentSyncBlock() {

    // Create a block.
    $block_title = 'Test Block';
    $block_content = 'Content sync test block';
    $edit = [];
    $edit['info[0][value]'] = $block_title;
    $edit['body[0][value]'] = $block_content;
    $this->drupalGet('block/add/basic');
    $this->submitForm($edit, 'Save');

    $this->drupalGet('admin/structure/block/block-content');
    $this->assertSession()->linkExists($block_title);

    // Enable content sync.
    $config = \Drupal::configFactory()->getEditable('mix.settings');
    $config->set('show_content_sync_id', TRUE)->save();
    // Rebulid all to load the serializer service.
    $this->rebuildAll();

    // Assert the sync link.
    $this->drupalGet('admin/structure/block/block-content');
    $this->assertSession()->linkExists('No');

    // Add UUID.
    $block = BlockContent::load(1);
    $content_sync_id = 'mix.content_sync.block_content.' . $block->bundle() . '.' . $block->uuid();
    $config->set('content_sync_ids', [$content_sync_id])->save();
    // Clear cache to update the sync link in block list.
    drupal_flush_all_caches();

    // Assert content_sync_ids.
    $content_sync_ids = $config->get('content_sync_ids');
    $this->assertTrue(in_array($content_sync_id, $content_sync_ids));

    // Assert the stop sync link.
    $this->drupalGet('admin/structure/block/block-content');
    $this->assertSession()->linkExists('Yes');

    // Assign and assert block.
    $this->drupalPlaceBlock('block_content:' . $block->uuid());
    $this->drupalGet('admin/structure/block/block-content');
    $this->assertSession()->pageTextContains($block_content);

    // Export the configuration.
    // @see ConfigExportImportUITest::testExportImport().
    $this->drupalGet('admin/config/development/configuration/full/export');
    $this->submitForm([], 'Export');
    $this->tarball = $this->getSession()->getPage()->getContent();

    // Delete the block.
    $block->delete();
    // Block should be removed.
    $this->drupalGet('admin/structure/block/block-content');
    $this->assertSession()->linkNotExists($block_title);
    $this->assertSession()->pageTextNotContains($block_content);

    // Import the configuration.
    // @see ConfigExportImportUITest::testExportImport().
    $filename = 'temporary://' . $this->randomMachineName();
    file_put_contents($filename, $this->tarball);
    $this->drupalGet('admin/config/development/configuration/full/import');
    $this->submitForm(['files[import_tarball]' => $filename], 'Upload');
    $this->submitForm([], 'Import all');

    // Block show up but the block content not.
    $this->assertSession()->pageTextContains('This block is broken or missing.');

    // Generate block.
    $this->drupalGet('admin/config/mix');
    $this->submitForm([], 'Generate missing contents');
    $this->assertSession()->pageTextContains('was generated successfully.');

    // Block content shows up.
    $this->drupalGet('admin/structure/block/block-content');
    $this->assertSession()->pageTextContains($block_content);
  }

}
