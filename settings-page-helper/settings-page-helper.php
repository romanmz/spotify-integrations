<?php
/**
 * Helper Class for Creating Settings Pages
 * 
 * @version 1.0.0
 * @since 1.0.0
 */
abstract class Settings_Page {
	
	/**
	 * Returns the configuration array for the settings page
	 * 
	 * @return array
	 */
	abstract public function getConfig();
	
	/**
	 * @var string Name of the options array in the database
	 */
	public $settings_name = '';
	
	/**
	 * @var array User settings stored in the database
	 */
	public $settings = [];
	
	/**
	 * @var array General page attributes
	 */
	public $page = [];
	
	/**
	 * @var array General menu attributes
	 */
	public $menu = [];
	
	/**
	 * @var array Processed list of section attributes
	 */
	public $sections = [];
	
	/**
	 * @var array Processed list of field attributes
	 */
	public $fields = [];
	
	/**
	 * Property getter
	 * 
	 * @param string $property
	 * @return mixed
	 */
	public function __get( $property ) {
		if( !empty( $this->settings[ $property ] ) ) {
			return $this->settings[ $property ];
		}
		$defaults = $this->get_default_values();
		if( !empty( $defaults[ $property ] ) ) {
			return $defaults[ $property ];
		}
	}
	
	/**
	 * Instance constructor
	 * 
	 * Loads the data stored in the database, and adds the required actions and filters
	 * 
	 * @return void
	 */
	protected function __construct() {
		
		// Copy and process configuration
		$config = $this->getConfig();
		$this->settings_name = $config['settings_name'];
		$this->settings = get_option( $this->settings_name );
		$this->page = $config['page'];
		$this->menu = $config['menu'];
		$this->sections = $config['sections'];
		$this->fields = call_user_func_array( 'array_merge', array_column( $config['sections'], 'fields' ) );
		$this->prepare_page_data( $this->page );
		array_walk( $this->sections, [$this, 'prepare_section_data'] );
		array_walk( $this->fields, [$this, 'prepare_field_data'] );
		
		// Runs hooks
		add_action( 'admin_menu', [$this, 'register_page'] );
		add_action( 'admin_init', [$this, 'register_settings'] );
		add_action( 'admin_enqueue_scripts', [$this, 'load_assets'] );
	}
	
	/**
	 * Fill in default page settings
	 * 
	 * @param array &$data
	 * @return array
	 */
	public function prepare_page_data( &$data ) {
		$default_values = [
			'id'          => '',
			'title'       => '',
			'hook'        => '',
			'required_capability' => 'manage_options',
			'file'        => 'settings-page.php',
		];
		$data = wp_parse_args( $data, $default_values );
		$data['url']  = is_string( $this->menu['position'] ) ? admin_url( $this->menu['position'] ) : admin_url( 'admin.php' );
		$data['url'] .= strpos( $data['url'], '?' ) === false ? '?' : '&';
		$data['url'] .= http_build_query( ['page' => $data['id'] ] );
		return $data;
	}
	
	/**
	 * Fill in default settings for sections
	 * 
	 * @param array $data
	 * @param string $slug
	 * @return array
	 */
	public function prepare_section_data( &$data, $slug ) {
		$default_values = [
			'slug'        => $slug,
			'id'          => "section-{$slug}",
			'title'       => '',
			'description' => '',
			'fields'      => [],
			'file'        => 'settings-section.php',
		];
		$data = wp_parse_args( $data, $default_values );
		$data['fields'] = array_keys( $data['fields'] );
		return $data;
	}
	
	/**
	 * Fill in default settings for fields
	 * 
	 * @param array $data
	 * @param string $slug
	 * @return array
	 */
	public function prepare_field_data( &$data, $slug ) {
		$default_values = [
			'slug'          => $slug,
			'id'            => "field-{$slug}",
			'type'          => 'text',
			'title'         => '',
			'description'   => '',
			'class'         => "field-{$slug}",
			'name'          => "{$this->settings_name}[{$slug}]",
			'default'       => '',
			'sanitize_func' => null,
			'file'          => 'field-text.php',
		];
		$data = wp_parse_args( $data, $default_values );
		$data['label_for'] = $data['id'];
		$data['value'] = isset( $this->settings[ $slug ] ) ? $this->settings[ $slug ] : $data['default'];
		return $data;
	}
	
	/**
	 * Get all registered fields and their default values
	 * 
	 * Returns a simple associative array with keys for each registered field and their respective default values
	 * 
	 * @return array
	 */
	public function get_default_values() {
		return array_column( $this->fields, 'default', 'slug' );
	}
	
