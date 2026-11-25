<?php
/**
 * VinhXD Connector – Module quản trị Chống Clone, bảo vệ Frontend, đồng bộ MU-Plugin & xác thực mở khóa.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VXD_GUARD_CORE_VERSION', '2.0.0' );

/* ==================================================================
 * 1. ĐỒNG BỘ MU-PLUGIN XD GUARD (wp-content/mu-plugins/xd-guard.php)
 * ================================================================ */
function vxd_guard_mu_path() {
	return WPMU_PLUGIN_DIR . '/xd-guard.php';
}

function vxd_guard_needs_mu() {
	return get_option( 'xd_hide_connector', '1' ) === '1'
		|| get_option( 'xd_anti_migration', '0' ) === '1'
		|| get_option( 'xd_disable_file_edit', '0' ) === '1'
		|| get_option( 'xd_block_zip_upload', '0' ) === '1'
		|| get_option( 'xd_block_rest_anon', '0' ) === '1'
		|| get_option( 'xd_block_tpl_export', '0' ) === '1';
}

/**
 * Tạo / cập nhật / gỡ mu-plugin theo trạng thái bảo vệ hiện tại.
 * Trả về true nếu mu-plugin đang tồn tại & khớp phiên bản mới nhất.
 */
function vxd_guard_sync_mu() {
	$path = vxd_guard_mu_path();
	if ( ! vxd_guard_needs_mu() ) {
		if ( file_exists( $path ) ) wp_delete_file( $path );
		return false;
	}
	$src = __DIR__ . '/guard-core.php';
	if ( ! is_readable( $src ) ) return false;
	$core = file_get_contents( $src );
	if ( ! $core ) return false;
	$code = preg_replace( '/^<\?php\s*/', '', $core );
	$body = "<?php\n/**\n * Plugin Name: XD Guard\n * Description: Lớp bảo vệ hệ thống chống clone/backup/sửa file. Quản lý bởi VinhXD Connector.\n * Version: " . VXD_GUARD_CORE_VERSION . "\n */\n" . $code;
	if ( file_exists( $path ) && md5_file( $path ) === md5( $body ) ) return true;
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
	return (bool) @file_put_contents( $path, $body );
}

// Giữ tương thích ngược cho script/quy trình gọi xd_guard_sync_mu().
// Trì hoãn sang plugins_loaded để tránh Fatal Error "Cannot redeclare xd_guard_sync_mu()" trên web còn chạy XD Extra <= 3.2.0.
add_action( 'plugins_loaded', function () {
	if ( ! function_exists( 'xd_guard_sync_mu' ) ) {
		function xd_guard_sync_mu() {
			return vxd_guard_sync_mu();
		}
	}
}, 20 );

// Tự đồng bộ mu-plugin khi nâng cấp hoặc khi bản mu-plugin trên đĩa cũ hơn VXD_GUARD_CORE_VERSION.
add_action( 'admin_init', function () {
	$need_sync = false;
	if ( ! defined( 'XD_GUARD_VERSION' ) || version_compare( XD_GUARD_VERSION, VXD_GUARD_CORE_VERSION, '<' ) ) {
		$need_sync = true;
	} elseif ( vxd_guard_needs_mu() && ! file_exists( vxd_guard_mu_path() ) ) {
		$need_sync = true;
	} elseif ( get_option( 'vxd_guard_mu_ver' ) !== VXD_GUARD_CORE_VERSION ) {
		$need_sync = true;
	}
	if ( $need_sync ) {
		if ( vxd_guard_sync_mu() || ! vxd_guard_needs_mu() ) {
			update_option( 'vxd_guard_mu_ver', VXD_GUARD_CORE_VERSION, false );
		}
	}
}, 2 );

/* ==================================================================
 * 2. QUÉT FILE BACKUP BỊ BỎ QUÊN (CẢNH BÁO RÒ RỈ MÃ NGUỒN)
 * ================================================================ */
function vxd_guard_scan_exposed_backups() {
	$found = [];
	$exts  = [ 'zip', 'wpress', 'sql', 'gz', 'tgz', 'tar', 'rar', '7z', 'bak' ];

	// Quét tầng gốc ABSPATH và wp-content/
	$dirs = [
		''            => rtrim( ABSPATH, '/\\' ),
		'wp-content/' => rtrim( WP_CONTENT_DIR, '/\\' ),
	];
	foreach ( $dirs as $prefix => $dir ) {
		if ( ! is_dir( $dir ) ) continue;
		foreach ( (array) @scandir( $dir ) as $entry ) {
			if ( $entry === '.' || $entry === '..' ) continue;
			$full = $dir . '/' . $entry;
			if ( ! is_file( $full ) ) continue;
			$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
			if ( in_array( $ext, $exts, true ) && @filesize( $full ) > 51200 ) {
				$found[] = $prefix . $entry . ' (' . size_format( (int) @filesize( $full ) ) . ')';
			}
		}
	}

	// Kiểm tra các thư mục backup phổ biến còn sót file nén lớn
	$backup_dirs = [ 'ai1wm-backups', 'updraft', 'backups-dup-lite', 'backups-dup-pro', 'wpvividbackups', 'backwpup' ];
	foreach ( $backup_dirs as $bd ) {
		$full_dir = rtrim( WP_CONTENT_DIR, '/\\' ) . '/' . $bd;
		if ( ! is_dir( $full_dir ) ) continue;
		foreach ( (array) @scandir( $full_dir ) as $entry ) {
			if ( $entry === '.' || $entry === '..' ) continue;
			$full = $full_dir . '/' . $entry;
			if ( ! is_file( $full ) ) continue;
			$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
			if ( in_array( $ext, $exts, true ) && @filesize( $full ) > 102400 ) {
				$found[] = 'wp-content/' . $bd . '/' . $entry . ' (' . size_format( (int) @filesize( $full ) ) . ')';
			}
		}
	}

	return array_slice( $found, 0, 15 );
}

