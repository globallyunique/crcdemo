<?php

namespace Drupal\mix\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Mix settings for this site.
 */
class MetaSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mix_meta_settings';
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

    $config = $this->config('mix.settings')->get('meta');

    $form['#mix_ajax_form'] = TRUE;

    // Frontpage.
    $form['frontpage'] = [
      '#title' => $this->t('Front page'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['frontpage']['active'] = [
      '#title' => $this->t('Active'),
      '#type' => 'checkbox',
      '#default_value' => $config['frontpage']['active'] ?? FALSE,
    ];

    $form['frontpage']['title'] = [
      '#title' => $this->t('Page Title'),
      '#type' => 'textfield',
      '#description' => $this->t('The text to display in the title bar and in a search engine result. It is common to use <code>[site:name]</code> and <code>[site:slogan]</code>, and recommended the title is no greater than 55 - 65 characters, including spaces.'),
      '#default_value' => $config['frontpage']['title'] ?? '[site:title]',
    ];

    $form['frontpage']['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#description' => $this->t("A brief and concise summary of the page's content. Search engines may use this description to display a snippet in the search results, keep this less than 160 characters in length."),
      '#default_value' => $config['frontpage']['description'] ?? '',
    ];

    $form['frontpage']['keywords'] = [
      '#title' => $this->t('Keywords'),
      '#type' => 'textfield',
      '#description' => $this->t('A comma-separated list of keywords about the page. This meta tag is no longer supported by most search engines.'),
      '#default_value' => $config['frontpage']['keywords'] ?? '',
    ];

    $form['frontpage']['metatags'] = [
      '#title' => $this->t('Meta tags'),
      '#type' => 'textarea',
      '#description' => $this->t('Other meta tags for site verification, Mobile & UI Adjustments or other. One per line. For example:<pre><code>
&lt;meta name="google-site-verification" content="......." /&gt;
&lt;meta name="MobileOptimized" content="width"&gt;
&lt;meta name="HandheldFriendly" content="true"&gt;
&lt;meta name="viewport" content="width=device-width,minimum-scale=1,maximum-scale=1"&gt;
&lt;meta http-equiv="X-UA-Compatible" content="IE=edge" /&gt;
&lt;meta property="og:xxx" content="xxx"&gt;
</code></pre>
      '),
      '#default_value' => $config['frontpage']['metatags'] ?? '',
    ];

    // Node.
    $form['node'] = [
      '#title' => $this->t('Node'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['node']['active'] = [
      '#title' => $this->t('Active'),
      '#type' => 'checkbox',
      '#default_value' => $config['node']['active'] ?? FALSE,
    ];

    $form['node']['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#description' => $this->t("A brief and concise summary of the page's content. Search engines may use this description to display a snippet in the search results, keep this less than 160 characters in length.<br>
Support node tokens such as: <code>[node:title]</code>, <code>[node:summary]</code>, <code>[node:body]</code> and so on."),
      '#default_value' => $config['node']['description'] ?? '[node:summary]',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('mix.settings')
      ->set('meta.frontpage', $form_state->getValue('frontpage'))
      ->set('meta.node', $form_state->getValue('node'))
      ->save();

    // Clear cache.
    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}
