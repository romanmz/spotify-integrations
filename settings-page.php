<?php

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

// Include files
require_once 'settings-page-helper/settings-page-helper.php';


/**
 * Class for Managing the General Settings Page
 * 
 * @version 1.0.0
 * @since 1.0.0
 */
class Settings_Page_General extends Settings_Page {
	
	/**
	 * Returns the configuration array for the settings page
	 * 
	 * @return array
	 */
	public function getConfig() {
		return [
			'settings_name' => 'spotify_api',
			'page' => [
				'id' => 'spotify-api',
				'title' => 'Spotify API Settings',
			],
			'menu' => [
				'title' => 'Spotify API',
				'position' => 'options-general.php',
			],
			'sections'=> [
				'general' => [
					'title' => 'General Settings',
					'fields' => [
						'client_id' => [
							'title' => 'Spotify API Client ID',
							'description' => '',
							'sanitize_func' => 'sanitize_title',
							'is_required' => true,
						],
						'client_secret' => [
							'title' => 'Spotify API Client Secret',
							'description' => '',
							'sanitize_func' => 'sanitize_title',
							'is_required' => true,
						],
					],
				],
			],
		];
	}
	
	/**
	 * Constructor function
	 * 
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}
	
}
new Settings_Page_General();