/* ==================================================================
 * 3. FRONTEND: CHỐNG COPY, CHẶN BOT SAO CHÉP, KHÓA TÊN MIỀN, RSS
 * ================================================================ */
function vxd_guard_allowed_hosts() {
	$home  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$bare  = preg_replace( '/^www\./', '', $home );
	$hosts = [ $home, $bare, 'www.' . $bare, 'localhost', '127.0.0.1' ];
	foreach ( preg_split( '/[\s,]+/', strtolower( (string) get_option( 'xd_domain_allow', '' ) ) ) as $h ) {
		$h = trim( preg_replace( '#^https?://#', '', $h ), "/ \t" );
		if ( $h ) $hosts[] = $h;
	}
	return array_values( array_unique( array_filter( $hosts ) ) );
}

function vxd_guard_is_bypass_user() {
	if ( function_exists( 'xd_guard_bypass' ) && xd_guard_bypass() ) return true;
	return is_user_logged_in() && current_user_can( 'edit_posts' );
}

// 3.1. Chặn phần mềm/bot tải cả website (HTTrack, WebCopier, wget, Scrapy, headless...).
add_action( 'template_redirect', function () {
	if ( get_option( 'xd_block_rippers', '0' ) !== '1' || vxd_guard_is_bypass_user() ) return;
	if ( ! empty( $_GET['vxd_connector'] ) ) return;
	$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	// Bot hợp lệ (tìm kiếm, MXH, PageSpeed, hệ thống Vĩnh XD) luôn được qua.
	if ( preg_match( '~(googlebot|google-|googleother|adsbot|mediapartners|bingbot|bingpreview|coccoc|facebookexternalhit|facebot|meta-externalagent|zalo|twitterbot|telegrambot|whatsapp|slackbot|linkedinbot|discordbot|applebot|yandex|duckduck|chrome-lighthouse|pingdom|uptimerobot|vinhxd|saleoto)~i', $ua ) ) {
		return;
	}
	$bad = '~(httrack|webcopier|webzip|teleport|offline ?explorer|offline ?downloader|sitesnagger|sitesucker|webreaper|webstripper|website ?copier|website ?downloader|websitedownloader|cyotek|webcopy|getright|wget|curl/|scrapy|python-requests|python-urllib|aiohttp|httpx|go-http-client|libwww-perl|node-fetch|axios/|headless|phantomjs|puppeteer|playwright|selenium|grab-site|archiveteam|saveweb2zip|copier|singlefile)~i';
	if ( trim( $ua ) === '' || preg_match( $bad, $ua ) ) {
		if ( function_exists( 'xd_guard_log' ) ) {
			xd_guard_log( 'bot', 'Chặn bot: ' . ( $ua ?: '(không có User-Agent)' ) . ' → ' . ( $_SERVER['REQUEST_URI'] ?? '' ) );
		}
		status_header( 403 );
		nocache_headers();
		exit( 'Forbidden' );
	}
}, 0 );

