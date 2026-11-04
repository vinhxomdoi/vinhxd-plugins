<?php
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

if ( is_singular() ) {
	if ( ! txd_do_location( 'single' ) ) {
		if ( txd_is_elementor_content() ) {
			while ( have_posts() ) { the_post(); the_content(); }
		} else {
			get_template_part( 'template-parts/content/' . ( is_page() ? 'page' : 'single' ) );
		}
	}
} elseif ( is_archive() || is_home() || is_search() ) {
	if ( ! txd_do_location( 'archive' ) ) {
		get_template_part( 'template-parts/content/archive' );
	}
} else {
	get_template_part( 'template-parts/content/404' );
}

get_footer();
