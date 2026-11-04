<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
if ( ! txd_do_location( 'single' ) ) {
	if ( txd_is_elementor_content() ) {
		while ( have_posts() ) { the_post(); the_content(); }
	} else {
		get_template_part( 'template-parts/content/single' );
	}
}
get_footer();