// 3.2. Khóa tên miền: chống proxy / nhúng iframe / bản sao HTML chạy ở domain lạ.
add_action( 'template_redirect', function () {
	if ( get_option( 'xd_domain_lock', '0' ) !== '1' || vxd_guard_is_bypass_user() ) return;
	$host = strtolower( preg_replace( '/:\d+$/', '', (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );
	if ( $host && ! in_array( $host, vxd_guard_allowed_hosts(), true ) ) {
		if ( function_exists( 'xd_guard_log' ) ) xd_guard_log( 'domain', 'Truy cập qua tên miền lạ: ' . $host );
		wp_redirect( home_url( $_SERVER['REQUEST_URI'] ?? '/' ), 301 );
		exit;
	}
}, 1 );

add_action( 'send_headers', function () {
	if ( get_option( 'xd_domain_lock', '0' ) !== '1' || is_admin() ) return;
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( "Content-Security-Policy: frame-ancestors 'self'" );
} );

add_action( 'wp_head', function () {
	if ( get_option( 'xd_domain_lock', '0' ) !== '1' || vxd_guard_is_bypass_user() ) return;
	$home = untrailingslashit( home_url() );
	$data = base64_encode( wp_json_encode( [ 'h' => vxd_guard_allowed_hosts(), 'u' => $home ] ) );
	echo '<script>(function(d){try{d=JSON.parse(atob(d));var h=location.hostname.toLowerCase();if(d.h.indexOf(h)<0&&!/\.translate\.goog$/.test(h)&&!/googleusercontent\.com$/.test(h)){location.replace(d.u+location.pathname+location.search);}}catch(e){}})("' . esc_js( $data ) . '");</script>' . "\n";
}, 0 );

// 3.3. Chống Copy / F12 / Ctrl+U / Ctrl+S / Ctrl+P / kéo ảnh (chỉ khách).
add_action( 'wp_footer', function () {
	if ( get_option( 'xd_anti_clone', '0' ) !== '1' || vxd_guard_is_bypass_user() ) return;
	?>
<style id="xd-anti-copy">body{-webkit-user-select:none;user-select:none}input,textarea,select,[contenteditable],[contenteditable] *{-webkit-user-select:text;user-select:text}img{-webkit-user-drag:none}</style>
<script>(function(){function ok(t){return t&&t.closest&&t.closest('input,textarea,select,[contenteditable]');}
document.addEventListener('contextmenu',function(e){if(!ok(e.target))e.preventDefault();});
['copy','cut'].forEach(function(n){document.addEventListener(n,function(e){if(!ok(e.target))e.preventDefault();});});
document.addEventListener('dragstart',function(e){if(e.target&&e.target.tagName==='IMG')e.preventDefault();});
document.addEventListener('keydown',function(e){var k=(e.key||'').toLowerCase(),m=e.ctrlKey||e.metaKey;
if(e.keyCode===123||(m&&e.shiftKey&&['i','j','c'].indexOf(k)>-1)||(m&&e.altKey&&['i','j','c'].indexOf(k)>-1)||(m&&['u','s','p'].indexOf(k)>-1)||(m&&k==='a'&&!ok(e.target))){e.preventDefault();e.stopPropagation();return false;}},true);})();</script>
	<?php
}, 100 );

// 3.4. Bảo vệ RSS: chỉ đưa tóm tắt + link bài gốc.
add_filter( 'pre_option_rss_use_excerpt', function ( $v ) {
	return get_option( 'xd_rss_protect', '0' ) === '1' ? '1' : $v;
} );
$vxd_rss_source = function ( $content ) {
	if ( get_option( 'xd_rss_protect', '0' ) !== '1' ) return $content;
	return $content . "\n" . '<p>Bài viết gốc: <a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a> – ' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
};
add_filter( 'the_excerpt_rss', $vxd_rss_source, 99 );
add_filter( 'the_content_feed', $vxd_rss_source, 99 );

/* ==================================================================
 * 4. XÁC THỰC MỞ KHÓA TRANG CẤU HÌNH BẢO MẬT & LƯU CẤU HÌNH
 * ================================================================ */
function vxd_guard_has_auth_secret() {
	$meg_key  = trim( (string) get_option( 'vxd_meg_key', '' ) );
	$pin_hash = (string) get_option( 'xd_guard_pin_hash', '' );
	return $meg_key !== '' || $pin_hash !== '';
}

function vxd_guard_unlock_transient_key() {
	return 'vxd_gu_' . get_current_user_id();
}

function vxd_guard_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:\.]/', '', $_SERVER['REMOTE_ADDR'] ) : '';
}

function vxd_guard_is_unlocked() {
	if ( function_exists( 'xd_guard_bypass' ) && xd_guard_bypass() ) return true;
	if ( ! vxd_guard_has_auth_secret() ) return true;
	$token = get_transient( vxd_guard_unlock_transient_key() );
	if ( ! is_array( $token ) ) return false;
	return ( $token['ip'] ?? '' ) === vxd_guard_client_ip();
}

