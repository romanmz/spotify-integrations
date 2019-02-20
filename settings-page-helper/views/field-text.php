<?php
/**
 * Displays the markup for text input fields
 * 
 * @version 1.0.0
 * @since 1.0.0
 * @package Locations_Search\Settings\Views
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

?>
<input type="<?php esc_attr_e( $atts['type'] ) ?>" id="<?php esc_attr_e( $atts['id'] ) ?>" class="large-text" name="<?php esc_attr_e( $atts['name'] ) ?>" value="<?php esc_attr_e( $atts['value'] ) ?>">
<?php if( !empty( $atts['description'] ) ) : ?>
	<p class="description"><?php echo wp_kses_post( $atts['description'] ) ?></p>
<?php endif ?>
