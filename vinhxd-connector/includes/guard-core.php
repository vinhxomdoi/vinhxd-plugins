<?php
/**
 * XD Guard – lõi bảo vệ chống clone, backup, file manager, sửa file và tự vệ cho VinhXD Connector.
 *
 * File này được nạp bởi VinhXD Connector, đồng thời được đồng bộ thành mu-plugin
 * (wp-content/mu-plugins/xd-guard.php) để:
 *  - chạy TRƯỚC mọi plugin thường, gỡ plugin bị chặn khỏi danh sách nạp;
 *  - ẩn & chống tắt/xóa VinhXD Connector;
 *  - khóa cứng sửa file theme/plugin, chặn upload ZIP, chặn REST ẩn danh;
 *  - vẫn bảo vệ ngay cả khi có nỗ lực can thiệp vào plugin thường.
 *
 * Tạm mở khóa để chính bạn backup / chuyển host / bảo trì: thêm vào wp-config.php
 *     define( 'XD_ALLOW_BACKUP', true );
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'XD_GUARD_VERSION' ) ) :

define( 'XD_GUARD_VERSION', '2.0.0' );

function xd_guard_connector_file() {
	return 'vinhxd-connector/vinhxd-connector.php';
}

function xd_guard_bypass() {
	return defined( 'XD_ALLOW_BACKUP' ) && XD_ALLOW_BACKUP;
}

function xd_guard_is_internal_upgrade() {
	if ( ! empty( $GLOBALS['vxd_connector_internal_upgrade'] ) ) return true;
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) return true;
	if ( defined( 'WP_CLI' ) && WP_CLI ) return true;
	return false;
}

function xd_guard_active() {
	if ( xd_guard_bypass() ) return false;
	return get_option( 'xd_anti_migration' ) === '1';
}

function xd_guard_hide_connector() {
	if ( xd_guard_bypass() ) return false;
	return get_option( 'xd_hide_connector', '1' ) === '1';
}

function xd_guard_file_edit_locked() {
	if ( xd_guard_bypass() ) return false;
	return get_option( 'xd_disable_file_edit', '0' ) === '1';
}

function xd_guard_zip_upload_blocked() {
	if ( xd_guard_bypass() || xd_guard_is_internal_upgrade() ) return false;
	return get_option( 'xd_block_zip_upload', '0' ) === '1';
}

function xd_guard_rest_anon_blocked() {
	if ( xd_guard_bypass() ) return false;
	return get_option( 'xd_block_rest_anon', '0' ) === '1';
}

function xd_guard_tpl_export_blocked() {
	if ( xd_guard_bypass() ) return false;
	return get_option( 'xd_block_tpl_export', '0' ) === '1';
}

/** Từ khóa trong slug / tên / header plugin bị chặn. */
function xd_guard_keywords() {
	return [
		// Backup / migration / clone / staging / static export
		'migrat', 'duplicator', 'backup', 'back-up', 'backitup', 'clone', 'cloner', 'copy-site', 'site-copier',
		'wp-staging', 'wpvivid', 'updraft', 'xcloner', 'backwpup', 'blogvault', 'jetbackup', 'backupbuddy',
		'prime-mover', 'shipper', 'wp-sync-db', 'wp-migrate', 'all-in-one-wp-migration', 'migrate-guru',
		'snapshot-backup', 'everest-backup', 'solid-backups', 'boldgrid-backup', 'total-upkeep',
		'wp-time-capsule', 'wptimecapsule', 'backuply', 'backup-migration', 'backup-bliss', 'servmask',
		'site-export', 'export-site', 'wp-all-export', 'demo-export', 'theme-export',
		'instawp', 'tastewp', 'zipwp', 'simply-static', 'staatic', 'wp2static', 'static-html-output',
		'download-plugins', 'download-theme', 'theme-downloader', 'plugin-downloader', 'wp-reset', 'wp-cfm',
		// Trình quản lý file / IDE / WebFTP / Shell / Terminal
		'file-manager', 'filemanager', 'file_manager', 'wpfm', 'file-explorer', 'file-browser', 'filester',
		'bit-file-manager', 'file-organizer', 'fileorganizer', 'wpide', 'wp-ide', 'aceide', 'elfinder',
		'tinyfilemanager', 'filegator', 'kodexplorer', 'extplorer', 'webftp', 'wp-ftp',
		'wp-console', 'wp-terminal', 'php-console', 'theme-editor', 'plugin-editor',
		// Quản lý / xuất CSDL trực tiếp
		'adminer', 'db-manager', 'dbmanager', 'sql-executioner', 'sql-buddy', 'wp-phpmyadmin', 'phpmyadmin',
		'database-manager', 'database-browser', 'wp-data-access', 'sqlite-manager', 'wp-db-backup', 'wp-database-backup',
		// Công cụ quản trị từ xa có chức năng kéo full backup
		'managewp', 'mainwp-child', 'infinitewp', 'iwp-client', 'wpremote', 'wp-remote', 'wp-umbrella',
		'modular-connector', 'ithemes-sync',
	];
}