/** Xử lý mở khóa / khóa lại phiên quản trị bảo mật. Trả về mảng [type, msg] hoặc null. */
function vxd_guard_handle_auth() {
	if ( ! current_user_can( 'manage_options' ) ) return null;

	if ( isset( $_POST['vxd_guard_lock_now'] ) && check_admin_referer( 'vxd_guard_auth' ) ) {
		delete_transient( vxd_guard_unlock_transient_key() );
		return [ 'success', 'Đã khóa lại mục Bảo Mật & Chống Clone.' ];
	}

	if ( isset( $_POST['vxd_guard_unlock'] ) && check_admin_referer( 'vxd_guard_auth' ) ) {
		$ip       = vxd_guard_client_ip();
		$fail_key = 'vxd_gfail_' . md5( $ip );
		$fails    = (int) get_transient( $fail_key );
		if ( $fails >= 5 ) {
			return [ 'error', 'Nhập sai quá 5 lần. Vui lòng thử lại sau 15 phút.' ];
		}

		$input    = trim( (string) wp_unslash( $_POST['vxd_secret'] ?? '' ) );
		$meg_key  = trim( (string) get_option( 'vxd_meg_key', '' ) );
		$pin_hash = (string) get_option( 'xd_guard_pin_hash', '' );
		$ok       = false;

		if ( $input !== '' && $meg_key !== '' && hash_equals( $meg_key, $input ) ) {
			$ok = true;
		} elseif ( $input !== '' && $pin_hash !== '' && wp_check_password( $input, $pin_hash ) ) {
			$ok = true;
		}

		if ( $ok ) {
			delete_transient( $fail_key );
			set_transient( vxd_guard_unlock_transient_key(), [ 'ip' => $ip, 'ts' => time() ], 30 * MINUTE_IN_SECONDS );
			return [ 'success', 'Đã mở khóa quản trị Bảo Mật trong 30 phút.' ];
		}

		set_transient( $fail_key, $fails + 1, 15 * MINUTE_IN_SECONDS );
		if ( function_exists( 'xd_guard_log' ) ) xd_guard_log( 'tamper', 'Nhập sai mã mở khóa trang Bảo Mật Connector' );
		return [ 'error', 'License Key MEG hoặc Mã PIN không đúng.' ];
	}

	return null;
}

/**
 * Áp dụng cấu hình chống clone (dùng chung cho Admin UI đã mở khóa, MEG remote endpoint & MEG features).
 *
 * @param array $cfg Mảng cấu hình gồm các khóa:
 *   hide_connector, anti_migration, disable_file_edit, block_zip_upload, block_rest_anon,
 *   block_tpl_export, anti_clone, block_rippers, domain_lock, rss_protect,
 *   backup_whitelist, domain_allow, guard_pin
 * @return array ['mu_ok' => bool, 'updated' => string[]]
 */
function vxd_guard_apply_config( array $cfg ) {
	$bool_keys = [
		'hide_connector', 'anti_migration', 'disable_file_edit', 'block_zip_upload',
		'block_rest_anon', 'block_tpl_export', 'anti_clone', 'block_rippers',
		'domain_lock', 'rss_protect',
	];

	$updated = [];
	$GLOBALS['vxd_guard_allow_save'] = true;
	try {
		foreach ( $bool_keys as $k ) {
			if ( array_key_exists( $k, $cfg ) ) {
				$v = ! empty( $cfg[ $k ] ) && $cfg[ $k ] !== '0' ? '1' : '0';
				update_option( 'xd_' . $k, $v );
				$updated[] = 'xd_' . $k;
			}
		}
		if ( array_key_exists( 'backup_whitelist', $cfg ) ) {
			update_option( 'xd_backup_whitelist', sanitize_textarea_field( (string) $cfg['backup_whitelist'] ) );
			$updated[] = 'xd_backup_whitelist';
		}
		if ( array_key_exists( 'domain_allow', $cfg ) ) {
			update_option( 'xd_domain_allow', sanitize_textarea_field( (string) $cfg['domain_allow'] ) );
			$updated[] = 'xd_domain_allow';
		}
		if ( ! empty( $cfg['clear_pin'] ) ) {
			delete_option( 'xd_guard_pin_hash' );
			$updated[] = 'xd_guard_pin_hash';
		} elseif ( isset( $cfg['guard_pin'] ) && trim( (string) $cfg['guard_pin'] ) !== '' ) {
			update_option( 'xd_guard_pin_hash', wp_hash_password( trim( (string) $cfg['guard_pin'] ) ), false );
			$updated[] = 'xd_guard_pin_hash';
		}
		$mu_ok = vxd_guard_sync_mu();
	} finally {
		$GLOBALS['vxd_guard_allow_save'] = false;
	}

	return [ 'mu_ok' => $mu_ok, 'updated' => $updated ];
}

/** Tóm tắt trạng thái chống clone gửi về MEG trong ping / status. */
function vxd_guard_status_summary() {
	$log = (array) get_option( 'xd_guard_log', [] );
	$flags = [];
	foreach ( [ 'hide_connector', 'anti_migration', 'disable_file_edit', 'block_zip_upload', 'block_rest_anon', 'block_tpl_export', 'anti_clone', 'block_rippers', 'domain_lock', 'rss_protect' ] as $k ) {
		$def = $k === 'hide_connector' ? '1' : '0';
		$flags[ $k ] = get_option( 'xd_' . $k, $def ) === '1' ? 1 : 0;
	}
	return [
		'version'         => VXD_GUARD_CORE_VERSION,
		'mu_ok'           => file_exists( vxd_guard_mu_path() ) ? 1 : 0,
		'bypass'          => ( function_exists( 'xd_guard_bypass' ) && xd_guard_bypass() ) ? 1 : 0,
		'flags'           => $flags,
		'log_count'       => count( $log ),
		'recent_logs'     => array_slice( array_reverse( $log ), 0, 5 ),
		'exposed_backups' => vxd_guard_scan_exposed_backups(),
	];
}

