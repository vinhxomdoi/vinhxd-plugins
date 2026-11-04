<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ghi nhận các đường link trả về lỗi 404 trên site (dùng wp_options, không cần bảng riêng).
 */
class Vinhxd_GA4_404_Monitor {

	const OPTION_KEY   = 'vinhxd_ga4_404_log';
	const MAX_ENTRIES  = 300;

	public static function track() {
		if ( is_admin() || ! is_404() ) return;

		$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( ! $path ) return;
		$path = strtok( $path, '?' );

		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) $log = array();

		if ( ! isset( $log[ $path ] ) ) {
			$log[ $path ] = array(
				'count'      => 0,
				'first_seen' => time(),
				'last_seen'  => time(),
				'referrer'   => '',
			);
		}

		$log[ $path ]['count']++;
		$log[ $path ]['last_seen'] = time();

		$ref = wp_get_referer();
		if ( $ref ) $log[ $path ]['referrer'] = $ref;

		if ( count( $log ) > self::MAX_ENTRIES ) {
			uasort( $log, function( $a, $b ) { return $a['last_seen'] <=> $b['last_seen']; } );
			$log = array_slice( $log, -1 * self::MAX_ENTRIES, null, true );
		}

		update_option( self::OPTION_KEY, $log, false );
	}

	public static function get_log() {
		$log = get_option( self::OPTION_KEY, array() );
		return is_array( $log ) ? $log : array();
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['vinhxd_404_clear_nonce'] ) && wp_verify_nonce( $_POST['vinhxd_404_clear_nonce'], 'vinhxd_404_clear' ) ) {
			delete_option( self::OPTION_KEY );
			echo '<div class="notice notice-success"><p>Đã xoá toàn bộ log 404.</p></div>';
		}

		$log = self::get_log();
		uasort( $log, function( $a, $b ) { return $b['count'] <=> $a['count']; } );

		echo '<div class="wrap">';
		echo '<h1>XD Analytics - Link lỗi 404</h1>';
		echo '<p>Danh sách các đường dẫn khách truy cập vào nhưng trả về lỗi 404 (không tồn tại) trên site này, kèm số lần xảy ra và nguồn dẫn tới (nếu có).</p>';

		echo '<form method="post" style="margin-bottom:16px;">';
		wp_nonce_field( 'vinhxd_404_clear', 'vinhxd_404_clear_nonce' );
		echo '<button type="submit" class="button" onclick="return confirm(\'Xoá toàn bộ log 404 đã ghi nhận?\');">Xoá toàn bộ log</button>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>Đường dẫn 404</th><th>Số lần</th><th>Lần đầu</th><th>Lần cuối</th><th>Nguồn dẫn tới (gần nhất)</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $log ) ) {
			echo '<tr><td colspan="6">Chưa ghi nhận lỗi 404 nào.</td></tr>';
		} else {
			$i = 1;
			foreach ( $log as $path => $data ) {
				echo '<tr>';
				echo '<td>' . $i++ . '</td>';
				echo '<td><code>' . esc_html( $path ) . '</code></td>';
				echo '<td>' . esc_html( number_format_i18n( $data['count'] ) ) . '</td>';
				echo '<td>' . esc_html( date_i18n( 'd/m/Y H:i', $data['first_seen'] ) ) . '</td>';
				echo '<td>' . esc_html( date_i18n( 'd/m/Y H:i', $data['last_seen'] ) ) . '</td>';
				echo '<td>' . ( $data['referrer'] ? '<span style="color:#777; word-break:break-all;">' . esc_html( $data['referrer'] ) . '</span>' : '-' ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}

add_action( 'template_redirect', array( 'Vinhxd_GA4_404_Monitor', 'track' ) );
