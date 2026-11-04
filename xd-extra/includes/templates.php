<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Template của plugin cho Bài viết (single/category/tag) và Page XD.
 * CPT (xe, dich-vu...) do JetThemeCore lo, plugin không đụng tới.
 */
add_filter( 'theme_page_templates', function ( $templates ) {
	$templates['page-xd-plugin'] = 'Page XD';
	return $templates;
} );

add_filter( 'template_include', function ( $template ) {
	if ( is_page() && get_post_meta( get_queried_object_id(), '_wp_page_template', true ) === 'page-xd-plugin' ) {
		return XD_TEMPLATES . 'page-xd.php';
	}
	if ( xd_uses_single_archive_template() ) {
		return XD_TEMPLATES . ( is_singular( 'post' ) ? 'single-template.php' : 'archive-template.php' );
	}
	return $template;
}, 99999 );

/** Ảnh đại diện hoặc khung trống (không gọi ảnh từ placehold.co). */
function xd_thumb( $size = 'medium', $attr = [] ) {
	if ( has_post_thumbnail() ) {
		$attr = array_merge( [ 'alt' => the_title_attribute( [ 'echo' => false ] ) ], $attr );
		return get_the_post_thumbnail( null, $size, $attr );
	}
	return '<span class="xd-noimg" aria-hidden="true"></span>';
}

/** Breadcrumb: Trang chủ » ... (có schema BreadcrumbList). */
function xd_breadcrumb( $items, $class = 'xd-breadcrumb' ) {
	array_unshift( $items, [ 'Trang chủ', home_url( '/' ) ] );
	$out = '<nav class="' . esc_attr( $class ) . '" aria-label="Breadcrumb"><ol itemscope itemtype="https://schema.org/BreadcrumbList">';
	$n = count( $items );
	foreach ( $items as $i => $it ) {
		$out .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
		if ( $i > 0 ) $out .= '<span class="xd-bc-sep" aria-hidden="true">»</span>';
		if ( ! empty( $it[1] ) && $i < $n - 1 ) {
			$out .= '<a itemprop="item" href="' . esc_url( $it[1] ) . '"><span itemprop="name">' . esc_html( $it[0] ) . '</span></a>';
		} else {
			$out .= '<span itemprop="name">' . esc_html( $it[0] ) . '</span>';
		}
		$out .= '<meta itemprop="position" content="' . ( $i + 1 ) . '"></li>';
	}
	return $out . '</ol></nav>';
}
