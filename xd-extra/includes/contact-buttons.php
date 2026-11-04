<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
 * Cấu hình
 * bottom   = cách dưới trên desktop (px)
 * bottom_m = cách dưới trên mobile ≤768px (px), để trống = giống desktop
 * ---------------------------------------------------------------- */
function xd_cbtn_defaults() {
	return [
		'phone' => [ 'enable' => '1', 'color' => '#e31b23', 'left' => '0',  'right' => '',   'bottom' => '20', 'bottom_m' => '' ],
		'zalo'  => [ 'enable' => '1', 'color' => '#0068ff', 'left' => '0',  'right' => '',   'bottom' => '95', 'bottom_m' => '' ],
		'fb'    => [ 'enable' => '0', 'color' => '#0084ff', 'left' => '',   'right' => '18', 'bottom' => '50', 'bottom_m' => '' ],
		'blink' => [ 'enable' => '0', 'color' => '#ff4b00', 'left' => '80', 'right' => '',   'bottom' => '25', 'bottom_m' => '', 'text' => 'Nhấn gọi - Ưu đãi thêm', 'highlight' => '50TR', 'phone' => '' ],
	];
}

function xd_cbtn_get( $key = null ) {
	static $all = null;
	if ( $all === null ) {
		$saved = get_option( 'xd_cbtn', [] );
		$all   = array_replace_recursive( xd_cbtn_defaults(), is_array( $saved ) ? $saved : [] );
	}
	return $key ? ( $all[ $key ] ?? [] ) : $all;
}

function xd_cbtn_sanitize( $val ) {
	$val   = is_array( $val ) ? $val : [];
	$num   = function ( $v ) { $v = trim( (string) $v ); return $v === '' ? '' : (string) intval( $v ); };
	$clean = [];
	foreach ( xd_cbtn_defaults() as $key => $def ) {
		$in = is_array( $val[ $key ] ?? null ) ? $val[ $key ] : [];
		$clean[ $key ] = [
			'enable'   => ! empty( $in['enable'] ) ? '1' : '0',
			'color'    => sanitize_hex_color( $in['color'] ?? '' ) ?: $def['color'],
			'left'     => $num( $in['left'] ?? '' ),
			'right'    => $num( $in['right'] ?? '' ),
			'bottom'   => $num( $in['bottom'] ?? '' ) === '' ? $def['bottom'] : $num( $in['bottom'] ),
			'bottom_m' => $num( $in['bottom_m'] ?? '' ),
		];
		if ( $key === 'blink' ) {
			$clean[ $key ]['text']      = sanitize_text_field( $in['text'] ?? $def['text'] );
			$clean[ $key ]['highlight'] = sanitize_text_field( $in['highlight'] ?? $def['highlight'] );
			$clean[ $key ]['phone']     = sanitize_text_field( $in['phone'] ?? '' );
		}
	}
	return $clean;
}

add_action( 'admin_init', function () {
	register_setting( 'xd_cbtn_group', 'xd_cbtn', [ 'sanitize_callback' => 'xd_cbtn_sanitize' ] );
} );

/** Vị trí qua biến CSS: desktop --xd-b, mobile --xd-bm (xem .xd-fixed trong xd-extra.css). */
function xd_cbtn_style( $cfg ) {
	$b  = (int) $cfg['bottom'];
	$bm = ( $cfg['bottom_m'] ?? '' ) === '' ? $b : (int) $cfg['bottom_m'];
	$s  = '--xd-b:' . $b . 'px;--xd-bm:' . $bm . 'px;';
	if ( $cfg['right'] !== '' ) {
		$s .= 'right:' . (int) $cfg['right'] . 'px;left:auto;';
	} else {
		$s .= 'left:' . (int) $cfg['left'] . 'px;right:auto;';
	}
	return $s;
}

/* ------------------------------------------------------------------
 * Hiển thị
 * ---------------------------------------------------------------- */
