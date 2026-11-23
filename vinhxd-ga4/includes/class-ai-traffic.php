<?php
/**
 * XD Analytics – Traffic AI (từ 1.15.0)
 *
 * Đếm lượt khách vào web TỪ các trợ lý AI (ChatGPT, Gemini, Perplexity, Claude, Copilot, DeepSeek, Grok...)
 * và (tuỳ chọn) lượt bot AI crawl nội dung (GPTBot, ClaudeBot, PerplexityBot...).
 *
 * CÁCH NHẬN DIỆN (đọc kỹ trước khi diễn giải số liệu – xem thêm README.md):
 *  1. Referrer: trang trước đó (document.referrer / header Referer) có host khớp danh sách nguồn AI.
 *  2. utm_source: link trong câu trả lời AI có gắn ?utm_source=chatgpt.com (ChatGPT, Perplexity... tự thêm).
 *  Không có 2 tín hiệu này (app di động không gửi referrer, khách copy link...) thì KHÔNG thể biết là từ AI
 *  → lượt đó rơi vào "Direct" ở GA4. Google AI Overviews / AI Mode gửi referrer google.com giống hệt
 *  tìm kiếm thường nên KHÔNG tách được (plugin không cố đoán).
 *
 * CÁCH GHI (chạy được cả khi bật cache trang LiteSpeed/WP Rocket):
 *  - JS beacon (assets/js/ai-traffic.js): chạy trên mọi trang kể cả trang lấy từ cache. Chỉ gửi request khi
 *    trình duyệt thấy referrer/utm khớp nguồn AI → khách thường không tốn request nào.
 *  - Fallback phía server (template_redirect): bắt được khi trang KHÔNG lấy từ cache (khách tắt JS/chặn script).
 *  - Hai đường trùng nhau được gộp: cùng visitor + cùng trang + cùng nguồn trong 30 phút = 1 lượt.
 *
 * QUYỀN RIÊNG TƯ: không lưu IP. Chỉ lưu visitor_hash = HMAC(IP + User-Agent + ngày, salt của site), cắt 16 ký tự,
 * đổi mỗi ngày → dùng để gộp trùng và đếm "khách duy nhất trong ngày", không truy ngược được IP.
 *
 * Bảng dữ liệu: {prefix}vinhxd_ai_visits, {prefix}vinhxd_ai_crawls (tạo bằng dbDelta). Cron xoá dữ liệu cũ hằng ngày.
 * Hook mở rộng: filter 'vinhxd_ai_sources' (sửa danh sách nguồn), 'vinhxd_ai_crawlers' (sửa danh sách bot),
 * action 'vinhxd_ai_visit_logged' (chạy sau khi ghi 1 lượt, nhận mảng dữ liệu).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Truy vấn trực tiếp bảng riêng của module. Tên bảng = $wpdb->prefix + hằng cố định (không phải input),
// mọi giá trị từ người dùng đều đi qua $wpdb->prepare(). Không dùng %i để giữ tương thích WP < 6.2.
// Không nonce có chủ đích ở: ajax_hit() (endpoint công khai cho beacon – trang cache giữ nonce hết hạn; thay bằng
// kiểm tra Origin, tính lại nguồn phía server, rate limit, gộp trùng), get_range()/msg (chỉ đọc bộ lọc hiển thị),
// server_track() (đọc utm_source của URL). Mọi thao tác GHI ở admin đều check_admin_referer + manage_options.
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

class Vinhxd_AI_Traffic {

	const DB_VERSION      = '1.0';
	const DB_OPTION       = 'vinhxd_ai_db_version';
	const SETTINGS_OPTION = 'vinhxd_ai_settings';
	const RULES_OPTION    = 'vinhxd_ai_rules';
	const CRON_HOOK       = 'vinhxd_ai_cleanup';
	const PAGE_SLUG       = 'vinhxd-ga4-ai';
	const DEDUPE_MINUTES  = 30;
	const RATE_MAX        = 20;   // tối đa 20 lượt ghi / visitor / 10 phút (chống spam endpoint)
	const RATE_WINDOW     = 600;

	/** @var string hook suffix của trang admin (để chỉ nạp JS/CSS đúng trang) */
	private static $page_hook = '';

	// ============================================================
	// KHỞI TẠO
	// ============================================================

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_beacon' ) );
		add_action( 'template_redirect', array( __CLASS__, 'server_track' ), 1 );
		add_action( 'wp_ajax_vinhxd_ai_hit', array( __CLASS__, 'ajax_hit' ) );
		add_action( 'wp_ajax_nopriv_vinhxd_ai_hit', array( __CLASS__, 'ajax_hit' ) );
		add_action( 'admin_post_vinhxd_ai_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_vinhxd_ai_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_vinhxd_ai_clear', array( __CLASS__, 'handle_clear' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );
	}

	public static function visits_table() {
		global $wpdb;
		return $wpdb->prefix . 'vinhxd_ai_visits';
	}

	public static function crawls_table() {
		global $wpdb;
		return $wpdb->prefix . 'vinhxd_ai_crawls';
	}

	/** Tạo/nâng cấp bảng (chạy 1 lần khi DB_VERSION đổi) + đặt cron dọn dữ liệu */
	public static function maybe_install() {
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::CRON_HOOK );
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$visits  = self::visits_table();
		$crawls  = self::crawls_table();

		// day = ngày theo múi giờ của site (dùng để lọc/gộp theo ngày), created_gmt = thời điểm UTC
		dbDelta( "CREATE TABLE {$visits} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_gmt datetime NOT NULL,
			day date NOT NULL,
			source varchar(40) NOT NULL DEFAULT '',
			signal_type varchar(10) NOT NULL DEFAULT '',
			referrer varchar(255) NOT NULL DEFAULT '',
			referrer_host varchar(100) NOT NULL DEFAULT '',
			utm_source varchar(100) NOT NULL DEFAULT '',
			utm_medium varchar(100) NOT NULL DEFAULT '',
			utm_campaign varchar(100) NOT NULL DEFAULT '',
			landing varchar(255) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			device varchar(10) NOT NULL DEFAULT '',
			visitor_hash char(16) NOT NULL DEFAULT '',
			method varchar(6) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY day_source (day,source),
			KEY visitor (visitor_hash,created_gmt)
		) {$charset};" );

		// Bot AI: gộp theo ngày + bot + đường dẫn (1 dòng / tổ hợp) để bảng không phình
		dbDelta( "CREATE TABLE {$crawls} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			day date NOT NULL,
			bot varchar(40) NOT NULL DEFAULT '',
			path varchar(191) NOT NULL DEFAULT '',
			hits int(10) unsigned NOT NULL DEFAULT 0,
			last_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY day_bot (day,bot),
			KEY day_bot_path (day,bot,path)
		) {$charset};" );

		update_option( self::DB_OPTION, self::DB_VERSION );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	// ============================================================
	// CÀI ĐẶT & DANH SÁCH NGUỒN
	// ============================================================

	public static function get_settings() {
		$defaults = array(
			'enabled'        => '1',  // bật ghi traffic AI
			'exclude_admins' => '1',  // bỏ qua user đăng nhập có quyền edit_posts (admin/editor/author)
			'log_crawlers'   => '1',  // ghi lượt bot AI crawl (chỉ thấy khi request không lấy từ cache)
			'retention_days' => 180,  // giữ dữ liệu bao nhiêu ngày (0 = giữ mãi)
		);
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Danh sách nguồn AI mặc định. Mỗi dòng: key | Tên hiển thị | host/đường dẫn referrer | giá trị utm_source.
	 * - host khớp chính nó và mọi subdomain (chatgpt.com khớp www.chatgpt.com).
	 * - có thể ghi kèm đường dẫn (bing.com/chat) → chỉ khớp khi referrer có path bắt đầu như vậy.
	 * - utm_source so sánh không phân biệt hoa thường, khớp nguyên giá trị.
	 * Sửa trong wp-admin: XD Analytics → Traffic AI → Cài đặt, hoặc filter 'vinhxd_ai_sources'.
	 */
	public static function default_rules_text() {
		return implode( "\n", array(
			'chatgpt | ChatGPT | chatgpt.com, chat.openai.com, searchgpt.com | chatgpt.com, chatgpt, openai, chat.openai.com',
			'gemini | Gemini | gemini.google.com, bard.google.com, aistudio.google.com | gemini, gemini.google.com, bard',
			'perplexity | Perplexity | perplexity.ai, perplexity.com | perplexity, perplexity.ai',
			'claude | Claude | claude.ai, claude.com | claude, claude.ai, anthropic',
			'copilot | Copilot | copilot.microsoft.com, copilot.cloud.microsoft, m365.cloud.microsoft, bing.com/chat, edgeservices.bing.com | copilot, copilot.microsoft.com, bing_chat',
			'deepseek | DeepSeek | deepseek.com, chat.deepseek.com | deepseek, deepseek.com',
			'grok | Grok | grok.com, x.ai, x.com/i/grok | grok, grok.com, x.ai',
			'meta_ai | Meta AI | meta.ai | meta.ai, meta_ai',
			'mistral | Mistral Le Chat | chat.mistral.ai, mistral.ai | mistral, lechat, le_chat',
			'you | You.com | you.com | you.com, you',
			'phind | Phind | phind.com | phind, phind.com',
			'poe | Poe | poe.com | poe, poe.com',
			'qwen | Qwen | chat.qwen.ai, qwen.ai, tongyi.aliyun.com | qwen',
			'kimi | Kimi | kimi.com, kimi.moonshot.cn | kimi',
			'huggingchat | HuggingChat | huggingface.co/chat | huggingchat',
			'pi | Pi | pi.ai | pi.ai',
			'duckai | Duck.ai | duck.ai | duck.ai',
		) );
	}

	/** Đọc text quy tắc → mảng [key => [label, hosts[], utm[]]] */
	public static function parse_rules( $text ) {
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( $line === '' || $line[0] === '#' ) continue;
			$parts = array_map( 'trim', explode( '|', $line ) );
			$key   = sanitize_key( $parts[0] ?? '' );
			if ( $key === '' ) continue;
			$split = function ( $s ) {
				return array_values( array_filter( array_map( function ( $v ) {
					return strtolower( trim( $v ) );
				}, explode( ',', (string) $s ) ) ) );
			};
			$hosts = array();
			foreach ( $split( $parts[2] ?? '' ) as $h ) {
				$h = preg_replace( '#^https?://#', '', $h );
				$h = preg_replace( '#^www\.#', '', $h );
				$h = rtrim( $h, '/' );
				if ( $h !== '' && preg_match( '#^[a-z0-9.\-]+(/[a-z0-9._~\-/]*)?$#', $h ) ) $hosts[] = $h;
			}
			$utms = array();
			foreach ( $split( $parts[3] ?? '' ) as $u ) {
				$u = substr( preg_replace( '/[^a-z0-9._\-]/', '', $u ), 0, 100 );
				if ( $u !== '' ) $utms[] = $u;
			}
			if ( ! $hosts && ! $utms ) continue;
			$label = sanitize_text_field( $parts[1] ?? '' );
			$rules[ substr( $key, 0, 40 ) ] = array(
				'label' => $label !== '' ? $label : $key,
				'hosts' => $hosts,
				'utm'   => $utms,
			);
		}
		return $rules;
	}

	public static function get_rules_text() {
		$text = get_option( self::RULES_OPTION, '' );
		return ( is_string( $text ) && trim( $text ) !== '' ) ? $text : self::default_rules_text();
	}

	public static function get_rules() {
		static $cache = null;
		if ( null === $cache ) {
			$cache = (array) apply_filters( 'vinhxd_ai_sources', self::parse_rules( self::get_rules_text() ) );
		}
		return $cache;
	}

	public static function source_label( $key ) {
		$rules = self::get_rules();
		return isset( $rules[ $key ] ) ? $rules[ $key ]['label'] : $key;
	}

	/**
	 * Bot AI (crawler) – nhận theo User-Agent. Lưu ý Google-Extended KHÔNG phải UA thật (chỉ là token robots.txt),
	 * Google dùng Googlebot để thu thập cho Gemini/AI Overviews nên không tách được.
	 */
	public static function get_crawlers() {
		return (array) apply_filters( 'vinhxd_ai_crawlers', array(
			'gptbot'             => 'GPTBot (OpenAI training)',
			'oai-searchbot'      => 'OAI-SearchBot (ChatGPT search)',
			'chatgpt-user'       => 'ChatGPT-User (đọc link theo yêu cầu)',
			'claudebot'          => 'ClaudeBot',
			'claude-user'        => 'Claude-User',
			'claude-searchbot'   => 'Claude-SearchBot',
			'anthropic-ai'       => 'anthropic-ai',
			'perplexitybot'      => 'PerplexityBot',
			'perplexity-user'    => 'Perplexity-User',
			'google-cloudvertexbot' => 'Google-CloudVertexBot',
			'gemini-deep-research'  => 'Gemini-Deep-Research',
			'bytespider'         => 'Bytespider (ByteDance)',
			'ccbot'              => 'CCBot (Common Crawl)',
			'meta-externalagent' => 'Meta-ExternalAgent',
			'meta-externalfetcher' => 'Meta-ExternalFetcher',
			'amazonbot'          => 'Amazonbot',
			'applebot-extended'  => 'Applebot-Extended',
			'duckassistbot'      => 'DuckAssistBot',
			'mistralai-user'     => 'MistralAI-User',
			'cohere-ai'          => 'cohere-ai',
			'youbot'             => 'YouBot',
			'deepseekbot'        => 'DeepSeekBot',
			'xai-'               => 'xAI / Grok',
			'grokbot'            => 'GrokBot',
		) );
	}

	// ============================================================
	// NHẬN DIỆN
	// ============================================================

	/** Chuẩn hoá host: bỏ www., chữ thường */
	private static function norm_host( $host ) {
		$host = strtolower( trim( (string) $host ) );
		return strpos( $host, 'www.' ) === 0 ? substr( $host, 4 ) : $host;
	}

	/**
	 * Xác định nguồn AI từ referrer + utm_source. Trả về ['source' => key, 'signal' => 'referrer'|'utm'] hoặc null.
	 * Referrer ưu tiên hơn utm (utm có thể bị copy đi chỗ khác).
	 */
	public static function detect( $referrer, $utm_source ) {
		$rules = self::get_rules();
		$host  = '';
		$path  = '';
		if ( $referrer !== '' ) {
			$parts = wp_parse_url( $referrer );
			$host  = isset( $parts['host'] ) ? self::norm_host( $parts['host'] ) : '';
			$path  = isset( $parts['path'] ) ? strtolower( $parts['path'] ) : '';
		}
		if ( $host !== '' && $host !== self::norm_host( wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			foreach ( $rules as $key => $rule ) {
				foreach ( $rule['hosts'] as $pattern ) {
					$slash = strpos( $pattern, '/' );
					$p_host = $slash === false ? $pattern : substr( $pattern, 0, $slash );
					$p_path = $slash === false ? '' : substr( $pattern, $slash );
					$host_ok = ( $host === $p_host ) || ( substr( $host, -strlen( '.' . $p_host ) ) === '.' . $p_host );
					if ( ! $host_ok ) continue;
					if ( $p_path !== '' && strpos( $path, $p_path ) !== 0 ) continue;
					return array( 'source' => $key, 'signal' => 'referrer' );
				}
			}
		}
		$utm = strtolower( trim( (string) $utm_source ) );
		if ( $utm !== '' ) {
			foreach ( $rules as $key => $rule ) {
				if ( in_array( $utm, $rule['utm'], true ) ) {
					return array( 'source' => $key, 'signal' => 'utm' );
				}
			}
		}
		return null;
	}

	/** Có nên bỏ qua request này không (bot, admin, prefetch...) */
	private static function should_skip() {
		$s = self::get_settings();
		if ( $s['enabled'] !== '1' ) return true;
		if ( $s['exclude_admins'] === '1' && is_user_logged_in() && current_user_can( 'edit_posts' ) ) return true;

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		if ( $ua === '' ) return true;
		if ( self::match_crawler( $ua ) ) return true;
		// Dùng bộ lọc bot chung của XD Analytics (UA bot, dải IP Google AdsBot, IP loại trừ thủ công)
		if ( class_exists( 'Vinhxd_GA4_Internal_Tracking' ) && Vinhxd_GA4_Internal_Tracking::is_bot() ) return true;
		// Bộ lọc tối thiểu khi admin tắt "Loại trừ bot" ở Cài đặt chung
		if ( preg_match( '/bot|crawl|spider|slurp|curl|wget|python|headless|phantom|preview/', $ua ) ) return true;
		return false;
	}

	private static function match_crawler( $ua_lower ) {
		foreach ( self::get_crawlers() as $needle => $label ) {
			if ( strpos( $ua_lower, (string) $needle ) !== false ) return $needle;
		}
		return '';
	}

	private static function visitor_hash() {
		$ip = class_exists( 'Vinhxd_GA4_Internal_Tracking' ) ? Vinhxd_GA4_Internal_Tracking::get_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return substr( hash_hmac( 'sha256', $ip . '|' . $ua . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) ), 0, 16 );
	}

	private static function device_from_ua( $ua ) {
		$ua = strtolower( $ua );
		if ( preg_match( '/ipad|tablet/', $ua ) ) return 'tablet';
		if ( preg_match( '/mobi|android|iphone/', $ua ) ) return 'mobile';
		return 'desktop';
	}

	/** Chỉ giữ path + query của URL thuộc chính site (bỏ fbclid/gclid dài cho gọn). Trả '' nếu khác domain. */
	private static function clean_landing( $url ) {
		$url   = esc_url_raw( (string) $url );
		$parts = wp_parse_url( $url );
		if ( ! $parts || empty( $parts['host'] ) ) return '';
		if ( self::norm_host( $parts['host'] ) !== self::norm_host( wp_parse_url( home_url(), PHP_URL_HOST ) ) ) return '';
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $q );
			$q = array_diff_key( (array) $q, array_flip( array( 'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', '_ga', '_gl' ) ) );
			if ( $q ) $path .= '?' . http_build_query( $q );
		}
		return substr( $path, 0, 255 );
	}

	private static function utm_from_url( $url, $param ) {
		$query = wp_parse_url( (string) $url, PHP_URL_QUERY );
		if ( ! $query ) return '';
		parse_str( $query, $q );
		return isset( $q[ $param ] ) && is_string( $q[ $param ] ) ? substr( sanitize_text_field( $q[ $param ] ), 0, 100 ) : '';
	}

	// ============================================================
	// GHI DỮ LIỆU
	// ============================================================

	/**
	 * Ghi 1 lượt AI. $referrer: URL trang trước; $landing_url: URL đầy đủ trang khách vào; $method: js|server.
	 * Trả về: 'logged' | 'duplicate' | 'not_ai' | 'invalid' | 'limited'.
	 */
	public static function record( $referrer, $landing_url, $method ) {
		global $wpdb;

		$referrer = substr( esc_url_raw( (string) $referrer ), 0, 1000 );
		$landing  = self::clean_landing( $landing_url );
		if ( $landing === '' ) return 'invalid';

		$utm_source = self::utm_from_url( $landing_url, 'utm_source' );
		$hit        = self::detect( $referrer, $utm_source );
		if ( ! $hit ) return 'not_ai';

		$hash = self::visitor_hash();

		// Chống spam: mỗi visitor tối đa RATE_MAX lượt ghi / 10 phút
		$rl_key = 'vinhxd_ai_rl_' . $hash;
		$count  = (int) get_transient( $rl_key );
		if ( $count >= self::RATE_MAX ) return 'limited';
		set_transient( $rl_key, $count + 1, self::RATE_WINDOW );

		$table = self::visits_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - self::DEDUPE_MINUTES * 60 );
		// Gộp trùng: JS beacon + server fallback cùng chạy trên trang không cache, hoặc khách F5
		$dup = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE visitor_hash = %s AND created_gmt >= %s AND landing = %s AND source = %s LIMIT 1",
			$hash, $since, $landing, $hit['source']
		) );
		if ( $dup ) return 'duplicate';

		$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
		$ref_host = $referrer !== '' ? self::norm_host( (string) wp_parse_url( $referrer, PHP_URL_HOST ) ) : '';
		// Referrer: chỉ giữ scheme://host/path (bỏ query – có thể chứa nội dung câu hỏi của khách)
		$ref_short = '';
		if ( $referrer !== '' ) {
			$rp        = wp_parse_url( $referrer );
			$ref_short = ( $rp['scheme'] ?? 'https' ) . '://' . ( $rp['host'] ?? '' ) . ( $rp['path'] ?? '' );
		}

		$row = array(
			'created_gmt'   => current_time( 'mysql', true ),
			'day'           => current_time( 'Y-m-d' ),
			'source'        => $hit['source'],
			'signal_type'   => $hit['signal'],
			'referrer'      => substr( $ref_short, 0, 255 ),
			'referrer_host' => substr( $ref_host, 0, 100 ),
			'utm_source'    => $utm_source,
			'utm_medium'    => self::utm_from_url( $landing_url, 'utm_medium' ),
			'utm_campaign'  => self::utm_from_url( $landing_url, 'utm_campaign' ),
			'landing'       => $landing,
			'user_agent'    => $ua,
			'device'        => self::device_from_ua( $ua ),
			'visitor_hash'  => $hash,
			'method'        => $method === 'server' ? 'server' : 'js',
		);
		$ok = $wpdb->insert( $table, $row );
		if ( ! $ok ) return 'invalid';

		do_action( 'vinhxd_ai_visit_logged', $row );
		return 'logged';
	}

	/** Ghi lượt bot AI (gộp theo ngày/bot/path) */
	public static function record_crawl( $bot, $path ) {
		global $wpdb;
		$table = self::crawls_table();
		$day   = current_time( 'Y-m-d' );
		$path  = substr( (string) $path, 0, 191 );
		$now   = current_time( 'mysql', true );
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE day = %s AND bot = %s AND path = %s LIMIT 1", $day, $bot, $path ) );
		if ( $id ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1, last_gmt = %s WHERE id = %d", $now, $id ) );
		} else {
			$wpdb->insert( $table, array( 'day' => $day, 'bot' => $bot, 'path' => $path, 'hits' => 1, 'last_gmt' => $now ) );
		}
	}

	/** Fallback phía server – chỉ chạy khi trang không lấy từ cache */
	public static function server_track() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) return;
		if ( is_feed() || is_robots() || is_trackback() ) return;

		$s  = self::get_settings();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		// 1) Bot AI crawl
		if ( $s['log_crawlers'] === '1' && $ua !== '' ) {
			$bot = self::match_crawler( $ua );
			if ( $bot ) {
				self::record_crawl( $bot, (string) wp_parse_url( $req_uri, PHP_URL_PATH ) );
				return;
			}
		}

		// 2) Khách người thật từ AI
		if ( is_404() ) return;
		// Bỏ qua prefetch/prerender (trình duyệt tải trước, khách chưa chắc vào)
		$purpose = isset( $_SERVER['HTTP_SEC_PURPOSE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_PURPOSE'] ) ) : ( isset( $_SERVER['HTTP_PURPOSE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PURPOSE'] ) ) : '' );
		if ( $purpose !== '' && stripos( $purpose, 'prefetch' ) !== false ) return;

		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$has_utm  = isset( $_GET['utm_source'] );
		if ( $referrer === '' && ! $has_utm ) return; // đường nhanh: không có tín hiệu → không làm gì
		if ( self::should_skip() ) return;

		self::record( $referrer, self::site_origin() . $req_uri, 'server' );
	}

	/**
	 * Endpoint nhận beacon từ JS. Không dùng nonce (trang cache sẽ giữ nonce hết hạn → mất dữ liệu), thay vào đó:
	 * nguồn được TÍNH LẠI phía server từ referrer/utm (không tin tham số "source" nào từ client), landing phải
	 * thuộc chính domain, lọc bot/admin, giới hạn tần suất theo visitor, gộp trùng 30 phút.
	 */
	public static function ajax_hit() {
		$ref = isset( $_POST['ref'] ) ? substr( esc_url_raw( wp_unslash( $_POST['ref'] ) ), 0, 1000 ) : '';
		$url = isset( $_POST['url'] ) ? substr( esc_url_raw( wp_unslash( $_POST['url'] ) ), 0, 1000 ) : '';

		// Request phải xuất phát từ chính site (Origin/Referer header nếu trình duyệt có gửi)
		$self   = self::norm_host( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( $origin !== '' && $origin !== 'null' && self::norm_host( wp_parse_url( $origin, PHP_URL_HOST ) ) !== $self ) {
			wp_send_json( array( 'r' => 'invalid' ), 403 );
		}

		if ( self::should_skip() ) wp_send_json( array( 'r' => 'skip' ) );
		wp_send_json( array( 'r' => self::record( $ref, $url, 'js' ) ) );
	}

	/** Nạp beacon (~1KB) ở frontend, truyền danh sách host/utm để JS tự lọc trước khi gửi */
	public static function enqueue_beacon() {
		$s = self::get_settings();
		if ( $s['enabled'] !== '1' ) return;
		// Không in theo user vì trang cache dùng chung cho mọi người – lọc admin làm phía server.
		$hosts = array();
		$utms  = array();
		foreach ( self::get_rules() as $rule ) {
			$hosts = array_merge( $hosts, $rule['hosts'] );
			$utms  = array_merge( $utms, $rule['utm'] );
		}
		wp_enqueue_script( 'vinhxd-ai-traffic', VINHXD_GA4_URL . 'assets/js/ai-traffic.js', array(), VINHXD_GA4_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script( 'vinhxd-ai-traffic', 'vinhxdAI', array(
			'u' => admin_url( 'admin-ajax.php' ),
			'h' => array_values( array_unique( $hosts ) ),
			's' => array_values( array_unique( $utms ) ),
		) );
	}

	/** Cron: xoá dữ liệu cũ hơn retention_days */
	public static function cleanup() {
		global $wpdb;
		$days = (int) self::get_settings()['retention_days'];
		if ( $days <= 0 ) return;
		$cut = gmdate( 'Y-m-d', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$v   = self::visits_table();
		$c   = self::crawls_table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$v} WHERE day < %s", $cut ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$c} WHERE day < %s", $cut ) );
	}

	// ============================================================
	// TRUY VẤN BÁO CÁO
	// ============================================================

	/** Khoảng ngày từ query string (mặc định 30 ngày). Trả [from, to, key]. */
	public static function get_range() {
		$key   = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '30';
		$today = current_time( 'Y-m-d' );
		if ( $key === 'custom' ) {
			$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
			$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
			$valid = function ( $d ) {
				return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && strtotime( $d );
			};
			if ( $valid( $from ) && $valid( $to ) ) {
				if ( $from > $to ) list( $from, $to ) = array( $to, $from );
				return array( $from, $to, 'custom' );
			}
			$key = '30';
		}
		$days = in_array( $key, array( '1', '7', '30', '90', '365' ), true ) ? (int) $key : 30;
		$from = gmdate( 'Y-m-d', strtotime( $today . ' -' . ( $days - 1 ) . ' days' ) );
		return array( $from, $today, (string) $days );
	}

	public static function query_report( $from, $to ) {
		global $wpdb;
		$v = self::visits_table();
		$c = self::crawls_table();
		$out = array();
		$out['total']   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$v} WHERE day BETWEEN %s AND %s", $from, $to ) );
		$out['uniques'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_hash) FROM {$v} WHERE day BETWEEN %s AND %s", $from, $to ) );
		$out['by_source'] = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) AS visits, COUNT(DISTINCT visitor_hash) AS uniques FROM {$v} WHERE day BETWEEN %s AND %s GROUP BY source ORDER BY visits DESC", $from, $to ), ARRAY_A );
		$out['by_day']  = $wpdb->get_results( $wpdb->prepare( "SELECT day, source, COUNT(*) AS visits FROM {$v} WHERE day BETWEEN %s AND %s GROUP BY day, source ORDER BY day ASC", $from, $to ), ARRAY_A );
		$out['pages']   = $wpdb->get_results( $wpdb->prepare( "SELECT landing, COUNT(*) AS visits, COUNT(DISTINCT source) AS sources FROM {$v} WHERE day BETWEEN %s AND %s GROUP BY landing ORDER BY visits DESC LIMIT 20", $from, $to ), ARRAY_A );
		$out['page_src'] = $wpdb->get_results( $wpdb->prepare( "SELECT landing, source, COUNT(*) AS visits FROM {$v} WHERE day BETWEEN %s AND %s GROUP BY landing, source", $from, $to ), ARRAY_A );
		$out['signals'] = $wpdb->get_results( $wpdb->prepare( "SELECT signal_type, method, COUNT(*) AS visits FROM {$v} WHERE day BETWEEN %s AND %s GROUP BY signal_type, method", $from, $to ), ARRAY_A );
		$out['devices'] = $wpdb->get_results( $wpdb->prepare( "SELECT device, COUNT(*) AS visits FROM {$v} WHERE day BETWEEN %s AND %s GROUP BY device ORDER BY visits DESC", $from, $to ), ARRAY_A );
		$out['recent']  = $wpdb->get_results( $wpdb->prepare( "SELECT created_gmt, source, signal_type, referrer, utm_source, landing, device, user_agent, method FROM {$v} WHERE day BETWEEN %s AND %s ORDER BY id DESC LIMIT 50", $from, $to ), ARRAY_A );
		$out['crawl_total'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(hits),0) FROM {$c} WHERE day BETWEEN %s AND %s", $from, $to ) );
		$out['crawls']  = $wpdb->get_results( $wpdb->prepare( "SELECT bot, SUM(hits) AS hits, COUNT(DISTINCT path) AS paths, MAX(last_gmt) AS last_gmt FROM {$c} WHERE day BETWEEN %s AND %s GROUP BY bot ORDER BY hits DESC", $from, $to ), ARRAY_A );
		$out['crawl_pages'] = $wpdb->get_results( $wpdb->prepare( "SELECT path, SUM(hits) AS hits FROM {$c} WHERE day BETWEEN %s AND %s GROUP BY path ORDER BY hits DESC LIMIT 10", $from, $to ), ARRAY_A );
		return $out;
	}

	// ============================================================
	// TRANG ADMIN
	// ============================================================

	public static function admin_menu() {
		self::$page_hook = add_submenu_page( 'vinhxd-ga4-dashboard', 'Traffic AI', 'Traffic AI', 'manage_options', self::PAGE_SLUG, array( __CLASS__, 'render_page' ) );
	}

	public static function admin_assets( $hook ) {
		if ( ! self::$page_hook || $hook !== self::$page_hook ) return;
		wp_enqueue_script( 'vinhxd-chartjs', VINHXD_GA4_URL . 'assets/js/vendor/chart.umd.js', array(), '4.4.4', true );
		wp_enqueue_script( 'vinhxd-ai-admin', VINHXD_GA4_URL . 'assets/js/ai-traffic-admin.js', array( 'vinhxd-chartjs' ), VINHXD_GA4_VERSION, true );
	}

	/** scheme://host[:port] của site (không kèm thư mục con – landing lưu path đầy đủ từ gốc domain) */
	public static function site_origin() {
		$p = wp_parse_url( home_url() );
		return ( $p['scheme'] ?? 'https' ) . '://' . ( $p['host'] ?? '' ) . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
	}

	private static function fmt_time( $gmt ) {
		return $gmt ? get_date_from_gmt( $gmt, 'd/m/Y H:i' ) : '';
	}

	private static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		list( $from, $to, $key ) = self::get_range();
		$r        = self::query_report( $from, $to );
		$settings = self::get_settings();
		$palette  = array( '#10a37f', '#4285f4', '#20808d', '#d97757', '#0078d4', '#4d6bfe', '#111111', '#0866ff', '#fa520f', '#7c3aed', '#e11d48', '#a16207', '#0891b2', '#65a30d', '#f59e0b', '#64748b', '#db2777' );

		// Dữ liệu biểu đồ: mỗi nguồn 1 dataset, trục X = từng ngày trong khoảng
		$labels = array();
		$cursor = strtotime( $from );
		$end    = strtotime( $to );
		$guard  = 0;
		while ( $cursor <= $end && $guard++ < 400 ) {
			$labels[] = gmdate( 'Y-m-d', $cursor );
			$cursor  += DAY_IN_SECONDS;
		}
		$series = array();
		foreach ( $r['by_day'] as $row ) {
			$series[ $row['source'] ][ $row['day'] ] = (int) $row['visits'];
		}
		$datasets = array();
		$i        = 0;
		foreach ( $r['by_source'] as $row ) {
			$src  = $row['source'];
			$data = array();
			foreach ( $labels as $d ) $data[] = $series[ $src ][ $d ] ?? 0;
			$datasets[] = array( 'label' => self::source_label( $src ), 'data' => $data, 'backgroundColor' => $palette[ $i % count( $palette ) ] );
			$i++;
		}
		$chart = array(
			'labels'   => array_map( function ( $d ) { return gmdate( 'd/m', strtotime( $d ) ); }, $labels ),
			'datasets' => $datasets,
		);

		$page_src = array();
		foreach ( $r['page_src'] as $row ) {
			$page_src[ $row['landing'] ][] = self::source_label( $row['source'] ) . ' ' . (int) $row['visits'];
		}
		$top_source = $r['by_source'] ? self::source_label( $r['by_source'][0]['source'] ) : '—';
		$sig        = array( 'referrer' => 0, 'utm' => 0, 'js' => 0, 'server' => 0 );
		foreach ( $r['signals'] as $row ) {
			$sig[ $row['signal_type'] ] = ( $sig[ $row['signal_type'] ] ?? 0 ) + (int) $row['visits'];
			$sig[ $row['method'] ]      = ( $sig[ $row['method'] ] ?? 0 ) + (int) $row['visits'];
		}
		$crawlers = self::get_crawlers();
		$notice = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		?>
		<div class="wrap vxd-ai">
			<h1>XD Analytics – Traffic từ AI</h1>
			<style>
				.vxd-ai .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; margin:16px 0; }
				.vxd-ai .card2 { background:#fff; border:1px solid #dcdcde; border-radius:6px; padding:14px 16px; }
				.vxd-ai .card2 .lb { color:#646970; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
				.vxd-ai .card2 .vl { font-size:26px; font-weight:700; margin-top:4px; }
				.vxd-ai .card2 .sub { color:#646970; font-size:12px; margin-top:2px; }
				.vxd-ai .box { background:#fff; border:1px solid #dcdcde; border-radius:6px; padding:14px 16px; margin:0 0 16px; }
				.vxd-ai .box h2 { margin:0 0 10px; font-size:15px; }
				.vxd-ai .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
				@media (max-width:1100px){ .vxd-ai .grid2 { grid-template-columns:1fr; } }
				.vxd-ai table.widefat td, .vxd-ai table.widefat th { padding:6px 8px; }
				.vxd-ai .muted { color:#8c8f94; }
				.vxd-ai .ua { max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:inline-block; vertical-align:bottom; }
				.vxd-ai .filters { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
				.vxd-ai .bar { background:#e9f2fb; height:8px; border-radius:4px; }
				.vxd-ai .bar span { display:block; background:#2271b1; height:8px; border-radius:4px; }
				.vxd-ai .note { background:#fcf9e8; border-left:4px solid #dba617; padding:10px 14px; margin:12px 0; }
			</style>

			<?php if ( $notice === 'saved' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt Traffic AI.</p></div>
			<?php elseif ( $notice === 'cleared' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Đã xoá toàn bộ dữ liệu Traffic AI.</p></div>
			<?php endif; ?>

			<form method="get" class="filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<select name="range" id="vxd-ai-range">
					<?php
					foreach ( array( '1' => 'Hôm nay', '7' => '7 ngày', '30' => '30 ngày', '90' => '90 ngày', '365' => '365 ngày', 'custom' => 'Tuỳ chọn…' ) as $k => $label ) {
						printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $key, (string) $k, false ), esc_html( $label ) );
					}
					?>
				</select>
				<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
				<span>→</span>
				<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
				<button class="button">Xem</button>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'vinhxd_ai_export', 'from' => $from, 'to' => $to ), admin_url( 'admin-post.php' ) ), 'vinhxd_ai_export' ) ); ?>">Tải CSV</a>
				<span class="muted">Dữ liệu từ <?php echo esc_html( gmdate( 'd/m/Y', strtotime( $from ) ) ); ?> đến <?php echo esc_html( gmdate( 'd/m/Y', strtotime( $to ) ) ); ?></span>
			</form>

			<div class="cards">
				<div class="card2"><div class="lb">Lượt vào từ AI</div><div class="vl"><?php echo esc_html( number_format_i18n( $r['total'] ) ); ?></div><div class="sub">đã gộp trùng 30 phút</div></div>
				<div class="card2"><div class="lb">Khách (ước tính)</div><div class="vl"><?php echo esc_html( number_format_i18n( $r['uniques'] ) ); ?></div><div class="sub">IP+trình duyệt, đếm lại mỗi ngày</div></div>
				<div class="card2"><div class="lb">Nguồn AI lớn nhất</div><div class="vl" style="font-size:20px"><?php echo esc_html( $top_source ); ?></div><div class="sub"><?php echo esc_html( count( $r['by_source'] ) ); ?> nguồn có traffic</div></div>
				<div class="card2"><div class="lb">Bot AI crawl</div><div class="vl"><?php echo esc_html( number_format_i18n( $r['crawl_total'] ) ); ?></div><div class="sub"><?php echo $settings['log_crawlers'] === '1' ? 'chỉ request không qua cache' : 'đang tắt'; ?></div></div>
			</div>

			<div class="box">
				<h2>Theo ngày</h2>
				<?php if ( $r['total'] ) : ?>
					<div style="position:relative;height:280px"><canvas id="vxd-ai-chart" data-chart="<?php echo esc_attr( wp_json_encode( $chart ) ); ?>"></canvas></div>
				<?php else : ?>
					<p class="muted">Chưa có lượt nào từ AI trong khoảng này.</p>
				<?php endif; ?>
			</div>

			<div class="grid2">
				<div class="box">
					<h2>Theo nguồn AI</h2>
					<table class="widefat striped">
						<thead><tr><th>Nguồn</th><th>Lượt</th><th>Khách</th><th style="width:35%">Tỷ trọng</th></tr></thead>
						<tbody>
						<?php if ( ! $r['by_source'] ) : ?>
							<tr><td colspan="4" class="muted">Chưa có dữ liệu.</td></tr>
						<?php endif; ?>
						<?php foreach ( $r['by_source'] as $row ) : $pct = $r['total'] ? round( 100 * $row['visits'] / $r['total'], 1 ) : 0; ?>
							<tr>
								<td><strong><?php echo esc_html( self::source_label( $row['source'] ) ); ?></strong></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['visits'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['uniques'] ) ); ?></td>
								<td><div class="bar"><span style="width:<?php echo esc_attr( $pct ); ?>%"></span></div><span class="muted"><?php echo esc_html( $pct ); ?>%</span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="muted" style="margin-top:8px">
						Nhận diện qua: referrer <?php echo esc_html( number_format_i18n( $sig['referrer'] ) ); ?> · utm_source <?php echo esc_html( number_format_i18n( $sig['utm'] ) ); ?>
						&nbsp;|&nbsp; Ghi bằng: JS <?php echo esc_html( number_format_i18n( $sig['js'] ) ); ?> · server <?php echo esc_html( number_format_i18n( $sig['server'] ) ); ?>
						<?php
						if ( $r['devices'] ) {
							echo '&nbsp;|&nbsp; Thiết bị: ';
							echo esc_html( implode( ' · ', array_map( function ( $d ) { return $d['device'] . ' ' . number_format_i18n( (int) $d['visits'] ); }, $r['devices'] ) ) );
						}
						?>
					</p>
				</div>

				<div class="box">
					<h2>Trang đích được AI dẫn về nhiều nhất</h2>
					<table class="widefat striped">
						<thead><tr><th>Trang</th><th>Lượt</th><th>Nguồn</th></tr></thead>
						<tbody>
						<?php if ( ! $r['pages'] ) : ?>
							<tr><td colspan="3" class="muted">Chưa có dữ liệu.</td></tr>
						<?php endif; ?>
						<?php foreach ( $r['pages'] as $row ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( self::site_origin() . $row['landing'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['landing'] ); ?></a></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['visits'] ) ); ?></td>
								<td class="muted"><?php echo esc_html( implode( ', ', $page_src[ $row['landing'] ] ?? array() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="box">
				<h2>50 lượt gần nhất</h2>
				<table class="widefat striped">
					<thead><tr><th>Thời gian</th><th>Nguồn</th><th>Tín hiệu</th><th>Referrer / utm</th><th>Trang đích</th><th>Thiết bị</th><th>User-Agent</th></tr></thead>
					<tbody>
					<?php if ( ! $r['recent'] ) : ?>
						<tr><td colspan="7" class="muted">Chưa có dữ liệu.</td></tr>
					<?php endif; ?>
					<?php foreach ( $r['recent'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( self::fmt_time( $row['created_gmt'] ) ); ?></td>
							<td><strong><?php echo esc_html( self::source_label( $row['source'] ) ); ?></strong></td>
							<td class="muted"><?php echo esc_html( $row['signal_type'] . ' / ' . $row['method'] ); ?></td>
							<td><?php echo esc_html( $row['referrer'] !== '' ? $row['referrer'] : '—' ); ?><?php echo $row['utm_source'] !== '' ? '<br><span class="muted">utm_source=' . esc_html( $row['utm_source'] ) . '</span>' : ''; ?></td>
							<td><?php echo esc_html( $row['landing'] ); ?></td>
							<td><?php echo esc_html( $row['device'] ); ?></td>
							<td><span class="ua" title="<?php echo esc_attr( $row['user_agent'] ); ?>"><?php echo esc_html( $row['user_agent'] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="grid2">
				<div class="box">
					<h2>Bot AI crawl nội dung</h2>
					<table class="widefat striped">
						<thead><tr><th>Bot</th><th>Lượt</th><th>Số URL</th><th>Lần cuối</th></tr></thead>
						<tbody>
						<?php if ( ! $r['crawls'] ) : ?>
							<tr><td colspan="4" class="muted">Chưa ghi nhận (hoặc bot chỉ đọc trang cache).</td></tr>
						<?php endif; ?>
						<?php foreach ( $r['crawls'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $crawlers[ $row['bot'] ] ?? $row['bot'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['paths'] ) ); ?></td>
								<td><?php echo esc_html( self::fmt_time( $row['last_gmt'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( $r['crawl_pages'] ) : ?>
						<p class="muted" style="margin:10px 0 4px">URL bị bot AI đọc nhiều nhất:</p>
						<ol style="margin-top:0">
							<?php foreach ( $r['crawl_pages'] as $row ) : ?>
								<li><?php echo esc_html( $row['path'] ); ?> <span class="muted">(<?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?>)</span></li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</div>

				<div class="box">
					<h2>Đọc số liệu thế nào</h2>
					<ul style="list-style:disc;padding-left:18px;margin:0">
						<li>Chỉ đếm lượt có <strong>referrer</strong> từ trang AI hoặc link gắn <strong>utm_source</strong> của AI. App di động (ChatGPT, Gemini app…) thường không gửi referrer → lượt đó thành <em>Direct</em> ở GA4 và không có ở đây. Số thật luôn <strong>cao hơn</strong> số hiển thị.</li>
						<li><strong>Google AI Overviews / AI Mode</strong>: Google gửi referrer google.com giống tìm kiếm thường → không tách được, đang nằm trong Organic Search. Xem Search Console để ước lượng.</li>
						<li>Khách chặn JavaScript và trang lấy từ cache thì không ghi được.</li>
						<li>Bot AI chỉ được đếm khi request đi tới PHP (không lấy từ cache LiteSpeed/CDN) → con số là <em>mức tối thiểu</em>.</li>
						<li>Không lưu IP; "Khách" là ước tính theo IP+trình duyệt, đổi mỗi ngày.</li>
					</ul>
				</div>
			</div>

			<div class="box">
				<h2>Cài đặt</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="vinhxd_ai_save">
					<?php wp_nonce_field( 'vinhxd_ai_save' ); ?>
					<table class="form-table" role="presentation">
						<tr><th scope="row">Ghi traffic AI</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], '1' ); ?>> Bật</label></td></tr>
						<tr><th scope="row">Bỏ qua người quản trị</th><td><label><input type="checkbox" name="exclude_admins" value="1" <?php checked( $settings['exclude_admins'], '1' ); ?>> Không đếm user đăng nhập có quyền viết bài (admin/editor/author)</label></td></tr>
						<tr><th scope="row">Ghi bot AI crawl</th><td><label><input type="checkbox" name="log_crawlers" value="1" <?php checked( $settings['log_crawlers'], '1' ); ?>> GPTBot, ClaudeBot, PerplexityBot…</label></td></tr>
						<tr><th scope="row"><label for="vxd-ai-ret">Giữ dữ liệu</label></th><td><input id="vxd-ai-ret" type="number" min="0" max="3650" name="retention_days" value="<?php echo esc_attr( (int) $settings['retention_days'] ); ?>" class="small-text"> ngày <span class="muted">(0 = giữ mãi; dữ liệu cũ hơn được xoá tự động mỗi ngày)</span></td></tr>
						<tr>
							<th scope="row"><label for="vxd-ai-rules">Danh sách nguồn AI</label></th>
							<td>
								<textarea id="vxd-ai-rules" name="rules" rows="12" class="large-text code"><?php echo esc_textarea( self::get_rules_text() ); ?></textarea>
								<p class="description">Mỗi dòng: <code>key | Tên hiển thị | host1, host2/duong-dan | utm1, utm2</code>. Host khớp cả subdomain. Dòng bắt đầu bằng # là ghi chú. Đổi <code>key</code> của nguồn cũ sẽ tách số liệu cũ ra riêng.</p>
								<label><input type="checkbox" name="reset_rules" value="1"> Khôi phục danh sách mặc định</label>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Lưu cài đặt' ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Xoá toàn bộ dữ liệu Traffic AI? Không khôi phục được.');">
					<input type="hidden" name="action" value="vinhxd_ai_clear">
					<?php wp_nonce_field( 'vinhxd_ai_clear' ); ?>
					<button class="button button-link-delete">Xoá toàn bộ dữ liệu Traffic AI</button>
				</form>
			</div>
		</div>
		<?php
	}

	// ============================================================
	// XỬ LÝ FORM ADMIN
	// ============================================================

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Không có quyền.' );
		check_admin_referer( 'vinhxd_ai_save' );

		update_option( self::SETTINGS_OPTION, array(
			'enabled'        => isset( $_POST['enabled'] ) ? '1' : '0',
			'exclude_admins' => isset( $_POST['exclude_admins'] ) ? '1' : '0',
			'log_crawlers'   => isset( $_POST['log_crawlers'] ) ? '1' : '0',
			'retention_days' => max( 0, min( 3650, isset( $_POST['retention_days'] ) ? absint( $_POST['retention_days'] ) : 180 ) ),
		) );

		if ( ! empty( $_POST['reset_rules'] ) ) {
			delete_option( self::RULES_OPTION );
		} elseif ( isset( $_POST['rules'] ) ) {
			$text = sanitize_textarea_field( wp_unslash( $_POST['rules'] ) );
			// Chỉ lưu nếu còn ít nhất 1 dòng hợp lệ, tránh lỡ tay xoá trắng làm mất nhận diện
			if ( self::parse_rules( $text ) ) {
				update_option( self::RULES_OPTION, $text === self::default_rules_text() ? '' : $text );
			}
		}

		wp_safe_redirect( self::page_url( array( 'msg' => 'saved' ) ) );
		exit;
	}

	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Không có quyền.' );
		check_admin_referer( 'vinhxd_ai_clear' );
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . self::visits_table() );
		$wpdb->query( 'DELETE FROM ' . self::crawls_table() );
		wp_safe_redirect( self::page_url( array( 'msg' => 'cleared' ) ) );
		exit;
	}

	/** Một ô CSV an toàn: chặn công thức Excel (=,+,-,@), bỏ xuống dòng */
	private static function csv_cell( $value ) {
		$value = str_replace( array( "\r", "\n" ), ' ', (string) $value );
		if ( $value !== '' && in_array( $value[0], array( '=', '+', '-', '@', "\t" ), true ) ) $value = "'" . $value;
		return $value;
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Không có quyền.' );
		check_admin_referer( 'vinhxd_ai_export' );
		$_GET['range'] = 'custom';
		list( $from, $to ) = self::get_range();

		global $wpdb;
		$v = self::visits_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT created_gmt, day, source, signal_type, method, referrer, referrer_host, utm_source, utm_medium, utm_campaign, landing, device, user_agent, visitor_hash FROM {$v} WHERE day BETWEEN %s AND %s ORDER BY id ASC LIMIT 100000", $from, $to ), ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="xd-ai-traffic-' . $from . '_' . $to . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM để Excel đọc đúng tiếng Việt
		fputcsv( $out, array( 'time_local', 'day', 'source', 'source_label', 'signal', 'method', 'referrer', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'landing', 'device', 'user_agent', 'visitor_hash' ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), array(
				get_date_from_gmt( $row['created_gmt'], 'Y-m-d H:i:s' ), $row['day'], $row['source'], self::source_label( $row['source'] ),
				$row['signal_type'], $row['method'], $row['referrer'], $row['referrer_host'], $row['utm_source'], $row['utm_medium'],
				$row['utm_campaign'], $row['landing'], $row['device'], $row['user_agent'], $row['visitor_hash'],
			) ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}

Vinhxd_AI_Traffic::init();