	/**
	 * Get all registered fields with updated and sanitized data
	 * 
	 * Also registers validation errors
	 * 
	 * @param array $new_values
	 * @return array
	 */
	public function get_sanitized_values( $new_values ) {
		$sanitized_values = [];
		foreach( $this->fields as $field_slug => $field_data ) {
			
			// Get and sanitize new value
			$new_value = isset( $new_values[ $field_slug ] ) ? $new_values[ $field_slug ] : '';
			$sanitize_func = is_callable( $field_data['sanitize_func'] ) ? $field_data['sanitize_func'] : 'sanitize_text_field';
			$new_value = call_user_func( $sanitize_func, $new_value );
			
			// Validate required
			if( !empty( $field_data['is_required'] ) && empty( $new_value ) ) {
				add_settings_error(
					$this->settings_name,
					$field_slug,
					isset( $field_data['is_required_message'] ) ? esc_html( $field_data['is_required_message'] ) : "The field {$field_data['title']} is required",
					isset( $field_data['is_required_type'] ) ? $field_data['is_required_type'] : 'error'
				);
				$new_value = $field_data['default']; // empty / previous / default / sanitized?
			}
			
			// Validate JSON
			if( !empty( $field_data['is_json'] ) && !empty( $new_value ) ) {
				$json_test = json_decode( $new_value );
				if( json_last_error() !== JSON_ERROR_NONE ) {
					add_settings_error(
						$this->settings_name,
						$field_slug,
						"The field {$field_data['title']} must have a valid JSON format",
						'error'
					);
					$new_value = '';
				}
			}
			
			// Add to results array
			$sanitized_values[ $field_slug ] = $new_value;
			
		}
		return $sanitized_values;
	}
	
	/**
	 * Register the settings page
	 * 
	 * @return void
	 */
	public function register_page() {
		if( is_string( $this->menu['position'] ) ) {
			$this->page['hook'] = add_submenu_page(
				$this->menu['position'],
				$this->page['title'],
				$this->menu['title'],
				$this->page['required_capability'],
				$this->page['id'],
				[$this, 'render_page']
			);
		} else {
			$this->page['hook'] = add_menu_page(
				$this->page['title'],
				$this->menu['title'],
				$this->page['required_capability'],
				$this->page['id'],
				[$this, 'render_page'],
				$this->menu['icon'],
				$this->menu['position']
			);
		}
	}
	
	/**
	 * Register the settings, sections and fields
	 * 
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			$this->page['id'],
			$this->settings_name,
			[
				'sanitize_callback' => [$this, 'get_sanitized_values'],
				'default' => $this->get_default_values(),					// available until after the 'admin_init' hook
			]
		);
		foreach( $this->sections as $section_slug => $section_data ) {
			add_settings_section(
				$section_slug,
				$section_data['title'],
				[$this, 'render_section'],
				$this->page['id']
			);
			foreach( $section_data['fields'] as $field_slug ) {
				$field_data = $this->fields[ $field_slug ];
				add_settings_field(
					$field_slug,
					$field_data['title'],
					[$this, 'render_field'],
					$this->page['id'],
					$section_slug,
					$field_data
				);
			}
		}
	}
	
	/**
	 * Load assets necessary for the settings page
	 * 
	 * @param string $hook
	 * @return void
	 */
	public function load_assets( $hook ) {
		if( $this->page['hook'] === $hook ) {
		}
	}
	
	/**
	 * Output the HTML of the settings page
	 * 
	 * @return void
	 */
	public function render_page() {
		if( !current_user_can( $this->page['required_capability'] ) ) {
			return;
		}
		if( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( $this->settings_name, 'settings-saved', 'Settings saved.', 'updated' );
			do_action( 'settings_page/updated', $this->page['id'] );
		}
		if( $this->menu['position'] !== 'options-general.php' ) {
			settings_errors( $this->settings_name );
		}
		// Load template
		$file_path = trailingslashit(__DIR__).'views/'.$this->page['file'];
		if( is_file( $file_path ) && is_readable( $file_path ) ) {
			include( $file_path );
		}
	}
	
	/**
	 * Outputs the HTML of each section
	 * 
	 * @param array $atts
	 * @return void
	 */
	public function render_section( $atts ) {
		// $atts['id']
		// $atts['title']
		// $atts['callback']
		$atts = $this->sections[ $atts['id'] ];
		// Load template
		$file_path = trailingslashit(__DIR__).'views/'.$atts['file'];
		if( is_file( $file_path ) && is_readable( $file_path ) ) {
			include( $file_path );
		}
	}
	
	/**
	 * Outputs the HTML of each input field
	 * 
	 * @param array $atts
	 * @return void
	 */
	public function render_field( $atts ) {
		// Load template
		$file_path = trailingslashit(__DIR__).'views/'.$atts['file'];
		if( is_file( $file_path ) && is_readable( $file_path ) ) {
			include( $file_path );
		}
	}
	
}
