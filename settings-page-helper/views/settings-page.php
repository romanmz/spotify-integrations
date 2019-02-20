<?php
/**
 * Displays the markup for the backend settings page
 * 
 * @version 1.0.0
 * @since 1.0.0
 * @package Locations_Search\Settings\Views
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ) ?></h1>
	<form action="options.php" method="post">
		<?php
		settings_fields( $this->page['id'] );
		do_settings_sections( $this->page['id'] );
		submit_button();
		?>
	</form>
</div>
