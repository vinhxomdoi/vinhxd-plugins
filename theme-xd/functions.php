<?php
/**
 * Theme XD - functions.php
 * Tương thích: Elementor, JetThemeCore (Crocoblock)
 *
 * @package ThemeXD
 * @version 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TXD_VERSION', '1.9.0' );
define( 'TXD_DIR',  get_template_directory() );
define( 'TXD_URI',  get_template_directory_uri() );

/* ============================================================
   HÀM CỐT LÕI - JETTHEMECORE LOCATION
============================================================ */
function txd_process_location( $location = null ) {
	if ( ! function_exists( 'jet_theme_core' ) || ! defined( 'ELEMENTOR_VERSION' ) ) {
		return false;
	}
	return jet_theme_core()->locations->do_location( $location );
}

/**
 * Thử render location qua JetThemeCore → Elementor Pro. Trả về true nếu đã render.
 */
function txd_do_location( $location ) {
	if ( txd_process_location( $location ) ) return true;
	if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location ) ) return true;
	return false;
}

/**
 * Bài/trang hiện tại có dựng bằng Elementor (hoặc là elementor_library) không.
 */
function txd_is_elementor_content( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	if ( 'elementor_library' === get_post_type( $post_id ) ) return true;
	return class_exists( '\Elementor\Plugin' )
		&& isset( \Elementor\Plugin::$instance->db )
		&& \Elementor\Plugin::$instance->db->is_built_with_elementor( $post_id );
}

/* ============================================================
   THEME SETUP
============================================================ */
add_action( 'after_setup_theme', 'txd_setup' );
function txd_setup() {
	load_theme_textdomain( 'theme-xd', TXD_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', [ 'search-form','comment-form','comment-list','gallery','caption','style','script','navigation-widgets' ] );
	add_theme_support( 'post-formats', [ 'aside','gallery','quote','video','audio' ] );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'woocommerce' );

	// Theme không đăng ký image size riêng (dùng size gốc WP: medium / medium_large / large).
	// Size tuỳ chỉnh quản lý bằng plugin XD Extra.

	register_nav_menus([
		'primary'   => __( 'Menu chính', 'theme-xd' ),
		'footer'    => __( 'Menu footer', 'theme-xd' ),
		'secondary' => __( 'Menu phụ', 'theme-xd' ),
	]);

	global $content_width;
	if ( ! isset( $content_width ) ) $content_width = 780;
}

