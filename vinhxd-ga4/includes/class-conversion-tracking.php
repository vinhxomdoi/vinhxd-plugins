<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Quản lý danh sách "Chuyển đổi": mỗi rule = 1 hành động muốn tính là chuyển đổi
 * (click vào 1 nút/link theo CSS selector, hoặc submit 1 form theo CSS selector).
 */
class Vinhxd_GA4_Conversion_Tracking {

	const OPTION_KEY = 'vinhxd_ga4_conversions';

	public static function get_conversions() {
		$list = get_option( self::OPTION_KEY, array() );
		return is_array( $list ) ? $list : array();
	}

	private static function save_conversions( $list ) {
		update_option( self::OPTION_KEY, $list );
	}

	private static function slugify( $text ) {
		$slug = sanitize_title( $text );
		$slug = str_replace( '-', '_', $slug );
		return substr( $slug, 0, 40 );
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$conversions = self::get_conversions();

		if ( isset( $_POST['vinhxd_conv_nonce'] ) && wp_verify_nonce( $_POST['vinhxd_conv_nonce'], 'vinhxd_conv_save' ) ) {
			$label    = sanitize_text_field( $_POST['label'] ?? '' );
			$trigger  = ( $_POST['trigger'] ?? 'click' ) === 'submit' ? 'submit' : 'click';
			$selector = sanitize_text_field( $_POST['selector'] ?? '' );

			if ( $label && $selector ) {
				$conversions[] = array(
					'id'         => uniqid( 'conv_' ),
					'label'      => $label,
					'trigger'    => $trigger,
					'selector'   => $selector,
					'event_name' => self::slugify( $label ) ?: ( 'conv_' . wp_rand( 1000, 9999 ) ),
				);
				self::save_conversions( $conversions );
				echo '<div class="notice notice-success"><p>Đã thêm chuyển đổi mới.</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Vui lòng nhập đủ Tên và CSS Selector.</p></div>';
			}
		}

		if ( isset( $_GET['delete'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'vinhxd_conv_delete' ) ) {
			$id_to_delete = sanitize_text_field( $_GET['delete'] );
			$conversions = array_values( array_filter( $conversions, function( $c ) use ( $id_to_delete ) {
				return $c['id'] !== $id_to_delete;
			} ) );
			self::save_conversions( $conversions );
			echo '<div class="notice notice-success"><p>Đã xoá.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>XD Analytics - Chuyển đổi</h1>
			<p>Chuyển đổi (conversion) là hành động bạn muốn đo lường: người dùng <strong>click vào 1 nút/link</strong> hoặc <strong>gửi 1 form</strong>. Không cần sửa code — chỉ cần chỉ ra CSS selector của phần tử đó (VD: <code>.fixcall</code>, <code>#form-lien-he</code>, <code>a[href^="tel:"]</code>).</p>
			<div class="notice notice-info" style="padding:10px 14px; max-width:900px;">
				<p style="margin:0;"><strong>Quy định ở đây dùng chung cho cả 2 hệ thống:</strong> vừa bắn event lên <strong>Google Analytics</strong>, vừa ghi vào <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=vinhxd-ga4-logs' ) ); ?>">Nhật ký chuyển đổi</a></strong> nội bộ (có IP, từ khóa Google Ads, thiết bị). Khai báo 1 lần, dùng cho cả hai. Tên gợi nhớ bạn đặt sẽ hiển thị ở cột "Tên chuyển đổi" trong nhật ký.</p>
			</div>

			<h2>Thêm chuyển đổi mới</h2>
			<form method="post" style="max-width:700px;">
				<?php wp_nonce_field( 'vinhxd_conv_save', 'vinhxd_conv_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="label">Tên gợi nhớ</label></th>
						<td><input type="text" name="label" id="label" class="regular-text" placeholder="VD: Click gọi điện" required /></td>
					</tr>
					<tr>
						<th><label for="trigger">Loại hành động</label></th>
						<td>
							<select name="trigger" id="trigger">
								<option value="click">Click vào phần tử (nút/link)</option>
								<option value="submit">Gửi form (submit)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="selector">CSS Selector</label></th>
						<td>
							<input type="text" name="selector" id="selector" class="regular-text code" placeholder="VD: .fixcall  hoặc  #fluentform_3" required />
							<p class="description">Click: selector của nút/link. Submit: selector của thẻ &lt;form&gt;.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Thêm chuyển đổi' ); ?>
			</form>

			<h2>Danh sách chuyển đổi (<?php echo count( $conversions ); ?>)</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Tên</th>
						<th>Loại</th>
						<th>CSS Selector</th>
						<th>Tên event (GA4)</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $conversions ) ) : ?>
						<tr><td colspan="5">Chưa có chuyển đổi nào.</td></tr>
					<?php else : foreach ( $conversions as $c ) :
						$delete_url = wp_nonce_url( add_query_arg( array( 'page' => 'vinhxd-ga4-conversions', 'delete' => $c['id'] ), admin_url( 'admin.php' ) ), 'vinhxd_conv_delete' );
					?>
						<tr>
							<td><?php echo esc_html( $c['label'] ); ?></td>
							<td><?php echo $c['trigger'] === 'submit' ? 'Gửi form' : 'Click'; ?></td>
							<td><code><?php echo esc_html( $c['selector'] ); ?></code></td>
							<td><code><?php echo esc_html( $c['event_name'] ); ?></code></td>
							<td><a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Xoá chuyển đổi này?');">Xoá</a></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function enqueue_frontend() {
		if ( is_admin() ) return;

		// Luôn nạp script: kể cả khi chưa khai báo chuyển đổi nào thì log nội bộ
		// vẫn cần chạy để bắt các link tel: / zalo.me
		wp_enqueue_script( 'vinhxd-ga4-tracking', VINHXD_GA4_URL . 'assets/js/tracking.js', array(), VINHXD_GA4_VERSION, true );

		wp_localize_script( 'vinhxd-ga4-tracking', 'vinhxdGA4Conversions', array_values( self::get_conversions() ) );
		Vinhxd_GA4_Internal_Tracking::localize_frontend( 'vinhxd-ga4-tracking' );
	}
}
add_action( 'wp_enqueue_scripts', array( 'Vinhxd_GA4_Conversion_Tracking', 'enqueue_frontend' ) );
