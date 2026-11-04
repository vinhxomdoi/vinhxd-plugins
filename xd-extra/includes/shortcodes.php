<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
 * [xd_news bv="4" cat=""] – 1 bài lớn + danh sách bài nhỏ
 * ---------------------------------------------------------------- */
add_shortcode( 'xd_news', function ( $atts ) {
	$atts  = shortcode_atts( [ 'bv' => 4, 'cat' => '' ], $atts, 'xd_news' );
	$args  = [
		'post_type'      => 'post',
		'posts_per_page' => max( 1, (int) $atts['bv'] ) + 1,
		'no_found_rows'  => true,
	];
	if ( $atts['cat'] !== '' ) {
		if ( is_numeric( $atts['cat'] ) ) $args['cat'] = (int) $atts['cat'];
		else $args['category_name'] = sanitize_title( $atts['cat'] );
	}
	$q = new WP_Query( $args );
	if ( ! $q->have_posts() ) return '';

	$img = function ( $size, $eager ) {
		if ( ! has_post_thumbnail() ) return '<span class="xd-noimg" aria-hidden="true"></span>';
		$attr = [ 'alt' => the_title_attribute( [ 'echo' => false ] ) ];
		if ( $eager ) { $attr['loading'] = 'eager'; }
		return get_the_post_thumbnail( null, $size, $attr );
	};

	$out = xd_shortcode_css( 'xd-news', 'news.css' ) . '<div class="xd-news-section">';
	$i   = 0;
	while ( $q->have_posts() ) {
		$q->the_post();
		$i++;
		$link  = esc_url( get_permalink() );
		$title = esc_html( get_the_title() );
		$date  = esc_html( get_the_date( 'd/m/Y' ) );
		if ( $i === 1 ) {
			$excerpt = esc_html( wp_trim_words( get_the_excerpt(), 20, '...' ) );
			$out .= '<div class="xd-news-col xd-news-big"><a href="' . $link . '" class="xd-news-big-item"><div class="xd-news-big-thumb">' . $img( 'large', false ) . '</div><div class="xd-news-big-body"><span class="xd-news-date">' . $date . '</span><h3 class="xd-news-title-big">' . $title . '</h3><p class="xd-news-excerpt">' . $excerpt . '</p></div></a></div>';
			$out .= '<div class="xd-news-col xd-news-list-small">';
		} else {
			$out .= '<a href="' . $link . '" class="xd-news-small-item"><div class="xd-news-small-thumb">' . $img( 'medium', false ) . '</div><div class="xd-news-small-body"><span class="xd-news-date">' . $date . '</span><h4 class="xd-news-title-small">' . $title . '</h4></div></a>';
		}
	}
	wp_reset_postdata();
	return $out . '</div></div>';
} );

/* ------------------------------------------------------------------
 * [xd_uu_dai form_id="3" popup_id=""] – khối ưu đãi trong tháng
 * ---------------------------------------------------------------- */
