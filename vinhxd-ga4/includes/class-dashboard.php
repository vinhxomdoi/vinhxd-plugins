<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Vinhxd_GA4_Dashboard {

	private static function get_date_range( $range_key ) {
		switch ( $range_key ) {
			case 'today':
				return array( 'today', 'today', 'Hôm nay' );
			case '7days':
				return array( '7daysAgo', 'today', '7 ngày qua' );
			case '30days':
				return array( '30daysAgo', 'today', '30 ngày qua' );
			case 'yesterday':
			default:
				return array( 'yesterday', 'yesterday', 'Hôm qua' );
		}
	}

	private static function get_chart_range( $chart_range_key ) {
		switch ( $chart_range_key ) {
			case '7days':
				return array( '7daysAgo', 'today', '7 ngày qua' );
			case '90days':
				return array( '90daysAgo', 'today', '90 ngày qua' );
			case '30days':
			default:
				return array( '30daysAgo', 'today', '30 ngày qua' );
		}
	}

	private static function fmt_duration( $seconds ) {
		$seconds = (int) round( (float) $seconds );
		if ( $seconds <= 0 ) return '-';
		$m = floor( $seconds / 60 );
		$s = $seconds % 60;
		return sprintf( '%d:%02d', $m, $s );
	}

	private static function fmt_percent( $value ) {
		if ( $value === null || $value === '' ) return '-';
		return round( (float) $value * 100 ) . '%';
	}

	private static function render_stat_card( $label, $value ) {
		return '<div style="flex:1; min-width:140px; background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:14px 16px;">'
			. '<div style="font-size:22px; font-weight:600; color:#1d2327;">' . esc_html( $value ) . '</div>'
			. '<div style="color:#646970; font-size:13px; margin-top:2px;">' . esc_html( $label ) . '</div>'
			. '</div>';
	}

	private static function render_stat_card_v2( $icon, $color, $label, $value, $delta_percent, $lower_is_better = false ) {
		$delta_html = '';
		if ( $delta_percent !== null ) {
			$up = $delta_percent >= 0;
			$arrow = $up ? '&uarr;' : '&darr;';
			// Chỉ số kiểu "càng thấp càng tốt" (VD tỷ lệ thoát): tăng là xấu => màu đỏ
			$good = $lower_is_better ? ! $up : $up;
			$delta_color = $good ? '#00a32a' : '#d63638';
			$delta_html = '<div style="color:' . $delta_color . '; font-size:12px; margin-top:6px;">' . $arrow . ' ' . number_format_i18n( abs( $delta_percent ), 1 ) . '%<span style="color:#8a8d91;"> so với kỳ trước</span></div>';
		}

		return '<div style="flex:1; min-width:150px; background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:16px 18px;">'
			. '<div style="display:flex; align-items:center; gap:6px;">'
			. '<span class="dashicons ' . esc_attr( $icon ) . '" style="color:' . esc_attr( $color ) . '; font-size:18px; width:18px; height:18px;"></span>'
			. '<span style="font-size:13px; color:#646970;">' . esc_html( $label ) . '</span>'
			. '</div>'
			. '<div style="font-size:26px; font-weight:600; color:#1d2327; margin-top:6px;">' . esc_html( $value ) . '</div>'
			. $delta_html
			. '</div>';
	}

	private static function calc_delta( $current, $previous ) {
		$previous = (float) $previous;
		if ( $previous <= 0 ) return null;
		return ( ( (float) $current - $previous ) / $previous ) * 100;
	}

	private static function compute_period_dates( $days ) {
		$tz = wp_timezone();
		$end_cur   = new DateTime( 'today', $tz );
		$start_cur = ( clone $end_cur )->modify( '-' . ( $days - 1 ) . ' days' );
		$end_prev  = ( clone $start_cur )->modify( '-1 day' );
		$start_prev = ( clone $end_prev )->modify( '-' . ( $days - 1 ) . ' days' );

		return array(
			$start_cur->format( 'Y-m-d' ),
			$end_cur->format( 'Y-m-d' ),
			$start_prev->format( 'Y-m-d' ),
			$end_prev->format( 'Y-m-d' ),
		);
	}

	/**
	 * Gọi 1 report GA4 duy nhất chứa cả kỳ hiện tại + kỳ trước.
	 * LƯU Ý: không được liệt kê 'dateRange' trong 'dimensions' - GA4 tự thêm dimension này vào
	 * response khi có từ 2 dateRanges trở lên, khai báo tường minh sẽ bị lỗi "not a dimension".
	 */
	private static function fetch_comparison_totals( $client, $property_id, $chart_range_key ) {
		$days_map = array( '7days' => 7, '30days' => 30, '90days' => 90 );
		$days = $days_map[ $chart_range_key ] ?? 30;

		list( $start_cur, $end_cur, $start_prev, $end_prev ) = self::compute_period_dates( $days );

		$cache_key = 'vinhxd_ga4_cmp_' . md5( $property_id . $chart_range_key );
		$report = get_transient( $cache_key );
		if ( false === $report ) {
			$report = $client->run_report( array(
				'dateRanges' => array(
					array( 'startDate' => $start_cur, 'endDate' => $end_cur ),
					array( 'startDate' => $start_prev, 'endDate' => $end_prev ),
				),
				'metrics' => array(
					array( 'name' => 'activeUsers' ),
					array( 'name' => 'sessions' ),
					array( 'name' => 'screenPageViews' ),
					array( 'name' => 'averageSessionDuration' ),
					array( 'name' => 'bounceRate' ),
				),
			) );
			if ( ! is_wp_error( $report ) ) set_transient( $cache_key, $report, 15 * MINUTE_IN_SECONDS );
		}
		return $report;
	}

	private static function fetch_chart_report( $client, $property_id, $start, $end, $cache_suffix ) {
		$cache_key = 'vinhxd_ga4_trend_' . md5( $property_id . $cache_suffix );
		$report = get_transient( $cache_key );
		if ( false === $report ) {
			$report = $client->run_report( array(
				'dateRanges' => array( array( 'startDate' => $start, 'endDate' => $end ) ),
				'dimensions' => array( array( 'name' => 'date' ) ),
				'metrics'    => array( array( 'name' => 'screenPageViews' ), array( 'name' => 'activeUsers' ) ),
				'orderBys'   => array( array( 'dimension' => array( 'dimensionName' => 'date' ) ) ),
			) );
			if ( ! is_wp_error( $report ) ) set_transient( $cache_key, $report, 15 * MINUTE_IN_SECONDS );
		}
		return $report;
	}

	private static function build_chart_points( $report ) {
		$labels = array(); $views = array(); $users = array();
		if ( is_wp_error( $report ) || empty( $report['rows'] ) ) {
			return array( $labels, $views, $users );
		}
		foreach ( $report['rows'] as $row ) {
			$date_raw = $row['dimensionValues'][0]['value'];
			$date_obj = DateTime::createFromFormat( 'Ymd', $date_raw );
			$labels[] = $date_obj ? $date_obj->format( 'd/m' ) : $date_raw;
			$views[]  = (int) $row['metricValues'][0]['value'];
			$users[]  = (int) $row['metricValues'][1]['value'];
		}
		return array( $labels, $views, $users );
	}

	public static function enqueue_assets( $hook ) {
		if ( $hook !== 'toplevel_page_vinhxd-ga4-dashboard' && $hook !== 'index.php' ) return;
		if ( $hook === 'index.php' && ! current_user_can( 'manage_options' ) ) return;

		wp_enqueue_script( 'vinhxd-chartjs', VINHXD_GA4_URL . 'assets/js/vendor/chart.umd.js', array(), '4.4.4', true );
		wp_enqueue_script( 'vinhxd-ga4-dashboard-chart', VINHXD_GA4_URL . 'assets/js/dashboard-chart.js', array( 'vinhxd-chartjs' ), VINHXD_GA4_VERSION, true );
	}

	public static function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		wp_add_dashboard_widget( 'vinhxd_ga4_widget', 'XD Analytics - Lượt xem & Người dùng (30 ngày qua)', array( __CLASS__, 'render_widget' ) );
	}

	public static function render_widget() {
		$property_id = get_option( 'vinhxd_ga4_property_id', '' );
		$sa_json     = get_option( 'vinhxd_ga4_service_account_json', '' );

		if ( empty( $property_id ) || empty( $sa_json ) ) {
			echo '<p>Chưa cấu hình GA4. <a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-settings' ) ) . '">Vào Cài đặt</a>.</p>';
			return;
		}

		$client = new Vinhxd_GA4_Client( $sa_json, $property_id );
		$report = self::fetch_chart_report( $client, $property_id, '30daysAgo', 'today', '30days' );

		if ( is_wp_error( $report ) ) {
			echo '<p>Lỗi GA4: ' . esc_html( $report->get_error_message() ) . '</p>';
			return;
		}
		if ( empty( $report['rows'] ) ) {
			echo '<p>Không có dữ liệu.</p>';
			return;
		}

		list( $labels, $views, $users ) = self::build_chart_points( $report );

		echo '<div style="position:relative; height:220px;"><canvas id="vinhxd-ga4-widget-chart"></canvas></div>';
		echo '<p style="margin-top:10px;"><a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-dashboard' ) ) . '">Xem đầy đủ báo cáo &rarr;</a></p>';

		wp_add_inline_script(
			'vinhxd-ga4-dashboard-chart',
			'window.vinhxdGA4WidgetChartData = ' . wp_json_encode( array(
				'labels'    => $labels,
				'pageviews' => $views,
				'users'     => $users,
			) ) . ';',
			'before'
		);
	}

	/**
	 * =========================================================
	 * TRANG "TỔNG QUAN": cards + biểu đồ + top 5 trang + Nguồn traffic/Chuyển đổi/Link ra ngoài
	 * =========================================================
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$property_id = get_option( 'vinhxd_ga4_property_id', '' );
		$sa_json     = get_option( 'vinhxd_ga4_service_account_json', '' );

		echo '<div class="wrap">';

		if ( empty( $property_id ) || empty( $sa_json ) ) {
			echo '<h1>XD Analytics - Tổng quan</h1>';
			echo '<div class="notice notice-warning"><p>Chưa cấu hình Property ID / Service Account JSON. Vào <a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-settings' ) ) . '">Cài đặt</a> để kết nối GA4.</p></div></div>';
			return;
		}

		$client = new Vinhxd_GA4_Client( $sa_json, $property_id );

		$chart_range_key = isset( $_GET['chart_range'] ) ? sanitize_text_field( $_GET['chart_range'] ) : '30days';
		list( $chart_start, $chart_end, $chart_label ) = self::get_chart_range( $chart_range_key );

		// ----- Header -----
		echo '<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">';
		echo '<h1 style="margin:0;">Tổng quan</h1>';
		echo '<form method="get" style="margin:0;">';
		echo '<input type="hidden" name="page" value="vinhxd-ga4-dashboard" />';
		echo '<span class="dashicons dashicons-calendar-alt" style="vertical-align:middle; color:#646970;"></span> ';
		echo '<select name="chart_range" onchange="this.form.submit()" style="border-radius:6px;">';
		foreach ( array( '7days' => '7 ngày qua', '30days' => '30 ngày qua', '90days' => '90 ngày qua' ) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $chart_range_key, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></form>';
		echo '</div>';

		// ----- 5 thẻ số liệu -----
		$comparison_report = self::fetch_comparison_totals( $client, $property_id, $chart_range_key );

		$current = null; $previous = null;
		if ( ! is_wp_error( $comparison_report ) && ! empty( $comparison_report['rows'] ) ) {
			foreach ( $comparison_report['rows'] as $row ) {
				$range_id = $row['dimensionValues'][0]['value'];
				$vals = wp_list_pluck( $row['metricValues'], 'value' );
				if ( $range_id === 'date_range_0' ) $current = $vals;
				elseif ( $range_id === 'date_range_1' ) $previous = $vals;
			}
		}

		if ( is_wp_error( $comparison_report ) ) {
			echo '<div class="notice notice-error"><p>Lỗi GA4: ' . esc_html( $comparison_report->get_error_message() ) . '</p></div>';
		} elseif ( $current ) {
			$p = $previous;
			echo '<div style="display:flex; flex-wrap:wrap; gap:14px; margin-bottom:24px;">';
			echo self::render_stat_card_v2( 'dashicons-admin-users', '#00a32a', 'Người dùng', number_format_i18n( $current[0] ), $p ? self::calc_delta( $current[0], $p[0] ) : null );
			echo self::render_stat_card_v2( 'dashicons-groups', '#2271b1', 'Phiên', number_format_i18n( $current[1] ), $p ? self::calc_delta( $current[1], $p[1] ) : null );
			echo self::render_stat_card_v2( 'dashicons-visibility', '#8c5fd6', 'Lượt xem', number_format_i18n( $current[2] ), $p ? self::calc_delta( $current[2], $p[2] ) : null );
			echo self::render_stat_card_v2( 'dashicons-clock', '#dba617', 'Thời lượng phiên TB', self::fmt_duration( $current[3] ), $p ? self::calc_delta( $current[3], $p[3] ) : null );
			echo self::render_stat_card_v2( 'dashicons-migrate', '#d63638', 'Tỷ lệ thoát', self::fmt_percent( $current[4] ), $p ? self::calc_delta( $current[4], $p[4] ) : null, true );
			echo '</div>';
		}

		// ----- Khối 2 cột: Biểu đồ + Top 5 trang -----
		$chart_report = self::fetch_chart_report( $client, $property_id, $chart_start, $chart_end, $chart_range_key );

		$mini_pages_cache_key = 'vinhxd_ga4_minipages_' . md5( $property_id . $chart_range_key );
		$mini_pages_report = get_transient( $mini_pages_cache_key );
		if ( false === $mini_pages_report ) {
			$mini_pages_report = $client->run_report( array(
				'dateRanges' => array( array( 'startDate' => $chart_start, 'endDate' => $chart_end ) ),
				'dimensions' => array( array( 'name' => 'pagePath' ) ),
				'metrics'    => array( array( 'name' => 'screenPageViews' ) ),
				'orderBys'   => array( array( 'metric' => array( 'metricName' => 'screenPageViews' ), 'desc' => true ) ),
				'limit'      => 5,
			) );
			if ( ! is_wp_error( $mini_pages_report ) ) set_transient( $mini_pages_cache_key, $mini_pages_report, 15 * MINUTE_IN_SECONDS );
		}

		echo '<div style="display:flex; flex-wrap:wrap; background:#fff; border:1px solid #dcdcde; border-radius:8px; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.04);">';

		echo '<div style="flex:2 1 500px; min-width:320px; padding:20px; border-right:1px solid #eee;">';
		echo '<h2 style="margin-top:0;">Lượt xem &amp; Người dùng</h2>';

		if ( is_wp_error( $chart_report ) ) {
			echo '<div class="notice notice-error"><p>Lỗi GA4: ' . esc_html( $chart_report->get_error_message() ) . '</p></div>';
		} elseif ( empty( $chart_report['rows'] ) ) {
			echo '<p>Không có dữ liệu.</p>';
		} else {
			list( $chart_labels, $chart_views, $chart_users ) = self::build_chart_points( $chart_report );

			echo '<div style="position:relative; height:320px;"><canvas id="vinhxd-ga4-chart"></canvas></div>';

			wp_add_inline_script(
				'vinhxd-ga4-dashboard-chart',
				'window.vinhxdGA4ChartData = ' . wp_json_encode( array(
					'labels'    => $chart_labels,
					'pageviews' => $chart_views,
					'users'     => $chart_users,
				) ) . ';',
				'before'
			);
		}
		echo '</div>';

		echo '<div style="flex:1 1 280px; min-width:260px; padding:20px; display:flex; flex-direction:column;">';
		echo '<h2 style="margin-top:0;">Top trang xem nhiều nhất</h2>';

		if ( is_wp_error( $mini_pages_report ) ) {
			echo '<div class="notice notice-error"><p>Lỗi GA4: ' . esc_html( $mini_pages_report->get_error_message() ) . '</p></div>';
		} elseif ( empty( $mini_pages_report['rows'] ) ) {
			echo '<p>Không có dữ liệu.</p>';
		} else {
			echo '<div style="flex:1;">';
			foreach ( $mini_pages_report['rows'] as $row ) {
				$path  = $row['dimensionValues'][0]['value'];
				$views = $row['metricValues'][0]['value'];
				echo '<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f0f0f1;">';
				echo '<span style="color:#1d2327; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:170px;" title="' . esc_attr( $path ) . '">' . esc_html( $path ) . '</span>';
				echo '<span style="color:#00a32a; font-weight:600; font-size:13px;">' . esc_html( number_format_i18n( $views ) ) . '</span>';
				echo '</div>';
			}
			echo '</div>';
		}

		echo '<div style="margin-top:14px;"><a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-daily' ) ) . '" class="button">Xem tất cả</a></div>';
		echo '</div>';

		echo '</div>'; // đóng khối 2 cột

		// ----- Khối 3 cột: Nguồn Traffic (donut) + Chuyển đổi + Link ra ngoài -----
		echo '<div style="display:flex; flex-wrap:wrap; gap:16px; margin-top:16px;">';

		// --- Nguồn Traffic ---
		$donut_cache_key = 'vinhxd_ga4_donut_' . md5( $property_id . $chart_range_key );
		$donut_report = get_transient( $donut_cache_key );
		if ( false === $donut_report ) {
			$donut_report = $client->run_report( array(
				'dateRanges' => array( array( 'startDate' => $chart_start, 'endDate' => $chart_end ) ),
				'dimensions' => array( array( 'name' => 'sessionSourceMedium' ) ),
				'metrics'    => array( array( 'name' => 'sessions' ) ),
				'orderBys'   => array( array( 'metric' => array( 'metricName' => 'sessions' ), 'desc' => true ) ),
				'limit'      => 20,
			) );
			if ( ! is_wp_error( $donut_report ) ) set_transient( $donut_cache_key, $donut_report, 15 * MINUTE_IN_SECONDS );
		}

		echo '<div style="flex:1 1 300px; min-width:280px; background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:18px;">';
		echo '<h2 style="margin-top:0;">Nguồn Traffic</h2>';

		if ( is_wp_error( $donut_report ) ) {
			echo '<div class="notice notice-error"><p>Lỗi GA4: ' . esc_html( $donut_report->get_error_message() ) . '</p></div>';
		} elseif ( empty( $donut_report['rows'] ) ) {
			echo '<p>Không có dữ liệu.</p>';
		} else {
			$palette = array( '#e34c8c', '#2271b1', '#f0a83c', '#26a69a', '#c7c7c7' );
			$rows = $donut_report['rows'];
			$total = 0;
			foreach ( $rows as $row ) $total += (int) $row['metricValues'][0]['value'];

			$top_rows = array_slice( $rows, 0, 4 );
			$rest_sum = 0;
			foreach ( array_slice( $rows, 4 ) as $row ) $rest_sum += (int) $row['metricValues'][0]['value'];

			$donut_labels = array(); $donut_values = array(); $donut_colors = array();
			echo '<div style="display:flex; align-items:center; gap:20px;">';
			echo '<div style="position:relative; width:110px; height:110px; flex-shrink:0;"><canvas id="vinhxd-ga4-donut"></canvas></div>';
			echo '<ul style="list-style:none; margin:0; padding:0; flex:1;">';
			foreach ( $top_rows as $i => $row ) {
				$label = $row['dimensionValues'][0]['value'];
				$val   = (int) $row['metricValues'][0]['value'];
				$pct   = $total > 0 ? round( $val / $total * 100, 1 ) : 0;
				$donut_labels[] = $label; $donut_values[] = $val; $donut_colors[] = $palette[ $i ];
				echo '<li style="display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:6px;">';
				echo '<span style="width:9px; height:9px; border-radius:50%; background:' . esc_attr( $palette[ $i ] ) . '; flex-shrink:0;"></span>';
				echo '<span style="flex:1; color:#1d2327; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . esc_html( $label ) . '</span>';
				echo '<span style="color:#646970;">' . esc_html( $pct ) . '%</span>';
				echo '</li>';
			}
			if ( $rest_sum > 0 ) {
				$pct = $total > 0 ? round( $rest_sum / $total * 100, 1 ) : 0;
				$donut_labels[] = 'Khác'; $donut_values[] = $rest_sum; $donut_colors[] = $palette[4];
				echo '<li style="display:flex; align-items:center; gap:8px; font-size:13px;">';
				echo '<span style="width:9px; height:9px; border-radius:50%; background:' . esc_attr( $palette[4] ) . '; flex-shrink:0;"></span>';
				echo '<span style="flex:1; color:#1d2327;">Khác</span>';
				echo '<span style="color:#646970;">' . esc_html( $pct ) . '%</span>';
				echo '</li>';
			}
			echo '</ul></div>';

			wp_add_inline_script(
				'vinhxd-ga4-dashboard-chart',
				'window.vinhxdGA4DonutData = ' . wp_json_encode( array(
					'labels' => $donut_labels,
					'values' => $donut_values,
					'colors' => $donut_colors,
				) ) . ';',
				'before'
			);
		}
		echo '</div>';

		// --- Chuyển đổi ---
		echo '<div style="flex:1 1 260px; min-width:240px; background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:18px;">';
		echo '<h2 style="margin-top:0;">Chuyển đổi</h2>';

		$conv_rules = Vinhxd_GA4_Conversion_Tracking::get_conversions();
		if ( empty( $conv_rules ) ) {
			echo '<p class="description">Chưa khai báo chuyển đổi nào. Vào <a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-conversions' ) ) . '">Chuyển đổi</a> để thêm.</p>';
		} else {
			$event_names = wp_list_pluck( $conv_rules, 'event_name' );
			$conv_sum_cache_key = 'vinhxd_ga4_convsum_' . md5( $property_id . $chart_range_key . implode( ',', $event_names ) );
			$conv_sum_report = get_transient( $conv_sum_cache_key );
			if ( false === $conv_sum_report ) {
				$conv_sum_report = $client->run_report( array(
					'dateRanges'      => array( array( 'startDate' => $chart_start, 'endDate' => $chart_end ) ),
					'dimensions'      => array( array( 'name' => 'eventName' ) ),
					'metrics'         => array( array( 'name' => 'eventCount' ) ),
					'dimensionFilter' => array(
						'filter' => array(
							'fieldName'    => 'eventName',
							'inListFilter' => array( 'values' => $event_names ),
						),
					),
					'limit' => 1000,
				) );
				if ( ! is_wp_error( $conv_sum_report ) ) set_transient( $conv_sum_cache_key, $conv_sum_report, 15 * MINUTE_IN_SECONDS );
			}

			$count_by_event = array();
			if ( ! is_wp_error( $conv_sum_report ) && ! empty( $conv_sum_report['rows'] ) ) {
				foreach ( $conv_sum_report['rows'] as $row ) {
					$count_by_event[ $row['dimensionValues'][0]['value'] ] = (int) $row['metricValues'][0]['value'];
				}
			}

			echo '<ul style="list-style:none; margin:0; padding:0;">';
			foreach ( $conv_rules as $rule ) {
				$icon = $rule['trigger'] === 'submit' ? 'dashicons-feedback' : 'dashicons-admin-links';
				$count = $count_by_event[ $rule['event_name'] ] ?? 0;
				echo '<li style="display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid #f0f0f1;">';
				echo '<span class="dashicons ' . esc_attr( $icon ) . '" style="color:#646970;"></span>';
				echo '<span style="flex:1; color:#1d2327; font-size:13px;">' . esc_html( $rule['label'] ) . '</span>';
				echo '<span style="color:#1d2327; font-weight:600; font-size:13px;">' . esc_html( number_format_i18n( $count ) ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		// --- Link ra ngoài ---
		echo '<div style="flex:1 1 260px; min-width:240px; background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:18px;">';
		echo '<h2 style="margin-top:0;">Link ra ngoài</h2>';

		$outdomain_cache_key = 'vinhxd_ga4_outdomain_' . md5( $property_id . $chart_range_key );
		$outdomain_report = get_transient( $outdomain_cache_key );
		if ( false === $outdomain_report ) {
			$outdomain_report = $client->run_report( array(
				'dateRanges'      => array( array( 'startDate' => $chart_start, 'endDate' => $chart_end ) ),
				'dimensions'      => array( array( 'name' => 'customEvent:link_domain' ) ),
				'metrics'         => array( array( 'name' => 'eventCount' ) ),
				'dimensionFilter' => array(
					'filter' => array(
						'fieldName'    => 'eventName',
						'stringFilter' => array( 'value' => 'click' ),
					),
				),
				'orderBys' => array( array( 'metric' => array( 'metricName' => 'eventCount' ), 'desc' => true ) ),
				'limit'    => 5,
			) );
			if ( ! is_wp_error( $outdomain_report ) ) set_transient( $outdomain_cache_key, $outdomain_report, 15 * MINUTE_IN_SECONDS );
		}

		if ( is_wp_error( $outdomain_report ) || empty( $outdomain_report['rows'] ) ) {
			echo '<p class="description">Chưa có dữ liệu. Xem hướng dẫn thiết lập ở trang <a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-daily' ) ) . '">Data theo ngày</a>.</p>';
		} else {
			echo '<ul style="list-style:none; margin:0; padding:0;">';
			foreach ( $outdomain_report['rows'] as $row ) {
				$domain = $row['dimensionValues'][0]['value'];
				$count  = $row['metricValues'][0]['value'];
				echo '<li style="display:flex; align-items:center; justify-content:space-between; padding:7px 0; border-bottom:1px solid #f0f0f1;">';
				echo '<span style="color:#1d2327; font-size:13px;">' . esc_html( $domain ) . '</span>';
				echo '<span style="color:#00a32a; font-weight:600; font-size:13px;">' . esc_html( number_format_i18n( $count ) ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		echo '</div>'; // đóng khối 3 cột

		echo '</div>'; // đóng .wrap
	}

	/**
	 * =========================================================
	 * TRANG "DATA THEO NGÀY" (không có biểu đồ)
	 * =========================================================
	 */
	public static function render_daily() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$property_id = get_option( 'vinhxd_ga4_property_id', '' );
		$sa_json     = get_option( 'vinhxd_ga4_service_account_json', '' );

		echo '<div class="wrap"><h1>XD Analytics - Data theo ngày</h1>';

		if ( empty( $property_id ) || empty( $sa_json ) ) {
			echo '<div class="notice notice-warning"><p>Chưa cấu hình Property ID / Service Account JSON. Vào <a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-settings' ) ) . '">Cài đặt</a> để kết nối GA4.</p></div></div>';
			return;
		}

		$client = new Vinhxd_GA4_Client( $sa_json, $property_id );

		$range_key = isset( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : 'yesterday';
		list( $start_date, $end_date, $range_label ) = self::get_date_range( $range_key );

		echo '<form method="get" style="margin:16px 0;">';
		echo '<input type="hidden" name="page" value="vinhxd-ga4-daily" />';
		echo '<select name="range" onchange="this.form.submit()">';
		foreach ( array( 'today' => 'Hôm nay', 'yesterday' => 'Hôm qua', '7days' => '7 ngày qua', '30days' => '30 ngày qua' ) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $range_key, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></form>';

		$totals_cache_key = 'vinhxd_ga4_totals_' . md5( $property_id . $range_key );
		$totals_report = get_transient( $totals_cache_key );
		if ( false === $totals_report ) {
			$totals_report = $client->run_report( array(
				'dateRanges' => array( array( 'startDate' => $start_date, 'endDate' => $end_date ) ),
				'metrics'    => array(
					array( 'name' => 'activeUsers' ),
					array( 'name' => 'sessions' ),
					array( 'name' => 'screenPageViews' ),
					array( 'name' => 'averageSessionDuration' ),
					array( 'name' => 'bounceRate' ),
				),
			) );
			if ( ! is_wp_error( $totals_report ) ) set_transient( $totals_cache_key, $totals_report, 15 * MINUTE_IN_SECONDS );
		}

		if ( is_wp_error( $totals_report ) ) {
			echo '<div class="notice notice-error"><p>Lỗi GA4: ' . esc_html( $totals_report->get_error_message() ) . '</p></div>';
		} elseif ( ! empty( $totals_report['rows'][0]['metricValues'] ) ) {
			$m = $totals_report['rows'][0]['metricValues'];
			echo '<div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:24px;">';
			echo self::render_stat_card( 'Người dùng', number_format_i18n( $m[0]['value'] ) );
			echo self::render_stat_card( 'Phiên (Sessions)', number_format_i18n( $m[1]['value'] ) );
			echo self::render_stat_card( 'Lượt xem', number_format_i18n( $m[2]['value'] ) );
			echo self::render_stat_card( 'Thời lượng phiên TB', self::fmt_duration( $m[3]['value'] ) );
			echo self::render_stat_card( 'Tỷ lệ thoát', self::fmt_percent( $m[4]['value'] ) );
			echo '</div>';
		}

		$conversion_rules = Vinhxd_GA4_Conversion_Tracking::get_conversions();
		$conversions_by_path = array();

		if ( ! empty( $conversion_rules ) ) {
			$event_names = wp_list_pluck( $conversion_rules, 'event_name' );
			$conv_cache_key = 'vinhxd_ga4_convs_' . md5( $property_id . $range_key . implode( ',', $event_names ) );
			$conv_report = get_transient( $conv_cache_key );

			if ( false === $conv_report ) {
				$conv_report = $client->run_report( array(
					'dateRanges'      => array( array( 'startDate' => $start_date, 'endDate' => $end_date ) ),
					'dimensions'      => array( array( 'name' => 'pagePath' ), array( 'name' => 'eventName' ) ),
					'metrics'         => array( array( 'name' => 'eventCount' ) ),
					'dimensionFilter' => array(
						'filter' => array(
							'fieldName'     => 'eventName',
							'inListFilter'  => array( 'values' => $event_names ),
						),
					),
					'limit'           => 1000,
				) );
				if ( ! is_wp_error( $conv_report ) ) set_transient( $conv_cache_key, $conv_report, 15 * MINUTE_IN_SECONDS );
			}

			if ( ! is_wp_error( $conv_report ) && ! empty( $conv_report['rows'] ) ) {
				foreach ( $conv_report['rows'] as $row ) {
					$path  = $row['dimensionValues'][0]['value'];
					$count = (int) $row['metricValues'][0]['value'];
					$conversions_by_path[ $path ] = ( $conversions_by_path[ $path ] ?? 0 ) + $count;
				}
			}
		}

		$pages_cache_key = 'vinhxd_ga4_pages_' . md5( $property_id . $range_key );
		$pages_report = get_transient( $pages_cache_key );
		if ( false === $pages_report ) {
			$pages_report = $client->run_report( array(
				'dateRanges' => array( array( 'startDate' => $start_date, 'endDate' => $end_date ) ),
				'dimensions' => array( array( 'name' => 'pageTitle' ), array( 'name' => 'pagePath' ) ),
				'metrics'    => array(
					array( 'name' => 'activeUsers' ),
					array( 'name' => 'screenPageViews' ),
					array( 'name' => 'averageSessionDuration' ),
					array( 'name' => 'bounceRate' ),
				),
				'orderBys'   => array( array( 'metric' => array( 'metricName' => 'activeUsers' ), 'desc' => true ) ),
				'limit'      => 10,
			) );
			if ( ! is_wp_error( $pages_report ) ) set_transient( $pages_cache_key, $pages_report, 15 * MINUTE_IN_SECONDS );
		}

		$traffic_cache_key = 'vinhxd_ga4_traffic_' . md5( $property_id . $range_key );
		$traffic_report = get_transient( $traffic_cache_key );
		if ( false === $traffic_report ) {
			$traffic_report = $client->run_report( array(
				'dateRanges' => array( array( 'startDate' => $start_date, 'endDate' => $end_date ) ),
				'dimensions' => array( array( 'name' => 'sessionSource' ), array( 'name' => 'sessionDefaultChannelGroup' ) ),
				'metrics'    => array(
					array( 'name' => 'activeUsers' ),
					array( 'name' => 'screenPageViews' ),
					array( 'name' => 'averageSessionDuration' ),
					array( 'name' => 'bounceRate' ),
				),
				'orderBys'   => array( array( 'metric' => array( 'metricName' => 'activeUsers' ), 'desc' => true ) ),
				'limit'      => 10,
			) );
			if ( ! is_wp_error( $traffic_report ) ) set_transient( $traffic_cache_key, $traffic_report, 15 * MINUTE_IN_SECONDS );
		}

		echo '<div style="display:flex; flex-wrap:wrap; gap:24px; margin-top:8px; align-items:flex-start;">';

		echo '<div style="flex:1 1 460px; min-width:320px;">';
		echo '<h2>Top trang xem nhiều nhất (' . esc_html( $range_label ) . ')</h2>';

		if ( is_wp_error( $pages_report ) ) {
			echo '<div class="notice notice-error"><p>Lỗi GA4: ' . esc_html( $pages_report->get_error_message() ) . '</p></div>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>#</th><th>Tiêu đề</th><th>Khách</th><th>Lượt xem</th><th>Thời lượng</th><th>Thoát</th><th>C.đổi</th>';
			echo '</tr></thead><tbody>';

			if ( empty( $pages_report['rows'] ) ) {
				echo '<tr><td colspan="7">Không có dữ liệu.</td></tr>';
			} else {
				$i = 1;
				foreach ( $pages_report['rows'] as $row ) {
					$title   = $row['dimensionValues'][0]['value'];
					$path    = $row['dimensionValues'][1]['value'];
					$users   = $row['metricValues'][0]['value'];
					$views   = $row['metricValues'][1]['value'];
					$avgdur  = self::fmt_duration( $row['metricValues'][2]['value'] );
					$bounce  = self::fmt_percent( $row['metricValues'][3]['value'] );
					$convs   = $conversions_by_path[ $path ] ?? 0;

					echo '<tr>';
					echo '<td>' . $i++ . '</td>';
					echo '<td><strong>' . esc_html( $title ) . '</strong><br><span style="color:#777; font-size:12px;">' . esc_html( $path ) . '</span></td>';
					echo '<td>' . esc_html( number_format_i18n( $users ) ) . '</td>';
					echo '<td>' . esc_html( number_format_i18n( $views ) ) . '</td>';
					echo '<td>' . esc_html( $avgdur ) . '</td>';
					echo '<td>' . esc_html( $bounce ) . '</td>';
					echo '<td>' . esc_html( number_format_i18n( $convs ) ) . '</td>';
					echo '</tr>';
				}
			}
			echo '</tbody></table>';
		}

		if ( empty( $conversion_rules ) ) {
			echo '<p class="description">Cột "C.đổi" đang hiển thị 0 vì bạn chưa khai báo chuyển đổi nào. Vào <a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-conversions' ) ) . '">Chuyển đổi</a> để thêm.</p>';
		}
		echo '</div>';

		echo '<div style="flex:1 1 460px; min-width:320px;">';
		echo '<h2>Nguồn Traffic (' . esc_html( $range_label ) . ')</h2>';

		if ( is_wp_error( $traffic_report ) ) {
			echo '<div class="notice notice-error"><p>Lỗi GA4: ' . esc_html( $traffic_report->get_error_message() ) . '</p></div>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>#</th><th>Nguồn</th><th>Loại</th><th>Khách</th><th>Lượt xem</th><th>T.lượng</th><th>Thoát</th>';
			echo '</tr></thead><tbody>';

			if ( empty( $traffic_report['rows'] ) ) {
				echo '<tr><td colspan="7">Không có dữ liệu.</td></tr>';
			} else {
				$i = 1;
				foreach ( $traffic_report['rows'] as $row ) {
					$source  = $row['dimensionValues'][0]['value'];
					$channel = $row['dimensionValues'][1]['value'];
					$users   = $row['metricValues'][0]['value'];
					$views   = $row['metricValues'][1]['value'];
					$avgdur  = self::fmt_duration( $row['metricValues'][2]['value'] );
					$bounce  = self::fmt_percent( $row['metricValues'][3]['value'] );

					echo '<tr>';
					echo '<td>' . $i++ . '</td>';
					echo '<td>' . esc_html( $source ) . '</td>';
					echo '<td>' . esc_html( $channel ) . '</td>';
					echo '<td>' . esc_html( number_format_i18n( $users ) ) . '</td>';
					echo '<td>' . esc_html( number_format_i18n( $views ) ) . '</td>';
					echo '<td>' . esc_html( $avgdur ) . '</td>';
					echo '<td>' . esc_html( $bounce ) . '</td>';
					echo '</tr>';
				}
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		echo '</div>';

		echo '<h2 style="margin-top:32px;">Link ra ngoài (' . esc_html( $range_label ) . ')</h2>';

		$outbound_cache_key = 'vinhxd_ga4_outbound_' . md5( $property_id . $range_key );
		$outbound_report = get_transient( $outbound_cache_key );
		if ( false === $outbound_report ) {
			$outbound_report = $client->run_report( array(
				'dateRanges'      => array( array( 'startDate' => $start_date, 'endDate' => $end_date ) ),
				'dimensions'      => array( array( 'name' => 'customEvent:link_domain' ), array( 'name' => 'customEvent:link_url' ) ),
				'metrics'         => array( array( 'name' => 'eventCount' ) ),
				'dimensionFilter' => array(
					'filter' => array(
						'fieldName'    => 'eventName',
						'stringFilter' => array( 'value' => 'click' ),
					),
				),
				'orderBys' => array( array( 'metric' => array( 'metricName' => 'eventCount' ), 'desc' => true ) ),
				'limit'    => 10,
			) );
			if ( ! is_wp_error( $outbound_report ) ) set_transient( $outbound_cache_key, $outbound_report, 15 * MINUTE_IN_SECONDS );
		}

		if ( is_wp_error( $outbound_report ) || empty( $outbound_report['rows'] ) ) {
			echo '<div class="notice notice-info" style="padding:12px;">';
			echo '<p><strong>Chưa có dữ liệu Link ra ngoài.</strong> Cần thiết lập 2 bước sau trong GA4 (làm 1 lần, không cần sửa code trên site):</p>';
			echo '<ol>';
			echo '<li>Vào GA4 &rarr; Quản trị (Admin) &rarr; Data Streams &rarr; chọn luồng Web &rarr; bánh răng <strong>Enhanced measurement</strong> &rarr; đảm bảo toggle <strong>Outbound clicks</strong> đang bật (mặc định GA4 đã bật sẵn).</li>';
			echo '<li>Vào GA4 &rarr; Quản trị &rarr; <strong>Custom definitions</strong> (Định nghĩa tuỳ chỉnh) &rarr; Create custom dimensions, tạo lần lượt 2 dimension: Event parameter <code>link_domain</code> và <code>link_url</code>, phạm vi (Scope) là <strong>Event</strong>.</li>';
			echo '</ol>';
			echo '<p class="description">Sau khi tạo, GA4 cần vài giờ để bắt đầu ghi nhận theo dimension mới, sau đó bảng này sẽ tự hiện dữ liệu ở lần tải trang tiếp theo.</p>';
			if ( is_wp_error( $outbound_report ) ) {
				echo '<details style="margin-top:8px;"><summary style="cursor:pointer;">Chi tiết lỗi API (để debug)</summary><code>' . esc_html( $outbound_report->get_error_message() ) . '</code></details>';
			}
			echo '</div>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>#</th><th>Tên miền</th><th>URL</th><th>Số lượt click</th>';
			echo '</tr></thead><tbody>';
			$i = 1;
			foreach ( $outbound_report['rows'] as $row ) {
				$domain = $row['dimensionValues'][0]['value'];
				$url    = $row['dimensionValues'][1]['value'];
				$clicks = $row['metricValues'][0]['value'];
				echo '<tr>';
				echo '<td>' . $i++ . '</td>';
				echo '<td>' . esc_html( $domain ) . '</td>';
				echo '<td><span style="color:#777;">' . esc_html( $url ) . '</span></td>';
				echo '<td>' . esc_html( number_format_i18n( $clicks ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2 style="margin-top:32px;">Link lỗi 404 gần đây</h2>';
		$log_404 = Vinhxd_GA4_404_Monitor::get_log();
		uasort( $log_404, function( $a, $b ) { return $b['count'] <=> $a['count']; } );
		$top5 = array_slice( $log_404, 0, 5, true );

		if ( empty( $top5 ) ) {
			echo '<p>Chưa ghi nhận lỗi 404 nào.</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Đường dẫn</th><th>Số lần</th><th>Lần cuối</th></tr></thead><tbody>';
			foreach ( $top5 as $path => $data ) {
				echo '<tr><td><code>' . esc_html( $path ) . '</code></td><td>' . esc_html( number_format_i18n( $data['count'] ) ) . '</td><td>' . esc_html( date_i18n( 'd/m/Y H:i', $data['last_seen'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=vinhxd-ga4-404' ) ) . '">Xem toàn bộ danh sách lỗi 404 &rarr;</a></p>';

		echo '</div>';
	}
}

add_action( 'admin_enqueue_scripts', array( 'Vinhxd_GA4_Dashboard', 'enqueue_assets' ) );
add_action( 'wp_dashboard_setup', array( 'Vinhxd_GA4_Dashboard', 'register_dashboard_widget' ) );
