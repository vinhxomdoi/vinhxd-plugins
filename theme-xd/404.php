<?php
/**
 * Template: 404
 *
 * @package ThemeXD
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

if ( ! txd_do_location( 'single' ) ) {
	if ( ! txd_render_elementor_template( txd_get_template_id( '404' ) ) ) {
		get_template_part( 'template-parts/content/404' );
	}
}

get_footer();
