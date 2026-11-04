<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// Elementor Canvas hoặc đang edit/xem elementor_library → không render header/footer theme
$page_template = get_page_template_slug();
$post_type     = get_post_type();
$is_lib        = ( 'elementor_library' === $post_type );
$is_canvas     = ( 'elementor_canvas' === $page_template );

if ( $is_lib || $is_canvas ) return;

?>
<a class="txd-skip-link" href="#txd-main"><?php esc_html_e( 'Chuyển đến nội dung', 'theme-xd' ); ?></a>
<div class="txd-header">
<?php
if ( ! txd_do_location( 'header' ) ) {
	if ( ! txd_render_elementor_template( txd_get_template_id( 'header' ) ) ) {
		get_template_part( 'template-parts/header/fallback-header' );
	}
}
?>
</div>

<main id="txd-main" class="txd-main" role="main">
