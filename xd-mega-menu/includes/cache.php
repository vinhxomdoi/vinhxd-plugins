<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
 * Cache phiên bản hoá
 * -------------------------------------------------------------------------
 * Mọi key cache đều gắn số phiên bản (option xd_mm_cache_ver). Khi dữ liệu
 * thay đổi chỉ cần tăng số này: key cũ không còn được dùng và tự hết hạn.
 * Cách này chạy đúng cả khi site dùng object cache (Redis/Memcached) –
 * nơi transient không nằm trong bảng wp_options nên không xoá bằng SQL được.
 * ========================================================================= */

function xd_mm_cache_ver() {
	return (int) get_option( 'xd_mm_cache_ver', 1 );
}

function xd_mm_bump_cache() {
	static $done = false;
	if ( $done ) {
		return; // Một request chỉ cần tăng 1 lần.
	}
	$done = true;
	update_option( 'xd_mm_cache_ver', xd_mm_cache_ver() + 1, true );
}

/**
 * Danh sách post type / meta key mà shortcode [xd_xe_grid] đang dùng,
 * để chỉ xoá cache khi đúng loại dữ liệu đó thay đổi.
 */
function xd_mm_grid_tracked() {
	$t = get_option( 'xd_mm_grid_track' );
	if ( ! is_array( $t ) ) {
		$t = [ 'types' => [ 'xe' ], 'metas' => [ 'gia' ] ];
	}
	$t['types'] = isset( $t['types'] ) ? (array) $t['types'] : [];
	$t['metas'] = isset( $t['metas'] ) ? (array) $t['metas'] : [];
	return $t;
}

function xd_mm_grid_track( $post_type, $meta_key ) {
	$t       = xd_mm_grid_tracked();
	$changed = false;
	if ( $post_type && ! in_array( $post_type, $t['types'], true ) ) {
		$t['types'][] = $post_type;
		$changed      = true;
	}
	if ( $meta_key && ! in_array( $meta_key, $t['metas'], true ) ) {
		$t['metas'][] = $meta_key;
		$changed      = true;
	}
	if ( $changed ) {
		update_option( 'xd_mm_grid_track', $t, true );
	}
}

/** Post type nào thay đổi thì cần tăng phiên bản cache. */
function xd_mm_is_tracked_type( $post_type ) {
	if ( ! $post_type ) {
		return false;
	}
	if ( 'elementor_library' === $post_type ) {
		return true; // Template mega menu (kể cả template lồng bên trong).
	}
	return in_array( $post_type, xd_mm_grid_tracked()['types'], true );
}

/* ---------- Các hook làm mới cache ---------- */

add_action( 'save_post', function ( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( $post && xd_mm_is_tracked_type( $post->post_type ) ) {
		xd_mm_bump_cache();
	}
}, 10, 2 );

// Đăng / gỡ / hẹn giờ / bỏ vào thùng rác / khôi phục.
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $new !== $old && $post && xd_mm_is_tracked_type( $post->post_type ) ) {
		xd_mm_bump_cache();
	}
}, 10, 3 );

add_action( 'deleted_post', function ( $post_id ) {
	if ( xd_mm_is_tracked_type( get_post_type( $post_id ) ) ) {
		xd_mm_bump_cache();
	}
} );

// Giá hoặc ảnh đại diện đổi mà không qua save_post (import, sửa nhanh, ACF/JetEngine…).
function xd_mm_on_meta_change( $meta_id, $object_id, $meta_key ) {
	if ( '_thumbnail_id' !== $meta_key && ! in_array( $meta_key, xd_mm_grid_tracked()['metas'], true ) ) {
		return;
	}
	$type = get_post_type( $object_id );
	if ( $type && 'elementor_library' !== $type && xd_mm_is_tracked_type( $type ) ) {
		xd_mm_bump_cache();
	}
}
add_action( 'added_post_meta', 'xd_mm_on_meta_change', 10, 3 );
add_action( 'updated_post_meta', 'xd_mm_on_meta_change', 10, 3 );
add_action( 'deleted_post_meta', 'xd_mm_on_meta_change', 10, 3 );

// Elementor: "Regenerate CSS", đổi Kit/Global, lưu bất kỳ tài liệu nào.
add_action( 'elementor/core/files/clear_cache', 'xd_mm_bump_cache' );
add_action( 'elementor/editor/after_save', 'xd_mm_bump_cache' );

/* =========================================================================
 * Shortcode [xd_xe_grid]
 * ========================================================================= */

/**
 * Lấy HTML danh sách card xe (ảnh/tên/giá), có cache transient.
 *
 * @param string $post_type   CPT chứa xe, vd: 'xe'
 * @param string $taxonomy    Taxonomy lọc theo hãng, vd: 'hang-xe'. Để trống nếu không lọc.
 * @param string $term        Slug term cần lọc, vd: 'vinfast'. Để trống = không lọc theo term.
 * @param int    $count       Số xe hiển thị
 * @param string $price_meta  Meta key chứa giá xe, vd: 'gia'
 * @param int    $columns     Số cột desktop
 * @param int    $columns_t   Số cột tablet (≤ 1024px)
 * @param int    $columns_m   Số cột mobile (≤ 767px)
 * @return string HTML
 */