/** Plugin an toàn (không bao giờ chặn nhầm — bao gồm plugin chèn mã nhúng phổ biến). */
function xd_guard_harmless() {
	return [
		'duplicate-post', 'post-duplicator', 'duplicate-page', 'duplicate-page-and-post', 'yoast-duplicate-post',
		'filebird', 'clone-widgets', 'elementor', 'jet-', 'litespeed', 'fluent', 'seo-by-rank-math',
		'wordpress-seo', 'wordfence', 'theme-xd',
		// Plugin chèn mã nhúng / snippets phổ biến (người dùng cần chèn GTM/Pixel/Analytics)
		'code-snippets', 'insert-headers-and-footers', 'wpcode', 'header-footer-code-manager',
		'custom-css-js', 'simple-custom-css-and-js', 'woody', 'insert-php', 'fluent-snippets',
	];
}

/** Danh sách cho phép do admin nhập (mỗi dòng 1 slug/file; mặc định rỗng — không còn whitelist wp-file-manager). */
function xd_guard_whitelist() {
	$raw = (string) get_option( 'xd_backup_whitelist', '' );
	return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', strtolower( $raw ) ) ) ) );
}

/**
 * @param string $plugin  "thu-muc/file.php" hoặc tên thư mục
 * @param string $meta    Tên plugin hoặc chuỗi gộp header (Name, Description, Author, URI, TextDomain)
 */
function xd_guard_is_blocked( $plugin, $meta = '' ) {
	$plugin = strtolower( (string) $plugin );
	$slug   = strpos( $plugin, '/' ) !== false ? substr( $plugin, 0, strpos( $plugin, '/' ) ) : preg_replace( '/\.php$/', '', $plugin );

	// Plugin của hệ thống Vĩnh XD.
	if ( strpos( $slug, 'xd-' ) === 0 || strpos( $slug, 'vinhxd' ) === 0 || $slug === 'xd-extra' || $slug === 'theme-xd' ) {
		return false;
	}

	foreach ( xd_guard_whitelist() as $w ) {
		if ( $w === $plugin || $w === $slug ) return false;
	}
	foreach ( xd_guard_harmless() as $h ) {
		if ( strpos( $slug, $h ) === 0 ) return false;
	}

	$hay = $plugin . ' ' . sanitize_title( (string) $meta );
	foreach ( xd_guard_keywords() as $kw ) {
		if ( strpos( $hay, $kw ) !== false ) return true;
	}
	return false;
}

/**
 * Quét chữ ký mã nguồn trong thư mục plugin/theme sắp cài (chống chiêu đổi tên thư mục / Plugin Name trong ZIP).
 * Trả về chuỗi mô tả chữ ký nếu phát hiện nguy hiểm, rỗng nếu an toàn.
 */
