<?php
wp_enqueue_script( 'theme-xd-main' );
$has_sidebar = txd_has_sidebar();
if ( get_option( 'txd_show_breadcrumb', 1 ) ) txd_breadcrumb();
?>

<div class="txd-single-layout<?php echo $has_sidebar ? ' has-sidebar' : ''; ?>">
	<?php while ( have_posts() ) : the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'txd-post-article' ); ?>>

		<header class="txd-post-header">
			<?php $cats = get_the_category(); if ( $cats ) : $cat = $cats[0]; ?>
			<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="txd-cat-badge"><?php echo esc_html( $cat->name ); ?></a>
			<?php endif; ?>
			<h1 class="txd-post-title"><?php the_title(); ?></h1>
			<div class="txd-post-meta">
				<span class="txd-author"><?php echo get_avatar( get_the_author_meta( 'ID' ), 26, '', '', [ 'loading' => 'lazy' ] ); ?><strong><?php the_author(); ?></strong></span>
				<span class="txd-divider" aria-hidden="true">·</span>
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?></time>
				<?php if ( get_option( 'txd_reading_time', 1 ) ) : ?>
				<span class="txd-divider" aria-hidden="true">·</span>
				<span class="txd-reading-time">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					<?php echo esc_html( txd_reading_time() ); ?>
				</span>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
		<div class="txd-featured-image"><?php the_post_thumbnail( 'large', [ 'alt' => the_title_attribute( [ 'echo' => false ] ), 'sizes' => '(max-width: 1024px) 100vw, 820px', 'fetchpriority' => 'high', 'loading' => 'eager' ] ); ?></div>
		<?php endif; ?>

		<div class="txd-post-content txd-prose"><?php the_content(); ?></div>

		<?php wp_link_pages( [ 'before' => '<nav class="txd-page-links">', 'after' => '</nav>' ] ); ?>

		<?php $tags = get_the_tags(); if ( $tags ) : ?>
		<div class="txd-post-tags">
			<?php foreach ( $tags as $tag ) : ?>
			<a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="tag">#<?php echo esc_html( $tag->name ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( get_option( 'txd_show_share', 1 ) ) txd_share_buttons(); ?>

		<?php if ( get_option( 'txd_show_author_box', 1 ) ) :
			$bio = get_the_author_meta( 'description' ); ?>
		<div class="txd-author-box">
			<?php echo get_avatar( get_the_author_meta( 'ID' ), 64, '', '', [ 'loading' => 'lazy' ] ); ?>
			<div class="txd-author-box-info">
				<h4><?php the_author(); ?></h4>
				<p><?php echo $bio ? esc_html( $bio ) : esc_html__( 'Biên tập viên của trang web.', 'theme-xd' ); ?></p>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( get_option( 'txd_show_post_nav', 1 ) ) :
			$prev = get_previous_post();
			$next = get_next_post();
			if ( $prev || $next ) : ?>
		<nav class="txd-post-nav" aria-label="<?php esc_attr_e( 'Điều hướng bài viết', 'theme-xd' ); ?>">
			<?php if ( $prev ) : ?>
			<a href="<?php echo esc_url( get_permalink( $prev ) ); ?>" class="txd-post-nav-item prev">
				<span class="nav-label">← <?php esc_html_e( 'Bài trước', 'theme-xd' ); ?></span>
				<span class="nav-title"><?php echo esc_html( get_the_title( $prev ) ); ?></span>
			</a>
			<?php else : ?><span></span><?php endif; ?>
			<?php if ( $next ) : ?>
			<a href="<?php echo esc_url( get_permalink( $next ) ); ?>" class="txd-post-nav-item next">
				<span class="nav-label"><?php esc_html_e( 'Bài tiếp', 'theme-xd' ); ?> →</span>
				<span class="nav-title"><?php echo esc_html( get_the_title( $next ) ); ?></span>
			</a>
			<?php endif; ?>
		</nav>
		<?php endif; endif; ?>

		<?php if ( get_option( 'txd_show_related', 1 ) ) :
			$related = txd_get_related_posts( get_the_ID(), get_option( 'txd_related_count', 3 ) );
			if ( $related ) : ?>
		<section class="txd-related">
			<h2 class="txd-related-title"><?php esc_html_e( 'Bài viết liên quan', 'theme-xd' ); ?></h2>
			<div class="txd-related-grid">
				<?php foreach ( $related as $rp ) : $rcats = get_the_category( $rp->ID ); ?>
				<article class="txd-related-card">
					<a href="<?php echo esc_url( get_permalink( $rp ) ); ?>" class="thumb" tabindex="-1" aria-hidden="true">
						<?php echo get_the_post_thumbnail( $rp, 'medium', [ 'alt' => '', 'loading' => 'lazy', 'sizes' => '(max-width: 480px) 100vw, 260px' ] ); ?>
					</a>
					<div class="info">
						<?php if ( $rcats ) : ?><a href="<?php echo esc_url( get_category_link( $rcats[0]->term_id ) ); ?>" class="cat"><?php echo esc_html( $rcats[0]->name ); ?></a><?php endif; ?>
						<h3><a href="<?php echo esc_url( get_permalink( $rp ) ); ?>"><?php echo esc_html( get_the_title( $rp ) ); ?></a></h3>
						<div class="date"><?php echo esc_html( get_the_date( 'd/m/Y', $rp ) ); ?></div>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; endif; ?>

		<?php if ( comments_open() || get_comments_number() ) : ?>
		<div class="txd-comments"><?php comments_template(); ?></div>
		<?php endif; ?>

	</article>
	<?php endwhile; ?>

	<?php if ( $has_sidebar ) : ?>
	<aside class="txd-sidebar" role="complementary">
		<?php txd_sidebar( 'sidebar-main' ); ?>
	</aside>
	<?php endif; ?>
</div>