function xd_mm_get_cards_html( $post_type, $taxonomy, $term, $count, $price_meta, $columns, $columns_t = 4, $columns_m = 2 ) {

	$columns   = max( 1, min( 12, (int) $columns ) );
	$columns_t = max( 1, min( 12, (int) $columns_t ) );
	$columns_m = max( 1, min( 12, (int) $columns_m ) );
	$count     = max( 1, min( 50, (int) $count ) );

	$cache_key = 'xd_mm_g' . xd_mm_cache_ver() . '_' . md5( implode( '|', [ $post_type, $taxonomy, $term, $count, $price_meta, $columns, $columns_t, $columns_m ] ) );

	$cached = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$args = [
		'post_type'              => $post_type,
		'post_status'            => 'publish',
		'posts_per_page'         => $count,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => false,
	];

	if ( ! empty( $taxonomy ) && ! empty( $term ) ) {
		$args['tax_query'] = [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $term,
			],
		];
	}

	$query = new WP_Query( $args );

	$style = sprintf( '--xd-mm-cols:%d;--xd-mm-cols-t:%d;--xd-mm-cols-m:%d', $columns, $columns_t, $columns_m );
	$html  = '<div class="xd-mm-grid" style="' . esc_attr( $style ) . '">';

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();

			$price      = get_post_meta( get_the_ID(), $price_meta, true );
			$price_html = ( '' !== $price && is_numeric( $price ) )
				? number_format( (float) $price, 0, '', '.' ) . ' đ'
				: '';

			$html .= '<a class="xd-mm-card" href="' . esc_url( get_permalink() ) . '">';
			if ( has_post_thumbnail() ) {
				// Ảnh nằm trong menu đang ẩn: luôn lazy, ưu tiên thấp – không để WP coi là ảnh LCP.
				$html .= '<div class="xd-mm-thumb">' . get_the_post_thumbnail( get_the_ID(), 'medium', [
					'loading'       => 'lazy',
					'fetchpriority' => 'low',
					'decoding'      => 'async',
				] ) . '</div>';
			}
			$html .= '<div class="xd-mm-name">' . esc_html( get_the_title() ) . '</div>';
			if ( '' !== $price_html ) {
				$html .= '<div class="xd-mm-price">Giá: ' . esc_html( $price_html ) . '</div>';
			}
			$html .= '</a>';
		}
		wp_reset_postdata();
	} else {
		$html .= '<p class="xd-mm-empty">Chưa có xe.</p>';
	}

	$html .= '</div>';

	set_transient( $cache_key, $html, 12 * HOUR_IN_SECONDS );

	return $html;
}

/**
 * Shortcode: [xd_xe_grid post_type="xe" taxonomy="hang-xe" term="vinfast" count="8" price_meta="gia" columns="8" columns_tablet="4" columns_mobile="2"]
 * Dùng để nhúng vào Elementor template (nội dung mega menu) qua widget Shortcode.
 */
add_shortcode( 'xd_xe_grid', function ( $atts ) {
	$atts = shortcode_atts( [
		'post_type'      => 'xe',
		'taxonomy'       => 'hang-xe',
		'term'           => '',
		'count'          => 8,
		'price_meta'     => 'gia',
		'columns'        => 8,
		'columns_tablet' => 4,
		'columns_mobile' => 2,
	], $atts, 'xd_xe_grid' );

	$post_type  = sanitize_key( $atts['post_type'] );
	$price_meta = sanitize_key( $atts['price_meta'] );

	xd_mm_grid_track( $post_type, $price_meta );

	// Dự phòng: trang nào chưa được dò sớm thì nạp CSS tại đây.
	xd_mm_register_assets();
	wp_enqueue_style( 'xd-xe-grid' );

	return xd_mm_get_cards_html(
		$post_type,
		sanitize_key( $atts['taxonomy'] ),
		sanitize_title( $atts['term'] ),
		(int) $atts['count'],
		$price_meta,
		(int) $atts['columns'],
		(int) $atts['columns_tablet'],
		(int) $atts['columns_mobile']
	);
} );

/**
 * Dò sớm: trang đang xem có shortcode trong nội dung / dữ liệu Elementor
 * thì nạp CSS ngay trong <head>.
 * (Shortcode nằm trong mega menu thì CSS được nạp ở footer – không sao vì menu đang ẩn.)
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$found = false !== strpos( (string) $post->post_content, '[xd_xe_grid' );
	if ( ! $found ) {
		$data  = get_post_meta( $post->ID, '_elementor_data', true );
		$found = is_string( $data ) && false !== strpos( $data, 'xd_xe_grid' );
	}
	if ( $found ) {
		wp_enqueue_style( 'xd-xe-grid' );
	}
}, 20 );