function xd_guard_scan_directory( $dir, $slug = '' ) {
	$slug = strtolower( (string) $slug );
	if ( strpos( $slug, 'xd-' ) === 0 || strpos( $slug, 'vinhxd' ) === 0 || $slug === 'xd-extra' || $slug === 'theme-xd' ) {
		return '';
	}
	foreach ( xd_guard_whitelist() as $w ) {
		if ( $w === $slug ) return '';
	}
	foreach ( xd_guard_harmless() as $h ) {
		if ( strpos( $slug, $h ) === 0 ) return '';
	}
	if ( ! is_dir( $dir ) ) return '';

	// 1. Chữ ký định danh các công cụ backup / clone / file manager / DB manager nổi tiếng bị đổi tên
	$tool_sigs = [
		'AI1WM_VERSION'          => 'All-in-One WP Migration',
		'AI1WM_PLUGIN_NAME'      => 'All-in-One WP Migration',
		'DUPLICATOR_VERSION'     => 'Duplicator',
		'DUP_PRO_VERSION'        => 'Duplicator Pro',
		'UPDRAFTPLUS_DIR'        => 'UpdraftPlus',
		'WPVIVID_PLUGIN_DIR'     => 'WPvivid Backup',
		'elFinderConnector'      => 'elFinder File Manager',
		'ELFINDER_IMG_PARENT'    => 'elFinder File Manager',
		'function adminer_object'=> 'Adminer DB Manager',
		'BACKWPUP_VERSION'       => 'BackWPup',
		'PRIME_MOVER_VERSION'    => 'Prime Mover',
		'BMI_ROOT_DIR'           => 'Backup Migration',
		'WPSTG_PLUGIN_DIR'       => 'WP Staging',
		'SIMPLY_STATIC_PATH'     => 'Simply Static',
		'INSTAWP_PLUGIN_DIR'     => 'InstaWP Connect',
	];

	// Kiểm tra nhanh tên file đặc trưng (vd: dup-installer, ai1wm, elfinder, adminer)
	$bad_files = [ 'dup-installer', 'ai1wm', 'elfinder', 'adminer', 'tinyfilemanager', 'filegator' ];

	$scanned = 0;
	$max_files = 150;
	try {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $it as $file ) {
			$fname = strtolower( $file->getFilename() );
			foreach ( $bad_files as $bf ) {
				if ( strpos( $fname, $bf ) !== false ) {
					return 'Phát hiện tệp công cụ bị chặn: ' . $file->getFilename();
				}
			}
			if ( ! $file->isFile() || strtolower( $file->getExtension() ) !== 'php' ) continue;
			if ( ++$scanned > $max_files ) break;

			$content = @file_get_contents( $file->getPathname(), false, null, 0, 131072 );
			if ( $content === false || $content === '' ) continue;

			foreach ( $tool_sigs as $sig => $label ) {
				if ( strpos( $content, $sig ) !== false ) {
					return 'Phát hiện chữ ký mã nguồn ' . $label . ' (đã đổi tên)';
				}
			}

			// 2. Chữ ký mã PHP tự chế chuyên dump DB
			if ( preg_match( '/\bmysqldump\b/i', $content ) ||
				( stripos( $content, 'SHOW CREATE TABLE' ) !== false && stripos( $content, 'INSERT INTO' ) !== false && strpos( $content, 'DB_NAME' ) !== false ) ) {
				return 'Phát hiện mã xuất cơ sở dữ liệu trong ' . $file->getFilename();
			}

			// 3. Chữ ký mã PHP tự chế nén toàn bộ mã nguồn website
			if ( ( stripos( $content, 'ZipArchive' ) !== false || stripos( $content, 'PharData' ) !== false )
				&& stripos( $content, 'RecursiveDirectoryIterator' ) !== false
				&& ( strpos( $content, 'ABSPATH' ) !== false || strpos( $content, 'WP_CONTENT_DIR' ) !== false )
				&& stripos( $content, 'wp-config.php' ) !== false ) {
				return 'Phát hiện mã nén toàn bộ mã nguồn trong ' . $file->getFilename();
			}

			// 4. Chữ ký webshell / mã obfuscate nguy hiểm
			if ( preg_match( '/\beval\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(/i', $content ) ) {
				return 'Phát hiện mã PHP mã hóa nguy hiểm (eval/obfuscated) trong ' . $file->getFilename();
			}
		}
	} catch ( Exception $e ) {
		return '';
	}

	return '';
}

/** Ghi nhật ký (giữ 200 dòng gần nhất; chống spam DB khi bot đánh liên tục). */
function xd_guard_log( $type, $detail ) {
	$log  = get_option( 'xd_guard_log', [] );
	$log  = is_array( $log ) ? $log : [];
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:\.]/', '', $_SERVER['REMOTE_ADDR'] ) : '';
	$now  = current_time( 'mysql' );
	$now_ts = strtotime( $now );

	// Tránh ghi trùng liên tiếp cùng loại + IP trong 60 giây đối với bot/rest flood
	if ( in_array( $type, [ 'bot', 'rest', 'domain' ], true ) && ! empty( $log ) ) {
		for ( $i = count( $log ) - 1; $i >= max( 0, count( $log ) - 5 ); $i-- ) {
			$e = $log[ $i ];
			if ( ( $e['type'] ?? '' ) === $type && ( $e['ip'] ?? '' ) === $ip && $now_ts - strtotime( $e['time'] ?? '' ) < 60 ) {
				return;
			}
		}
	}

	$user  = function_exists( 'wp_get_current_user' ) && did_action( 'set_current_user' ) ? wp_get_current_user()->user_login : '';
	$log[] = [
		'time'   => $now,
		'type'   => $type,
		'detail' => mb_substr( (string) $detail, 0, 300 ),
		'ip'     => $ip,
		'user'   => $user,
		'ua'     => mb_substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 200 ),
	];
	if ( count( $log ) > 200 ) $log = array_slice( $log, -200 );
	update_option( 'xd_guard_log', $log, false );
}

function xd_guard_die( $msg ) {
	wp_die( '<h1>Bảo mật hệ thống</h1><p>' . esc_html( $msg ) . '</p>', 'Bảo mật hệ thống', [ 'response' => 403, 'back_link' => true ] );
}