/* ==================================================================
 * 5. GIAO DIỆN TAB BẢO MẬT & NHẬT KÝ CHẶN TRONG VINHXD CONNECTOR
 * ================================================================ */
function vxd_guard_render_unlock_gate( $notice = null ) {
	if ( $notice ) {
		echo '<div class="notice notice-' . esc_attr( $notice[0] ) . ' inline"><p>' . esc_html( $notice[1] ) . '</p></div>';
	}
	$mu_ok = file_exists( vxd_guard_mu_path() );
	?>
	<div style="background:#fff;padding:24px;border:1px solid #ccd0d4;border-radius:8px;max-width:560px;margin-top:16px;">
		<h2 style="margin-top:0;">🔒 Mục Bảo Mật &amp; Chống Clone đang được khóa</h2>
		<p>Nhập <strong>License Key MEG</strong> của website này (hoặc <strong>Mã PIN quản trị</strong> đã thiết lập) để mở khóa chỉnh sửa trong 30 phút.</p>
		<form method="post">
			<?php wp_nonce_field( 'vxd_guard_auth' ); ?>
			<p><input type="password" name="vxd_secret" class="regular-text code" style="width:100%;" placeholder="Nhập License Key MEG hoặc Mã PIN..." autocomplete="off" required></p>
			<p style="margin-bottom:0;"><button type="submit" name="vxd_guard_unlock" class="button button-primary">Mở khóa quản trị</button></p>
		</form>
		<hr style="margin:18px 0;">
		<p class="description" style="margin:0;">
			Trạng thái lớp bảo vệ MU-Plugin: <?php echo $mu_ok ? '<strong style="color:#008a20;">● Đang hoạt động (v' . esc_html( defined( 'XD_GUARD_VERSION' ) ? XD_GUARD_VERSION : VXD_GUARD_CORE_VERSION ) . ')</strong>' : '<strong style="color:#d63638;">● Chưa kích hoạt</strong>'; ?>.<br>
			Cần mở khóa khẩn cấp từ hosting: thêm <code>define( 'XD_ALLOW_BACKUP', true );</code> vào <code>wp-config.php</code>.
		</p>
	</div>
	<?php
}

