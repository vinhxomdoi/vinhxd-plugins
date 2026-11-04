<div class="txd-404">
	<div class="txd-404-code" aria-hidden="true">404</div>
	<h1 class="txd-404-title"><?php esc_html_e( 'Không tìm thấy trang', 'theme-xd' ); ?></h1>
	<p class="txd-404-desc"><?php esc_html_e( 'Trang bạn tìm kiếm có thể đã bị xoá hoặc không tồn tại.', 'theme-xd' ); ?></p>
	<?php get_search_form(); ?>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="txd-btn">← <?php esc_html_e( 'Về trang chủ', 'theme-xd' ); ?></a>
</div>
