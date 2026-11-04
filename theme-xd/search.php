<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
if ( ! txd_do_location( 'archive' ) ) {
	// Search Template gán trong Theme XD → Elementor Templates (trước đây lưu nhưng không dùng)
	if ( ! txd_render_elementor_template( txd_get_template_id( 'search' ) ) ) {
		get_template_part( 'template-parts/content/archive' );
	}
}
get_footer();
