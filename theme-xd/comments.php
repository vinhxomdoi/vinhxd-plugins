<?php
/**
 * Comments template
 * (Trước đây theme thiếu file này → WP dùng theme-compat đã deprecated và báo notice.)
 *
 * @package ThemeXD
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( post_password_required() ) return;
?>
<section id="comments" class="txd-comment-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="txd-comments-title">
			<?php
			$n = get_comments_number();
			/* translators: %s: number of comments */
			printf( esc_html( _n( '%s bình luận', '%s bình luận', $n, 'theme-xd' ) ), esc_html( number_format_i18n( $n ) ) );
			?>
		</h2>
		<ol class="txd-comment-list">
			<?php wp_list_comments( [ 'style' => 'ol', 'short_ping' => true, 'avatar_size' => 40 ] ); ?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="txd-no-comments"><?php esc_html_e( 'Bình luận đã đóng.', 'theme-xd' ); ?></p>
	<?php endif; ?>

	<?php comment_form( [ 'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">', 'title_reply_after' => '</h3>' ] ); ?>
</section>
