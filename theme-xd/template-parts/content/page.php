<?php if ( get_option( 'txd_show_breadcrumb', 1 ) ) txd_breadcrumb(); ?>
<div class="txd-page-layout">
	<?php while ( have_posts() ) : the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<h1 class="txd-page-title"><?php the_title(); ?></h1>
		<?php if ( has_post_thumbnail() ) : ?>
		<div class="txd-featured-image"><?php the_post_thumbnail( 'large', [ 'sizes' => '(max-width: 900px) 100vw, 860px' ] ); ?></div>
		<?php endif; ?>
		<div class="txd-page-content txd-prose"><?php the_content(); ?></div>
		<?php wp_link_pages( [ 'before' => '<nav class="txd-page-links">', 'after' => '</nav>' ] ); ?>
	</article>
	<?php if ( comments_open() || get_comments_number() ) comments_template(); ?>
	<?php endwhile; ?>
</div>
