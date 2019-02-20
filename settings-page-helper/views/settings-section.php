<?php
/**
 * Displays the markup for the backend settings sections
 * 
 * @version 1.0.0
 * @since 1.0.0
 * @package Locations_Search\Settings\Views
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

?>
<?php if( !empty( $atts['description'] ) ) : ?>
	<p><?php echo wp_kses_post( $atts['description'] ) ?></p>
<?php endif ?>