/* ------------------------------------------------------------------
 * 0. Ẩn & chống tắt/xóa VinhXD Connector + ẩn tab Must-Use
 * ---------------------------------------------------------------- */
add_filter( 'all_plugins', function ( $plugins ) {
	if ( ! xd_guard_hide_connector() ) return $plugins;
	unset( $plugins[ xd_guard_connector_file() ] );
	return $plugins;
}, 9999 );

add_filter( 'show_advanced_plugins', function ( $show, $type ) {
	if ( $type === 'mustuse' && ( xd_guard_active() || xd_guard_hide_connector() || xd_guard_file_edit_locked() ) ) {
		return false;
	}
	return $show;
}, 999, 2 );

// Chặn tắt VinhXD Connector qua mọi đường (UI, URL trực tiếp, bulk action, code)
add_action( 'deactivate_plugin', function ( $plugin ) {
	if ( ! xd_guard_hide_connector() || xd_guard_is_internal_upgrade() ) return;
	if ( $plugin === xd_guard_connector_file() ) {
		xd_guard_log( 'tamper', 'Chặn tắt plugin hệ thống: ' . $plugin );
		xd_guard_die( 'Plugin hệ thống không thể tắt.' );
	}
}, 0 );

// Chặn tắt/xóa trên trang plugins.php (kể cả URL thủ công hoặc bulk action)
add_action( 'load-plugins.php', function () {
	if ( ! xd_guard_hide_connector() || xd_guard_is_internal_upgrade() ) return;
	$target = xd_guard_connector_file();
	$action = sanitize_key( $_REQUEST['action'] ?? '' );
	$action2 = sanitize_key( $_REQUEST['action2'] ?? '' );
	$acts = [ $action, $action2 ];
	$blocked_acts = [ 'deactivate', 'deactivate-selected', 'delete-selected' ];
	if ( ! array_intersect( $acts, $blocked_acts ) ) return;

	$single = isset( $_REQUEST['plugin'] ) ? wp_unslash( (string) $_REQUEST['plugin'] ) : '';
	$checked = isset( $_REQUEST['checked'] ) ? array_map( 'wp_unslash', (array) $_REQUEST['checked'] ) : [];
	if ( $single === $target || in_array( $target, $checked, true ) ) {
		xd_guard_log( 'tamper', 'Chặn thao tác ' . implode( '/', array_filter( $acts ) ) . ' lên ' . $target );
		xd_guard_die( 'Plugin hệ thống không thể tắt hoặc xóa.' );
	}
}, 0 );

// Chặn AJAX xóa plugin VinhXD Connector
add_action( 'wp_ajax_delete-plugin', function () {
	if ( ! xd_guard_hide_connector() || xd_guard_is_internal_upgrade() ) return;
	$plugin = isset( $_POST['plugin'] ) ? wp_unslash( (string) $_POST['plugin'] ) : '';
	if ( $plugin === xd_guard_connector_file() ) {
		xd_guard_log( 'tamper', 'Chặn AJAX xóa plugin hệ thống: ' . $plugin );
		wp_send_json_error( [ 'errorMessage' => 'Plugin hệ thống không thể xóa.' ], 403 );
	}
}, 0 );

// Tự phục hồi nếu VinhXD Connector bị gỡ khỏi active_plugins trong DB
add_filter( 'pre_update_option_active_plugins', function ( $new_value, $old_value ) {
	if ( ! is_array( $new_value ) || ! xd_guard_hide_connector() || xd_guard_is_internal_upgrade() ) {
		return $new_value;
	}
	$target = xd_guard_connector_file();
	if ( ! in_array( $target, $new_value, true ) && file_exists( WP_PLUGIN_DIR . '/' . $target ) ) {
		$new_value[] = $target;
		sort( $new_value );
		xd_guard_log( 'tamper', 'Tự phục hồi plugin hệ thống trong active_plugins' );
	}
	return $new_value;
}, 9999, 2 );