/* ============================================================
   ENQUEUE
   - main.js không còn phụ thuộc jQuery, nạp defer
   - Chỉ enqueue từ template-part (single/archive) → trang Elementor/JetThemeCore không tải
============================================================ */
add_action( 'wp_enqueue_scripts', 'txd_enqueue' );
function txd_enqueue() {
	wp_enqueue_style( 'theme-xd-style', get_stylesheet_uri(), [], TXD_VERSION );
	wp_add_inline_style( 'theme-xd-style', txd_inline_css() );

	wp_register_script( 'theme-xd-main', TXD_URI . '/assets/js/main.js', [], TXD_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

/**
 * Gộp toàn bộ CSS động (Customizer + XD Extra) vào 1 khối inline ngay sau style.css.
 * (Trước đây XD Extra in ở wp_head:6, TRƯỚC style.css nên bị :root mặc định ghi đè → không có tác dụng.)
 */
function txd_inline_css() {
	$css = '';

	// Customizer
	$content_width = absint( get_theme_mod( 'txd_content_width', 1200 ) );
	if ( $content_width && 1200 !== $content_width ) {
		$css .= ':root{--txd-max-width:' . $content_width . 'px}';
	}
	if ( 'left' === get_theme_mod( 'txd_sidebar_position', 'right' ) ) {
		$css .= '@media(min-width:1025px){.txd-single-layout.has-sidebar,.txd-archive-layout.has-sidebar{grid-template-columns:var(--txd-sidebar-width) minmax(0,1fr)}.has-sidebar>.txd-sidebar{order:-1}}';
	}

	// XD Extra plugin → biến theme
	$primary   = sanitize_hex_color( get_option( 'xd_color_primary', '' ) );
	$secondary = sanitize_hex_color( get_option( 'xd_color_secondary', '' ) );
	$text      = sanitize_hex_color( get_option( 'xd_color_text', '' ) );
	$accent    = sanitize_hex_color( get_option( 'xd_color_accent', '' ) );
	$font      = trim( preg_replace( '/[^\p{L}\p{N}\s\-]/u', '', (string) get_option( 'xd_font_family', '' ) ) );

	$vars = '';
	if ( $primary )   $vars .= '--txd-accent:' . $primary . ';';
	if ( $secondary ) $vars .= '--txd-primary:' . $secondary . ';';
	if ( $text )      $vars .= '--txd-text:' . $text . ';';
	if ( $accent )    $vars .= '--txd-highlight:' . $accent . ';';
	if ( $font )      $vars .= '--txd-font:"' . $font . '";';
	if ( $vars ) $css .= ':root{' . $vars . '}';
	if ( $font ) $css .= 'body,h1,h2,h3,h4,h5,h6{font-family:var(--txd-font),system-ui,sans-serif}';

	return $css;
}

/* ============================================================
   TỐI ƯU TỐC ĐỘ
   Có thể tắt từng mục bằng filter, ví dụ:
   add_filter( 'txd_disable_emoji', '__return_false' );
============================================================ */
add_action( 'init', 'txd_perf_cleanup' );
function txd_perf_cleanup() {
	// Bỏ các thẻ <head> thừa
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );

	// Emoji: bỏ script + CSS inline (trình duyệt hiện đại tự hiển thị emoji)
	if ( apply_filters( 'txd_disable_emoji', true ) ) {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
		add_filter( 'wp_resource_hints', function ( $urls, $type ) {
			if ( 'dns-prefetch' === $type ) {
				$urls = array_filter( $urls, function ( $u ) {
					return false === strpos( is_array( $u ) ? ( $u['href'] ?? '' ) : $u, 's.w.org' );
				} );
			}
			return $urls;
		}, 10, 2 );
	}
}

/**
 * Gutenberg đã tắt → bỏ CSS block (~15KB gzip) khi trang không cần.
 * Giữ lại nếu: sidebar có widget dạng block, hoặc nội dung bài có block.
 */
add_action( 'wp_enqueue_scripts', 'txd_dequeue_block_css', 100 );
function txd_dequeue_block_css() {
	if ( ! apply_filters( 'txd_disable_block_css', true ) ) return;
	if ( txd_has_block_widgets() ) return;
	if ( is_singular() && has_blocks( get_queried_object_id() ) ) return;

	foreach ( [ 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles' ] as $h ) {
		wp_dequeue_style( $h );
	}
}

function txd_has_block_widgets() {
	$sidebars = wp_get_sidebars_widgets();
	unset( $sidebars['wp_inactive_widgets'] );
	foreach ( (array) $sidebars as $widgets ) {
		foreach ( (array) $widgets as $w ) {
			if ( 0 === strpos( $w, 'block-' ) ) return true;
		}
	}
	return false;
}

/* ============================================================
   WIDGETS
============================================================ */
add_action( 'widgets_init', 'txd_widgets_init' );
function txd_widgets_init() {
	$args = [
		'before_widget' => '<div id="%1$s" class="txd-widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="txd-widget-title">',
		'after_title'   => '</h3>',
	];
	register_sidebar( array_merge( $args, ['name' => __('Sidebar chính','theme-xd'), 'id' => 'sidebar-main'] ));
	register_sidebar( array_merge( $args, ['name' => __('Sidebar trang đơn','theme-xd'), 'id' => 'sidebar-page'] ));
	register_sidebar( array_merge( $args, ['name' => __('Footer Col 1','theme-xd'), 'id' => 'footer-1'] ));
	register_sidebar( array_merge( $args, ['name' => __('Footer Col 2','theme-xd'), 'id' => 'footer-2'] ));
	register_sidebar( array_merge( $args, ['name' => __('Footer Col 3','theme-xd'), 'id' => 'footer-3'] ));
}

/* ============================================================
   BODY CLASSES
============================================================ */
add_filter( 'body_class', 'txd_body_classes' );
function txd_body_classes( $classes ) {
	if ( is_singular() )                             $classes[] = 'txd-single';
	if ( is_archive() || is_home() || is_search() )  $classes[] = 'txd-archive';
	if ( is_page() )                                 $classes[] = 'txd-page';
	if ( function_exists('jet_theme_core') )         $classes[] = 'txd-jetcore';
	if ( ! txd_has_sidebar() )                       $classes[] = 'txd-no-sidebar';
	return $classes;
}

/* ============================================================
   HELPER FUNCTIONS
============================================================ */
function txd_get_template_id( $type ) {
	return absint( get_option( 'txd_template_' . $type, 0 ) );
}

function txd_render_elementor_template( $template_id ) {
	if ( ! $template_id || ! class_exists('\Elementor\Plugin') ) return false;
	echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true ); // phpcs:ignore
	return true;
}

/**
 * Có sidebar để hiển thị không (tránh cột 320px trống khi chưa gán widget).
 */
function txd_has_sidebar( $sidebar_id = 'sidebar-main' ) {
	static $cache = [];
	if ( isset( $cache[ $sidebar_id ] ) ) return $cache[ $sidebar_id ];
	if ( 'none' === get_theme_mod( 'txd_sidebar_position', 'right' ) ) return $cache[ $sidebar_id ] = false;
	return $cache[ $sidebar_id ] = ( txd_get_template_id( 'sidebar' ) || is_active_sidebar( $sidebar_id ) );
}

function txd_sidebar( $sidebar_id = 'sidebar-main' ) {
	$template_id = txd_get_template_id('sidebar');
	if ( $template_id ) { txd_render_elementor_template($template_id); return; }
	if ( is_active_sidebar($sidebar_id) ) dynamic_sidebar($sidebar_id);
}

function txd_breadcrumb() {
	if ( function_exists('yoast_breadcrumb') ) {
		yoast_breadcrumb('<nav class="txd-breadcrumb" aria-label="breadcrumb"><div class="txd-container">', '</div></nav>');
		return;
	}
	if ( function_exists('rank_math_the_breadcrumbs') ) {
		echo '<nav class="txd-breadcrumb" aria-label="breadcrumb"><div class="txd-container">';
		rank_math_the_breadcrumbs();
		echo '</div></nav>';
		return;
	}

	$items   = [];
	$items[] = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Trang chủ', 'theme-xd' ) . '</a>';

	$cat_trail = function ( $term ) use ( &$items ) {
		foreach ( array_reverse( get_ancestors( $term->term_id, 'category' ) ) as $pid ) {
			$p = get_term( $pid, 'category' );
			if ( $p && ! is_wp_error( $p ) ) $items[] = '<a href="' . esc_url( get_category_link( $p ) ) . '">' . esc_html( $p->name ) . '</a>';
		}
	};

	if ( is_single() ) {
		$cats = get_the_category();
		if ( $cats ) {
			$cat_trail( $cats[0] );
			$items[] = '<a href="' . esc_url( get_category_link( $cats[0] ) ) . '">' . esc_html( $cats[0]->name ) . '</a>';
		}
		$items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_category() ) {
		$term = get_queried_object();
		$cat_trail( $term );
		$items[] = '<span aria-current="page">' . esc_html( $term->name ) . '</span>';
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $pid ) {
			$items[] = '<a href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a>';
		}
		$items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_search() ) {
		/* translators: %s: search keyword */
		$items[] = '<span aria-current="page">' . sprintf( esc_html__( 'Tìm kiếm: %s', 'theme-xd' ), esc_html( get_search_query( false ) ) ) . '</span>';
	} elseif ( is_archive() ) {
		$items[] = '<span aria-current="page">' . esc_html( wp_strip_all_tags( txd_archive_title() ) ) . '</span>';
	} elseif ( is_home() && ! is_front_page() ) {
		$items[] = '<span aria-current="page">' . esc_html( get_the_title( get_option( 'page_for_posts' ) ) ) . '</span>';
	}

	echo '<nav class="txd-breadcrumb" aria-label="breadcrumb"><div class="txd-container"><ol><li>' . implode( '</li><li>', $items ) . '</li></ol></div></nav>'; // phpcs:ignore
}

