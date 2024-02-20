<?php

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * Implements hook_form_system_theme_settings_alter().
 */
function dark_responsive_form_system_theme_settings_alter(&$form, FormStateInterface $form_state, $form_id = NULL) {
	$form['dark_responsive_settings']['video'] = [
		'#type' => 'details',
		'#title' => t('Video box'),
		'#collapsible' => TRUE,
		'#collapsed' => TRUE,
	];

	$form['dark_responsive_settings']['video']['show_hide_video'] = [
		'#type' => 'checkbox',
		'#title' => t('Show video box'),
		'#default_value' => theme_get_setting('show_hide_video', 'dark_responsive'),
		'#description' => t("Check this option to show video box. Uncheck to hide."),
	];
	
	$form['dark_responsive_settings']['video']['slide'] = [
    '#markup' => t('Change the video, title, description using below fieldset.'),
  ];

	for ($i = 1; $i <= 3; $i++) {
    $form['dark_responsive_settings']['video']['slide' . $i] = [
      '#type' => 'details',
      '#title' => t('Slide @i.',['@i' => $i]),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['dark_responsive_settings']['video']['slide' . $i]['slide' . $i . '_video'] = [
			'#type' => 'managed_file',
			'#upload_location'  => 'public://',
			'#multiple' => FALSE,
			'#description' => t('Allowed extensions: mp4'),
			'#upload_validators' => [
				'file_validate_extensions'  => ['mp4'],
			],
			'#title' => t('Slide Upload an mp4 file for video background.'),
			'#default_value' => theme_get_setting('slide' . $i . '_video', 'dark_responsive'),
		];

    $form['dark_responsive_settings']['video']['slide' . $i]['slide' . $i . '_title'] = [
      '#type' => 'textfield',
      '#title' => t('Slide Title'),
      '#default_value' => theme_get_setting('slide' . $i . '_title', 'dark_responsive'),
    ];

    $form['dark_responsive_settings']['video']['slide' . $i]['slide' . $i . '_description'] = [
      '#type' => 'textarea',
      '#title' => t('Slide Description'),
      '#default_value' => theme_get_setting('slide' . $i . '_description', 'dark_responsive'),
    ];

		// Footer Social Icon Link.
    $form['dark_responsive_settings']['social_share_icon'] = [
      '#type' => 'details',
      '#title' => t('Social Icons Links'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    
		$form['dark_responsive_settings']['social_share_icon']['show_social_icons'] = [
      '#type' => 'checkbox',
      '#title' => t('Show Social Icons'),
      '#default_value' => theme_get_setting('show_social_icons'),
      '#description'   => t("Show/hide Social media links."),
    ];
    
		$form['dark_responsive_settings']['social_share_icon']['facebook_url'] = [
      '#type' => 'textfield',
      '#title' => t('Facebook URL'),
      '#default_value' => theme_get_setting('facebook_url'),
    ];
    
		$form['dark_responsive_settings']['social_share_icon']['instagram_url'] = [
      '#type' => 'textfield',
      '#title' => t('Instagram URL'),
      '#default_value' => theme_get_setting('instagram_url'),
    ];
    
		$form['dark_responsive_settings']['social_share_icon']['twitter_url'] = [
      '#type' => 'textfield',
      '#title' => t('Twitter URL'),
      '#default_value' => theme_get_setting('twitter_url'),
    ];

    $form['dark_responsive_settings']['social_share_icon']['youtube_url'] = [
      '#type' => 'textfield',
      '#title' => t('Youtube URL'),
      '#default_value' => theme_get_setting('youtube_url'),
    ];

    // Copyright Text.
    $form['dark_responsive_settings']['copyright'] = [
      '#type' => 'details',
      '#title' => t('Copyright'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    
    $form['dark_responsive_settings']['copyright']['show_hide_copyright'] = [
      '#type' => 'checkbox',
      '#title' => t('Show Hide Copyright text'),
      '#default_value' => theme_get_setting('show_hide_copyright'),
      '#description'   => t("Check this option to show Copyright text. Uncheck to hide."),
    ];
  
    $form['dark_responsive_settings']['copyright']['copyright_text'] = [
      '#type' => 'textfield',
      '#title' => t('Enter copyright text'),
      '#default_value' => theme_get_setting('copyright_text'),
    ];

	}

	$form['#submit'][] = 'dark_responsive_settings_form_submit';
	$theme = \Drupal::theme()->getActiveTheme()->getName();
	$theme_file = \Drupal::service('extension.list.theme')->getPath($theme) . '/dark_responsive.theme';
	$build_info = $form_state->getBuildInfo();
	if (!in_array($theme_file, $build_info['files'])) {
		$build_info['files'][] = $theme_file;
	}
	$form_state->setBuildInfo($build_info);
}
