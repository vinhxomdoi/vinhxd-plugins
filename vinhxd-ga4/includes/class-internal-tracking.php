<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theo dõi chuyển đổi NỘI BỘ (độc lập với GA4): ghi log từng lượt click gọi/Zalo/gửi form
 * kèm IP, thiết bị, từ khóa & gclid từ Google Ads.
 *
 * Dùng CHUNG danh sách quy định chuyển đổi với Vinhxd_GA4_Conversion_Tracking
 * (khai báo 1 lần ở trang "Chuyển đổi", áp dụng cho cả GA4 event lẫn log nội bộ này).
 *
 * Cột CSV: datetime,ip,action,keyword,device,source,count,referrer,destination_url,conversion_name,source_page,device_id,risk_flags
 */
class Vinhxd_GA4_Internal_Tracking {

	/** Các class mặc định luôn được theo dõi, kể cả khi chưa khai báo ở trang Chuyển đổi */
	const DEFAULT_CLASSES = array( 'fixzalo', 'fixcall', 'widgetcall', 'widgetform', 'kmform', 'footcall', 'ctacall' );
	const MANUAL_IP_EXCLUDE_OPTION = 'vinhxd_ga4_manual_ip_exclude';

	// ============================================================
	// DANH SÁCH IP LOẠI TRỪ THỦ CÔNG (Cài đặt → Danh sách IP loại trừ)
	// ============================================================

	/** Trả về mảng các dòng đã nhập (mỗi dòng 1 IP hoặc 1 dải CIDR), đã dọn khoảng trắng/dòng trống */
	public static function get_manual_excluded_ips() {
		$raw = get_option( self::MANUAL_IP_EXCLUDE_OPTION, '' );
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	private static function is_manually_excluded_ip( $ip ) {
		foreach ( self::get_manual_excluded_ips() as $entry ) {
			if ( strpos( $entry, '/' ) !== false ) {
				if ( self::ip_in_cidr( $ip, $entry ) ) return true;
			} elseif ( $entry === $ip ) {
				return true;
			}
		}
		return false;
	}

	// ============================================================
	// CHROME PRIVACY-PRESERVING PREFETCH PROXY (googlezip.net)
	// ============================================================

	/**
	 * Chrome tự động tải trước (prefetch) các link có khả năng cao được bấm khi người dùng xem
	 * kết quả tìm kiếm Google, và giấu IP thật của họ sau proxy dùng chung của Google để bảo vệ
	 * quyền riêng tư. Đây KHÔNG phải click tặc, KHÔNG phải bot xấu - chặn IP này trên Ads vô ích
	 * (IP dùng chung cho rất nhiều người dùng Chrome thật) nên cần loại khỏi log ngay từ đầu.
	 *
	 * Nhận diện qua PTR record (tên miền ngược) luôn có đuôi cố định *.googlezip.net - ổn định
	 * hơn nhiều so với việc tự lưu danh sách IP tĩnh vì dải IP này có thể đổi theo thời gian.
	 * Kết quả cache 7 ngày theo từng IP để không phải tra DNS lặp lại với IP đã gặp.
	 */
	private static function is_google_prefetch_proxy_ip( $ip ) {
		if ( ! $ip ) return false;

		$cache_key = 'vinhxd_ga4_ptr_' . md5( $ip );
		$cached = get_transient( $cache_key );
		if ( $cached !== false ) return $cached === '1';

		$host = @gethostbyaddr( $ip );
		$is_proxy = is_string( $host ) && stripos( $host, '.googlezip.net' ) !== false;

		set_transient( $cache_key, $is_proxy ? '1' : '0', 7 * DAY_IN_SECONDS );
		return $is_proxy;
	}

	/** Lưu bản đồ device_id => danh sách IP đã dùng, để phát hiện 1 thiết bị đổi nhiều IP */
	const DEVICE_MAP_OPTION      = 'vinhxd_ga4_device_ip_map';
	const DEVICE_MAP_MAX_DEVICES = 500;
	const DEVICE_IP_MAX_ENTRIES  = 15;
	const MULTI_IP_WINDOW        = 7 * DAY_IN_SECONDS; // chỉ tính đổi IP trong 7 ngày gần nhất
	const MULTI_IP_THRESHOLD     = 3; // >3 nghĩa là từ 4 IP khác nhau trở lên bị đánh dấu

	public static function get_log_file() {
		$dir = trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'ip-logs/';
		$key = get_option( self::LOG_FILE_KEY_OPTION, '' );
		if ( ! $key ) {
			$key = strtolower( wp_generate_password( 24, false ) );
			update_option( self::LOG_FILE_KEY_OPTION, $key, false );
		}
		$file = $dir . 'visitors-' . $key . '.csv';

		// Chuyển file log cũ (tên cố định, dễ đoán) sang tên mới - chỉ chạy 1 lần
		$legacy = $dir . 'visitors.csv';
		if ( ! file_exists( $file ) && file_exists( $legacy ) ) @rename( $legacy, $file );

		return $file;
	}

	// ============================================================
	// LỌC BOT
	// ============================================================

	/**
	 * Nhận diện bot/crawler qua User-Agent. Bật/tắt tại Cài đặt.
	 * Lưu ý: phần lớn bot không chạy JavaScript nên không kích hoạt AJAX log,
	 * nhưng log "Visit" chạy phía server thì bot vẫn chạm tới - đây là nơi lọc quan trọng nhất.
	 */
	public static function is_bot() {
		if ( get_option( 'vinhxd_ga4_exclude_bots', '1' ) !== '1' ) return false;

		$ip = self::get_client_ip();

		// Danh sách IP loại trừ thủ công (Cài đặt → Danh sách IP loại trừ) - kiểm tra trước,
		// rẻ (không gọi mạng), cho phép bạn tự loại bất kỳ IP/dải nào ngoài các trường hợp tự động bên dưới.
		if ( $ip && self::is_manually_excluded_ip( $ip ) ) return true;

		// AdsBot của Google (kiểm tra chất lượng trang đích quảng cáo) cố tình giả User-Agent
		// giống trình duyệt thật để đo đúng trải nghiệm người dùng, nên KHÔNG lọc được qua UA -
		// phải đối chiếu bằng dải IP chính thức Google công bố.
		if ( $ip && self::is_google_crawler_ip( $ip ) ) return true;

		$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		if ( $ua === '' ) return true; // không có User-Agent => gần như chắc chắn là bot

		$patterns = array(
			'bot', 'crawler', 'spider', 'crawl', 'slurp', 'curl', 'wget', 'python-requests',
			'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'scrapy', 'http_request',
			'facebookexternalhit', 'preview', 'monitor', 'uptime', 'pingdom', 'gtmetrix',
			'lighthouse', 'pagespeed', 'ahrefs', 'semrush', 'mj12', 'dotbot', 'petalbot',
			'bytespider', 'applebot', 'yandex', 'baiduspider', 'sogou', 'archive.org',
		);

		foreach ( $patterns as $p ) {
			if ( strpos( $ua, $p ) !== false ) return true;
		}
		return false;
	}

	/** Dải IP chính thức của Cloudflare (https://www.cloudflare.com/ips/) - có thể bổ sung qua filter 'vinhxd_ga4_cloudflare_ranges' */
	const CLOUDFLARE_RANGES = array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
		'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
		'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
		'2a06:98c0::/29', '2c0f:f248::/32',
	);
	const TRUSTED_PROXIES_OPTION = 'vinhxd_ga4_trusted_proxies';

	public static function is_cloudflare_ip( $ip ) {
		foreach ( (array) apply_filters( 'vinhxd_ga4_cloudflare_ranges', self::CLOUDFLARE_RANGES ) as $cidr ) {
			if ( self::ip_in_cidr( $ip, $cidr ) ) return true;
		}
		return false;
	}

