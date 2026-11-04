<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
if ( ! txd_do_location( 'archive' ) ) {
	get_template_part( 'template-parts/content/archive' );
}
get_footer();