// Chặn sửa trái phép các option bảo vệ (vd: qua trang ẩn /wp-admin/options.php hoặc AJAX/REST)
foreach ( [
	'xd_anti_migration', 'xd_disable_file_edit', 'xd_block_zip_upload', 'xd_block_rest_anon',
	'xd_block_tpl_export', 'xd_anti_clone', 'xd_block_rippers', 'xd_domain_lock',
	'xd_domain_allow', 'xd_rss_protect', 'xd_backup_whitelist', 'xd_hide_connector', 'xd_guard_pin_hash',
] as $xd_protected_opt ) {
	add_filter( 'pre_update_option_' . $xd_protected_opt, function ( $new_value, $old_value ) use ( $xd_protected_opt ) {
		if ( xd_guard_bypass() || ! empty( $GLOBALS['vxd_guard_allow_save'] ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $new_value;
		}
		if ( ! xd_guard_hide_connector() ) return $new_value;
		// Chỉ chặn khi gọi từ WP Admin / AJAX / REST mà chưa mở khóa qua Connector
		$in_web_admin = is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		if ( $in_web_admin && $new_value !== $old_value ) {
			xd_guard_log( 'tamper', 'Chặn sửa trái phép tùy chọn bảo mật: ' . $xd_protected_opt );
			return $old_value;
		}
		return $new_value;
	}, 9999, 2 );
}

// Chặn xóa/ghi đè License Key MEG đã cài từ WP Admin khi chưa mở khóa (tránh tự thay key để mở khóa)
add_filter( 'pre_update_option_vxd_meg_key', function ( $new_value, $old_value ) {
	if ( trim( (string) $old_value ) === '' ) return $new_value;
	if ( xd_guard_bypass() || ! empty( $GLOBALS['vxd_guard_allow_save'] ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return $new_value;
	}
	if ( ! xd_guard_hide_connector() ) return $new_value;
	$in_web_admin = is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	if ( $in_web_admin && $new_value !== $old_value ) {
		if ( function_exists( 'vxd_guard_is_unlocked' ) && vxd_guard_is_unlocked() ) {
			return $new_value;
		}
		xd_guard_log( 'tamper', 'Chặn sửa trái phép License Key MEG' );
		return $old_value;
	}
	return $new_value;
}, 9999, 2 );

// Ẩn VinhXD Connector khỏi màn hình Dashboard -> Updates để không lộ tên khi có bản mới (auto-update vẫn chạy ngầm)
add_action( 'load-update-core.php', function () {
	if ( ! xd_guard_hide_connector() ) return;
	add_filter( 'site_transient_update_plugins', function ( $t ) {
		if ( is_object( $t ) && ! empty( $t->response ) && isset( $t->response[ xd_guard_connector_file() ] ) ) {
			unset( $t->response[ xd_guard_connector_file() ] );
		}
		return $t;
	}, 9999 );
} );

/* ------------------------------------------------------------------
 * 1. Không nạp plugin bị chặn (gỡ khỏi active_plugins ngay khi WP đọc)
 * ---------------------------------------------------------------- */
function xd_guard_filter_active( $plugins ) {
	if ( ! is_array( $plugins ) ) return $plugins;
	$target = xd_guard_connector_file();
	if ( xd_guard_hide_connector() && ! xd_guard_is_internal_upgrade() && ! in_array( $target, $plugins, true ) && file_exists( WP_PLUGIN_DIR . '/' . $target ) ) {
		$plugins[] = $target;
	}
	if ( ! xd_guard_active() ) return $plugins;
	return array_values( array_filter( $plugins, function ( $p ) { return ! xd_guard_is_blocked( $p ); } ) );
}
add_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );

/** Danh sách plugin đang bật thật trong database (bỏ qua bộ lọc ở trên). */
function xd_guard_raw_active() {
	remove_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );
	$raw = get_option( 'active_plugins', [] );
	add_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );
	return is_array( $raw ) ? $raw : [];
}

// Trong admin: gỡ hẳn plugin bị chặn khỏi database (xét cả tên & mô tả plugin), ghi nhật ký.
add_action( 'admin_init', function () {
	if ( ! xd_guard_active() ) return;
	$raw = xd_guard_raw_active();
	if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$all     = get_plugins();
	$dropped = [];
	foreach ( $raw as $p ) {
		$d    = $all[ $p ] ?? [];
		$meta = implode( ' ', [ $d['Name'] ?? '', $d['Description'] ?? '', $d['Author'] ?? '', $d['PluginURI'] ?? '', $d['TextDomain'] ?? '' ] );
		if ( xd_guard_is_blocked( $p, $meta ) ) $dropped[] = $p;
	}
	if ( ! $dropped ) return;
	remove_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );
	update_option( 'active_plugins', array_values( array_diff( $raw, $dropped ) ) );
	add_filter( 'option_active_plugins', 'xd_guard_filter_active', 1 );
	foreach ( $dropped as $p ) xd_guard_log( 'plugin', 'Đã tắt: ' . $p );
	add_action( 'admin_notices', function () use ( $dropped ) {
		echo '<div class="notice notice-warning"><p><strong>Bảo mật hệ thống:</strong> đã tắt plugin backup/clone/file manager không được phép: <code>' . esc_html( implode( ', ', $dropped ) ) . '</code></p></div>';
	} );
}, 1 );

/* ------------------------------------------------------------------
 * 2. Chặn kích hoạt plugin bị chặn (kiểm tra cả header & chữ ký)
 * ---------------------------------------------------------------- */
