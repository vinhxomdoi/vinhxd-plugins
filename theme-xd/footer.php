<?php
$page_template = get_page_template_slug();
$post_type     = get_post_type();
$is_lib        = ( 'elementor_library' === $post_type );
$is_canvas     = ( 'elementor_canvas' === $page_template );

if ( $is_lib || $is_canvas ) {
	wp_footer();
	echo '</body></html>';
	return;
}
?>

	</main><!-- #txd-main -->

<div class="txd-footer">
<?php
if ( ! txd_do_location( 'footer' ) ) {
	if ( ! txd_render_elementor_template( txd_get_template_id( 'footer' ) ) ) {
		get_template_part( 'template-parts/footer/fallback-footer' );
	}
}
?>
</div>

<?php wp_footer(); ?>
</body>
</html>