add_shortcode( 'xd_uu_dai', function ( $atts ) {
	$atts      = shortcode_atts( [ 'form_id' => '3', 'popup_id' => '' ], $atts, 'xd_uu_dai' );
	$ten       = xd_info( 'ten-dai-ly' );
	$sdt       = xd_info( 'sdt' );
	$km        = (string) xd_info( 'khuyenmai' );
	$form_id   = (int) $atts['form_id'];
	$popup_id  = (int) $atts['popup_id'];
	$is_html   = $km !== wp_strip_all_tags( $km );
	$items     = ( ! $is_html && $km ) ? array_values( array_filter( array_map( 'trim', explode( "\n", $km ) ) ) ) : [];
	$box_id    = 'xd-uu-dai-popup-' . wp_unique_id();

	wp_enqueue_script( 'xd-frontend' );

	ob_start(); ?>
<div class="xd-uudai-wrap"><div class="xd-uudai-box">
	<h2 class="xd-uudai-title"><?php echo esc_html( mb_strtoupper( $ten, 'UTF-8' ) ); ?><br><span>ƯU ĐÃI LỚN TRONG THÁNG <?php echo esc_html( wp_date( 'n/Y' ) ); ?></span></h2>
	<p class="xd-uudai-sub">KHUYẾN NGHỊ QUÝ ANH(CHỊ) GỌI ĐIỆN TRỰC TIẾP ĐỂ NHẬN GIÁ XE VÀ CHƯƠNG TRÌNH ƯU ĐÃI MỚI NHẤT</p>
	<?php if ( $is_html ) : ?>
		<div class="xd-uudai-list xd-uudai-list--html"><?php echo wp_kses_post( $km ); ?></div>
	<?php elseif ( $items ) : ?>
		<ul class="xd-uudai-list">
		<?php foreach ( $items as $i => $item ) :
			$parts = preg_split( '/\s*:\s*/u', $item, 2 ); ?>
			<li class="xd-uudai-item"><span class="xd-uudai-num"><?php echo (int) $i + 1; ?></span><span class="xd-uudai-text"><strong><?php echo esc_html( $parts[0] ); ?></strong><?php if ( ! empty( $parts[1] ) ) : ?>: <span class="xd-uudai-km"><?php echo esc_html( $parts[1] ); ?></span><?php endif; ?></span></li>
		<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<div class="xd-uudai-cta">
		<?php if ( $sdt ) : ?>
			<a href="tel:<?php echo esc_attr( xd_tel( $sdt ) ); ?>" class="xd-uudai-btn xd-uudai-btn--phone"><?php echo xd_icon( 'phone', 18 ); ?>Hotline <?php echo esc_html( $sdt ); ?><br><small>Quý khách vui lòng gọi để có giá xe tốt nhất</small></a>
		<?php endif; ?>
		<?php if ( $popup_id ) : ?>
			<button type="button" class="xd-uudai-btn xd-uudai-btn--form" data-xd-jetpopup="<?php echo esc_attr( $popup_id ); ?>">NHẬN BÁO GIÁ<br><small>Hoặc đăng ký nhận Báo giá xe lăn bánh tốt nhất</small></button>
		<?php else : ?>
			<button type="button" class="xd-uudai-btn xd-uudai-btn--form" data-xd-open="<?php echo esc_attr( $box_id ); ?>">NHẬN BÁO GIÁ<br><small>Hoặc đăng ký nhận Báo giá xe lăn bánh tốt nhất</small></button>
		<?php endif; ?>
	</div>
</div></div>
<?php if ( ! $popup_id ) : ?>
<div id="<?php echo esc_attr( $box_id ); ?>" class="xd-uudai-overlay" style="display:none">
	<div class="xd-uudai-popup" role="dialog" aria-label="Đăng ký nhận báo giá"><button type="button" class="xd-uudai-close" aria-label="Đóng">&times;</button><h3>Đăng ký nhận báo giá</h3><?php echo do_shortcode( '[fluentform id="' . $form_id . '"]' ); ?></div>
</div>
<?php endif;
	return ob_get_clean();
} );

/* ------------------------------------------------------------------
 * [vinhxd id="123"] – chèn template Elementor (kèm CSS của template)
 * ---------------------------------------------------------------- */
add_shortcode( 'vinhxd', function ( $atts ) {
	$atts = shortcode_atts( [ 'id' => '' ], $atts, 'vinhxd' );
	$id   = (int) $atts['id'];
	if ( ! $id ) return '<!-- vinhxd: thiếu id -->';
	if ( ! did_action( 'elementor/loaded' ) ) return '<!-- vinhxd: Elementor chưa active -->';
	$status = get_post_status( $id );
	if ( ! $status ) return '';
	if ( $status !== 'publish' && ! current_user_can( 'edit_post', $id ) ) return '';
	return \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true );
} );

/* ------------------------------------------------------------------
 * [xd_lien_he] – trang liên hệ: bản đồ nền, thẻ thông tin & form nổi
 *   form_id    ID FluentForm (mặc định 4)
 *   tieu_de    Tiêu đề (mặc định: ten-dai-ly trong Thông tin)
 *   mo_ta      Dòng mô tả dưới tiêu đề
 *   gio        Giờ làm việc, vd "7:30 – 17:30, Thứ 2 – Chủ nhật" (mặc định: trường gio-lam-viec nếu có)
 *   ban_do     click (mặc định, chỉ tải khi bấm) | luon (tải luôn) | tat (ẩn bản đồ)
 *   form_tieu_de / form_mo_ta  Tiêu đề và mô tả thẻ form
 * ---------------------------------------------------------------- */