add_action( 'activate_plugin', function ( $plugin ) {
	if ( ! xd_guard_active() ) return;
	$meta = '';
	$file = WP_PLUGIN_DIR . '/' . $plugin;
	if ( is_readable( $file ) && function_exists( 'get_plugin_data' ) ) {
		$data = get_plugin_data( $file, false, false );
		$meta = implode( ' ', [ $data['Name'] ?? '', $data['Description'] ?? '', $data['Author'] ?? '', $data['PluginURI'] ?? '', $data['TextDomain'] ?? '' ] );
	}
	if ( xd_guard_is_blocked( $plugin, $meta ) ) {
		xd_guard_log( 'activate', 'Chặn kích hoạt: ' . $plugin );
		xd_guard_die( 'Website này không cho phép kích hoạt plugin backup / migration / clone / file manager (' . $plugin . ').' );
	}
	$dir  = dirname( $file );
	$slug = strpos( $plugin, '/' ) !== false ? dirname( $plugin ) : preg_replace( '/\.php$/', '', $plugin );
	if ( $dir !== WP_PLUGIN_DIR && is_dir( $dir ) ) {
		$sig = xd_guard_scan_directory( $dir, $slug );
		if ( $sig !== '' ) {
			xd_guard_log( 'signature', 'Chặn kích hoạt (' . $plugin . '): ' . $sig );
			xd_guard_die( 'Không thể kích hoạt plugin (' . $plugin . '): ' . $sig );
		}
	}
}, 1 );

/* ------------------------------------------------------------------
 * 3. Chặn cài đặt (kho WP + upload ZIP + quét chữ ký cả plugin & theme)
 * ---------------------------------------------------------------- */
add_filter( 'upgrader_source_selection', function ( $source, $remote_source = '', $upgrader = null, $hook_extra = [] ) {
	if ( is_wp_error( $source ) || ! xd_guard_active() || xd_guard_is_internal_upgrade() ) return $source;

	$dir      = untrailingslashit( $source );
	$slug     = basename( $dir );
	$is_theme = ! empty( $hook_extra['theme'] ) || ( isset( $hook_extra['type'] ) && $hook_extra['type'] === 'theme' );
	$metas    = [];

	if ( ! $is_theme && function_exists( 'get_plugin_data' ) ) {
		foreach ( (array) glob( $dir . '/*.php' ) as $php ) {
			$data = get_plugin_data( $php, false, false );
			if ( ! empty( $data['Name'] ) ) {
				$metas[] = implode( ' ', [ $data['Name'] ?? '', $data['Description'] ?? '', $data['Author'] ?? '', $data['PluginURI'] ?? '', $data['TextDomain'] ?? '' ] );
			}
		}
		if ( ! $metas && file_exists( $dir . '/style.css' ) ) {
			$is_theme = true;
		}
	}

	if ( ! $is_theme && xd_guard_is_blocked( $slug, implode( ' ', $metas ) ) ) {
		xd_guard_log( 'install', 'Chặn cài plugin: ' . $slug );
		return new WP_Error( 'xd_guard_blocked', 'Website này không cho phép cài plugin backup / migration / clone / file manager (' . $slug . ').' );
	}

	// Quét chữ ký mã nguồn cho CẢ plugin lẫn theme (chống đổi tên ZIP hoặc chèn mã dump vào theme)
	$sig = xd_guard_scan_directory( $dir, $slug );
	if ( $sig !== '' ) {
		xd_guard_log( 'signature', 'Chặn cài ' . ( $is_theme ? 'theme' : 'plugin' ) . ' (' . $slug . '): ' . $sig );
		return new WP_Error( 'xd_guard_sig', 'Bảo mật hệ thống chặn cài đặt (' . $slug . '): ' . $sig );
	}

	return $source;
}, 1, 4 );

// Ẩn plugin bị chặn khỏi kết quả tìm kiếm ở Plugins → Cài mới.
add_filter( 'plugins_api_result', function ( $res, $action ) {
	if ( ! xd_guard_active() || $action !== 'query_plugins' || ! is_object( $res ) || empty( $res->plugins ) ) return $res;
	$res->plugins = array_values( array_filter( $res->plugins, function ( $p ) {
		$p    = (array) $p;
		$meta = implode( ' ', [ $p['name'] ?? '', $p['short_description'] ?? '', $p['author'] ?? '' ] );
		return ! xd_guard_is_blocked( $p['slug'] ?? '', $meta );
	} ) );
	return $res;
}, 99, 2 );

/* ------------------------------------------------------------------
 * 4. Chặn tải lên file ZIP plugin & theme từ bên ngoài
 * ---------------------------------------------------------------- */