/**
 * Tiêu đề archive không kèm tiền tố "Chuyên mục:", "Thẻ:"...
 */
function txd_archive_title() {
	add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
	$title = get_the_archive_title();
	remove_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
	return $title;
}

/**
 * Thời gian đọc. Đếm theo khoảng trắng (str_word_count cũ đếm sai tiếng Việt có dấu).
 */
function txd_reading_time( $post_id = null ) {
	$content = get_post_field( 'post_content', $post_id ?: get_the_ID() );
	$text    = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );
	$words   = $text === '' ? 0 : count( preg_split( '/\s+/u', $text ) );
	$minutes = max( 1, (int) ceil( $words / 220 ) );
	/* translators: %d: minutes */
	return sprintf( _n( '%d phút đọc', '%d phút đọc', $minutes, 'theme-xd' ), $minutes );
}

/**
 * Bài liên quan: bỏ ORDER BY RAND() (rất chậm khi DB lớn), cache 12h.
 */
function txd_get_related_posts( $post_id = null, $count = 3 ) {
	$post_id = $post_id ?: get_the_ID();
	$count   = max( 1, min( 12, absint( $count ) ) );
	$key     = 'txd_rel_' . $post_id . '_' . $count . '_' . absint( get_option( 'txd_rel_ver', 1 ) );
	$ids     = get_transient( $key );

	if ( false === $ids ) {
		$cats = wp_get_post_categories( $post_id );
		$args = [
			'post__not_in'           => [ $post_id ],
			'posts_per_page'         => $count,
			'ignore_sticky_posts'    => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];
		if ( $cats ) $args['category__in'] = $cats;
		$ids = get_posts( $args );
		set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
	}
	if ( empty( $ids ) ) return [];

	$q = new WP_Query( [
		'post__in'            => array_map( 'absint', (array) $ids ),
		'orderby'             => 'post__in',
		'posts_per_page'      => count( $ids ),
		'ignore_sticky_posts' => 1,
		'no_found_rows'       => true,
	] );
	update_post_thumbnail_cache( $q ); // 1 query cho toàn bộ ảnh thay vì N query
	return $q->posts;
}
// Làm mới cache bài liên quan khi đăng/sửa bài (đổi "phiên bản" key, an toàn với object cache)
add_action( 'save_post_post', function () {
	update_option( 'txd_rel_ver', absint( get_option( 'txd_rel_ver', 1 ) ) + 1, false );
} );