function vxd_guard_render_security_tab() {
	$auth_notice = vxd_guard_handle_auth();
	if ( ! vxd_guard_is_unlocked() ) {
		vxd_guard_render_unlock_gate( $auth_notice );
		return;
	}

	$save_notice = null;
	if ( isset( $_POST['vxd_save_guard'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'vxd_save_guard' ) ) {
		$p   = wp_unslash( $_POST );
		$res = vxd_guard_apply_config( [
			'hide_connector'    => isset( $p['hide_connector'] ) ? '1' : '0',
			'anti_migration'    => isset( $p['anti_migration'] ) ? '1' : '0',
			'disable_file_edit' => isset( $p['disable_file_edit'] ) ? '1' : '0',
			'block_zip_upload'  => isset( $p['block_zip_upload'] ) ? '1' : '0',
			'block_rest_anon'   => isset( $p['block_rest_anon'] ) ? '1' : '0',
			'block_tpl_export'  => isset( $p['block_tpl_export'] ) ? '1' : '0',
			'anti_clone'        => isset( $p['anti_clone'] ) ? '1' : '0',
			'block_rippers'     => isset( $p['block_rippers'] ) ? '1' : '0',
			'domain_lock'       => isset( $p['domain_lock'] ) ? '1' : '0',
			'rss_protect'       => isset( $p['rss_protect'] ) ? '1' : '0',
			'backup_whitelist'  => $p['backup_whitelist'] ?? '',
			'domain_allow'      => $p['domain_allow'] ?? '',
			'guard_pin'         => $p['guard_pin'] ?? '',
			'clear_pin'         => ! empty( $p['clear_pin'] ),
		] );
		if ( vxd_guard_needs_mu() && ! $res['mu_ok'] ) {
			$save_notice = [ 'warning', 'Đã lưu. Lưu ý: không ghi được file wp-content/mu-plugins/xd-guard.php (kiểm tra quyền ghi thư mục mu-plugins).' ];
		} else {
			$save_notice = [ 'success', 'Đã lưu cấu hình chống clone & đồng bộ lớp bảo vệ MU-Plugin.' ];
		}
	}

	if ( $auth_notice ) echo '<div class="notice notice-' . esc_attr( $auth_notice[0] ) . ' is-dismissible"><p>' . esc_html( $auth_notice[1] ) . '</p></div>';
	if ( $save_notice ) echo '<div class="notice notice-' . esc_attr( $save_notice[0] ) . ' is-dismissible"><p>' . esc_html( $save_notice[1] ) . '</p></div>';

	if ( function_exists( 'xd_guard_bypass' ) && xd_guard_bypass() ) {
		echo '<div class="notice notice-warning inline"><p><strong>Chế độ tạm mở khóa đang bật:</strong> <code>XD_ALLOW_BACKUP = true</code> trong <code>wp-config.php</code>. Mọi lớp chặn đang tạm nghỉ — nhớ xóa dòng đó sau khi bảo trì xong!</p></div>';
	}
	if ( ! vxd_guard_has_auth_secret() ) {
		echo '<div class="notice notice-warning inline"><p><strong>Khuyến nghị:</strong> Website chưa có License Key MEG và chưa đặt Mã PIN quản trị. Hãy nhập License Key ở tab <em>Kết nối MEG</em> hoặc đặt Mã PIN bên dưới để khóa mục Chống Clone khỏi tài khoản khách.</p></div>';
	}

	$wl_raw = (string) get_option( 'xd_backup_whitelist', '' );
	if ( stripos( $wl_raw, 'wp-file-manager' ) !== false ) {
		echo '<div class="notice notice-error inline"><p><strong>Cảnh báo:</strong> Ô "Plugin được phép" đang chứa <code>wp-file-manager</code> (từ bản XD Extra cũ). Hãy xóa dòng này và bấm Lưu để chặn triệt để WP File Manager!</p></div>';
	}

	$exposed = vxd_guard_scan_exposed_backups();
	if ( $exposed ) {
		echo '<div class="notice notice-error inline"><p><strong>Phát hiện tệp nén / backup có nguy cơ lộ mã nguồn trên server:</strong></p><ul style="list-style:disc;margin-left:20px;">';
		foreach ( $exposed as $f ) echo '<li><code>' . esc_html( $f ) . '</code></li>';
		echo '</ul><p>Nên xóa các tệp này trên hosting nếu không còn dùng.</p></div>';
	}

	$on        = function ( $k, $def = '0' ) { return get_option( 'xd_' . $k, $def ) === '1' ? ' checked' : ''; };
	$mu_exists = file_exists( vxd_guard_mu_path() );
	$box       = 'background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-bottom:16px;';
	?>
	<div style="display:flex;justify-content:space-between;align-items:center;max-width:960px;margin:12px 0;">
		<div>
			Lớp MU-Plugin <code>xd-guard.php</code>:
			<?php echo $mu_exists ? '<strong style="color:#008a20;">● Đang hoạt động (v' . esc_html( defined( 'XD_GUARD_VERSION' ) ? XD_GUARD_VERSION : VXD_GUARD_CORE_VERSION ) . ')</strong>' : '<strong style="color:#d63638;">● Chưa ghi</strong>'; ?>
		</div>
		<?php if ( vxd_guard_has_auth_secret() && ! ( function_exists( 'xd_guard_bypass' ) && xd_guard_bypass() ) ) : ?>
		<form method="post" style="margin:0;">
			<?php wp_nonce_field( 'vxd_guard_auth' ); ?>
			<button type="submit" name="vxd_guard_lock_now" class="button">🔒 Khóa lại ngay</button>
		</form>
		<?php endif; ?>
	</div>

	<form method="post" style="max-width:960px;">
		<?php wp_nonce_field( 'vxd_save_guard' ); ?>

		<div style="<?php echo esc_attr( $box ); ?>">
			<h2 style="margin-top:0;">1. Tự vệ &amp; Ẩn VinhXD Connector</h2>
			<p><label><input type="checkbox" name="hide_connector" value="1"<?php echo $on( 'hide_connector', '1' ); ?>> <strong>Ẩn VinhXD Connector khỏi danh sách Plugin &amp; menu Cài đặt</strong></label></p>
			<ul style="font-size:12.5px;color:#555;margin:-4px 0 8px 24px;list-style:disc;">
				<li>Ẩn hoàn toàn khỏi <em>Plugins → Đã cài đặt</em>, <em>Dashboard → Cập nhật</em> và tab <em>Must-Use</em> (<code>xd-guard.php</code>).</li>
				<li>Chặn tắt/xóa qua URL trực tiếp, Bulk Action, AJAX, REST API và tự phục hồi nếu bị gỡ khỏi <code>active_plugins</code>.</li>
				<li>Chặn sửa trái phép tùy chọn bảo mật qua <code>/wp-admin/options.php</code>.</li>
				<li>Truy cập lại trang này bằng URL trực tiếp: <code><?php echo esc_html( admin_url( 'options-general.php?page=vxd-connector' ) ); ?></code></li>
			</ul>
		</div>

		<div style="<?php echo esc_attr( $box ); ?>">
			<h2 style="margin-top:0;">2. Chống Clone / Backup / File Manager từ WP Admin</h2>

			<p><label><input type="checkbox" name="anti_migration" value="1"<?php echo $on( 'anti_migration' ); ?>> <strong>Chặn plugin Backup / Migration / Clone / File Manager / DB Manager + Quét chữ ký mã nguồn</strong></label></p>
			<ul style="font-size:12.5px;color:#555;margin:-4px 0 12px 24px;list-style:disc;">
				<li>Tự tắt, ẩn khỏi tìm kiếm kho WP, chặn cài mới &amp; chặn kích hoạt: All-in-One WP Migration, Duplicator, UpdraftPlus, WPvivid, WP Staging, BackWPup, Simply Static, WP File Manager, Filester, WPIDE, elFinder, Adminer, phpMyAdmin, ManageWP…</li>
				<li><strong>Quét chữ ký mã nguồn</strong> cả plugin &amp; theme khi cài đặt để chống chiêu đổi tên thư mục/tên plugin trong file ZIP hoặc chèn mã dump DB/zip source.</li>
				<li>Tắt <em>Công cụ → Xuất dữ liệu</em> (export XML). <strong>Không chặn</strong> các plugin chèn mã nhúng phổ biến (Code Snippets, WPCode, HFCM…).</li>
			</ul>

			<p><label><input type="checkbox" name="disable_file_edit" value="1"<?php echo $on( 'disable_file_edit' ); ?>> <strong>Khóa cứng sửa file Theme &amp; Plugin</strong></label><br>
				<span style="font-size:12.5px;color:#666;margin-left:24px;">Khóa ở cả tầng quyền (<code>map_meta_cap</code>), màn hình trình soạn thảo, AJAX và <code>DISALLOW_FILE_EDIT</code> — có hiệu lực kể cả khi <code>wp-config.php</code> đang đặt <code>DISALLOW_FILE_EDIT = false</code>.</span></p>

			<p><label><input type="checkbox" name="block_zip_upload" value="1"<?php echo $on( 'block_zip_upload' ); ?>> <strong>Chặn tải lên file ZIP plugin &amp; giao diện từ bên ngoài</strong></label><br>
				<span style="font-size:12.5px;color:#666;margin-left:24px;">Ngăn upload plugin/theme chế ngoài luồng; vẫn cho cài plugin sạch từ kho WordPress.org và cập nhật tự động / cập nhật từ MEG.</span></p>

			<p><label><input type="checkbox" name="block_tpl_export" value="1"<?php echo $on( 'block_tpl_export' ); ?>> <strong>Chặn xuất mẫu Elementor (Export Template / Kit) &amp; Skin JetEngine</strong></label><br>
				<span style="font-size:12.5px;color:#666;margin-left:24px;">Tùy chọn nâng cao: ngăn tải bộ thiết kế Elementor và cấu trúc CPT JetEngine ra file JSON.</span></p>

			<p style="margin:14px 0 4px;"><strong>Plugin được phép ngoại lệ</strong> <span style="color:#777;font-size:12px;">(mỗi dòng 1 slug thư mục, mặc định để trống)</span></p>
			<textarea name="backup_whitelist" rows="2" style="width:100%;font-family:monospace;font-size:12px;" placeholder="Để trống để chặn toàn bộ plugin backup/file manager"><?php echo esc_textarea( $wl_raw ); ?></textarea>
			<p class="description">Khi chính bạn cần chuyển host/backup: thêm <code>define( 'XD_ALLOW_BACKUP', true );</code> vào <code>wp-config.php</code>, xong việc thì xóa dòng đó.</p>
		</div>

		<div style="<?php echo esc_attr( $box ); ?>">
			<h2 style="margin-top:0;">3. Chống Cào Dữ Liệu &amp; Sao Chép Từ Bên Ngoài</h2>

			<p><label><input type="checkbox" name="block_rest_anon" value="1"<?php echo $on( 'block_rest_anon' ); ?>> <strong>Chặn REST API ẩn danh (<code>/wp-json/wp/v2/*</code>) &amp; tắt XML-RPC</strong></label><br>
				<span style="font-size:12.5px;color:#666;margin-left:24px;">Chặn tool bên ngoài cào bài viết, CPT <code>xe</code>, danh sách user qua REST API &amp; XML-RPC. User đã đăng nhập, Fluent Forms, Elementor, VinhXD vẫn chạy bình thường.</span></p>

			<p><label><input type="checkbox" name="block_rippers" value="1"<?php echo $on( 'block_rippers' ); ?>> <strong>Chặn phần mềm tải cả website</strong></label><br>
				<span style="font-size:12.5px;color:#666;margin-left:24px;">Chặn HTTrack, WebCopier, Cyotek, SaveWeb2Zip, wget, curl, Scrapy, Playwright/Puppeteer/Selenium… Bot Google, Cốc Cốc, Facebook, Zalo, PageSpeed luôn được qua.</span></p>

			<p><label><input type="checkbox" name="anti_clone" value="1"<?php echo $on( 'anti_clone' ); ?>> <strong>Chống Copy / Chuột phải / F12 / Ctrl+U / Ctrl+S</strong></label><br>
				<span style="font-size:12.5px;color:#666;margin-left:24px;">Chỉ áp dụng với khách; vẫn gõ &amp; copy/dán bình thường trong ô form.</span></p>

			<p><label><input type="checkbox" name="domain_lock" value="1"<?php echo $on( 'domain_lock' ); ?>> <strong>Khóa tên miền</strong></label><br>
				<span style="font-size:12.5px;color:#666;margin-left:24px;">Site nhái (reverse proxy hoặc copy HTML sang domain khác) sẽ tự chuyển về <code><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code>; chặn nhúng iframe.</span></p>
			<p style="margin:0 0 4px 24px;font-size:12px;">Tên miền phụ được phép (mỗi dòng 1 tên miền):</p>
			<textarea name="domain_allow" rows="2" style="width:calc(100% - 24px);margin-left:24px;font-family:monospace;font-size:12px;" placeholder="ten-mien-phu.vn"><?php echo esc_textarea( get_option( 'xd_domain_allow', '' ) ); ?></textarea>

			<p style="margin-top:12px;"><label><input type="checkbox" name="rss_protect" value="1"<?php echo $on( 'rss_protect' ); ?>> <strong>Bảo vệ RSS</strong></label><br>
				<span style="font-size:12.5px;color:#666;margin-left:24px;">RSS chỉ trả tóm tắt kèm link bài gốc, tránh bị lấy bài tự động.</span></p>
		</div>

		<div style="<?php echo esc_attr( $box ); ?>">
			<h2 style="margin-top:0;">4. Mã PIN Quản Trị Bổ Sung (Tùy Chọn)</h2>
			<p class="description">Mặc định bạn luôn mở khóa được bằng <strong>License Key MEG</strong>. Bạn có thể đặt thêm Mã PIN riêng (dùng cho site chưa gắn key MEG hoặc để nhớ nhanh).</p>
			<p>
				<input type="password" name="guard_pin" class="regular-text" placeholder="Để trống nếu giữ nguyên" autocomplete="new-password">
				<?php if ( get_option( 'xd_guard_pin_hash', '' ) !== '' ) : ?>
					<label style="margin-left:12px;"><input type="checkbox" name="clear_pin" value="1"> Xóa Mã PIN đang lưu</label>
				<?php endif; ?>
			</p>
		</div>

		<?php submit_button( 'Lưu cấu hình Bảo Mật & Chống Clone', 'primary large', 'vxd_save_guard' ); ?>
	</form>
	<?php
}

function vxd_guard_render_log_tab() {
	$auth_notice = vxd_guard_handle_auth();
	if ( ! vxd_guard_is_unlocked() ) {
		vxd_guard_render_unlock_gate( $auth_notice );
		return;
	}

	if ( isset( $_POST['vxd_clear_log'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'vxd_clear_log' ) ) {
		delete_option( 'xd_guard_log' );
		echo '<div class="notice notice-success is-dismissible"><p>Đã xóa nhật ký chặn.</p></div>';
	}

	$log    = array_reverse( (array) get_option( 'xd_guard_log', [] ) );
	$labels = [
		'tamper'     => 'Can thiệp hệ thống',
		'plugin'     => 'Tắt plugin',
		'activate'   => 'Kích hoạt',
		'install'    => 'Cài plugin',
		'signature'  => 'Chữ ký mã nguồn',
		'upload_zip' => 'Tải lên ZIP',
		'file_edit'  => 'Sửa file',
		'export'     => 'Xuất dữ liệu',
		'rest'       => 'REST / XML-RPC',
		'bot'        => 'Bot tải web',
		'domain'     => 'Tên miền lạ',
	];

	echo '<div style="max-width:1100px;margin-top:16px;"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
	echo '<h2 style="margin:0;">Nhật ký chặn (' . count( $log ) . ' lần gần nhất)</h2>';
	if ( $log ) {
		echo '<form method="post" onsubmit="return confirm(\'Xóa toàn bộ nhật ký?\')">';
		wp_nonce_field( 'vxd_clear_log' );
		echo '<button type="submit" name="vxd_clear_log" class="button">Xóa nhật ký</button></form>';
	}
	echo '</div>';
	if ( ! $log ) {
		echo '<p style="color:#888;">Chưa có lần chặn nào.</p></div>';
		return;
	}
	echo '<table class="wp-list-table widefat fixed striped" style="font-size:13px;"><thead><tr><th style="width:150px;">Thời gian</th><th style="width:130px;">Loại</th><th>Chi tiết</th><th style="width:120px;">IP</th><th style="width:110px;">Tài khoản</th></tr></thead><tbody>';
	foreach ( $log as $e ) {
		echo '<tr><td>' . esc_html( $e['time'] ?? '' ) . '</td><td><strong>' . esc_html( $labels[ $e['type'] ?? '' ] ?? ( $e['type'] ?? '' ) ) . '</strong></td><td style="word-break:break-word;">' . esc_html( $e['detail'] ?? '' ) . '</td><td><code>' . esc_html( $e['ip'] ?? '' ) . '</code></td><td>' . esc_html( $e['user'] ?? '' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