add_action( 'load-update.php', function () {
	if ( ! xd_guard_zip_upload_blocked() ) return;
	$action = sanitize_key( $_REQUEST['action'] ?? '' );
	if ( in_array( $action, [ 'upload-plugin', 'upload-theme' ], true ) ) {
		xd_guard_log( 'upload_zip', 'Chặn tải lên ZIP: ' . $action );
		xd_guard_die( 'Chức năng tải lên file ZIP plugin / giao diện từ bên ngoài đã bị khóa trên website này.' );
	}
}, 0 );

add_filter( 'wp_handle_upload_prefilter', function ( $file ) {
	if ( ! xd_guard_zip_upload_blocked() ) return $file;
	$action = sanitize_key( $_REQUEST['action'] ?? '' );
	if ( in_array( $action, [ 'upload-plugin', 'upload-theme' ], true ) || isset( $_FILES['pluginzip'] ) || isset( $_FILES['themezip'] ) ) {
		xd_guard_log( 'upload_zip', 'Chặn upload file ZIP: ' . ( $file['name'] ?? '' ) );
		$file['error'] = 'Website này không cho phép tải lên file ZIP plugin hoặc giao diện từ bên ngoài.';
	}
	return $file;
}, 0 );

add_filter( 'upgrader_package_options', function ( $options ) {
	if ( ! xd_guard_zip_upload_blocked() || ! is_array( $options ) ) return $options;
	$pkg = (string) ( $options['package'] ?? '' );
	$uploads = wp_upload_dir();
	$base = ! empty( $uploads['basedir'] ) ? wp_normalize_path( $uploads['basedir'] ) : '';
	if ( $pkg !== '' && $base !== '' && strpos( wp_normalize_path( $pkg ), $base ) === 0 ) {
		xd_guard_log( 'upload_zip', 'Chặn cài đặt gói ZIP tải lên cục bộ: ' . basename( $pkg ) );
		xd_guard_die( 'Website này không cho phép cài đặt plugin/giao diện từ file ZIP tải lên.' );
	}
	return $options;
}, 0 );

add_action( 'admin_head', function () {
	if ( ! xd_guard_zip_upload_blocked() ) return;
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && in_array( $screen->id, [ 'plugin-install', 'theme-install' ], true ) ) {
		echo '<style>.upload-view-toggle,.upload-plugin,.upload-theme{display:none!important;}</style>';
	}
} );

/* ------------------------------------------------------------------
 * 5. Khóa cứng sửa file Theme & Plugin (kể cả khi wp-config.php mở)
 * ---------------------------------------------------------------- */
if ( xd_guard_file_edit_locked() ) {
	if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
		define( 'DISALLOW_FILE_EDIT', true );
	}
	add_filter( 'map_meta_cap', function ( $caps, $cap ) {
		if ( in_array( $cap, [ 'edit_themes', 'edit_plugins', 'edit_files' ], true ) ) {
			return [ 'do_not_allow' ];
		}
		return $caps;
	}, 9999, 2 );

	add_action( 'load-theme-editor.php', function () {
		xd_guard_log( 'file_edit', 'Chặn truy cập Trình sửa giao diện' );
		xd_guard_die( 'Trình sửa file giao diện đã bị khóa trên website này.' );
	}, 0 );

	add_action( 'load-plugin-editor.php', function () {
		xd_guard_log( 'file_edit', 'Chặn truy cập Trình sửa plugin' );
		xd_guard_die( 'Trình sửa file plugin đã bị khóa trên website này.' );
	}, 0 );

	add_action( 'wp_ajax_edit-theme-plugin-file', function () {
		xd_guard_log( 'file_edit', 'Chặn AJAX sửa file theme/plugin' );
		wp_send_json_error( [ 'message' => 'Trình sửa file đã bị khóa trên website này.' ], 403 );
	}, 0 );

	add_action( 'admin_menu', function () {
		remove_submenu_page( 'themes.php', 'theme-editor.php' );
		remove_submenu_page( 'plugins.php', 'plugin-editor.php' );
	}, 999 );
}

/* ------------------------------------------------------------------
 * 6. Chặn Công cụ → Xuất dữ liệu (export XML) & tùy chọn Export Elementor/JetEngine
 * ---------------------------------------------------------------- */
add_action( 'load-export.php', function () {
	if ( ! xd_guard_active() ) return;
	xd_guard_log( 'export', 'Chặn trang Xuất dữ liệu' );
	xd_guard_die( 'Chức năng xuất dữ liệu đã bị tắt trên website này.' );
} );

add_action( 'export_wp', function () {
	if ( ! xd_guard_active() ) return;
	xd_guard_log( 'export', 'Chặn export_wp' );
	xd_guard_die( 'Chức năng xuất dữ liệu đã bị tắt trên website này.' );
}, 0 );