add_action( 'wp_footer', function () {
	$all = xd_cbtn_get();

	// Điện thoại
	$cfg = $all['phone'];
	$sdt = xd_info( 'sdt' );
	if ( $cfg['enable'] === '1' && $sdt ) {
		$cls = 'xd-btn xd-phone' . ( $cfg['right'] !== '' ? ' xd-btn-right' : '' );
		echo '<div class="xd-cbtn-wrap xd-fixed" style="' . esc_attr( xd_cbtn_style( $cfg ) ) . '">';
		echo '<a href="tel:' . esc_attr( xd_tel( $sdt ) ) . '" class="' . esc_attr( $cls ) . '" style="--xd-c:' . esc_attr( $cfg['color'] ) . '" aria-label="' . esc_attr( 'Gọi ' . $sdt ) . '">';
		echo '<span class="xd-icon">' . xd_icon( 'phone', 25 ) . '</span>';
		echo '<span class="xd-body">' . esc_html( $sdt ) . '</span></a></div>';
	}

	// Zalo
	$cfg  = $all['zalo'];
	$zalo = xd_zalo_url( xd_info( 'zalo' ) );
	if ( $cfg['enable'] === '1' && $zalo ) {
		$cls = 'xd-btn xd-zalo' . ( $cfg['right'] !== '' ? ' xd-btn-right' : '' );
		echo '<div class="xd-cbtn-wrap xd-fixed" style="' . esc_attr( xd_cbtn_style( $cfg ) ) . '">';
		echo '<a href="' . esc_url( $zalo ) . '" class="' . esc_attr( $cls ) . '" style="--xd-c:' . esc_attr( $cfg['color'] ) . '" target="_blank" rel="noopener nofollow" aria-label="Chat Zalo">';
		echo '<span class="xd-icon"><img src="' . esc_url( XD_ASSETS_URL . 'images/zalo.png' ) . '" width="25" height="25" alt=""></span>';
		echo '<span class="xd-body">Chat Zalo</span></a></div>';
	}

	// Messenger
	$cfg = $all['fb'];
	$fb  = xd_info( 'facebook' );
	if ( $cfg['enable'] === '1' && $fb ) {
		$user = filter_var( $fb, FILTER_VALIDATE_URL ) ? basename( rtrim( (string) wp_parse_url( $fb, PHP_URL_PATH ), '/' ) ) : $fb;
		if ( $user ) {
			echo '<a href="' . esc_url( 'https://m.me/' . rawurlencode( $user ) ) . '" class="xd-messenger-btn xd-fixed" style="' . esc_attr( xd_cbtn_style( $cfg ) . 'background:' . $cfg['color'] ) . '" target="_blank" rel="noopener nofollow" aria-label="Chat Messenger">';
			echo '<svg viewBox="0 0 48 48" width="30" height="30" aria-hidden="true"><path fill="#fff" d="M24 4C13 4 4 12.5 4 23c0 5.8 2.6 11 6.8 14.7V44l6.4-3.5c1.7.5 3.5.8 5.4.8 11.1 0 20-8.5 20-19C42 12.5 35.1 4 24 4z"/><path fill="#0084ff" d="M26.1 30.3l-5.1-5.4-9.9 5.4 10.9-11.6 5.2 5.4 9.8-5.4z"/></svg></a>';
		}
	}

	// Nút nhấp nháy (chỉ hiện trên mobile)
	$cfg   = $all['blink'];
	$phone = trim( $cfg['phone'] ) ?: $sdt;
	if ( $cfg['enable'] === '1' && $phone ) {
		echo '<a href="tel:' . esc_attr( xd_tel( $phone ) ) . '" class="xd-call-tooltip xd-fixed" style="' . esc_attr( xd_cbtn_style( $cfg ) . 'background:' . $cfg['color'] ) . '">';
		echo '<span class="xd-call-hand" aria-hidden="true">👈</span> ' . esc_html( $cfg['text'] ) . ' <span class="xd-call-highlight"><strong>' . esc_html( $cfg['highlight'] ) . '</strong></span></a>';
	}
}, 20 );

