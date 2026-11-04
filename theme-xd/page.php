<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
if ( ! txd_do_location( 'single' ) ) {
	if ( txd_is_elementor_content() ) {
		while ( have_posts() ) { the_post(); the_content(); }
	} else {
		// Trước đây dùng nhầm template bài viết (hiện tác giả, chia sẻ, bài liên quan trên trang tĩnh)
		get_template_part( 'template-parts/content/page' );
	}
}
get_footer();