add_action( 'admin_menu', function () {
	if ( xd_guard_active() ) remove_submenu_page( 'tools.php', 'export.php' );
}, 999 );

// Tùy chọn chặn xuất Template/Kit Elementor & Skin JetEngine
add_action( 'wp_ajax_elementor_library_direct_actions', function () {
	if ( ! xd_guard_tpl_export_blocked() ) return;
	$la = sanitize_key( $_REQUEST['library_action'] ?? '' );
	if ( strpos( $la, 'export' ) !== false ) {
		xd_guard_log( 'export', 'Chặn xuất mẫu Elementor: ' . $la );
		xd_guard_die( 'Chức năng xuất mẫu Elementor đã bị khóa trên website này.' );
	}
}, 0 );

add_action( 'wp_ajax_elementor_ajax', function () {
	if ( ! xd_guard_tpl_export_blocked() ) return;
	$raw = isset( $_POST['actions'] ) ? wp_unslash( (string) $_POST['actions'] ) : '';
	if ( $raw !== '' && ( strpos( $raw, 'export_template' ) !== false || strpos( $raw, 'export_kit' ) !== false ) ) {
		xd_guard_log( 'export', 'Chặn Elementor AJAX export' );
		wp_send_json_error( [ 'message' => 'Chức năng xuất mẫu Elementor đã bị khóa.' ], 403 );
	}
}, 0 );

add_action( 'wp_ajax_jet_engine_export_skin', function () {
	if ( ! xd_guard_tpl_export_blocked() ) return;
	xd_guard_log( 'export', 'Chặn xuất Skin JetEngine' );
	wp_send_json_error( [ 'message' => 'Chức năng xuất Skin JetEngine đã bị khóa.' ], 403 );
}, 0 );

/* ------------------------------------------------------------------
 * 7. Chặn REST API ẩn danh, chặn REST thao tác Connector/plugin cấm & tắt XML-RPC
 * ---------------------------------------------------------------- */
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( xd_guard_bypass() ) return $result;
	$route  = (string) $request->get_route();
	$method = strtoupper( (string) $request->get_method() );

	// Luôn chặn thao tác tắt/xóa/cài plugin hệ thống hoặc plugin cấm qua REST API (kể cả khi đã đăng nhập)
	if ( strpos( $route, '/wp/v2/plugins' ) === 0 && in_array( $method, [ 'POST', 'PUT', 'PATCH', 'DELETE' ], true ) ) {
		$body_plugin = (string) ( $request->get_param( 'plugin' ) ?? '' );
		$body_slug   = (string) ( $request->get_param( 'slug' ) ?? '' );
		if ( xd_guard_hide_connector() && ( strpos( $route, 'vinhxd-connector' ) !== false || strpos( $body_plugin, 'vinhxd-connector' ) !== false ) ) {
			xd_guard_log( 'tamper', 'Chặn REST API tác động VinhXD Connector (' . $method . ' ' . $route . ')' );
			return new WP_Error( 'xd_guard_forbidden', 'Plugin hệ thống không thể thay đổi.', [ 'status' => 403 ] );
		}
		if ( xd_guard_active() && ( xd_guard_is_blocked( $body_slug ) || xd_guard_is_blocked( $body_plugin ) ) ) {
			xd_guard_log( 'activate', 'Chặn REST API cài/bật plugin cấm: ' . ( $body_slug ?: $body_plugin ) );
			return new WP_Error( 'xd_guard_forbidden', 'Plugin này không được phép trên website.', [ 'status' => 403 ] );
		}
	}

	// Chặn khách ẩn danh cào bài viết / CPT xe / users qua REST API core /wp/v2/*
	if ( xd_guard_rest_anon_blocked() && ! is_user_logged_in() ) {
		if ( preg_match( '#^/wp/v2(/|$)#', $route ) ) {
			xd_guard_log( 'rest', 'Chặn REST API ẩn danh: ' . $route );
			return new WP_Error( 'xd_rest_restricted', 'REST API bị hạn chế đối với truy cập chưa đăng nhập.', [ 'status' => 403 ] );
		}
	}

	return $result;
}, 1, 3 );

if ( xd_guard_rest_anon_blocked() ) {
	add_filter( 'xmlrpc_enabled', '__return_false', 9999 );
	add_filter( 'xmlrpc_methods', '__return_empty_array', 9999 );
	add_filter( 'wp_headers', function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}, 999 );
	add_action( 'init', function () {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			xd_guard_log( 'rest', 'Chặn truy cập XML-RPC' );
			status_header( 403 );
			exit( 'Forbidden' );
		}
	}, 0 );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
}

endif; // XD_GUARD_VERSION