	/** Danh sách proxy tin cậy do admin khai báo (Cài đặt → Proxy tin cậy) */
	public static function get_trusted_proxies() {
		$raw = (string) get_option( self::TRUSTED_PROXIES_OPTION, '' );
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	/**
	 * Proxy tin cậy = IP nội bộ/loopback (reverse proxy chạy cùng máy chủ: Nginx → Apache, Docker...)
	 * hoặc IP/dải admin tự khai báo. Chỉ khi request đi qua proxy tin cậy mới đọc X-Forwarded-For.
	 */
	public static function is_trusted_proxy( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return false;
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) return true;
		foreach ( self::get_trusted_proxies() as $entry ) {
			if ( self::ip_in_cidr( $ip, $entry ) ) return true;
		}
		return false;
	}

	/**
	 * Lấy IP thật của khách - dùng chung cho is_bot() và get_visitor_details().
	 *
	 * BẢO MẬT: các header CF-Connecting-IP / X-Forwarded-For do trình duyệt tự gửi được, nên KHÔNG được tin
	 * vô điều kiện (nếu tin, kẻ xấu ghi IP của người khác vào log → bấm "Chặn IP" là chặn nhầm khách thật,
	 * hoặc giả IP Googlebot để né bộ lọc). Quy tắc:
	 *  - CF-Connecting-IP: chỉ dùng khi kết nối thật (REMOTE_ADDR) đến từ dải IP Cloudflare.
	 *  - X-Forwarded-For: chỉ dùng khi REMOTE_ADDR là proxy tin cậy; đọc từ PHẢI sang TRÁI, bỏ qua các
	 *    hop là proxy tin cậy/Cloudflare, lấy hop đầu tiên không tin cậy (phần bên trái do client tự ghi, không tin).
	 *  - Còn lại: dùng REMOTE_ADDR.
	 */
	public static function get_client_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! filter_var( $remote, FILTER_VALIDATE_IP ) ) return '';

		$ip = $remote;

		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && self::is_cloudflare_ip( $remote ) ) {
			$cf = trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) $ip = $cf;
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && self::is_trusted_proxy( $remote ) ) {
			$chain = array_reverse( array_map( 'trim', explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );
			foreach ( $chain as $hop ) {
				if ( ! filter_var( $hop, FILTER_VALIDATE_IP ) ) break;
				$ip = $hop;
				if ( ! self::is_trusted_proxy( $hop ) && ! self::is_cloudflare_ip( $hop ) ) break;
			}
		}

		return $ip;
	}

	/**
	 * Tải danh sách dải IP chính thức của Google (AdsBot + Googlebot), cache 24h.
	 * Nguồn: https://developers.google.com/search/docs/crawling-indexing/google-special-case-crawlers
	 */
	const GOOGLE_RANGES_OPTION = 'vinhxd_ga4_google_ranges';
	const GOOGLE_RANGES_CRON   = 'vinhxd_ga4_refresh_google_ranges';

	/**
	 * Chỉ ĐỌC danh sách đã lưu - không bao giờ gọi mạng trên request của khách (bản cũ có thể bắt khách
	 * chờ tới 20 giây khi cache hết hạn). Nếu chưa có dữ liệu thì hẹn cron tải ngay ở nền.
	 */
	private static function get_google_crawler_ranges() {
		static $ranges = null;
		if ( null !== $ranges ) return $ranges;

		$stored = get_option( self::GOOGLE_RANGES_OPTION, array() );
		$ranges = ( is_array( $stored ) && ! empty( $stored['ranges'] ) ) ? $stored['ranges'] : array();

		$last_try = is_array( $stored ) ? (int) ( $stored['attempted'] ?? 0 ) : 0;
		if ( empty( $ranges ) && time() - $last_try > HOUR_IN_SECONDS && ! wp_next_scheduled( self::GOOGLE_RANGES_CRON . '_once' ) ) {
			wp_schedule_single_event( time(), self::GOOGLE_RANGES_CRON . '_once' );
		}
		return $ranges;
	}

	/** Chạy bằng WP-Cron (hằng ngày + 1 lần khi chưa có dữ liệu): tải dải IP chính thức của Google */
	public static function refresh_google_crawler_ranges() {

		$urls = array(
			'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json', // AdsBot & các crawler đặc biệt khác
			'https://developers.google.com/static/search/apis/ipranges/googlebot.json',          // Googlebot thông thường
		);

		$ranges = array();
		foreach ( $urls as $url ) {
			$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
			if ( is_wp_error( $response ) ) continue;

			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $data['prefixes'] ) ) continue;

			foreach ( $data['prefixes'] as $p ) {
				if ( ! empty( $p['ipv4Prefix'] ) ) $ranges[] = $p['ipv4Prefix'];
				if ( ! empty( $p['ipv6Prefix'] ) ) $ranges[] = $p['ipv6Prefix'];
			}
		}

		// Tải lỗi (VD hosting chặn mạng ra ngoài) thì GIỮ danh sách cũ, chỉ ghi lại thời điểm thử
		// (để request của khách không hẹn cron tải lại liên tục - chờ ít nhất 1 tiếng mới thử lại)
		$stored = get_option( self::GOOGLE_RANGES_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		if ( ! empty( $ranges ) ) {
			$stored['ranges']  = array_values( array_unique( $ranges ) );
			$stored['updated'] = time();
		}
		$stored['attempted'] = time();
		update_option( self::GOOGLE_RANGES_OPTION, $stored, false );
		return $ranges;
	}

	public static function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::GOOGLE_RANGES_CRON ) ) {
			wp_schedule_event( time() + 60, 'daily', self::GOOGLE_RANGES_CRON );
		}
	}

	/** Kiểm tra 1 IP có nằm trong dải CIDR cho trước không (hỗ trợ cả IPv4 và IPv6) */
	private static function ip_in_cidr( $ip, $cidr ) {
		if ( strpos( $cidr, '/' ) === false ) return $ip === $cidr;
		list( $subnet, $mask ) = explode( '/', $cidr );
		$mask = (int) $mask;

		if ( strpos( $cidr, ':' ) !== false ) { // IPv6
			$ip_bin = @inet_pton( $ip );
			$subnet_bin = @inet_pton( $subnet );
			if ( $ip_bin === false || $subnet_bin === false ) return false;

			$bytes = intdiv( $mask, 8 );
			$bits  = $mask % 8;
			if ( substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) return false;
			if ( $bits === 0 ) return true;

			$mask_byte = ( 0xFF << ( 8 - $bits ) ) & 0xFF;
			return ( ord( $ip_bin[ $bytes ] ) & $mask_byte ) === ( ord( $subnet_bin[ $bytes ] ) & $mask_byte );
		}

		// IPv4
		if ( strpos( $ip, '.' ) === false ) return false; // IP thật là IPv6 nhưng dải là IPv4 -> chắc chắn không khớp
		$ip_long = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		if ( $ip_long === false || $subnet_long === false ) return false;

		$mask_long = $mask === 0 ? 0 : ( ~0 << ( 32 - $mask ) ) & 0xFFFFFFFF;
		return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
	}

	public static function is_google_crawler_ip( $ip ) {
		foreach ( self::get_google_crawler_ranges() as $cidr ) {
			if ( self::ip_in_cidr( $ip, $cidr ) ) return true;
		}
		return false;
	}

	// ============================================================
	// THÔNG TIN KHÁCH TRUY CẬP
	// ============================================================

	public static function get_visitor_details() {
		$ip = self::get_client_ip();

		$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$device = ( strpos( $ua, 'mobile' ) !== false || strpos( $ua, 'android' ) !== false )
			? 'Mobile'
			: ( ( strpos( $ua, 'ipad' ) !== false || strpos( $ua, 'tablet' ) !== false ) ? 'Tablet' : 'Desktop' );

		// Cookie do trình duyệt gửi lên => luôn sanitize + giới hạn độ dài (bản cũ dùng thô, gây vỡ cột CSV)
		$kw = ! empty( $_GET['keyword'] )
			? self::clean_text( urldecode( wp_unslash( $_GET['keyword'] ) ), 200 )
			: ( ! empty( $_COOKIE['ga_keyword'] ) ? self::clean_text( wp_unslash( $_COOKIE['ga_keyword'] ), 200 ) : '' );
		if ( $kw === '' ) $kw = '(không có)';
		$gclid = ! empty( $_GET['gclid'] )
			? self::clean_text( wp_unslash( $_GET['gclid'] ), 255 )
			: ( ! empty( $_COOKIE['ga_gclid'] ) ? self::clean_text( wp_unslash( $_COOKIE['ga_gclid'] ), 255 ) : '' );

		return array(
			'ip'      => sanitize_text_field( $ip ),
			'device'  => $device,
			'keyword' => $kw,
			'gclid'   => $gclid,
			'source'  => self::detect_source( $gclid, $kw ),
		);
	}

	/**
	 * Xác định nguồn truy cập: Google Ads / Facebook / Zalo / Tự nhiên.
	 * Ưu tiên gclid & keyword (dấu hiệu chắc chắn của Google Ads), sau đó mới xét referrer.
	 * Referrer được lưu vào cookie để giữ được nguồn gốc xuyên suốt phiên,
	 * vì khi khách click sang trang thứ 2 thì referrer sẽ trở thành chính domain của mình.
	 */
	public static function detect_source( $gclid = '', $keyword = '' ) {
		if ( ! empty( $gclid ) || ( $keyword && $keyword !== '(không có)' ) ) {
			return 'Google Ads';
		}

		$ref = $_COOKIE['ga_referrer'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' );
		$ref = strtolower( esc_url_raw( wp_unslash( (string) $ref ) ) );

		if ( $ref ) {
			$host = wp_parse_url( $ref, PHP_URL_HOST ) ?: '';
			// Bỏ qua nếu referrer là chính site này (điều hướng nội bộ)
			$self_host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';
			if ( $host && $host !== $self_host ) {
				if ( strpos( $host, 'facebook.' ) !== false || strpos( $host, 'fb.com' ) !== false || strpos( $host, 'fb.watch' ) !== false || strpos( $host, 'instagram.' ) !== false ) {
					return 'Facebook';
				}
				if ( strpos( $host, 'zalo.' ) !== false ) {
					return 'Zalo';
				}
				if ( strpos( $host, 'google.' ) !== false ) {
					return 'Google (tự nhiên)';
				}
			}
		}

		return 'Tự nhiên/Khác';
	}

	// ============================================================
	// QUY ĐỊNH CHUYỂN ĐỔI DÙNG CHUNG
	// ============================================================

	/**
	 * Lấy danh sách selector cần theo dõi ở frontend = quy định chuyển đổi của XD Analytics
	 * + các class mặc định. Trả về dạng ['selector' => ..., 'label' => ...]
	 */
	public static function get_tracked_rules() {
		$rules = array();

		foreach ( Vinhxd_GA4_Conversion_Tracking::get_conversions() as $c ) {
			$rules[] = array(
				'selector' => $c['selector'],
				'label'    => $c['label'],
			);
		}

		// Bổ sung class mặc định nếu chưa được khai báo
		$declared = array_map( function( $r ) { return ltrim( trim( $r['selector'] ), '.#' ); }, $rules );
		foreach ( self::DEFAULT_CLASSES as $cls ) {
			if ( ! in_array( $cls, $declared, true ) ) {
				$rules[] = array( 'selector' => '.' . $cls, 'label' => '' );
			}
		}

		return $rules;
	}

	/**
	 * Đổi selector thành tên chuyển đổi thân thiện. Nếu chưa đặt tên thì trả lại chính selector.
	 */
	public static function resolve_conversion_name( $selector ) {
		static $rules = null;
		if ( empty( $selector ) ) return '';
		if ( null === $rules ) $rules = self::get_tracked_rules();

		foreach ( $rules as $rule ) {
			if ( trim( $rule['selector'] ) === trim( $selector ) ) {
				return $rule['label'] ?: $selector;
			}
		}
		return $selector;
	}

	// ============================================================
	// THEO DÕI THIẾT BỊ (DEVICE ID) & PHÁT HIỆN NGHI VẤN
	// ============================================================

	/**
	 * Ghi nhận 1 thiết bị (device_id, bền qua localStorage+cookie ở trình duyệt) vừa xuất hiện với IP nào.
	 * Gọi ở MỌI lượt tải trang (không chỉ lúc chuyển đổi) để có đủ dữ liệu phát hiện đổi IP.
	 */
	public static function record_device_ip( $device_id, $ip ) {
		if ( empty( $device_id ) || empty( $ip ) ) return;

		$map = get_option( self::DEVICE_MAP_OPTION, array() );
		if ( ! is_array( $map ) ) $map = array();
		if ( ! isset( $map[ $device_id ] ) ) $map[ $device_id ] = array();

		$found = false;
		foreach ( $map[ $device_id ] as &$entry ) {
			if ( $entry['ip'] === $ip ) { $entry['ts'] = time(); $found = true; break; }
		}
		unset( $entry );
		if ( ! $found ) $map[ $device_id ][] = array( 'ip' => $ip, 'ts' => time() );

		// Giới hạn số IP lưu cho mỗi thiết bị
		if ( count( $map[ $device_id ] ) > self::DEVICE_IP_MAX_ENTRIES ) {
			usort( $map[ $device_id ], function( $a, $b ) { return $a['ts'] <=> $b['ts']; } );
			$map[ $device_id ] = array_slice( $map[ $device_id ], -1 * self::DEVICE_IP_MAX_ENTRIES );
		}

		// Giới hạn tổng số thiết bị theo dõi (loại bớt thiết bị lâu không hoạt động)
		if ( count( $map ) > self::DEVICE_MAP_MAX_DEVICES ) {
			uasort( $map, function( $a, $b ) {
				$a_last = ! empty( $a ) ? end( $a )['ts'] : 0;
				$b_last = ! empty( $b ) ? end( $b )['ts'] : 0;
				return $a_last <=> $b_last;
			} );
			$map = array_slice( $map, -1 * self::DEVICE_MAP_MAX_DEVICES, null, true );
		}

		update_option( self::DEVICE_MAP_OPTION, $map, false );
	}

	/** Đếm số IP khác nhau mà 1 thiết bị đã dùng trong khoảng MULTI_IP_WINDOW gần nhất */
	public static function count_distinct_ips( $device_id ) {
		if ( empty( $device_id ) ) return 0;
		$map = get_option( self::DEVICE_MAP_OPTION, array() );
		if ( empty( $map[ $device_id ] ) ) return 0;

		$cutoff = time() - self::MULTI_IP_WINDOW;
		$ips = array();
		foreach ( $map[ $device_id ] as $entry ) {
			if ( $entry['ts'] >= $cutoff ) $ips[ $entry['ip'] ] = true;
		}
		return count( $ips );
	}

	/**
	 * Tính danh sách "cờ nghi vấn" cho 1 lượt chuyển đổi, dựa trên các tín hiệu thu được từ trình duyệt.
	 * $args: device_id, webdriver (bool), time_to_click (int mili-giây|null), had_interaction (bool|null)
	 */
	public static function compute_risk_flags( $args ) {
		$flags = array();

		if ( ! empty( $args['webdriver'] ) ) {
			$flags[] = 'webdriver';
		}

		if ( ! empty( $args['device_id'] ) ) {
			$distinct = self::count_distinct_ips( $args['device_id'] );
			if ( $distinct > self::MULTI_IP_THRESHOLD ) {
				$flags[] = 'multi_ip:' . $distinct;
			}
		}

		if ( isset( $args['time_to_click'] ) && $args['time_to_click'] !== null && $args['time_to_click'] >= 0 && $args['time_to_click'] < 3000 ) {
			$flags[] = 'fast_click:' . round( $args['time_to_click'] / 1000, 1 );
		}

		if ( array_key_exists( 'had_interaction', $args ) && $args['had_interaction'] === false ) {
			$flags[] = 'no_interaction';
		}

		return $flags;
	}

	/** Render badge nghi vấn cho 1 dòng log, kèm tooltip giải thích lý do */
	private static function render_risk_badges( $risk_flags_str ) {
		$risk_flags_str = trim( (string) $risk_flags_str );
		if ( $risk_flags_str === '' ) return '<span class="risk-empty">—</span>';

		$html = '';
		foreach ( explode( '|', $risk_flags_str ) as $f ) {
			if ( strpos( $f, 'webdriver' ) === 0 ) {
				$html .= '<span class="risk-badge risk-high" title="navigator.webdriver = true: trình duyệt đang bị điều khiển bởi công cụ tự động (Selenium/Puppeteer/Playwright) - gần như chắc chắn là bot.">🤖 Bot tool</span>';
			} elseif ( strpos( $f, 'multi_ip:' ) === 0 ) {
				$count = str_replace( 'multi_ip:', '', $f );
				$html .= '<span class="risk-badge risk-mid" title="Cùng 1 thiết bị (trình duyệt) nhưng đã đổi qua ' . esc_attr( $count ) . ' IP khác nhau trong 7 ngày qua.">🌐 ' . esc_html( $count ) . ' IP</span>';
			} elseif ( strpos( $f, 'fast_click:' ) === 0 ) {
				$sec = str_replace( 'fast_click:', '', $f );
				$html .= '<span class="risk-badge risk-mid" title="Click chỉ ' . esc_attr( $sec ) . ' giây sau khi vào trang - nhanh bất thường so với người đọc rồi mới quyết định.">⚡ ' . esc_html( $sec ) . 's</span>';
			} elseif ( trim( $f ) === 'no_interaction' ) {
				$html .= '<span class="risk-badge risk-low" title="Không phát hiện cuộn trang / di chuột / chạm trước khi click - dấu hiệu thường gặp ở script tự động.">👆 Không tương tác</span>';
			}
		}
		return $html !== '' ? $html : '<span class="risk-empty">—</span>';
	}

	// ============================================================
	// HELPER: SANITIZE / CSV / COOKIE
	// ============================================================

	/** sanitize_text_field + cắt độ dài (đa byte) */
	public static function clean_text( $value, $max = 255 ) {
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/** device_id do client gửi: chỉ cho phép ký tự an toàn, tối đa 64 ký tự */
	public static function clean_device_id( $value ) {
		$value = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value );
		return substr( $value, 0, 64 );
	}

	/**
	 * Dựng 1 dòng CSV an toàn: escape dấu ", bỏ xuống dòng, và chặn "CSV formula injection"
	 * (ô bắt đầu bằng = + @ sẽ bị Excel hiểu là công thức khi mở file).
	 */
	public static function csv_row( $fields ) {
		$out = array();
		foreach ( $fields as $v ) {
			$v = str_replace( array( "\r", "\n" ), ' ', (string) $v );
			if ( $v !== '' && in_array( $v[0], array( '=', '+', '@' ), true ) ) $v = "'" . $v;
			$out[] = '"' . str_replace( '"', '""', $v ) . '"';
		}
		return implode( ',', $out ) . "\n";
	}

	private static function set_cookie( $name, $value, $ttl = DAY_IN_SECONDS ) {
		if ( headers_sent() ) return;
		setcookie( $name, $value, array(
			'expires'  => time() + $ttl,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		) );
	}

	// ============================================================
	// FILE LOG: tên file ngẫu nhiên + chặn truy cập trực tiếp
	// ============================================================

	const LOG_FILE_KEY_OPTION = 'vinhxd_ga4_log_file_key';

	/**
	 * Bảo vệ thư mục log: .htaccess hỗ trợ cả Apache 2.2 & 2.4 + index.php chống liệt kê thư mục.
	 * Nginx bỏ qua .htaccess nên tên file log được gắn chuỗi ngẫu nhiên để không đoán được đường dẫn.
	 */
	public static function protect_log_dir( $dir ) {
		if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
		$ht = $dir . '.htaccess';
		if ( ! file_exists( $ht ) || trim( (string) @file_get_contents( $ht ) ) === 'Deny from all' ) {
			@file_put_contents( $ht, "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . 'index.php' ) ) {
			@file_put_contents( $dir . 'index.php', "<?php // Silence is golden.\n" );
		}
	}

	// ============================================================
	// GHI LOG
	// ============================================================

	public static function write_log( $type = 'visit', $destination_url = '', $conversion_name = '', $source_page = '', $device_id = '', $risk_flags = array() ) {
		if ( self::is_bot() ) return;

		$info = self::get_visitor_details();

		// Chrome Prefetch Proxy (googlezip.net) - không phải click tặc, không phải bot xấu,
		// chỉ là Chrome tự tải trước trang khi xem kết quả tìm kiếm. IP dùng chung cho rất
		// nhiều người dùng thật nên không có giá trị ghi log/chặn - loại ngay tại đây.
		if ( self::is_google_prefetch_proxy_ip( $info['ip'] ) ) return;

		$file = self::get_log_file();
		self::protect_log_dir( trailingslashit( dirname( $file ) ) );

		// Khoá file trong suốt quá trình đọc-sửa-ghi: bản cũ chỉ khoá lúc ghi nên 2 click cùng lúc làm mất dòng
		$fh = @fopen( $file, 'c+' );
		if ( ! $fh ) return;
		if ( ! flock( $fh, LOCK_EX ) ) { fclose( $fh ); return; }

		$content = stream_get_contents( $fh );
		if ( $content === '' || $content === false ) {
			$content = "datetime,ip,action,keyword,device,source,count,referrer,destination_url,conversion_name,source_page,device_id,risk_flags\n";
		}

		$action_labels = array(
			'call'      => 'Call Click',
			'zalo'      => 'Zalo Click',
			'form_lead' => 'Gửi Form',
			'click'     => 'Click',
			'visit'     => 'Visit',
		);
		$action_label = $action_labels[ $type ] ?? 'Visit';

		$rows    = preg_split( '/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY );
		$updated = false;
		$now     = current_time( 'mysql' );

		// Gộp các lượt trùng (cùng IP + cùng hành động) trong vòng 3 phút thành 1 dòng, tăng cột "Lượt"
		for ( $i = count( $rows ) - 1; $i >= 1; $i-- ) {
			$data = str_getcsv( $rows[ $i ] );
			// Log ghi theo thời gian => gặp dòng cũ hơn 10 phút là dừng, không cần duyệt toàn bộ file
			if ( ! empty( $data[0] ) && strtotime( $now ) - strtotime( $data[0] ) > 600 ) break;
			if ( isset( $data[1] ) && $data[1] === $info['ip'] && ( $data[2] ?? '' ) === $action_label
				&& ( strtotime( $now ) - strtotime( $data[0] ) < 180 ) ) {
				$data[6] = (int) ( $data[6] ?? 0 ) + 1;
				while ( count( $data ) < 13 ) $data[] = '';
				if ( $destination_url ) $data[8]  = $destination_url;
				if ( $conversion_name ) $data[9]  = $conversion_name;
				if ( $source_page )     $data[10] = $source_page;
				if ( $device_id )       $data[11] = $device_id;
				if ( ! empty( $risk_flags ) ) {
					$existing = array_filter( explode( '|', $data[12] ?? '' ) );
					$data[12] = implode( '|', array_unique( array_merge( $existing, $risk_flags ) ) );
				}
				$rows[ $i ] = self::csv_row( $data );
				$updated = true;
				break;
			}
		}

		if ( ! $updated ) {
			$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : 'Direct';
			$rows[] = self::csv_row( array(
				$now, $info['ip'], $action_label, $info['keyword'],
				$info['device'], $info['source'], 1,
				$referrer, $destination_url, $conversion_name, $source_page,
				$device_id, implode( '|', $risk_flags ),
			) );
		}

		rewind( $fh );
		ftruncate( $fh, 0 );
		fwrite( $fh, implode( '', $rows ) );
		fflush( $fh );
		flock( $fh, LOCK_UN );
		fclose( $fh );
	}

	// ============================================================
	// HOOK FRONTEND
	// ============================================================

	/** Lưu cookie keyword/gclid/referrer + ghi log Visit cho khách từ Google Ads + ghi nhận IP theo thiết bị */
	public static function handle_init() {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) return;
		if ( self::is_bot() ) return;

		if ( ! empty( $_GET['keyword'] ) ) self::set_cookie( 'ga_keyword', self::clean_text( wp_unslash( $_GET['keyword'] ), 200 ) );
		if ( ! empty( $_GET['gclid'] ) )   self::set_cookie( 'ga_gclid',   self::clean_text( wp_unslash( $_GET['gclid'] ), 255 ) );

		// Ghi nhớ referrer gốc của phiên để xác định nguồn Facebook/Zalo về sau
		if ( empty( $_COOKIE['ga_referrer'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$ref  = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$host = wp_parse_url( $ref, PHP_URL_HOST ) ?: '';
			$self = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';
			if ( $host && $host !== $self ) {
				self::set_cookie( 'ga_referrer', substr( $ref, 0, 500 ) );
			}
		}

		// Ghi nhận IP hiện tại của thiết bị (nếu trình duyệt đã có device_id từ lần trước) -
		// đây là nguồn dữ liệu chính để phát hiện "1 thiết bị đổi nhiều IP"
		if ( ! empty( $_COOKIE['vinhxd_device_id'] ) ) {
			self::record_device_ip( self::clean_device_id( wp_unslash( $_COOKIE['vinhxd_device_id'] ) ), self::get_client_ip() );
		}

		if ( ! empty( $_GET['gclid'] ) || ! empty( $_GET['keyword'] ) ) {
			$lock = 'ga_v_' . md5( self::get_client_ip() );
			if ( ! get_transient( $lock ) ) {
				set_transient( $lock, 1, 180 );
				self::write_log( 'visit' );
			}
		}
	}

	/** Fluent Forms: ghi nhận lượt gửi form (kèm risk flag nếu thiết bị đã đổi nhiều IP) */
	public static function handle_fluentform_submission( $submission, $formData, $form ) {
		$source_page = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$device_id   = isset( $_COOKIE['vinhxd_device_id'] ) ? self::clean_device_id( wp_unslash( $_COOKIE['vinhxd_device_id'] ) ) : '';
		$risk_flags  = self::compute_risk_flags( array( 'device_id' => $device_id ) );

		self::write_log( 'form_lead', $source_page, 'Gửi form', $source_page, $device_id, $risk_flags );
	}

	/** Giới hạn số lượt log mỗi IP: tối đa RATE_LIMIT_MAX lượt / RATE_LIMIT_WINDOW giây (chống spam làm phình log) */
	const RATE_LIMIT_MAX    = 30;
	const RATE_LIMIT_WINDOW = 600;

	private static function is_rate_limited( $ip ) {
		if ( ! $ip ) return true;
		$key  = 'vinhxd_ga4_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= (int) apply_filters( 'vinhxd_ga4_rate_limit_max', self::RATE_LIMIT_MAX ) ) return true;
		set_transient( $key, $hits + 1, self::RATE_LIMIT_WINDOW );
		return false;
	}

	/** AJAX nhận log click từ frontend, kèm các tín hiệu chống gian lận thu được ở trình duyệt */
	public static function handle_ajax_log() {
		if ( self::is_bot() ) wp_send_json_success();

		$type = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		if ( ! in_array( $type, array( 'call', 'zalo', 'click' ), true ) ) wp_send_json_success();

		if ( self::is_rate_limited( self::get_client_ip() ) ) wp_send_json_success();

		$destination = isset( $_POST['destination'] ) ? substr( esc_url_raw( wp_unslash( $_POST['destination'] ), array( 'http', 'https', 'tel', 'sms', 'mailto', 'zalo' ) ), 0, 500 ) : '';
		$source_page = isset( $_POST['source_page'] ) ? substr( esc_url_raw( wp_unslash( $_POST['source_page'] ) ), 0, 500 ) : '';
		$selector    = isset( $_POST['selector'] ) ? self::clean_text( wp_unslash( $_POST['selector'] ), 200 ) : '';
		$device_id   = isset( $_POST['device_id'] ) ? self::clean_device_id( wp_unslash( $_POST['device_id'] ) ) : '';
		$webdriver   = isset( $_POST['webdriver'] ) && $_POST['webdriver'] === '1';
		$time_to_click   = isset( $_POST['time_to_click'] ) && $_POST['time_to_click'] !== '' ? (int) $_POST['time_to_click'] : null;
		$had_interaction = isset( $_POST['had_interaction'] ) ? ( $_POST['had_interaction'] === '1' ) : null;

		$conversion_name = $selector ? self::resolve_conversion_name( $selector ) : '';

		if ( $device_id ) {
			self::record_device_ip( $device_id, self::get_client_ip() );
		}

		$risk_flags = self::compute_risk_flags( array(
			'device_id'       => $device_id,
			'webdriver'       => $webdriver,
			'time_to_click'   => $time_to_click,
			'had_interaction' => $had_interaction,
		) );

		self::write_log( $type, $destination, $conversion_name, $source_page, $device_id, $risk_flags );
		wp_send_json_success();
	}

	/** Truyền cấu hình cho script frontend (dùng chung script với phần bắn event GA4) */
	public static function localize_frontend( $handle ) {
		wp_localize_script( $handle, 'vinhxdGA4Internal', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'rules'    => array_values( self::get_tracked_rules() ),
		) );
	}

	// ============================================================
	// GIAO DIỆN TRANG NHẬT KÝ CHUYỂN ĐỔI
	// ============================================================

	public static function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Không có quyền.' );
		check_admin_referer( 'ga_clear_logs_nonce' );

		$file = self::get_log_file();
		if ( file_exists( $file ) ) unlink( $file );

		wp_safe_redirect( admin_url( 'admin.php?page=vinhxd-ga4-logs&cleared=1' ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$file   = self::get_log_file();
		$filter = isset( $_GET['log_filter'] ) ? sanitize_text_field( $_GET['log_filter'] ) : 'all';
		$source_filter = isset( $_GET['log_source'] ) ? sanitize_text_field( $_GET['log_source'] ) : 'all';

		$ga4_configured = get_option( 'vinhxd_ga4_property_id', '' ) && get_option( 'vinhxd_ga4_service_account_json', '' );
		$ads_configured = get_option( 'vinhxd_ads_refresh_token', '' ) && get_option( 'vinhxd_ads_customer_id', '' );
		?>
		<style>
			#ads-logs-wrap { max-width:1300px; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
			#ads-logs-wrap h1 { color:#0E9577; font-size:22px; font-weight:700; margin-bottom:4px; }
			.ads-notice { background:#f0fdf9; border-left:4px solid #0E9577; padding:12px 16px; border-radius:4px; margin-bottom:12px; font-size:13.5px; color:#1a1a1a; }
			.ads-notice a { color:#0E9577; font-weight:600; text-decoration:none; }
			.ads-notice-ga { background:#fffbea; border-left:4px solid #dba617; padding:12px 16px; border-radius:4px; margin-bottom:20px; font-size:13.5px; color:#1a1a1a; }
			.ads-notice-ga a { color:#b8860b; font-weight:600; }
			.ads-toolbar { display:flex; align-items:center; justify-content:space-between; background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px 16px; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
			.ads-filters { display:flex; gap:6px; flex-wrap:wrap; }
			.ads-filters input[type=radio] { display:none; }
			.ads-filters label { padding:7px 16px; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; border:1.5px solid #e5e7eb; color:#555; background:#fafafa; transition:all .15s; user-select:none; }
			.ads-filters input[type=radio]:checked + label { background:#0E9577; border-color:#0E9577; color:#fff; }
			.ads-filters label:hover { border-color:#0E9577; color:#0E9577; }
			.ads-filters input[type=radio]:checked + label:hover { color:#fff; }
			.ads-toolbar-right { display:flex; gap:8px; align-items:center; }
			.ads-btn-danger { display:inline-flex; align-items:center; gap:6px; background:#fff; color:#dc2626 !important; padding:7px 16px; border-radius:6px; font-size:13px; font-weight:600; text-decoration:none !important; border:1.5px solid #fca5a5; transition:all .15s; cursor:pointer; }
			.ads-btn-danger:hover { background:#fee2e2; border-color:#dc2626; }
			.ads-meta { font-size:12.5px; color:#888; margin-bottom:10px; }
			.ads-meta strong { color:#0E9577; }
			.ads-success-msg { background:#f0fdf9; border-left:4px solid #0E9577; padding:10px 16px; border-radius:4px; margin-bottom:14px; font-size:13.5px; color:#065f46; }
			#ads-logs-wrap table { border-radius:8px; overflow:hidden; border:1px solid #e5e7eb; border-collapse:separate; border-spacing:0; width:100%; font-size:13px; background:#fff; }
			#ads-logs-wrap table thead tr { background:#0E9577 !important; }
			#ads-logs-wrap table thead th { color:#fff !important; font-weight:600; padding:11px 14px; border:none; font-size:12.5px; letter-spacing:.3px; text-transform:uppercase; }
			#ads-logs-wrap table tbody tr:hover td { filter:brightness(0.96); }
			#ads-logs-wrap table tbody td { padding:9px 14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
			.badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11.5px; font-weight:600; }
			.badge-visit { background:#f3f4f6; color:#555; }
			.badge-call  { background:#fee2e2; color:#b91c1c; }
			.badge-zalo  { background:#dbeafe; color:#1d4ed8; }
			.badge-form  { background:#dcfce7; color:#15803d; }
			.badge-click { background:#f3f0ff; color:#6d28d9; }
			.src-badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11.5px; font-weight:600; }
			.src-ads    { background:#fef3c7; color:#b45309; }
			.src-fb     { background:#dbeafe; color:#1d4ed8; }
			.src-zalo   { background:#e0f2fe; color:#0369a1; }
			.src-google { background:#dcfce7; color:#15803d; }
			.src-other  { background:#f3f4f6; color:#555; }
			.ip-abnormal { color:#dc2626; font-weight:700; }
			.ip-count-badge { display:inline-block; background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:10px; font-size:10.5px; font-weight:700; padding:1px 6px; margin-left:4px; vertical-align:middle; }
			.dest-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; background:#f0fdf9; border:1.5px solid #0E9577; color:#0E9577; text-decoration:none; font-size:16px; font-weight:700; line-height:1; transition:all .15s; }
			.dest-btn:hover { background:#0E9577; color:#fff; text-decoration:none; }
			.dest-empty { color:#ddd; }
			.cls-badge { display:inline-block; padding:3px 9px; border-radius:4px; font-size:11.5px; font-weight:600; background:#f3f0ff; color:#6d28d9; border:1px solid #ddd6fe; }
			.cls-empty { color:#ccc; }
			.risk-badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:10.5px; font-weight:600; margin:1px 3px 1px 0; cursor:help; }
			.risk-high { background:#fee2e2; color:#b91c1c; }
			.risk-mid  { background:#ffedd5; color:#c2410c; }
			.risk-low  { background:#f3f4f6; color:#6b7280; }
			.risk-empty { color:#ddd; }
			.ads-stats { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
			.ads-stat-card { flex:1; min-width:130px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px 16px; }
			.ads-stat-card .num { font-size:22px; font-weight:700; color:#0E9577; }
			.ads-stat-card.is-risk .num { color:#dc2626; }
			.ads-stat-card .lbl { font-size:12px; color:#888; margin-top:2px; }
			.ads-source-select { padding:7px 12px; border-radius:6px; border:1.5px solid #e5e7eb; font-size:13px; color:#333; background:#fafafa; }
			.block-badge-done { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; background:#fee2e2; color:#b91c1c; cursor:help; }
			.vinhxd-block-ip-btn { color:#b91c1c !important; border-color:#fca5a5 !important; }
			.vinhxd-block-ip-btn:hover { background:#fee2e2 !important; }
			.block-ip-result.is-ok { color:#15803d; }
			.block-ip-result.is-err { color:#b91c1c; }
			#ga-confirm-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:99999; align-items:center; justify-content:center; }
			#ga-confirm-modal.open { display:flex; }
			#ga-confirm-box { background:#fff; border-radius:12px; padding:28px 32px; max-width:420px; width:90%; box-shadow:0 8px 40px rgba(0,0,0,.18); text-align:center; }
			#ga-confirm-box h3 { color:#dc2626; font-size:17px; margin-bottom:10px; }
			#ga-confirm-box p { color:#555; font-size:13.5px; margin-bottom:22px; }
			#ga-confirm-box .btn-row { display:flex; gap:10px; justify-content:center; }
			#ga-confirm-box .btn-cancel { padding:9px 22px; border-radius:6px; border:1.5px solid #e5e7eb; background:#fafafa; color:#555; font-size:13px; font-weight:600; cursor:pointer; }
			#ga-confirm-box .btn-confirm { padding:9px 22px; border-radius:6px; border:none; background:#dc2626; color:#fff; font-size:13px; font-weight:600; cursor:pointer; }
			#ga-confirm-box .btn-confirm:hover { background:#b91c1c; }
		</style>

		<div id="ga-confirm-modal">
			<div id="ga-confirm-box">
				<h3>⚠️ Xóa toàn bộ bản ghi?</h3>
				<p>Tất cả dữ liệu theo dõi chuyển đổi sẽ bị xóa vĩnh viễn và không thể khôi phục.</p>
				<div class="btn-row">
					<button class="btn-cancel" type="button" onclick="document.getElementById('ga-confirm-modal').classList.remove('open')">Hủy</button>
					<button class="btn-confirm" type="button" onclick="document.getElementById('ga-clear-form').submit()">Xóa toàn bộ</button>
				</div>
			</div>
		</div>

		<div class="wrap" id="ads-logs-wrap">
			<h1>📊 Nhật ký quảng cáo &amp; Chuyển đổi</h1>
			<div class="ads-notice">
				💡 Truy cập <a href="https://vinhxd.com/chuyendoi" target="_blank">vinhxd.com/chuyendoi</a> để xem hướng dẫn <strong>show từ khóa từ Google Ads</strong> và cách <strong>lấy &amp; chặn IP trong Google Ads</strong>.
			</div>

			<?php if ( ! $ga4_configured ) : ?>
				<div class="ads-notice-ga">
					📈 Đây là thống kê nội bộ của website. Để có bức tranh tổng thể hơn về traffic, hãy <a href="<?php echo esc_url( admin_url( 'admin.php?page=vinhxd-ga4-settings' ) ); ?>">cài đặt Google Analytics</a> vào để xem data chi tiết hơn.
				</div>
			<?php endif; ?>

			<?php if ( ! $ads_configured ) : ?>
				<div class="ads-notice-ga">
					🚫 Muốn chặn IP click tặc ngay tại đây (không cần mở Google Ads)? <a href="<?php echo esc_url( admin_url( 'admin.php?page=vinhxd-ga4-ads' ) ); ?>">Kết nối Google Ads</a> và chọn tài khoản cho website này.
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['cleared'] ) ) : ?>
				<div class="ads-success-msg">✅ Đã xóa toàn bộ bản ghi thành công.</div>
			<?php endif; ?>

			<?php if ( ! file_exists( $file ) ) { echo '<p>Chưa có dữ liệu.</p></div>'; return; } ?>

			<form id="ga-clear-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
				<input type="hidden" name="action" value="vinhxd_ga4_clear_logs">
				<?php wp_nonce_field( 'ga_clear_logs_nonce' ); ?>
			</form>

			<div class="ads-toolbar">
				<form method="get" style="margin:0; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
					<input type="hidden" name="page" value="vinhxd-ga4-logs">
					<div class="ads-filters">
						<?php
						$filters = array(
							'all'        => '🗂 Tất cả',
							'conversion' => '🔥 Chỉ chuyển đổi',
							'frequent'   => '🚨 Nghi vấn click tặc',
						);
						foreach ( $filters as $val => $label ) {
							$checked = ( $filter === $val ) ? 'checked' : '';
							echo "<input type='radio' name='log_filter' id='f_{$val}' value='{$val}' {$checked} onchange='this.form.submit()'>
								  <label for='f_{$val}'>{$label}</label>";
						}
						?>
					</div>
					<select name="log_source" class="ads-source-select" onchange="this.form.submit()">
						<?php
						$source_options = array(
							'all'               => 'Tất cả nguồn',
							'Google Ads'        => 'Chỉ Google Ads',
							'Facebook'          => 'Chỉ Facebook',
							'Zalo'              => 'Chỉ Zalo',
							'Google (tự nhiên)' => 'Chỉ Google (tự nhiên)',
							'Tự nhiên/Khác'     => 'Tự nhiên/Khác',
						);
						foreach ( $source_options as $val => $label ) {
							echo '<option value="' . esc_attr( $val ) . '" ' . selected( $source_filter, $val, false ) . '>' . esc_html( $label ) . '</option>';
						}
						?>
					</select>
				</form>
				<div class="ads-toolbar-right">
					<button class="ads-btn-danger" type="button" onclick="document.getElementById('ga-confirm-modal').classList.add('open')">
						🗑 Xóa toàn bộ bản ghi
					</button>
				</div>
			</div>

			<?php
			$lines = file( $file );
			array_shift( $lines );
			$rows = array();
			foreach ( $lines as $ln ) {
				$c = str_getcsv( $ln );
				while ( count( $c ) < 13 ) $c[] = '';
				if ( count( $c ) >= 7 ) $rows[] = $c;
			}

			$ip_visit_count = array();
			foreach ( $rows as $c ) {
				if ( $c[2] === 'Visit' ) $ip_visit_count[ $c[1] ] = ( $ip_visit_count[ $c[1] ] ?? 0 ) + (int) ( $c[6] ?? 1 );
			}

			$conversion_actions = array( 'Call Click', 'Zalo Click', 'Gửi Form', 'Click' );

			$filtered = array();
			foreach ( $rows as $c ) {
				$is_flagged = trim( $c[12] ?? '' ) !== '' || ( $ip_visit_count[ $c[1] ] ?? 0 ) > 3;

				if ( $filter === 'conversion' && ! in_array( $c[2], $conversion_actions, true ) ) continue;
				if ( $filter === 'frequent'   && ! $is_flagged ) continue;
				if ( $source_filter !== 'all' && trim( $c[5] ?? '' ) !== $source_filter ) continue;
				$filtered[] = $c;
			}

			// Thẻ thống kê nhanh (tính trên tập đã lọc, để quan sát tổng thể theo đúng bộ lọc đang chọn)
			$stat_calls = 0; $stat_zalo = 0; $stat_forms = 0; $stat_clicks = 0; $stat_flagged = 0;
			foreach ( $filtered as $c ) {
				switch ( $c[2] ) {
					case 'Call Click': $stat_calls  += (int) ( $c[6] ?? 1 ); break;
					case 'Zalo Click': $stat_zalo   += (int) ( $c[6] ?? 1 ); break;
					case 'Gửi Form':   $stat_forms  += (int) ( $c[6] ?? 1 ); break;
					case 'Click':      $stat_clicks += (int) ( $c[6] ?? 1 ); break;
				}
				if ( trim( $c[12] ?? '' ) !== '' || ( $ip_visit_count[ $c[1] ] ?? 0 ) > 3 ) $stat_flagged++;
			}
			?>

			<div class="ads-stats">
				<div class="ads-stat-card"><div class="num"><?php echo (int) $stat_calls; ?></div><div class="lbl">📞 Gọi điện</div></div>
				<div class="ads-stat-card"><div class="num"><?php echo (int) $stat_zalo; ?></div><div class="lbl">💬 Zalo</div></div>
				<div class="ads-stat-card"><div class="num"><?php echo (int) $stat_forms; ?></div><div class="lbl">📝 Gửi Form</div></div>
				<div class="ads-stat-card"><div class="num"><?php echo (int) $stat_clicks; ?></div><div class="lbl">🖱 Click khác</div></div>
				<div class="ads-stat-card is-risk"><div class="num"><?php echo (int) $stat_flagged; ?></div><div class="lbl">🚨 Nghi vấn</div></div>
			</div>


			<p class="ads-meta">Hiển thị <strong><?php echo count( $filtered ); ?></strong> bản ghi.</p>

			<table>
				<thead>
					<tr>
						<th>Thời gian</th>
						<th>IP</th>
						<th>Hành động</th>
						<th>Nguồn</th>
						<th style="text-align:center;">Lượt</th>
						<th style="text-align:center;">Trang nguồn</th>
						<th style="text-align:center;">Tên chuyển đổi</th>
						<th>Thiết bị</th>
						<th>Nghi vấn</th>
						<th>Google Ads</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( array_reverse( $filtered ) as $c ) :
					$action = $c[2];
					$row_bg = ''; $badge_class = 'badge-visit';
					if ( $action === 'Call Click' ) { $row_bg = 'background:#fff5f5;'; $badge_class = 'badge-call'; }
					if ( $action === 'Zalo Click' ) { $row_bg = 'background:#f0f6ff;'; $badge_class = 'badge-zalo'; }
					if ( $action === 'Gửi Form' )   { $row_bg = 'background:#f0fff8;'; $badge_class = 'badge-form'; }
					if ( $action === 'Click' )      { $row_bg = 'background:#faf8ff;'; $badge_class = 'badge-click'; }
					$label = $action === 'Gửi Form' ? '🔥 Gửi Form' : $action;

					$source_page = trim( $c[10] ?? '' );
					$conv_name   = trim( $c[9] ?? '' );
					$source      = trim( $c[5] ?? '' );
					$ip_count    = $ip_visit_count[ $c[1] ] ?? 0;
					$is_abnormal = $ip_count > 3;

					// Log cũ lưu CSS class ở cột này -> vẫn đổi được sang tên thân thiện
					$conv_display = $conv_name ? self::resolve_conversion_name( $conv_name ) : '';

					$src_class = 'src-other';
					if ( strpos( $source, 'Google Ads' ) !== false )   $src_class = 'src-ads';
					elseif ( strpos( $source, 'Facebook' ) !== false ) $src_class = 'src-fb';
					elseif ( strpos( $source, 'Zalo' ) !== false )     $src_class = 'src-zalo';
					elseif ( strpos( $source, 'Google' ) !== false )   $src_class = 'src-google';
				?>
					<tr style="<?php echo $row_bg; ?>">
						<td style="color:#777;font-size:12px;"><?php echo esc_html( $c[0] ); ?></td>
						<td>
							<span<?php if ( $is_abnormal ) echo ' class="ip-abnormal"'; ?>><?php echo esc_html( $c[1] ); ?></span>
							<?php if ( $is_abnormal ) : ?>
								<span class="ip-count-badge"><?php echo (int) $ip_count; ?>x</span>
							<?php endif; ?>
						</td>
						<td><span class="badge <?php echo $badge_class; ?>"><?php echo esc_html( $label ); ?></span></td>
						<td>
							<span class="src-badge <?php echo $src_class; ?>"><?php echo esc_html( $source ?: 'Tự nhiên/Khác' ); ?></span>
							<?php if ( ! empty( $c[3] ) && $c[3] !== '(không có)' ) : ?>
								<br><span style="font-size:11px;color:#888;">🔍 <?php echo esc_html( $c[3] ); ?></span>
							<?php endif; ?>
						</td>
						<td style="text-align:center;font-weight:700;color:#0E9577;"><?php echo (int) $c[6]; ?></td>
						<td style="text-align:center;">
							<?php if ( ! empty( $source_page ) ) : ?>
								<a href="<?php echo esc_url( $source_page ); ?>" target="_blank" class="dest-btn" title="<?php echo esc_attr( $source_page ); ?>">&#8594;</a>
							<?php else : ?>
								<span class="dest-empty">—</span>
							<?php endif; ?>
						</td>
						<td style="text-align:center;">
							<?php if ( ! empty( $conv_display ) ) : ?>
								<span class="cls-badge"><?php echo esc_html( $conv_display ); ?></span>
							<?php else : ?>
								<span class="cls-empty">—</span>
							<?php endif; ?>
						</td>
						<td style="color:#888;"><?php echo esc_html( $c[4] ); ?></td>
						<td><?php echo self::render_risk_badges( $c[12] ?? '' ); ?></td>
						<td><?php echo self::render_block_ip_cell( $c[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<script>
			( function () {
				document.querySelectorAll( '.vinhxd-block-ip-btn' ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function () {
						var ip = btn.getAttribute( 'data-ip' );
						if ( ! confirm( 'Chặn IP ' + ip + ' trên toàn bộ chiến dịch đang chạy của tài khoản Ads đã chọn?' ) ) return;

						btn.disabled = true;
						btn.textContent = 'Đang chặn...';
						var resultEl = btn.nextElementSibling;

						var fd = new FormData();
						fd.append( 'action', 'vinhxd_block_ip' );
						fd.append( 'nonce', '<?php echo esc_js( wp_create_nonce( 'vinhxd_block_ip' ) ); ?>' );
						fd.append( 'ip', ip );

						fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } )
							.then( function ( r ) { return r.json(); } )
							.then( function ( res ) {
								if ( res.success ) {
									btn.outerHTML = '<span class="block-badge-done">🚫 Đã chặn</span>';
									if ( resultEl ) { resultEl.textContent = res.data.message; resultEl.className = 'block-ip-result is-ok'; }
								} else {
									btn.disabled = false;
									btn.textContent = 'Chặn IP';
									if ( resultEl ) { resultEl.textContent = res.data; resultEl.className = 'block-ip-result is-err'; }
									alert( 'Lỗi: ' + res.data );
								}
							} )
							.catch( function () {
								btn.disabled = false;
								btn.textContent = 'Chặn IP';
								alert( 'Lỗi kết nối, thử lại sau.' );
							} );
					} );
				} );
			} )();
			</script>
		</div>
		<?php
	}
	/** Render ô "Google Ads": nút Chặn IP, hoặc badge nếu đã chặn trước đó */
	private static function render_block_ip_cell( $ip ) {
		$ip = trim( (string) $ip );
		if ( ! $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return '<span class="risk-empty">—</span>';

		$blocked = self::get_blocked_ips();
		if ( isset( $blocked[ $ip ] ) ) {
			$b = $blocked[ $ip ];
			$level_text = ( $b['level'] ?? 'campaign' ) === 'account' ? 'cấp tài khoản (mọi chiến dịch)' : ( $b['success'] . '/' . $b['total'] . ' chiến dịch' );
			return '<span class="block-badge-done" title="Chặn lúc ' . esc_attr( date_i18n( 'd/m/Y H:i', $b['blocked_at'] ) ) . ' - ' . esc_attr( $level_text ) . '">🚫 Đã chặn</span>';
		}

		return '<button type="button" class="button button-small vinhxd-block-ip-btn" data-ip="' . esc_attr( $ip ) . '">Chặn IP</button><span class="block-ip-result" style="margin-left:6px; font-size:11.5px;"></span>';
	}

	// ============================================================
	// CHẶN IP TRÊN GOOGLE ADS (từ nút "Chặn IP" trong bảng nhật ký)
	// ============================================================

	const BLOCKED_IPS_OPTION = 'vinhxd_ga4_blocked_ips';

	public static function get_blocked_ips() {
		$list = get_option( self::BLOCKED_IPS_OPTION, array() );
		return is_array( $list ) ? $list : array();
	}

	public static function handle_block_ip_ajax() {
		check_ajax_referer( 'vinhxd_block_ip', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền.' );

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( $_POST['ip'] ) : '';
		if ( ! $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) wp_send_json_error( 'Địa chỉ IP không hợp lệ.' );

		$customer_id = get_option( 'vinhxd_ads_customer_id', '' );
		$login_id    = get_option( 'vinhxd_ads_login_customer_id', '' );

		if ( ! $customer_id ) {
			wp_send_json_error( 'Chưa chọn tài khoản Ads cho website này. Vào XD Analytics → Google Ads → Bước 4 để chọn.' );
		}

		$client = Vinhxd_Ads_Settings::get_client();
		if ( ! $client->is_connected() ) {
			wp_send_json_error( 'Chưa kết nối Google Ads. Vào XD Analytics → Google Ads để kết nối.' );
		}

		$result = $client->block_ip_on_active_campaigns( $customer_id, $ip, $login_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$blocked = self::get_blocked_ips();
		$blocked[ $ip ] = array(
			'blocked_at'  => time(),
			'level'       => $result['level'] ?? 'campaign',
			'success'     => $result['success'],
			'total'       => $result['total'],
			'customer_id' => $customer_id,
		);
		update_option( self::BLOCKED_IPS_OPTION, $blocked, false );

		if ( ( $result['level'] ?? '' ) === 'account' ) {
			$message = 'Đã chặn IP ở cấp tài khoản (áp dụng mọi chiến dịch, kể cả tạo sau này).';
		} else {
			$message = 'Đã chặn IP trên ' . $result['success'] . '/' . $result['total'] . ' chiến dịch đang hoạt động.';
		}
		if ( ! empty( $result['failed'] ) ) {
			$message .= ' Lỗi ở: ' . implode( '; ', $result['failed'] );
		}

		wp_send_json_success( array( 'message' => $message, 'total' => $result['total'], 'success_count' => $result['success'] ) );
	}
}

add_action( 'init', array( 'Vinhxd_GA4_Internal_Tracking', 'handle_init' ) );
add_action( 'fluentform/submission_inserted', array( 'Vinhxd_GA4_Internal_Tracking', 'handle_fluentform_submission' ), 10, 3 );
add_action( 'wp_ajax_log_contact_click', array( 'Vinhxd_GA4_Internal_Tracking', 'handle_ajax_log' ) );
add_action( 'wp_ajax_nopriv_log_contact_click', array( 'Vinhxd_GA4_Internal_Tracking', 'handle_ajax_log' ) );
add_action( 'admin_post_vinhxd_ga4_clear_logs', array( 'Vinhxd_GA4_Internal_Tracking', 'handle_clear_logs' ) );
add_action( 'wp_ajax_vinhxd_block_ip', array( 'Vinhxd_GA4_Internal_Tracking', 'handle_block_ip_ajax' ) );
add_action( Vinhxd_GA4_Internal_Tracking::GOOGLE_RANGES_CRON, array( 'Vinhxd_GA4_Internal_Tracking', 'refresh_google_crawler_ranges' ) );
add_action( Vinhxd_GA4_Internal_Tracking::GOOGLE_RANGES_CRON . '_once', array( 'Vinhxd_GA4_Internal_Tracking', 'refresh_google_crawler_ranges' ) );
