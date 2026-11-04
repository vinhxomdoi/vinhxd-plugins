<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Thêm cột "Lượt xem" (toàn thời gian, lấy từ GA4) vào bảng danh sách
 * cho các post type được chọn trong Cài đặt. Hỗ trợ sắp xếp (sortable)
 * bằng cách cache số liệu vào post meta mỗi khi đồng bộ lại từ GA4.
 */
class Vinhxd_GA4_Post_Views {

	const META_KEY        = '_vinhxd_ga4_views';
	const TRANSIENT_KEY   = 'vinhxd_ga4_alltime_views';
	const CACHE_HOURS     = 6;

	public static function get_post_types() {
		$saved = get_option( 'vinhxd_ga4_view_post_types', array( 'post', 'page' ) );
		return is_array( $saved ) && ! empty( $saved ) ? $saved : array( 'post', 'page' );
	}

	public static function init() {
		foreach ( self::get_post_types() as $post_type ) {
			$columns_hook = ( 'page' === $post_type ) ? 'manage_pages_columns' : "manage_{$post_type}_posts_columns";
			$render_hook  = ( 'page' === $post_type ) ? 'manage_pages_custom_column' : "manage_{$post_type}_posts_custom_column";

			add_filter( $columns_hook, array( __CLASS__, 'add_column' ) );
			add_action( $render_hook, array( __CLASS__, 'render_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( __CLASS__, 'make_sortable' ) );
		}
		add_action( 'pre_get_posts', array( __CLASS__, 'handle_sort' ) );
	}

	public static function add_column( $columns ) {
		$columns['vinhxd_ga4_views'] = 'Lượt xem';
		return $columns;
	}

	public static function make_sortable( $columns ) {
		$columns['vinhxd_ga4_views'] = 'vinhxd_ga4_views';
		return $columns;
	}

	public static function handle_sort( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) return;
		if ( $query->get( 'orderby' ) !== 'vinhxd_ga4_views' ) return;

		$query->set( 'meta_key', self::META_KEY );
		$query->set( 'orderby', 'meta_value_num' );
	}

	public static function render_column( $column, $post_id ) {
		if ( $column !== 'vinhxd_ga4_views' ) return;

		$views = get_post_meta( $post_id, self::META_KEY, true );
		echo $views !== '' ? esc_html( number_format_i18n( (int) $views ) ) : '<span style="color:#999;">-</span>';
	}

	public static function sync_views_from_ga4() {
		$property_id = get_option( 'vinhxd_ga4_property_id', '' );
		$sa_json     = get_option( 'vinhxd_ga4_service_account_json', '' );
		if ( empty( $property_id ) || empty( $sa_json ) ) return;

		$client = new Vinhxd_GA4_Client( $sa_json, $property_id );

		$report = $client->run_report( array(
			'dateRanges' => array( array( 'startDate' => '2015-08-14', 'endDate' => 'today' ) ),
			'dimensions' => array( array( 'name' => 'pagePath' ) ),
			'metrics'    => array( array( 'name' => 'screenPageViews' ) ),
			'limit'      => 100000,
		) );

		if ( is_wp_error( $report ) || empty( $report['rows'] ) ) return;

		$views_by_path = array();
		foreach ( $report['rows'] as $row ) {
			$path  = $row['dimensionValues'][0]['value'];
			$path  = strtok( $path, '?' );
			$path  = untrailingslashit( $path );
			$count = (int) $row['metricValues'][0]['value'];
			$views_by_path[ $path ] = ( $views_by_path[ $path ] ?? 0 ) + $count;
		}

		$post_ids = get_posts( array(
			'post_type'      => self::get_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		foreach ( $post_ids as $post_id ) {
			$permalink = get_permalink( $post_id );
			$path = untrailingslashit( wp_parse_url( $permalink, PHP_URL_PATH ) );
			$views = $views_by_path[ $path ] ?? 0;
			update_post_meta( $post_id, self::META_KEY, $views );
		}

		set_transient( self::TRANSIENT_KEY, time(), self::CACHE_HOURS * HOUR_IN_SECONDS );
	}

	public static function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'vinhxd_ga4_sync_views_event' ) ) {
			wp_schedule_event( time(), 'vinhxd_ga4_six_hours', 'vinhxd_ga4_sync_views_event' );
		}
	}

	public static function add_cron_schedule( $schedules ) {
		$schedules['vinhxd_ga4_six_hours'] = array(
			'interval' => self::CACHE_HOURS * HOUR_IN_SECONDS,
			'display'  => 'Mỗi ' . self::CACHE_HOURS . ' tiếng (XD Analytics)',
		);
		return $schedules;
	}
}

add_filter( 'cron_schedules', array( 'Vinhxd_GA4_Post_Views', 'add_cron_schedule' ) );
add_action( 'wp', array( 'Vinhxd_GA4_Post_Views', 'maybe_schedule_cron' ) );
add_action( 'vinhxd_ga4_sync_views_event', array( 'Vinhxd_GA4_Post_Views', 'sync_views_from_ga4' ) );

Vinhxd_GA4_Post_Views::init();
