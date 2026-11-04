<?php
/**
 * Archive / Blog / Search — layout tối giản
 * - Bài đầu (chỉ trang 1) nổi bật: ảnh trái, chữ phải
 * - Còn lại: lưới card không viền, phân tách bằng khoảng trắng
 * - Mobile: card dạng hàng ngang (ảnh nhỏ bên trái) cho gọn
 *
 * @package ThemeXD
 */
global $wp_query;

$has_sidebar = txd_has_sidebar();
if ( $has_sidebar ) wp_enqueue_script( 'theme-xd-main' );

$paged      = max( 1, (int) get_query_var( 'paged' ) );
$show_rt    = (bool) get_option( 'txd_reading_time', 1 );
$found      = (int) $wp_query->found_posts;

// Nhãn nhỏ phía trên tiêu đề + tiêu đề + mô tả
$eyebrow = '';
$desc    = '';
if ( is_search() ) {
	$eyebrow = __( 'Tìm kiếm', 'theme-xd' );
	/* translators: %s: search keyword */
	$title   = sprintf( __( 'Kết quả cho “%s”', 'theme-xd' ), get_search_query( false ) );
} elseif ( is_category() ) {
	$eyebrow = __( 'Chuyên mục', 'theme-xd' );
	$title   = single_cat_title( '', false );
	$desc    = category_description();
} elseif ( is_tag() ) {
	$eyebrow = __( 'Thẻ', 'theme-xd' );
	$title   = '#' . single_tag_title( '', false );
	$desc    = tag_description();
} elseif ( is_author() ) {
	$eyebrow = __( 'Tác giả', 'theme-xd' );
	$title   = get_the_author_meta( 'display_name', (int) get_query_var( 'author' ) );
	$desc    = get_the_author_meta( 'description', (int) get_query_var( 'author' ) );
} elseif ( is_archive() ) {
	$title   = wp_strip_all_tags( txd_archive_title() );
	$desc    = get_the_archive_description();
} elseif ( is_home() && ! is_front_page() && get_option( 'page_for_posts' ) ) {
	$title   = get_the_title( get_option( 'page_for_posts' ) );
} else {
	$title   = __( 'Bài viết mới nhất', 'theme-xd' );
}

if ( get_option( 'txd_show_breadcrumb', 1 ) ) txd_breadcrumb();
?>

<div class="txd-archive-layout<?php echo $has_sidebar ? ' has-sidebar' : ''; ?>">
	<div class="txd-archive-main">

		<header class="txd-archive-header">
			<?php if ( $eyebrow ) : ?><span class="txd-archive-eyebrow"><?php echo esc_html( $eyebrow ); ?></span><?php endif; ?>
			<h1 class="txd-archive-title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $desc ) : ?><div class="txd-archive-desc"><?php echo wp_kses_post( wpautop( $desc ) ); ?></div><?php endif; ?>
			<p class="txd-archive-count">
				<?php
				/* translators: %s: number of posts */
				printf( esc_html( _n( '%s bài viết', '%s bài viết', $found, 'theme-xd' ) ), esc_html( number_format_i18n( $found ) ) );
				if ( $paged > 1 ) {
					/* translators: 1: current page, 2: total pages */
					echo ' · ' . esc_html( sprintf( __( 'Trang %1$d/%2$d', 'theme-xd' ), $paged, (int) $wp_query->max_num_pages ) );
				}
				?>
			</p>
			<?php if ( is_search() ) get_search_form(); ?>
		</header>

		<?php if ( have_posts() ) : ?>
		<div class="txd-post-grid">
			<?php
			$i = 0;
			while ( have_posts() ) : the_post();
				$i++;
				$featured  = ( 1 === $i && 1 === $paged && ! is_search() );
				$cats      = get_the_category();
				$cat       = $cats ? $cats[0] : null;
				$has_thumb = has_post_thumbnail();
				$classes   = 'txd-card' . ( $featured ? ' txd-card--featured' : '' ) . ( $has_thumb ? '' : ' txd-card--no-thumb' );

				$img_attr = [ 'alt' => '' ];
				if ( $featured ) {
					$img_attr += [ 'sizes' => '(max-width: 860px) 100vw, 480px', 'loading' => 'eager', 'fetchpriority' => 'high' ];
				} else {
					$img_attr += [ 'sizes' => '(max-width: 640px) 120px, (max-width: 1024px) 50vw, 400px' ];
				}
			?>
			<article <?php post_class( $classes ); ?>>
				<?php if ( $has_thumb ) : ?>
				<a href="<?php the_permalink(); ?>" class="txd-card__thumb" tabindex="-1" aria-hidden="true">
					<?php the_post_thumbnail( $featured ? 'large' : 'medium_large', $img_attr ); ?>
				</a>
				<?php endif; ?>

				<div class="txd-card__body">
					<?php if ( $cat ) : ?>
					<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="txd-card__cat"><?php echo esc_html( $cat->name ); ?></a>
					<?php endif; ?>

					<h2 class="txd-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

					<p class="txd-card__excerpt"><?php echo esc_html( txd_excerpt( $featured ? 200 : 120 ) ); ?></p>

					<div class="txd-card__meta">
						<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?></time>
						<?php if ( $show_rt ) : ?><span><?php echo esc_html( txd_reading_time() ); ?></span><?php endif; ?>
					</div>
				</div>
			</article>
			<?php endwhile; ?>
		</div>

		<?php
		$links = paginate_links( [
			'prev_text' => '<span aria-hidden="true">←</span> ' . esc_html__( 'Trước', 'theme-xd' ),
			'next_text' => esc_html__( 'Tiếp', 'theme-xd' ) . ' <span aria-hidden="true">→</span>',
			'type'      => 'plain',
			'mid_size'  => 1,
			'end_size'  => 1,
		] );
		if ( $links ) : ?>
		<nav class="txd-pagination" aria-label="<?php esc_attr_e( 'Phân trang', 'theme-xd' ); ?>"><?php echo $links; // phpcs:ignore ?></nav>
		<?php endif; ?>

		<?php else : ?>
		<div class="txd-archive-empty">
			<p><?php esc_html_e( 'Không tìm thấy bài viết nào.', 'theme-xd' ); ?></p>
			<?php if ( ! is_search() ) get_search_form(); ?>
		</div>
		<?php endif; ?>
	</div>

	<?php if ( $has_sidebar ) : ?>
	<aside class="txd-sidebar" role="complementary">
		<?php txd_sidebar( 'sidebar-main' ); ?>
	</aside>
	<?php endif; ?>
</div>
