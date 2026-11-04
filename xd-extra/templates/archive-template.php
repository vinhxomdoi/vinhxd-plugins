<?php
/**
 * XD – Archive bài viết (category, tag)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$term_title = is_category() || is_tag() ? single_term_title( '', false ) : wp_strip_all_tags( get_the_archive_title() );
$sdt        = xd_info( 'sdt' );
?>
<div class="xd-archive-page">
	<div class="xd-arc-hero">
		<div class="xd-container">
			<?php echo xd_breadcrumb( [ [ $term_title, '' ] ] ); ?>
			<h1 class="xd-arc-title"><?php echo esc_html( $term_title ); ?></h1>
		</div>
	</div>

	<div class="xd-container">
		<div class="xd-arc-layout">
			<main class="xd-arc-main">
				<?php if ( have_posts() ) : ?>
					<div class="xd-arc-grid">
						<?php $n = 0; while ( have_posts() ) : the_post(); $n++;
							$cats = get_the_category(); ?>
						<article class="xd-arc-card">
							<a href="<?php the_permalink(); ?>" class="xd-arc-thumb">
								<?php echo xd_thumb( 'medium_large', $n <= 3 ? [ 'loading' => 'eager' ] : [] ); ?>
								<span class="xd-arc-date-badge">
									<span class="xd-arc-day"><?php echo esc_html( get_the_date( 'd' ) ); ?></span>
									<span class="xd-arc-month"><?php echo esc_html( get_the_date( 'M' ) ); ?></span>
								</span>
							</a>
							<div class="xd-arc-body">
								<?php if ( $cats ) : ?>
									<a href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>" class="xd-arc-cat"><?php echo esc_html( $cats[0]->name ); ?></a>
								<?php endif; ?>
								<h2 class="xd-arc-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
								<p class="xd-arc-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '...' ) ); ?></p>
								<a href="<?php the_permalink(); ?>" class="xd-arc-readmore" aria-label="<?php echo esc_attr( 'Đọc tiếp: ' . get_the_title() ); ?>">Đọc tiếp <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5l8 7-8 7"/></svg></a>
							</div>
						</article>
						<?php endwhile; ?>
					</div>

					<div class="xd-arc-pagination">
						<?php the_posts_pagination( [ 'mid_size' => 2, 'prev_text' => '«', 'next_text' => '»' ] ); ?>
					</div>
				<?php else : ?>
					<p class="xd-no-posts">Chưa có bài viết nào trong danh mục này.</p>
				<?php endif; ?>
			</main>

			<aside class="xd-arc-sidebar">
				<div class="xd-sb-block">
					<h3 class="xd-sb-title"><span>Bài viết mới nhất</span></h3>
					<?php
					$recent = get_posts( [ 'post_type' => 'post', 'posts_per_page' => 5, 'no_found_rows' => true, 'ignore_sticky_posts' => true ] );
					foreach ( $recent as $post ) : setup_postdata( $post ); ?>
					<a href="<?php the_permalink(); ?>" class="xd-sb-recent-item">
						<div class="xd-sb-recent-thumb"><?php echo xd_thumb( 'thumbnail' ); ?></div>
						<div class="xd-sb-recent-body">
							<p class="xd-sb-recent-title"><?php the_title(); ?></p>
							<span class="xd-sb-recent-date"><?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?></span>
						</div>
					</a>
					<?php endforeach; wp_reset_postdata(); ?>
				</div>

				<div class="xd-sb-block xd-sb-cta">
					<p class="xd-sb-cta-text">Nhận báo giá <strong>ưu đãi mới nhất</strong> từ đại lý chính hãng</p>
					<?php if ( $sdt ) : ?>
						<a href="tel:<?php echo esc_attr( xd_tel( $sdt ) ); ?>" class="xd-sb-cta-btn"><?php echo xd_icon( 'phone', 16 ); ?> <?php echo esc_html( $sdt ); ?></a>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</div>
</div>
<?php get_footer();
