<?php
/**
 * Trang cài đặt: bật/tắt từng widget (widget tắt không được đăng ký → nhẹ cả khung soạn thảo).
 *
 * @package Elementor_XD
 */

namespace EXD;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 900 );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( EXD_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
	}

	public static function menu() {
		$parent = did_action( 'elementor/loaded' ) ? 'elementor' : 'options-general.php';
		add_submenu_page( $parent, 'Elementor XD', 'Elementor XD', 'manage_options', 'elementor-xd', array( __CLASS__, 'page' ) );
	}

	public static function settings() {
		register_setting(
			'exd_settings',
			Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/** Form gửi danh sách widget ĐANG BẬT; lưu danh sách widget TẮT để widget mới mặc định bật. */
	public static function sanitize( $input ) {
		// WordPress có thể gọi sanitize 2 lần (lần 2 nhận lại kết quả lần 1) → chỉ xử lý dữ liệu từ form.
		if ( ! is_array( $input ) || ! isset( $input['on'] ) ) {
			return is_array( $input ) ? array_values( array_intersect( $input, array_keys( Plugin::widgets() ) ) ) : array();
		}
		$on  = array_map( 'sanitize_key', (array) $input['on'] );
		$off = array();
		foreach ( array_keys( Plugin::widgets() ) as $slug ) {
			if ( ! in_array( $slug, $on, true ) ) {
				$off[] = $slug;
			}
		}
		return $off;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$off = Plugin::disabled();
		?>
		<div class="wrap">
			<h1>Elementor XD <small style="font-size:13px;color:#646970">v<?php echo esc_html( EXD_VERSION ); ?></small></h1>
			<p>Tắt widget không dùng để khung Elementor gọn hơn. CSS/JS của widget vốn chỉ nạp ở trang có đặt widget đó.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'exd_settings' ); ?>
				<input type="hidden" name="<?php echo esc_attr( Plugin::OPTION ); ?>[on][]" value="">
				<table class="widefat striped" style="max-width:640px">
					<thead><tr><th style="width:48px">Bật</th><th>Widget</th><th>Tên trong Elementor</th></tr></thead>
					<tbody>
					<?php foreach ( Plugin::widgets() as $slug => $w ) : ?>
						<tr>
							<td><input type="checkbox" id="exd-<?php echo esc_attr( $slug ); ?>" name="<?php echo esc_attr( Plugin::OPTION ); ?>[on][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( ! in_array( $slug, $off, true ) ); ?>></td>
							<td><label for="exd-<?php echo esc_attr( $slug ); ?>"><strong><?php echo esc_html( $w[1] ); ?></strong></label></td>
							<td><code>exd-<?php echo esc_html( $slug ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( 'Lưu thay đổi' ); ?>
			</form>
			<p>Tác giả: <a href="https://vinhxd.com" target="_blank" rel="noopener">Vĩnh XD</a> · <a href="<?php echo esc_url( EXD_HOME ); ?>" target="_blank" rel="noopener">Trang plugin &amp; hướng dẫn</a></p>
		</div>
		<?php
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=elementor-xd' ) ) . '">Cài đặt</a>' );
		return $links;
	}

	public static function row_meta( $meta, $file ) {
		if ( plugin_basename( EXD_FILE ) === $file ) {
			$meta[] = '<a href="' . esc_url( EXD_HOME ) . '" target="_blank" rel="noopener">Hướng dẫn</a>';
		}
		return $meta;
	}
}
