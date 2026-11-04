<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Meta box "XD Analytics - Thống kê bài viết" hiển thị ở màn hình edit gốc của WordPress
 * (không tích hợp Elementor editor). Dữ liệu tải qua AJAX sau khi trang đã load xong,
 * tránh làm chậm màn hình soạn thảo.
 */
class Vinhxd_GA4_Post_Metabox {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_vinhxd_ga4_post_stats', array( __CLASS__, 'ajax_post_stats' ) );
	}

	public static function register() {
		foreach ( Vinhxd_GA4_Post_Views::get_post_types() as $post_type ) {
			add_meta_box(
				'vinhxd_ga4_post_stats',
				'XD Analytics - Thống kê bài viết',
				array( __CLASS__, 'render_metabox' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;

		global $post;
		if ( ! $post || ! in_array( $post->post_type, Vinhxd_GA4_Post_Views::get_post_types(), true ) ) return;

		$property_id = get_option( 'vinhxd_ga4_property_id', '' );
		$sa_json     = get_option( 'vinhxd_ga4_service_account_json', '' );
		if ( empty( $property_id ) || empty( $sa_json ) ) return;

		wp_enqueue_script( 'vinhxd-chartjs', VINHXD_GA4_URL . 'assets/js/vendor/chart.umd.js', array(), '4.4.4', true );
		wp_enqueue_script( 'vinhxd-ga4-post-metabox', VINHXD_GA4_URL . 'assets/js/post-metabox.js', array( 'vinhxd-chartjs' ), VINHXD_GA4_VERSION, true );

		wp_localize_script( 'vinhxd-ga4-post-metabox', 'vinhxdGA4PostBox', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'vinhxd_ga4_post_stats' ),
			'post_id'  => $post->ID,
		) );
	}

	public static function render_metabox( $post ) {
		$property_id = get_option( 'vinhxd_ga4_property_id', '' );
		$sa_json     = get_option( 'vinhxd_ga4_service_account_json', '' );

		if ( empty( $property_id ) || empty( $sa_json ) ) {
			echo '<p>Chưa cấu hình GA4. Vào <a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-settings' ) ) . '">XD Analytics &rarr; Cài đặt</a>.</p>';
			return;
		}
		?>
		<div id="vinhxd-ga4-postbox">
			<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
				<div id="vinhxd-ga4-postbox-totals" style="display:flex; gap:20px; font-size:13px; color:#646970;">
					<span>Đang tải...</span>
				</div>
				<div>
					<button type="button" class="button button-primary button-small vinhxd-ga4-granularity" data-granularity="day">Theo ngày</button>
					<button type="button" class="button button-small vinhxd-ga4-granularity" data-granularity="month">Theo tháng</button>
				</div>
			</div>

			<div style="position:relative; height:260px;">
				<canvas id="vinhxd-ga4-postbox-chart"></canvas>
			</div>
			<p class="description" style="margin-top:6px;">Biểu đồ tính từ trước đến nay. Bài đăng lâu? Thử chuyển sang "Theo tháng" để dễ nhìn hơn.</p>

			<div style="display:flex; flex-wrap:wrap; gap:16px; margin-top:16px;">
				<div style="flex:1; min-width:200px;">
					<h4 style="margin:0 0 8px;">Link ra ngoài</h4>
					<div id="vinhxd-ga4-postbox-linkout"><p style="color:#787c82;">Đang tải...</p></div>
				</div>
				<div style="flex:1; min-width:200px;">
					<h4 style="margin:0 0 8px;">Chuyển đổi</h4>
					<div id="vinhxd-ga4-postbox-events"><p style="color:#787c82;">Đang tải...</p></div>
				</div>
				<div style="flex:1; min-width:220px;">
					<h4 style="margin:0 0 8px;">Nguồn truy cập <span style="font-weight:normal; color:#787c82; font-size:12px;">(khi bài này là trang đầu tiên)</span></h4>
					<div id="vinhxd-ga4-postbox-traffic"><p style="color:#787c82;">Đang tải...</p></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Trả về các biến thể đường dẫn (có/không dấu / cuối) của 1 bài, để khớp chính xác với
	 * cách GA4 ghi nhận pagePath (tránh trường hợp lệch nhau vì dấu / cuối URL).
	 */
	private static function path_variants( $post_id ) {
		$permalink = get_permalink( $post_id );
		$path = wp_parse_url( $permalink, PHP_URL_PATH );
		if ( ! $path ) return array( '/' );
		return array_values( array_unique( array( untrailingslashit( $path ), trailingslashit( $path ) ) ) );
	}

	public static function ajax_post_stats() {
		check_ajax_referer( 'vinhxd_ga4_post_stats', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'forbidden' );
		}

		$granularity = ( isset( $_POST['granularity'] ) && $_POST['granularity'] === 'month' ) ? 'month' : 'day';

		$property_id = get_option( 'vinhxd_ga4_property_id', '' );
		$sa_json     = get_option( 'vinhxd_ga4_service_account_json', '' );
		if ( empty( $property_id ) || empty( $sa_json ) ) {
			wp_send_json_error( 'GA4 chưa được cấu hình.' );
		}

		$paths = self::path_variants( $post_id );

		$cache_key = 'vinhxd_ga4_postbox_' . md5( $post_id . $granularity . implode( ',', $paths ) );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( $cached );
		}

		$client = new Vinhxd_GA4_Client( $sa_json, $property_id );
		$path_filter = array( 'filter' => array( 'fieldName' => 'pagePath', 'inListFilter' => array( 'values' => $paths ) ) );

		// 1) Tổng lượt xem + người dùng (toàn thời gian) của riêng bài này
		$totals_report = $client->run_report( array(
			'dateRanges'      => array( array( 'startDate' => '2015-08-14', 'endDate' => 'today' ) ),
			'metrics'         => array( array( 'name' => 'screenPageViews' ), array( 'name' => 'activeUsers' ) ),
			'dimensionFilter' => $path_filter,
		) );

		// 2) Biểu đồ xu hướng (toàn thời gian, theo ngày hoặc theo tháng tuỳ lựa chọn)
		$dim_name = ( $granularity === 'month' ) ? 'yearMonth' : 'date';
		$chart_report = $client->run_report( array(
			'dateRanges'      => array( array( 'startDate' => '2015-08-14', 'endDate' => 'today' ) ),
			'dimensions'      => array( array( 'name' => $dim_name ) ),
			'metrics'         => array( array( 'name' => 'screenPageViews' ), array( 'name' => 'activeUsers' ) ),
			'orderBys'        => array( array( 'dimension' => array( 'dimensionName' => $dim_name ) ) ),
			'dimensionFilter' => $path_filter,
		) );

		// 3) Link ra ngoài xảy ra khi đang đứng trên bài này
		$linkout_report = $client->run_report( array(
			'dateRanges'      => array( array( 'startDate' => '2015-08-14', 'endDate' => 'today' ) ),
			'dimensions'      => array( array( 'name' => 'customEvent:link_domain' ), array( 'name' => 'customEvent:link_url' ) ),
			'metrics'         => array( array( 'name' => 'eventCount' ) ),
			'dimensionFilter' => array( 'andGroup' => array( 'expressions' => array(
				array( 'filter' => array( 'fieldName' => 'eventName', 'stringFilter' => array( 'value' => 'click' ) ) ),
				$path_filter,
			) ) ),
			'orderBys' => array( array( 'metric' => array( 'metricName' => 'eventCount' ), 'desc' => true ) ),
			'limit'    => 5,
		) );

		// 4) Chuyển đổi xảy ra trên bài này
		$conv_rules = Vinhxd_GA4_Conversion_Tracking::get_conversions();
		$events_data = array();
		if ( ! empty( $conv_rules ) ) {
			$event_names = wp_list_pluck( $conv_rules, 'event_name' );
			$events_report = $client->run_report( array(
				'dateRanges'      => array( array( 'startDate' => '2015-08-14', 'endDate' => 'today' ) ),
				'dimensions'      => array( array( 'name' => 'eventName' ) ),
				'metrics'         => array( array( 'name' => 'eventCount' ) ),
				'dimensionFilter' => array( 'andGroup' => array( 'expressions' => array(
					array( 'filter' => array( 'fieldName' => 'eventName', 'inListFilter' => array( 'values' => $event_names ) ) ),
					$path_filter,
				) ) ),
				'limit' => 1000,
			) );

			$count_by_event = array();
			if ( ! is_wp_error( $events_report ) && ! empty( $events_report['rows'] ) ) {
				foreach ( $events_report['rows'] as $row ) {
					$count_by_event[ $row['dimensionValues'][0]['value'] ] = (int) $row['metricValues'][0]['value'];
				}
			}
			foreach ( $conv_rules as $rule ) {
				$events_data[] = array(
					'label' => $rule['label'],
					'count' => $count_by_event[ $rule['event_name'] ] ?? 0,
				);
			}
		}

		// 5) Nguồn truy cập - chỉ tính các phiên mà bài này là landingPage (trang đầu tiên của phiên)
		$traffic_report = $client->run_report( array(
			'dateRanges'      => array( array( 'startDate' => '2015-08-14', 'endDate' => 'today' ) ),
			'dimensions'      => array( array( 'name' => 'sessionSourceMedium' ) ),
			'metrics'         => array( array( 'name' => 'sessions' ) ),
			'orderBys'        => array( array( 'metric' => array( 'metricName' => 'sessions' ), 'desc' => true ) ),
			'dimensionFilter' => array( 'filter' => array( 'fieldName' => 'landingPage', 'inListFilter' => array( 'values' => $paths ) ) ),
			'limit'           => 5,
		) );

		// Lỗi API (token, quota, mạng...) thì báo lỗi và KHÔNG cache - tránh hiện số 0 sai suốt 6 tiếng
		if ( is_wp_error( $totals_report ) ) {
			wp_send_json_error( 'Lỗi GA4: ' . $totals_report->get_error_message() );
		}

		// ----- Gộp kết quả -----
		$result = array();

		$result['totals'] = array( 'views' => 0, 'users' => 0 );
		if ( ! is_wp_error( $totals_report ) && ! empty( $totals_report['rows'][0]['metricValues'] ) ) {
			$mv = $totals_report['rows'][0]['metricValues'];
			$result['totals'] = array( 'views' => (int) $mv[0]['value'], 'users' => (int) $mv[1]['value'] );
		}

		$chart_labels = array(); $chart_views = array(); $chart_users = array();
		if ( ! is_wp_error( $chart_report ) && ! empty( $chart_report['rows'] ) ) {
			foreach ( $chart_report['rows'] as $row ) {
				$raw = $row['dimensionValues'][0]['value'];
				if ( $granularity === 'month' ) {
					$dt = DateTime::createFromFormat( 'Ym', $raw );
					$label = $dt ? $dt->format( 'm/Y' ) : $raw;
				} else {
					$dt = DateTime::createFromFormat( 'Ymd', $raw );
					$label = $dt ? $dt->format( 'd/m' ) : $raw;
				}
				$chart_labels[] = $label;
				$chart_views[]  = (int) $row['metricValues'][0]['value'];
				$chart_users[]  = (int) $row['metricValues'][1]['value'];
			}
		}
		$result['chart'] = array( 'labels' => $chart_labels, 'views' => $chart_views, 'users' => $chart_users );

		$linkout_data = array();
		if ( ! is_wp_error( $linkout_report ) && ! empty( $linkout_report['rows'] ) ) {
			foreach ( $linkout_report['rows'] as $row ) {
				$linkout_data[] = array(
					'domain' => $row['dimensionValues'][0]['value'],
					'url'    => $row['dimensionValues'][1]['value'],
					'count'  => (int) $row['metricValues'][0]['value'],
				);
			}
		}
		$result['linkout'] = $linkout_data;
		$result['events']  = $events_data;

		$traffic_data = array();
		if ( ! is_wp_error( $traffic_report ) && ! empty( $traffic_report['rows'] ) ) {
			foreach ( $traffic_report['rows'] as $row ) {
				$traffic_data[] = array(
					'source'   => $row['dimensionValues'][0]['value'],
					'sessions' => (int) $row['metricValues'][0]['value'],
				);
			}
		}
		$result['traffic_sources'] = $traffic_data;

		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );

		wp_send_json_success( $result );
	}
}

Vinhxd_GA4_Post_Metabox::init();
