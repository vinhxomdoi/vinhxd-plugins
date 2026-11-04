<?php
/**
 * Template Name: Page XD
 * Template Post Type: page
 * Banner tiêu đề (ảnh nền lấy từ trường bg-tit trong Option page "thong-tin") + khung nội dung.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$bg    = xd_media_url( xd_info( 'bg-tit' ), 'full' );
$style = $bg ? '--xd-page-bg:url(\'' . esc_url( $bg ) . '\')' : '';
?>
<div class="custom-page-wrapper">
	<div class="custom-page-header<?php echo $bg ? '' : ' no-bg'; ?>"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
		<h1 class="custom-page-title"><?php the_title(); ?></h1>
	</div>
	<div class="custom-page-container">
		<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
	</div>
</div>
<?php get_footer();