/**
 * Excerpt nhanh: KHÔNG gọi get_the_excerpt() khi bài không có excerpt
 * (vì nó chạy the_content → render cả Elementor cho từng bài trong archive).
 */
function txd_excerpt( $length = 120, $post_id = null ) {
	$post    = get_post( $post_id ?: get_the_ID() );
	if ( ! $post ) return '';
	$excerpt = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
	$excerpt = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $excerpt ) ) ) );
	$excerpt = html_entity_decode( $excerpt, ENT_QUOTES, 'UTF-8' );
	if ( mb_strlen( $excerpt ) <= $length ) return $excerpt;
	$cut = mb_substr( $excerpt, 0, $length );
	$sp  = mb_strrpos( $cut, ' ' );
	if ( $sp > $length * 0.6 ) $cut = mb_substr( $cut, 0, $sp );
	return rtrim( $cut, " ,.;:-–" ) . '…';
}

function txd_share_buttons() {
	$permalink = get_permalink();
	$fb = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $permalink );
	$x  = 'https://twitter.com/intent/tweet?url=' . rawurlencode( $permalink ) . '&text=' . rawurlencode( get_the_title() );
	?>
	<div class="txd-share">
		<span class="txd-share-label"><?php esc_html_e( 'Chia sẻ:', 'theme-xd' ); ?></span>
		<a class="txd-share-btn facebook" href="<?php echo esc_url( $fb ); ?>" target="_blank" rel="noopener nofollow">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
			Facebook
		</a>
		<a class="txd-share-btn twitter" href="<?php echo esc_url( $x ); ?>" target="_blank" rel="noopener nofollow">X</a>
		<button type="button" class="txd-share-btn copy-link" data-url="<?php echo esc_url( $permalink ); ?>" data-copied="<?php esc_attr_e( 'Đã sao chép!', 'theme-xd' ); ?>">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
			<?php esc_html_e( 'Sao chép link', 'theme-xd' ); ?>
		</button>
	</div>
	<?php
}

