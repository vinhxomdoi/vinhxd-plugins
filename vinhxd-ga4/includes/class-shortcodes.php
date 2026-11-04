<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Vinhxd_GA4_Shortcodes {

	public static function init() {
		add_shortcode( 'post_views_xd', array( __CLASS__, 'render_post_views' ) );
		add_shortcode( 'top_posts_xd', array( __CLASS__, 'render_top_posts' ) );
	}

	public static function render_post_views( $atts ) {
		$atts = shortcode_atts( array(
			'post_id'    => get_the_ID(),
			'label'      => 'lượt xem',
			'show_label' => 'yes',
			'class'      => '',
		), $atts, 'post_views_xd' );

		$post_id = (int) $atts['post_id'];
		if ( ! $post_id ) return '';

		$views = get_post_meta( $post_id, Vinhxd_GA4_Post_Views::META_KEY, true );
		$views_display = number_format_i18n( (int) $views );

		$class = 'vinhxd-ga4-post-views' . ( $atts['class'] ? ' ' . sanitize_html_class( $atts['class'] ) : '' );
		$label = ( $atts['show_label'] !== 'no' && $atts['label'] !== '' ) ? ' ' . esc_html( $atts['label'] ) : '';

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $views_display ) . $label . '</span>';
	}

	public static function render_top_posts( $atts ) {
		$atts = shortcode_atts( array(
			'post_type'  => 'post',
			'count'      => 5,
			'taxonomy'   => '',
			'term'       => '',
			'show_views' => 'yes',
		), $atts, 'top_posts_xd' );

		$post_types = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $atts['post_type'] ) ) ) );
		if ( empty( $post_types ) ) $post_types = array( 'post' );

		$count = max( 1, (int) $atts['count'] );

		$query_args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'meta_key'       => Vinhxd_GA4_Post_Views::META_KEY,
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'ignore_sticky_posts' => true,
		);

		if ( $atts['taxonomy'] && $atts['term'] ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => sanitize_key( $atts['taxonomy'] ),
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['term'] ),
				),
			);
		}

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '<p class="vinhxd-ga4-top-posts-empty">Không có dữ liệu. (Kiểm tra post type đã được tick ở XD Analytics &rarr; Cài đặt và đã đồng bộ lượt xem chưa.)</p>';
		}

		$show_views = $atts['show_views'] !== 'no';

		$output = '<ul class="vinhxd-ga4-top-posts">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$views = get_post_meta( get_the_ID(), Vinhxd_GA4_Post_Views::META_KEY, true );

			$output .= '<li class="vinhxd-ga4-top-posts-item">';
			$output .= '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
			if ( $show_views ) {
				$output .= ' <span class="vinhxd-ga4-top-posts-views">(' . esc_html( number_format_i18n( (int) $views ) ) . ' lượt xem)</span>';
			}
			$output .= '</li>';
		}
		$output .= '</ul>';

		wp_reset_postdata();

		return $output;
	}
}

Vinhxd_GA4_Shortcodes::init();
