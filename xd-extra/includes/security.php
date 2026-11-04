<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Lõi chặn backup/clone (có thể đã được mu-plugin nạp trước).
require_once XD_INCLUDES . 'guard-core.php';

/* ==================================================================
 * MU-PLUGIN XD GUARD
 * ================================================================ */
function xd_guard_mu_path() {
	return WPMU_PLUGIN_DIR . '/xd-guard.php';
}

/** Tạo / cập nhật / gỡ mu-plugin theo trạng thái "Chặn backup/clone". Trả về true nếu mu-plugin đang hoạt động. */
function xd_guard_sync_mu() {
	$path = xd_guard_mu_path();
	if ( get_option( 'xd_anti_migration' ) !== '1' ) {
		if ( file_exists( $path ) ) wp_delete_file( $path );
		return false;
	}
	$core = file_get_contents( XD_INCLUDES . 'guard-core.php' );
	if ( ! $core ) return false;
	$code = preg_replace( '/^<\?php\s*/', '', $core );
	$body = "<?php\n/**\n * Plugin Name: XD Guard\n * Description: Bảo vệ website khỏi plugin backup/clone. Tạo tự động bởi XD Extra.\n * Version: " . XD_GUARD_VERSION . "\n */\n" . $code;
	if ( file_exists( $path ) && md5_file( $path ) === md5( $body ) ) return true;
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
	return (bool) @file_put_contents( $path, $body );
}

/* ==================================================================
 * FRONTEND: chống copy, chặn bot sao chép, khoá tên miền, RSS
 * ================================================================ */

/** Tên miền được phép (tên miền chính, bản www/không www, và danh sách thêm). */
function xd_allowed_hosts() {
	$home  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$hosts = [ $home, preg_replace( '/^www\./', '', $home ), 'www.' . preg_replace( '/^www\./', '', $home ), 'localhost', '127.0.0.1' ];
	foreach ( preg_split( '/[\s,]+/', strtolower( (string) get_option( 'xd_domain_allow', '' ) ) ) as $h ) {
		$h = trim( preg_replace( '#^https?://#', '', $h ), "/ \t" );
		if ( $h ) $hosts[] = $h;
	}
	return array_values( array_unique( array_filter( $hosts ) ) );
}

function xd_is_bypass_user() {
	return is_user_logged_in() && current_user_can( 'edit_posts' );
}

// 1. Chặn bot / phần mềm tải cả website (HTTrack, WebCopier, wget, Scrapy...).
add_action( 'template_redirect', function () {
	if ( get_option( 'xd_block_rippers', '0' ) !== '1' || xd_is_bypass_user() ) return;
	$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	// Bot tốt (tìm kiếm, xem trước link mạng xã hội, PageSpeed) luôn được qua.
	if ( preg_match( '~(googlebot|google-|googleother|adsbot|mediapartners|bingbot|bingpreview|coccoc|facebookexternalhit|facebot|meta-externalagent|zalo|twitterbot|telegrambot|whatsapp|slackbot|linkedinbot|discordbot|applebot|yandex|duckduck|chrome-lighthouse|pingdom|uptimerobot)~i', $ua ) ) return;
	$bad = '~(httrack|webcopier|webzip|teleport|offline ?explorer|offline ?downloader|sitesnagger|sitesucker|webreaper|webstripper|website ?copier|website ?downloader|websitedownloader|cyotek|webcopy|getright|wget|curl/|scrapy|python-requests|python-urllib|aiohttp|httpx|go-http-client|libwww-perl|node-fetch|axios/|headless|phantomjs|puppeteer|playwright|selenium|grab-site|archiveteam|saveweb2zip|copier)~i';
	if ( trim( $ua ) === '' || preg_match( $bad, $ua ) ) {
		xd_guard_log( 'bot', 'Chặn bot: ' . ( $ua ?: '(không có User-Agent)' ) . ' → ' . ( $_SERVER['REQUEST_URI'] ?? '' ) );
		status_header( 403 );
		nocache_headers();
		exit( 'Forbidden' );
	}
}, 0 );