/* ============================================================
   NỘI DUNG BÀI VIẾT (Classic editor)
   - Bọc <table> trong .txd-table-wrap để cuộn ngang trên mobile
   - Bỏ <p>&nbsp;</p> rỗng (hay gặp khi copy từ Word/web) gây hở khoảng cách
============================================================ */
add_filter( 'the_content', 'txd_content_filter', 20 );
function txd_content_filter( $content ) {
	if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() ) return $content;
	if ( txd_is_elementor_content() ) return $content;

	$content = preg_replace( '#<p[^>]*>(?:\s|&nbsp;|&\#160;|\x{00A0}|<br\s*/?>)*</p>#iu', '', $content );

	if ( false !== stripos( $content, '<table' ) && false === strpos( $content, 'txd-table-wrap' ) ) {
		$content = preg_replace( '#<table\b#i', '<div class="txd-table-wrap"><table', $content );
		$content = preg_replace( '#</table>#i', '</table></div>', $content );
	}
	return $content;
}

/* ============================================================
   MISC
============================================================ */
add_filter('excerpt_length', function(){ return 25; });
add_filter('excerpt_more',   function(){ return '…'; });

/**
 * Mã nhúng: nếu đoạn mã đã có thẻ HTML (<script>, <noscript>, <meta>...) thì in nguyên,
 * nếu chỉ là JS thuần thì tự bọc <script>.
 */
function txd_print_code( $code, $id ) {
	$code = trim( (string) $code );
	if ( '' === $code ) return;
	if ( preg_match( '/<\s*(script|noscript|style|link|meta|iframe|div|img|!--)/i', $code ) ) {
		echo "\n" . $code . "\n"; // phpcs:ignore
	} else {
		echo '<script id="' . esc_attr( $id ) . '">' . $code . '</script>' . "\n"; // phpcs:ignore
	}
}

add_action('wp_head', 'txd_output_custom_head', 100);
function txd_output_custom_head() {
	$css        = get_option('txd_custom_css','');
	$css_mobile = get_option('txd_custom_css_mobile','');
	if ($css)        echo '<style id="txd-custom-css">' . $css . '</style>'; // phpcs:ignore
	if ($css_mobile) echo '<style id="txd-custom-css-mobile">@media (max-width: 768px){' . $css_mobile . '}</style>'; // phpcs:ignore
	txd_print_code( get_option('txd_custom_js_head',''), 'txd-custom-js-head' );
}
add_action('wp_footer', 'txd_output_custom_foot', 100);
function txd_output_custom_foot() {
	txd_print_code( get_option('txd_custom_js_foot',''), 'txd-custom-js-foot' );
}

/**
 * Dọn option TOC cũ (chạy 1 lần trong admin sau khi cập nhật theme).
 */
add_action( 'admin_init', function () {
	if ( get_option( 'txd_db_version' ) === TXD_VERSION ) return;
	delete_option( 'txd_auto_toc' );
	remove_theme_mod( 'txd_auto_toc' );
	update_option( 'txd_db_version', TXD_VERSION, true );
} );

/* ============================================================
   CUSTOM FUNCTIONS (Vĩnh XD)
============================================================ */