/* ------------------------------------------------------------------
 * Trang quản trị: XD Extra → Nút Liên Hệ
 * ---------------------------------------------------------------- */
function xd_contact_buttons_page() {
	$cfg    = xd_cbtn_get();
	$groups = [ 'phone' => 'Số Điện Thoại', 'zalo' => 'Zalo', 'fb' => 'Facebook (Messenger)', 'blink' => 'Nút Nhấp Nháy (chỉ mobile)' ];
	?>
	<div class="wrap" style="max-width:900px;">
		<h1>Nút Liên Hệ</h1>
		<p style="color:#666;">Đơn vị px. Chỉ điền một trong hai ô "Cách trái" hoặc "Cách phải". "Cách dưới (mobile)" để trống thì dùng giống desktop. Số điện thoại, Zalo, Facebook lấy từ Option page <code>thong-tin</code>.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'xd_cbtn_group' ); ?>
			<?php foreach ( $groups as $key => $label ) : $btn = $cfg[ $key ]; $n = 'xd_cbtn[' . $key . ']'; ?>
			<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-bottom:16px;">
				<h2 style="margin-top:0;"><?php echo esc_html( $label ); ?></h2>
				<table class="form-table">
					<tr>
						<th>Bật / Tắt</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enable]" value="1" <?php checked( '1', $btn['enable'] ); ?>> Hiển thị nút này</label></td>
					</tr>
					<tr>
						<th>Màu nền</th>
						<td><input type="color" name="<?php echo esc_attr( $n ); ?>[color]" value="<?php echo esc_attr( $btn['color'] ); ?>" style="width:50px;height:34px;padding:2px;cursor:pointer;"></td>
					</tr>
					<tr>
						<th>Vị trí ngang</th>
						<td>
							Cách trái <input type="number" name="<?php echo esc_attr( $n ); ?>[left]" value="<?php echo esc_attr( $btn['left'] ); ?>" style="width:80px;" placeholder="px">
							&nbsp;&nbsp;Cách phải <input type="number" name="<?php echo esc_attr( $n ); ?>[right]" value="<?php echo esc_attr( $btn['right'] ); ?>" style="width:80px;" placeholder="px">
						</td>
					</tr>
					<tr>
						<th>Cách dưới</th>
						<td>
							Desktop <input type="number" name="<?php echo esc_attr( $n ); ?>[bottom]" value="<?php echo esc_attr( $btn['bottom'] ); ?>" style="width:80px;" placeholder="px">
							&nbsp;&nbsp;Mobile <input type="number" name="<?php echo esc_attr( $n ); ?>[bottom_m]" value="<?php echo esc_attr( $btn['bottom_m'] ); ?>" style="width:80px;" placeholder="= desktop">
							<p class="description">Mobile hữu ích khi trang có thanh liên hệ dưới đáy hoặc nút chat che mất nút.</p>
						</td>
					</tr>
					<?php if ( $key === 'blink' ) : ?>
					<tr>
						<th>SĐT riêng</th>
						<td>
							<input type="text" name="xd_cbtn[blink][phone]" value="<?php echo esc_attr( $btn['phone'] ); ?>" placeholder="VD: 0356347113" class="regular-text">
							<p class="description">Để trống sẽ tự lấy SĐT từ Thông tin.</p>
						</td>
					</tr>
					<tr>
						<th>Nội dung</th>
						<td>
							<input type="text" name="xd_cbtn[blink][text]" value="<?php echo esc_attr( $btn['text'] ); ?>" class="regular-text">
							&nbsp;<strong style="color:#ff4b00;">Highlight</strong>
							<input type="text" name="xd_cbtn[blink][highlight]" value="<?php echo esc_attr( $btn['highlight'] ); ?>" style="width:90px;">
						</td>
					</tr>
					<?php endif; ?>
				</table>
			</div>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