// 2. Khoá tên miền: chặn site nhái chạy proxy / copy HTML sang tên miền khác.
add_action( 'template_redirect', function () {
	if ( get_option( 'xd_domain_lock', '0' ) !== '1' || xd_is_bypass_user() ) return;
	$host = strtolower( preg_replace( '/:\d+$/', '', (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );
	if ( $host && ! in_array( $host, xd_allowed_hosts(), true ) ) {
		xd_guard_log( 'domain', 'Truy cập qua tên miền lạ: ' . $host );
		wp_redirect( home_url( $_SERVER['REQUEST_URI'] ?? '/' ), 301 );
		exit;
	}
}, 1 );

add_action( 'send_headers', function () {
	if ( get_option( 'xd_domain_lock', '0' ) !== '1' || is_admin() ) return;
	// Không cho site khác nhúng website vào iframe.
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( "Content-Security-Policy: frame-ancestors 'self'" );
} );

add_action( 'wp_head', function () {
	if ( get_option( 'xd_domain_lock', '0' ) !== '1' || xd_is_bypass_user() ) return;
	$home  = untrailingslashit( home_url() );
	$data  = base64_encode( wp_json_encode( [ 'h' => xd_allowed_hosts(), 'u' => $home ] ) );
	// Bản sao chạy trên tên miền khác sẽ tự chuyển về trang gốc.
	echo '<script>(function(d){try{d=JSON.parse(atob(d));var h=location.hostname.toLowerCase();if(d.h.indexOf(h)<0&&!/\.translate\.goog$/.test(h)&&!/googleusercontent\.com$/.test(h)){location.replace(d.u+location.pathname+location.search);}}catch(e){}})("' . esc_js( $data ) . '");</script>' . "\n";
}, 0 );

// 3. Chống copy / F12 / Ctrl+U / Ctrl+S (chỉ khách; vẫn gõ & copy được trong ô nhập liệu).
add_action( 'wp_footer', function () {
	if ( get_option( 'xd_anti_clone' ) !== '1' || xd_is_bypass_user() ) return;
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

// 4. Bảo vệ RSS: chỉ đưa tóm tắt + link bài gốc (chống site tự động lấy bài qua RSS).
add_filter( 'pre_option_rss_use_excerpt', function ( $v ) {
	return get_option( 'xd_rss_protect', '0' ) === '1' ? '1' : $v;
} );
$xd_rss_source = function ( $content ) {
	if ( get_option( 'xd_rss_protect', '0' ) !== '1' ) return $content;
	return $content . "\n" . '<p>Bài viết gốc: <a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a> – ' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
};
add_filter( 'the_excerpt_rss', $xd_rss_source, 99 );
add_filter( 'the_content_feed', $xd_rss_source, 99 );

/* ==================================================================
 * ADMIN: ẩn plugin / menu, CSS admin, tắt sửa file
 * ================================================================ */
add_filter( 'all_plugins', function ( $plugins ) {
	foreach ( (array) get_option( 'xd_hidden_plugins', [] ) as $f ) unset( $plugins[ $f ] );
	return $plugins;
} );

add_action( 'admin_menu', function () {
	add_menu_page( 'XD Hide', 'XD Hide', 'manage_options', 'xd-hide', 'xd_security_settings_page', 'dashicons-shield', 99 );
}, 99 );

add_action( 'admin_menu', function () {
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'xd-hide' ) return;
	foreach ( (array) get_option( 'xd_hidden_menus', [] ) as $slug ) {
		if ( $slug !== 'xd-hide' ) remove_menu_page( $slug );
	}
}, 999 );

add_action( 'admin_head', function () {
	$css = (string) get_option( 'xd_admin_css', '' );
	if ( trim( $css ) !== '' ) echo '<style id="xd-custom-admin-css">' . xd_sanitize_css( $css ) . '</style>';
} );

if ( get_option( 'xd_disable_file_edit', '0' ) === '1' && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/* ==================================================================
 * TRANG XD HIDE
 * ================================================================ */
function xd_security_save() {
	if ( ! isset( $_POST['xd_save_security'] ) || ! current_user_can( 'manage_options' ) ) return '';
	check_admin_referer( 'xd_security_save' );
	$p = wp_unslash( $_POST );
	update_option( 'xd_hidden_plugins', array_map( 'sanitize_text_field', (array) ( $p['hidden_plugins'] ?? [] ) ) );
	update_option( 'xd_hidden_menus', array_map( 'sanitize_text_field', (array) ( $p['hidden_menus'] ?? [] ) ) );
	update_option( 'xd_admin_css', xd_sanitize_css( $p['custom_css'] ?? '' ) );
	foreach ( [ 'anti_clone', 'anti_migration', 'disable_file_edit', 'block_rippers', 'domain_lock', 'rss_protect' ] as $k ) {
		update_option( 'xd_' . $k, isset( $p[ $k ] ) ? '1' : '0' );
	}
	update_option( 'xd_backup_whitelist', sanitize_textarea_field( $p['backup_whitelist'] ?? '' ) );
	update_option( 'xd_domain_allow', sanitize_textarea_field( $p['domain_allow'] ?? '' ) );

	$mu_ok = xd_guard_sync_mu();
	if ( get_option( 'xd_anti_migration' ) === '1' && ! $mu_ok ) {
		return 'Đã lưu. Lưu ý: không ghi được mu-plugin vào wp-content/mu-plugins (thiếu quyền ghi), lớp chặn sớm chưa bật; các lớp chặn khác vẫn hoạt động.';
	}
	return 'Đã lưu cấu hình.';
}

function xd_security_settings_page() {
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'security';
	$base = admin_url( 'admin.php?page=xd-hide' );
	$log  = (array) get_option( 'xd_guard_log', [] );
	$tabs = [ 'security' => '🛡️ Bảo Mật & Ẩn', 'log' => '📋 Nhật ký chặn' . ( $log ? ' (' . count( $log ) . ')' : '' ), 'shortcodes' => '🧩 Shortcodes' ];

	echo '<div class="wrap"><h1>🛡️ XD Hide &amp; Bảo Mật</h1><nav class="nav-tab-wrapper" style="margin-bottom:20px;">';
	foreach ( $tabs as $k => $label ) {
		echo '<a href="' . esc_url( $base . '&tab=' . $k ) . '" class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';

	if ( $tab === 'log' ) { xd_security_log_tab(); echo '</div>'; return; }
	if ( $tab === 'shortcodes' ) { xd_security_shortcodes_tab(); echo '</div>'; return; }

	$msg = xd_security_save();
	if ( $msg ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';

	if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$all_plugins    = get_plugins();
	$hidden_plugins = (array) get_option( 'xd_hidden_plugins', [] );
	$hidden_menus   = (array) get_option( 'xd_hidden_menus', [] );
	$on             = function ( $k ) { return get_option( 'xd_' . $k, '0' ) === '1' ? ' checked' : ''; };
	$box            = 'background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:6px;';
	$mu_exists      = file_exists( xd_guard_mu_path() );

	global $menu;
	$all_menus = [];
	foreach ( (array) $menu as $item ) {
		if ( empty( $item[0] ) || empty( $item[2] ) ) continue;
		$label = trim( wp_strip_all_tags( preg_replace( '#<span.*?</span>#s', '', $item[0] ) ) );
		if ( $label ) $all_menus[] = [ 'slug' => $item[2], 'label' => $label ];
	}
	?>
	<form method="post"><?php wp_nonce_field( 'xd_security_save' ); ?>
	<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
		<div style="flex:2;min-width:420px;display:flex;flex-direction:column;gap:20px;">
			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">1. Ẩn Plugin</h2>
				<table class="wp-list-table widefat fixed striped"><thead><tr><th style="width:40px;">Ẩn</th><th>Tên</th><th>File</th></tr></thead><tbody>
				<?php foreach ( $all_plugins as $file => $d ) : ?>
					<tr><td><input type="checkbox" name="hidden_plugins[]" value="<?php echo esc_attr( $file ); ?>"<?php checked( in_array( $file, $hidden_plugins, true ) ); ?>></td><td><strong><?php echo esc_html( $d['Name'] ); ?></strong></td><td><code style="font-size:11px;"><?php echo esc_html( $file ); ?></code></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">2. Ẩn Menu Admin</h2>
				<table class="wp-list-table widefat fixed striped"><thead><tr><th style="width:40px;">Ẩn</th><th>Tên</th><th>Slug</th></tr></thead><tbody>
				<?php foreach ( $all_menus as $m ) : if ( $m['slug'] === 'xd-hide' ) continue; ?>
					<tr><td><input type="checkbox" name="hidden_menus[]" value="<?php echo esc_attr( $m['slug'] ); ?>"<?php checked( in_array( $m['slug'], $hidden_menus, true ) ); ?>></td><td><?php echo esc_html( $m['label'] ); ?></td><td><code style="font-size:11px;"><?php echo esc_html( $m['slug'] ); ?></code></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
		</div>

		<div style="flex:1;min-width:320px;display:flex;flex-direction:column;gap:20px;">
			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">3. Chống Backup / Clone</h2>
				<p><label><input type="checkbox" name="anti_migration" value="1"<?php echo $on( 'anti_migration' ); ?>> <strong>Chặn plugin Backup / Migration / File Manager</strong></label></p>
				<ul style="font-size:12px;color:#666;margin:-4px 0 10px 24px;list-style:disc;">
					<li>Tự tắt và không nạp các plugin: All-in-One WP Migration, Duplicator, UpdraftPlus, WPvivid, WP Staging, File Manager, Adminer...</li>
					<li>Chặn cài mới (kể cả upload file zip) và chặn kích hoạt.</li>
					<li>Tắt Công cụ → Xuất dữ liệu (export XML).</li>
					<li>Chạy dạng mu-plugin, vẫn chặn khi XD Extra bị tắt.
						<?php if ( get_option( 'xd_anti_migration' ) === '1' ) echo $mu_exists ? '<span style="color:#008a20;">● Đang hoạt động</span>' : '<span style="color:#d63638;">● Chưa ghi được mu-plugin</span>'; ?></li>
				</ul>
				<p style="margin-bottom:4px;"><strong>Plugin được phép</strong> <span style="color:#888;font-size:12px;">(mỗi dòng 1 slug thư mục)</span></p>
				<textarea name="backup_whitelist" rows="3" style="width:100%;font-family:monospace;font-size:12px;" placeholder="wp-file-manager"><?php echo esc_textarea( get_option( 'xd_backup_whitelist', 'wp-file-manager' ) ); ?></textarea>
				<p class="description" style="font-size:11px;">Chính bạn cần backup/chuyển host: thêm <code>define( 'XD_ALLOW_BACKUP', true );</code> vào wp-config.php, xong việc thì xoá dòng đó.</p>
				<hr>
				<p><label><input type="checkbox" name="anti_clone" value="1"<?php echo $on( 'anti_clone' ); ?>> <strong>Chống Copy / F12 / Ctrl+U / Ctrl+S</strong></label><br><span style="font-size:12px;color:#888;margin-left:24px;">Chỉ áp dụng với khách; ô nhập liệu vẫn copy/dán bình thường.</span></p>
				<p><label><input type="checkbox" name="block_rippers" value="1"<?php echo $on( 'block_rippers' ); ?>> <strong>Chặn phần mềm tải cả website</strong></label><br><span style="font-size:12px;color:#888;margin-left:24px;">HTTrack, WebCopier, Cyotek, wget, curl, Scrapy, trình duyệt headless... Bot Google, Cốc Cốc, Facebook, Zalo, PageSpeed vẫn được qua.</span></p>
				<p><label><input type="checkbox" name="domain_lock" value="1"<?php echo $on( 'domain_lock' ); ?>> <strong>Khoá tên miền</strong></label><br><span style="font-size:12px;color:#888;margin-left:24px;">Site nhái (proxy hoặc copy HTML) sẽ tự chuyển về <?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>; không cho nhúng iframe.</span></p>
				<p style="margin:0 0 4px 24px;font-size:12px;">Tên miền phụ được phép (mỗi dòng 1 tên miền):</p>
				<textarea name="domain_allow" rows="2" style="width:calc(100% - 24px);margin-left:24px;font-family:monospace;font-size:12px;" placeholder="ten-mien-phu.vn"><?php echo esc_textarea( get_option( 'xd_domain_allow', '' ) ); ?></textarea>
				<p><label><input type="checkbox" name="rss_protect" value="1"<?php echo $on( 'rss_protect' ); ?>> <strong>Bảo vệ RSS</strong></label><br><span style="font-size:12px;color:#888;margin-left:24px;">RSS chỉ có tóm tắt + link bài gốc, site lấy bài tự động không lấy được toàn văn.</span></p>
				<p><label><input type="checkbox" name="disable_file_edit" value="1"<?php echo $on( 'disable_file_edit' ); ?>> <strong>Tắt sửa file plugin &amp; theme</strong></label></p>
			</div>
			<div style="<?php echo esc_attr( $box ); ?>">
				<h2 style="margin-top:0;">4. CSS Admin</h2>
				<textarea name="custom_css" rows="8" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( get_option( 'xd_admin_css', '' ) ); ?></textarea>
			</div>
			<?php submit_button( 'Lưu cấu hình', 'primary large', 'xd_save_security', false, [ 'style' => 'width:100%;' ] ); ?>
		</div>
	</div>
	</form>
	<?php
	echo '</div>';
}

function xd_security_log_tab() {
	if ( isset( $_POST['xd_clear_log'] ) && check_admin_referer( 'xd_clear_log' ) ) {
		delete_option( 'xd_guard_log' );
		echo '<div class="notice notice-success is-dismissible"><p>Đã xóa nhật ký.</p></div>';
	}
	$log    = array_reverse( (array) get_option( 'xd_guard_log', [] ) );
	$labels = [ 'plugin' => 'Tắt plugin', 'activate' => 'Kích hoạt', 'install' => 'Cài plugin', 'export' => 'Xuất dữ liệu', 'bot' => 'Bot tải web', 'domain' => 'Tên miền lạ' ];
	echo '<div style="max-width:1100px;"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
	echo '<h2 style="margin:0;">Nhật ký chặn (' . count( $log ) . ' lần gần nhất)</h2>';
	if ( $log ) {
		echo '<form method="post" onsubmit="return confirm(\'Xóa toàn bộ nhật ký?\')">';
		wp_nonce_field( 'xd_clear_log' );
		echo '<button type="submit" name="xd_clear_log" class="button">Xóa nhật ký</button></form>';
	}
	echo '</div>';
	if ( ! $log ) { echo '<p style="color:#888;">Chưa có lần chặn nào.</p></div>'; return; }
	echo '<table class="wp-list-table widefat fixed striped" style="font-size:13px;"><thead><tr><th style="width:150px;">Thời gian</th><th style="width:110px;">Loại</th><th>Chi tiết</th><th style="width:120px;">IP</th><th style="width:110px;">Tài khoản</th></tr></thead><tbody>';
	foreach ( $log as $e ) {
		echo '<tr><td>' . esc_html( $e['time'] ?? '' ) . '</td><td>' . esc_html( $labels[ $e['type'] ?? '' ] ?? ( $e['type'] ?? '' ) ) . '</td><td style="word-break:break-word;">' . esc_html( $e['detail'] ?? '' ) . '</td><td><code>' . esc_html( $e['ip'] ?? '' ) . '</code></td><td>' . esc_html( $e['user'] ?? '' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

function xd_security_shortcodes_tab() {
	$rows = [
		[ '[xd_lien_he]', 'Khối liên hệ: bản đồ nền + thông tin + form.', 'form_id="4" tieu_de="" mo_ta="" gio="7:30 – 17:30" ban_do="click|luon|tat" form_tieu_de="" form_mo_ta=""' ],
		[ '[xd_news]', '1 bài lớn + danh sách bài nhỏ.', 'bv="4" cat="slug hoặc ID"' ],
		[ '[xd_uu_dai]', 'Khối ưu đãi trong tháng (trường khuyenmai).', 'form_id="3" popup_id="ID JetPopup"' ],
		[ '[vinhxd id="123"]', 'Chèn template Elementor bất kỳ.', 'id' ],
	];
	echo '<table class="wp-list-table widefat fixed striped" style="max-width:1100px;"><thead><tr><th style="width:170px;">Shortcode</th><th>Mô tả</th><th>Tham số</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td><code>' . esc_html( $r[0] ) . '</code></td><td>' . esc_html( $r[1] ) . '</td><td><code style="font-size:11px;">' . esc_html( $r[2] ) . '</code></td></tr>';
	}
	echo '</tbody></table><p class="description">Dữ liệu (tên, địa chỉ, SĐT, Zalo, email, giờ làm việc…) lấy từ Option page <code>thong-tin</code> của JetEngine.</p>';
}
