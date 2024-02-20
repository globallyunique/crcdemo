<?php

namespace Drupal\Tests\mix\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests meta functions.
 *
 * @group mix
 */
class MixMetaTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mix'];

  /**
   * Tests menu item visibility by role.
   */
  public function testFrontpageMeta() {

    // Change site name.
    $this->config('system.site')->set('name', 'Drupal Meta Test')->save();

    $this->drupalGet('<front>');
    $this->assertSession()->elementContains('css', 'title', ' | Drupal Meta Test');
    $this->assertSession()->elementNotExists('css', 'meta[name="description"]');

    // Set frontpage meta tags.
    $frontpage_meta = [
      'active' => TRUE,
      'title' => '[site:name]',
      'description' => $this->t('This is an example description'),
      'keywords' => $this->t('Word 1, Word 2, Word 3'),
      'metatags' => '<meta name="google-site-verification" content="xxx" />
<meta name="custom-meta" content="xxx" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta property="og:xxx" content="xxx">',
    ];
    $this->config('mix.settings')->set('meta.frontpage', $frontpage_meta)->save();
    Cache::invalidateTags(['rendered']);

    $this->drupalGet('<front>');
    $this->assertSession()->elementNotContains('css', 'title', ' | Drupal Meta Test');
    $this->assertSession()->elementContains('css', 'title', 'Drupal Meta Test');
    $this->assertSession()->elementExists('css', 'meta[name="description"]');
    $this->assertSession()->elementExists('css', 'meta[name="keywords"]');
    $this->assertSession()->elementExists('css', 'meta[name="google-site-verification"]');
    $this->assertSession()->elementExists('css', 'meta[name="custom-meta"]');
    $this->assertSession()->elementExists('css', 'meta[http-equiv="X-UA-Compatible"]');
    $this->assertSession()->elementExists('css', 'meta[property="og:xxx"]');
  }

}