add_shortcode( 'xd_lien_he', function ( $atts ) {
	$atts = shortcode_atts( [
		'form_id'      => '4',
		'tieu_de'      => '',
		'mo_ta'        => 'Gọi hoặc nhắn Zalo để được tư vấn và nhận báo giá nhanh nhất.',
		'gio'          => '',
		'ban_do'       => 'click',
		'form_tieu_de' => 'Gửi yêu cầu tư vấn',
		'form_mo_ta'   => 'Để lại số điện thoại, chúng tôi sẽ gọi lại cho bạn sớm nhất.',
	], $atts, 'xd_lien_he' );

	$ten   = $atts['tieu_de'] !== '' ? $atts['tieu_de'] : xd_info( 'ten-dai-ly' );
	$dc    = xd_info( 'dia-chi' );
	$email = xd_info( 'email' );
	$sdt   = xd_info( 'sdt' );
	$zalo  = xd_zalo_url( xd_info( 'zalo' ) );
	$gio   = $atts['gio'] !== '' ? $atts['gio'] : xd_info( 'gio-lam-viec' );
	$fid   = (int) $atts['form_id'];
	$map   = in_array( $atts['ban_do'], [ 'click', 'luon', 'tat' ], true ) ? $atts['ban_do'] : 'click';
	if ( ! $dc ) $map = 'tat';

	$embed = $dc ? 'https://maps.google.com/maps?q=' . rawurlencode( $dc ) . '&output=embed&z=15' : '';
	$route = $dc ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $dc ) : '';

	if ( $map === 'click' ) wp_enqueue_script( 'xd-frontend' );

	ob_start();
	echo xd_shortcode_css( 'xd-lien-he', 'lien-he.css' ); ?>
<section class="xd-lh<?php echo $map === 'tat' ? ' xd-lh--nomap' : ''; ?>">
	<?php if ( $map === 'luon' ) : ?>
		<div class="xd-lh-map is-loaded"><iframe src="<?php echo esc_url( $embed ); ?>" title="Bản đồ" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe></div>
	<?php elseif ( $map === 'click' ) : ?>
		<div class="xd-lh-map">
			<div class="xd-lh-map-ph" aria-hidden="true"></div>
			<span class="xd-lh-pin" aria-hidden="true"><?php echo xd_icon( 'pin', 36 ); ?></span>
			<button type="button" class="xd-lh-map-btn" data-xd-map="<?php echo esc_url( $embed ); ?>"><?php echo xd_icon( 'map', 16 ); ?>Xem bản đồ</button>
		</div>
	<?php endif; ?>
	<div class="xd-lh-in"><div class="xd-lh-grid">
		<div class="xd-lh-info">
			<span class="xd-lh-eyebrow">Liên hệ</span>
			<?php if ( $ten ) : ?><h2 class="xd-lh-title"><?php echo esc_html( $ten ); ?></h2><?php endif; ?>
			<?php if ( $atts['mo_ta'] !== '' ) : ?><p class="xd-lh-desc"><?php echo esc_html( $atts['mo_ta'] ); ?></p><?php endif; ?>
			<ul class="xd-lh-list">
				<?php if ( $dc ) : ?><li class="xd-lh-row"><span class="xd-lh-ic"><?php echo xd_icon( 'pin' ); ?></span><span><span class="xd-lh-lb">Địa chỉ</span><span class="xd-lh-val"><?php echo esc_html( $dc ); ?></span></span></li><?php endif; ?>
				<?php if ( $sdt ) : ?><li class="xd-lh-row"><span class="xd-lh-ic"><?php echo xd_icon( 'phone' ); ?></span><span><span class="xd-lh-lb">Hotline</span><a class="xd-lh-val" href="tel:<?php echo esc_attr( xd_tel( $sdt ) ); ?>"><?php echo esc_html( $sdt ); ?></a></span></li><?php endif; ?>
				<?php if ( $email ) : ?><li class="xd-lh-row"><span class="xd-lh-ic"><?php echo xd_icon( 'mail' ); ?></span><span><span class="xd-lh-lb">Email</span><a class="xd-lh-val" href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></span></li><?php endif; ?>
			</ul>
			<div class="xd-lh-act">
				<?php if ( $sdt ) : ?><a class="xd-lh-btn xd-lh-btn--main" href="tel:<?php echo esc_attr( xd_tel( $sdt ) ); ?>"><?php echo xd_icon( 'phone', 16 ); ?>Gọi ngay</a><?php endif; ?>
				<?php if ( $zalo ) : ?><a class="xd-lh-btn xd-lh-btn--zalo" href="<?php echo esc_url( $zalo ); ?>" target="_blank" rel="noopener nofollow"><?php echo xd_icon( 'chat', 16 ); ?>Chat Zalo</a><?php endif; ?>
				<?php if ( $route ) : ?><a class="xd-lh-btn" href="<?php echo esc_url( $route ); ?>" target="_blank" rel="noopener nofollow"><?php echo xd_icon( 'route', 16 ); ?>Chỉ đường</a><?php endif; ?>
			</div>
		</div>
		<div class="xd-lh-form">
			<div class="xd-lh-form-hd">
				<?php if ( $atts['form_tieu_de'] !== '' ) : ?><h3><?php echo esc_html( $atts['form_tieu_de'] ); ?></h3><?php endif; ?>
				<?php if ( $atts['form_mo_ta'] !== '' ) : ?><p><?php echo esc_html( $atts['form_mo_ta'] ); ?></p><?php endif; ?>
			</div>
			<?php if ( $gio ) : ?><div class="xd-lh-time"><span class="xd-lh-dot" aria-hidden="true"></span><span>Giờ làm việc: <b><?php echo esc_html( $gio ); ?></b></span></div><?php endif; ?>
			<div class="xd-lh-form-bd"><?php echo do_shortcode( '[fluentform id="' . $fid . '"]' ); ?></div>
		</div>
	</div></div>
</section>
<?php
	return ob_get_clean();
} );
