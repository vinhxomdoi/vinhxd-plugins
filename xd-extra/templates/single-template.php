<?php
/**
 * XD – Single bài viết: nội dung + sidebar
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$sdt        = xd_info( 'sdt' );
$zalo_url   = xd_zalo_url( xd_info( 'zalo' ) );
$anh_sale   = xd_media_url( xd_info( 'anh-sale' ), 'thumbnail' );
$ten_dai_ly = xd_info( 'ten-dai-ly' );
$banner     = xd_media_url( xd_info( 'banner' ), 'medium_large' );
$banner_lnk = xd_info( 'link' ) ?: home_url( '/' );
?>
<div class="xd-single-page">
<?php while ( have_posts() ) : the_post();
	$cats    = get_the_category();
	$cat     = $cats ? $cats[0] : null;
	$crumbs  = [];
	if ( $cat ) $crumbs[] = [ $cat->name, get_category_link( $cat->term_id ) ];
	$crumbs[] = [ wp_trim_words( get_the_title(), 8, '...' ), '' ];

	// 1 truy vấn cho bài liên quan: 3 bài đầu hiện dưới nội dung, phần còn lại ở sidebar.
	$related = [];
	if ( $cat ) {
		$related = get_posts( [
			'post_type'              => 'post',
			'posts_per_page'         => 8,
			'post__not_in'           => [ get_the_ID() ],
			'cat'                    => $cat->term_id,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		] );
	}
	$related_bottom = array_slice( $related, 0, 3 );
	$related_side   = array_slice( $related, 3, 5 );
	if ( ! $related_side ) $related_side = $related_bottom; // ít bài thì sidebar vẫn có nội dung
?>
	<div class="xd-container">
		<?php echo xd_breadcrumb( $crumbs, 'xd-breadcrumb xd-single-breadcrumb' ); ?>

		<div class="xd-single-layout">
			<main class="xd-single-main">
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'xd-article' ); ?>>
					<?php if ( $cat ) : ?>
						<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="xd-single-cat-label"><?php echo esc_html( $cat->name ); ?></a>
					<?php endif; ?>

					<h1 class="xd-single-title"><?php the_title(); ?></h1>

					<div class="xd-single-meta">
						<span class="xd-meta-item"><?php echo xd_icon( 'clock', 15 ); ?><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?></time></span>
						<span class="xd-meta-item"><?php echo xd_icon( 'user', 15 ); ?><?php the_author(); ?></span>
						<?php if ( function_exists( 'the_views' ) ) : ?>
							<span class="xd-meta-item"><?php echo xd_icon( 'eye', 15 ); ?><?php the_views(); ?> lượt xem</span>
						<?php endif; ?>
					</div>

					<?php if ( has_post_thumbnail() ) : ?>
						<div class="xd-single-featured-img">
							<?php the_post_thumbnail( 'large', [ 'class' => 'xd-featured-img', 'loading' => 'eager', 'fetchpriority' => 'high' ] ); ?>
						</div>
					<?php endif; ?>

					<div class="entry-content xd-single-content">
						<?php the_content(); ?>
					</div>

					<?php $tags = get_the_tags(); if ( $tags ) : ?>
						<div class="xd-single-tags">
							<?php echo xd_icon( 'tag', 16 ); ?>
							<?php foreach ( $tags as $tag ) : ?>
								<a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="xd-tag-pill"><?php echo esc_html( $tag->name ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<div class="xd-share-bar">
						<span class="xd-share-label">Chia sẻ:</span>
						<a href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( get_permalink() ) ); ?>" target="_blank" rel="noopener nofollow" class="xd-share-btn xd-share-fb"><?php echo xd_icon( 'fb', 16 ); ?> Facebook</a>
						<a href="<?php echo esc_url( 'https://sp.zalo.me/share_inline?d=' . rawurlencode( get_permalink() ) ); ?>" target="_blank" rel="noopener nofollow" class="xd-share-btn xd-share-zalo"><?php echo xd_icon( 'chat', 16 ); ?> Zalo</a>
					</div>
				</article>

				<?php if ( $related_bottom ) : ?>
				<div class="xd-related-posts">
					<h2 class="xd-related-title">Bài viết liên quan</h2>
					<div class="xd-related-grid">
						<?php foreach ( $related_bottom as $post ) : setup_postdata( $post ); ?>
						<a href="<?php the_permalink(); ?>" class="xd-related-card">
							<div class="xd-related-thumb"><?php echo xd_thumb( 'medium' ); ?></div>
							<p class="xd-related-card-title"><?php the_title(); ?></p>
						</a>
						<?php endforeach; wp_reset_postdata(); ?>
					</div>
				</div>
				<?php endif; ?>
			</main>

			<aside class="xd-single-sidebar">
				<div class="xd-sb-block xd-sb-cta">
					<?php if ( $anh_sale ) : ?>
						<div class="xd-sb-cta-avatar"><img src="<?php echo esc_url( $anh_sale ); ?>" alt="<?php echo esc_attr( $ten_dai_ly ); ?>" width="80" height="80" loading="lazy"></div>
					<?php endif; ?>
					<p class="xd-sb-cta-role">Phòng Kinh Doanh</p>
					<?php if ( $ten_dai_ly ) : ?><p class="xd-sb-cta-dealer"><?php echo esc_html( $ten_dai_ly ); ?></p><?php endif; ?>
					<?php if ( $sdt ) : ?>
						<a href="tel:<?php echo esc_attr( xd_tel( $sdt ) ); ?>" class="xd-sb-cta-btn"><?php echo xd_icon( 'phone', 16 ); ?> <?php echo esc_html( $sdt ); ?></a>
					<?php endif; ?>
					<?php if ( $zalo_url ) : ?>
						<a href="<?php echo esc_url( $zalo_url ); ?>" target="_blank" rel="noopener nofollow" class="xd-sb-cta-btn xd-sb-cta-btn--zalo"><?php echo xd_icon( 'chat', 16 ); ?> Chat Zalo ngay</a>
					<?php endif; ?>
				</div>

				<?php if ( $related_side ) : ?>
				<div class="xd-sb-block">
					<h3 class="xd-sb-title"><span>Bài viết liên quan</span></h3>
					<?php foreach ( $related_side as $post ) : setup_postdata( $post ); ?>
					<a href="<?php the_permalink(); ?>" class="xd-sb-recent-item">
						<div class="xd-sb-recent-thumb"><?php echo xd_thumb( 'thumbnail' ); ?></div>
						<div class="xd-sb-recent-body">
							<p class="xd-sb-recent-title"><?php the_title(); ?></p>
							<span class="xd-sb-recent-date"><?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?></span>
						</div>
					</a>
					<?php endforeach; wp_reset_postdata(); ?>
				</div>
				<?php endif; ?>

				<?php
				// Danh sách xe: chỉ chạy khi site có CPT "xe" (web ô tô).
				if ( post_type_exists( 'xe' ) ) :
					$xe = get_posts( [ 'post_type' => 'xe', 'posts_per_page' => 8, 'orderby' => 'menu_order', 'order' => 'ASC', 'no_found_rows' => true ] );
					if ( $xe ) : ?>
				<div class="xd-sb-block xd-sb-xe-list">
					<h3 class="xd-sb-title"><span>Sản Phẩm</span></h3>
					<?php foreach ( $xe as $post ) : setup_postdata( $post ); $gia = get_post_meta( get_the_ID(), 'gia', true ); ?>
					<a href="<?php the_permalink(); ?>" class="xd-sb-recent-item">
						<div class="xd-sb-recent-thumb"><?php echo xd_thumb( 'thumbnail' ); ?></div>
						<div class="xd-sb-recent-body">
							<p class="xd-sb-recent-title xd-sb-xe-name"><?php the_title(); ?></p>
							<?php if ( $gia ) : ?><span class="xd-sb-recent-date xd-sb-xe-price"><?php echo esc_html( $gia ); ?></span><?php endif; ?>
						</div>
					</a>
					<?php endforeach; wp_reset_postdata(); ?>
				</div>
				<?php endif; endif; ?>

				<?php if ( $banner ) : ?>
				<div class="xd-sb-block xd-sb-banner">
					<a href="<?php echo esc_url( $banner_lnk ); ?>" target="_blank" rel="noopener">
						<img src="<?php echo esc_url( $banner ); ?>" alt="<?php echo esc_attr( $ten_dai_ly ); ?>" loading="lazy">
					</a>
				</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
<?php endwhile; ?>
</div>
<?php get_footer();