/**
 * Hook Footer - Credit Vĩnh XD
 */
add_action( 'wp_footer', 'foot_xd' );
function foot_xd() {
	echo '<div class="footxd"><a href="https://www.vinhxd.com/thiet-ke-website-ban-o-to/" target="_blank" rel="noopener">Thiết Kế Web Ô tô</a> Bởi Vĩnh XD</div>';
}

/**
 * Tắt Gutenberg editor
 */
add_filter( 'use_block_editor_for_post', '__return_false' );

/**
 * Dashboard Widget - Bảng Hướng Dẫn
 */
add_action( 'wp_dashboard_setup', 'my_custom_dashboard_widgets', 1000 );
function my_custom_dashboard_widgets() {
	wp_add_dashboard_widget( 'custom_help_widget', 'Hướng Dẫn Sử Dụng', 'custom_dashboard_help' );

	global $wp_meta_boxes;
	if ( empty( $wp_meta_boxes['dashboard']['normal']['core']['custom_help_widget'] ) ) return;
	$dashboard_widgets        = $wp_meta_boxes['dashboard']['normal']['core'];
	$dashboard_widgets_backup = [ 'custom_help_widget' => $dashboard_widgets['custom_help_widget'] ];
	unset( $dashboard_widgets['custom_help_widget'] );
	$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $dashboard_widgets_backup, $dashboard_widgets );
}
function custom_dashboard_help() {
	echo '<p>Cảm ơn bạn đã dùng Website thiết kế bởi <a href="https://www.vinhxd.com" target="_blank" style="background:#23a455;padding:2px;border-radius:2px;margin:0 2px;color:#fff;">Vĩnh XD</a>. Nếu bạn cần thêm hướng dẫn, vui lòng truy cập <a href="https://www.vinhxd.com/huong-dan-su-dung/" target="_blank">Trang Hướng Dẫn Sử Dụng</a>.</p>
	<ul>
		<li><a href="https://www.vinhxd.com/tao-chinh-sua-bai-viet-wordpress/" target="_blank">Làm Quen Với WordPress - Đăng, sửa bài viết, sản phẩm</a></li>
		<li><a href="https://www.vinhxd.com/huong-dan-nen-anh-bang-phan-mem-tren-may-tinh/" target="_blank">Hướng dẫn nén ảnh - rất quan trọng nếu bạn muốn website tải nhanh</a></li>
		<li><a href="https://www.vinhxd.com/huong-dan-su-dung-elementor/" target="_blank">Hướng Dẫn Sử Dụng Elementor</a></li>
		<li><a href="https://www.vinhxd.com/huong-dan-viet-bai-chuan-seo/" target="_blank">Hướng dẫn viết bài chuẩn SEO</a></li>
		<li><a href="https://www.vinhxd.com/cach-copy-bai-viet-van-chuan-seo/" target="_blank">Hướng dẫn copy bài viết mà không làm giảm chất lượng SEO</a></li>
	</ul>
	<iframe width="520" height="300" style="max-width:100%" src="https://www.youtube-nocookie.com/embed/p6AIwP4H2bo" loading="lazy" frameborder="0" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
}

/**
 * Toolbar - Hướng Dẫn Sử Dụng
 */
add_action( 'admin_bar_menu', 'toolbar_huong_dan_sd', 999 );
function toolbar_huong_dan_sd( $wp_admin_bar ) {
	$wp_admin_bar->add_node([
		'id'    => 'huong-dan',
		'title' => 'Hướng Dẫn Sử Dụng Website',
		'href'  => 'https://www.vinhxd.com/huong-dan-su-dung/',
		'meta'  => [ 'class' => 'huong-dan-xd', 'title' => 'Hướng Dẫn Sử Dụng Website' ],
	]);
}

/* ============================================================
   INCLUDES
============================================================ */
require_once TXD_DIR . '/inc/theme-options.php';
require_once TXD_DIR . '/inc/widgets/menu-xd.php';
require_once TXD_DIR . '/inc/customizer.php';
