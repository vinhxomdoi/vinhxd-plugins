<?php
/**
 * Lõi đọc dữ liệu xe, tính ưu đãi lên đến và chi phí lăn bánh theo từng phiên bản.
 * Đồng bộ 100% công thức với VinhXD_Pricing, vinhxd_km_tong(), vinhxd_km_the() và xd-trangxe.js.
 */

defined( 'ABSPATH' ) || exit;

final class XD_BGSEO_Calc {

	public static function defaults() {
		return array(
			'mode'             => 'tong',       // tong | max | mac_dinh
			'exclude_nhom'     => '',           // VD: congvu (ngăn cách dấu phẩy)
			'skip_ended_title' => '1',          // tự bỏ qua KM có chữ "đã hết" / "hết hạn" trong tên
			'orderby'          => 'price_asc',  // price_asc | price_desc | menu_order | title
			'show_img'         => '1',
			'show_uudai'       => '1',
			'show_after'       => '1',
			'show_lanbanh'     => '0',
			'show_schema'      => '1',
			'show_note'        => '1',
			'show_detail_tip'  => '1',
			'settings_ver'     => 1,
		);
	}

	public static function settings() {
		$saved = get_option( XD_BGSEO_OPT, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/** Chuẩn hoá chuỗi danh sách ngăn bởi dấu phẩy thành mảng chữ thường. */
	public static function csv_list( $raw ) {
		if ( is_array( $raw ) ) return array_values( array_filter( array_map( 'trim', $raw ) ) );
		$parts = array_map( 'trim', explode( ',', (string) $raw ) );
		return array_values( array_filter( $parts, function ( $x ) { return $x !== ''; } ) );
	}

	/** Lấy danh sách xe từ VinhXD_Pricing::car() đã lọc và sắp xếp. */
	public static function get_cars( $args = array() ) {
		$q = array(
			'post_type'      => 'xe',
			'post_status'    => 'publish',
			'posts_per_page' => 150,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		);

		$dong = self::csv_list( $args['dong_xe'] ?? '' );
		if ( $dong ) {
			$all_num = count( array_filter( $dong, 'is_numeric' ) ) === count( $dong );
			$q['tax_query'] = array(
				array(
					'taxonomy' => 'dong-xe',
					'field'    => $all_num ? 'term_id' : 'slug',
					'terms'    => $all_num ? array_map( 'intval', $dong ) : $dong,
				),
			);
		}

		$inc = array_filter( array_map( 'intval', self::csv_list( $args['include'] ?? '' ) ) );
		if ( $inc ) {
			$q['post__in'] = $inc;
		}
		$exc = array_filter( array_map( 'intval', self::csv_list( $args['exclude'] ?? '' ) ) );
		if ( $exc ) {
			$q['post__not_in'] = $exc;
		}

		$ids = get_posts( $q );
		if ( ! $ids ) return array();

		$cars = array();
		foreach ( $ids as $id ) {
			$car = VinhXD_Pricing::car( $id );
			// Fallback nếu xe chỉ có 1 phiên bản nhưng trường gia trong gia-xe bị để trống (VD: VF Wild)
			$fallback = function_exists( 'vinhxd_xe_gia_num' ) ? (float) vinhxd_xe_gia_num( $id ) : 0.0;
			if ( empty( $car['versions'] ) ) {
				$car['versions'] = array(
					array( 'name' => $car['title'], 'price' => $fallback, 'ptb' => 0, 'note' => '' ),
				);
			} elseif ( count( $car['versions'] ) === 1 && (float) $car['versions'][0]['price'] <= 0 && $fallback > 0 ) {
				$car['versions'][0]['price'] = $fallback;
			}

			$min_price = 0.0;
			foreach ( $car['versions'] as $v ) {
				$gp = (float) ( $v['price'] ?? 0 );
				if ( $gp > 0 && ( $min_price <= 0 || $gp < $min_price ) ) {
					$min_price = $gp;
				}
			}

			$p = get_post( $id );
			$car['_min_price']  = $min_price;
			$car['_menu_order'] = $p ? (int) $p->menu_order : 0;
			$car['_thumb']      = get_the_post_thumbnail_url( $id, 'medium' ) ?: ( $car['img'] ?? '' );
			$cars[]             = $car;
		}

		$orderby = (string) ( $args['orderby'] ?? 'price_asc' );
		if ( $orderby === 'price_asc' ) {
			usort( $cars, function ( $a, $b ) {
				$pa = $a['_min_price'] > 0 ? $a['_min_price'] : PHP_FLOAT_MAX;
				$pb = $b['_min_price'] > 0 ? $b['_min_price'] : PHP_FLOAT_MAX;
				if ( $pa === $pb ) return strcasecmp( $a['title'], $b['title'] );
				return $pa < $pb ? -1 : 1;
			} );
		} elseif ( $orderby === 'price_desc' ) {
			usort( $cars, function ( $a, $b ) {
				if ( $a['_min_price'] === $b['_min_price'] ) return strcasecmp( $a['title'], $b['title'] );
				return $a['_min_price'] > $b['_min_price'] ? -1 : 1;
			} );
		} elseif ( $orderby === 'title' ) {
			usort( $cars, function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			} );
		} elseif ( $orderby === 'post__in' && $inc ) {
			$pos = array_flip( $inc );
			usort( $cars, function ( $a, $b ) use ( $pos ) {
				return ( $pos[ $a['id'] ] ?? 9999 ) <=> ( $pos[ $b['id'] ] ?? 9999 );
			} );
		}

		return $cars;
	}

	/**
	 * Tính ưu đãi lên đến cho 1 phiên bản cụ thể của 1 xe.
	 *
	 * @param array $car       Dữ liệu từ VinhXD_Pricing::car()
	 * @param int   $ver_index Chỉ số phiên bản trong $car['versions']
	 * @param float $price     Giá niêm yết phiên bản
	 * @param array $opts      mode, exclude_nhom, skip_ended_title
	 * @return array { discount: float, net: float, items: array }
	 */
	public static function calc_version_promo( $car, $ver_index, $price, $opts = array() ) {
		$price = (float) $price;
		if ( $price <= 0 || empty( $car['offers'] ) ) {
			return array( 'discount' => 0.0, 'net' => max( 0.0, $price ), 'items' => array() );
		}

		$mode             = (string) ( $opts['mode'] ?? 'tong' );
		$skip_ended_title = ! isset( $opts['skip_ended_title'] ) || $opts['skip_ended_title'] === '1' || $opts['skip_ended_title'] === 'yes' || $opts['skip_ended_title'] === true;
		$exclude_nhom     = array_map( 'strtolower', self::csv_list( $opts['exclude_nhom'] ?? '' ) );

		// Lọc danh sách ưu đãi áp dụng cho phiên bản này
		$eligible = array();
		foreach ( $car['offers'] as $idx => $o ) {
			if ( ! VinhXD_Pricing::offer_fits( $o, $ver_index ) ) continue;
			$nhom = strtolower( trim( (string) ( $o['nhom'] ?? '' ) ) );
			if ( $nhom !== '' && in_array( $nhom, $exclude_nhom, true ) ) continue;
			if ( $skip_ended_title && preg_match( '/(đã\s*hết|hết\s*hạn|đã\s*kết\s*thúc)/iu', (string) $o['name'] ) ) continue;
			if ( (float) $o['amount'] <= 0 && (float) $o['pct'] <= 0 ) continue;
			$o['_idx'] = $idx;
			$eligible[] = $o;
		}

		if ( ! $eligible ) {
			return array( 'discount' => 0.0, 'net' => $price, 'items' => array() );
		}

		if ( $mode === 'mac_dinh' ) {
			$res = self::sum_stackable( array_values( array_filter( $eligible, function ( $o ) {
				return ! empty( $o['on'] );
			} ) ), $price );
			return $res;
		}

		if ( $mode === 'max' ) {
			// Cùng công thức vinhxd_km_the() theo từng phiên bản:
			// lớn hơn giữa (1 chương trình đơn cao nhất) và (tổng các chương trình mặc định).
			$best_amt  = 0.0;
			$best_item = null;
			foreach ( $eligible as $o ) {
				$amt = (float) $o['amount'] > 0
					? (float) $o['amount']
					: round( $price * (float) $o['pct'] / 100 );
				if ( $amt > $best_amt ) {
					$best_amt  = $amt;
					$best_item = array( 'name' => $o['name'], 'amt' => $amt );
				}
			}

			$defaults = array_values( array_filter( $eligible, function ( $o ) {
				return ! empty( $o['on'] );
			} ) );
			$def_res = self::sum_stackable( $defaults, $price );

			if ( $def_res['discount'] >= $best_amt && $def_res['discount'] > 0 ) {
				return $def_res;
			}
			$disc = min( $price, $best_amt );
			return array(
				'discount' => round( $disc ),
				'net'      => max( 0.0, round( $price - $disc ) ),
				'items'    => $best_item ? array( $best_item ) : array(),
			);
		}

		// Mặc định: mode === 'tong' (cùng nhóm lấy cao nhất, khác nhóm cộng dồn, % sau ưu đãi tính trên phần còn lại)
		return self::sum_stackable( $eligible, $price );
	}

	/**
	 * Cộng dồn ưu đãi theo chuẩn VinhXD Ô tô:
	 * - Cùng nhom: chọn mức giảm cao nhất; khác nhom (hoặc không có nhom): cộng dồn.
	 * - Ưu đãi after (sau-uu-dai = 1): tính % trên giá đã trừ các ưu đãi thường.
	 */
	private static function sum_stackable( $offers, $price ) {
		if ( ! $offers || $price <= 0 ) {
			return array( 'discount' => 0.0, 'net' => max( 0.0, $price ), 'items' => array() );
		}

		$normal = array();
		$after  = array();

		foreach ( $offers as $o ) {
			$nhom = trim( (string) ( $o['nhom'] ?? '' ) );
			$key  = $nhom !== '' ? 'nhom_' . strtolower( $nhom ) : 'id_' . (int) $o['id'];

			if ( ! empty( $o['after'] ) && (float) $o['amount'] <= 0 && (float) $o['pct'] > 0 ) {
				if ( ! isset( $after[ $key ] ) || (float) $o['pct'] > $after[ $key ]['pct'] ) {
					$after[ $key ] = array( 'name' => $o['name'], 'pct' => (float) $o['pct'] );
				}
				continue;
			}

			$val = (float) $o['amount'] > 0
				? (float) $o['amount']
				: round( $price * (float) $o['pct'] / 100 );

			if ( ! isset( $normal[ $key ] ) || $val > $normal[ $key ]['amt'] ) {
				$normal[ $key ] = array( 'name' => $o['name'], 'amt' => $val );
			}
		}

		$sum   = 0.0;
		$items = array();
		foreach ( $normal as $row ) {
			if ( $row['amt'] <= 0 ) continue;
			$sum    += $row['amt'];
			$items[] = $row;
		}

		$base = max( 0.0, $price - $sum );
		foreach ( $after as $row ) {
			$amt = round( $base * $row['pct'] / 100 );
			if ( $amt <= 0 ) continue;
			$sum    += $amt;
			$items[] = array( 'name' => $row['name'], 'amt' => $amt );
		}

		$disc = min( $price, $sum );
		return array(
			'discount' => round( $disc ),
			'net'      => max( 0.0, round( $price - $disc ) ),
			'items'    => $items,
		);
	}

	/**
	 * Tính chi phí & giá lăn bánh cho 1 phiên bản ở 1 khu vực đăng ký.
	 * Dùng đúng công thức VinhXD_Pricing::fees() + ptb_rate() + plate().
	 */
	public static function calc_rolling( $car, $ver_index, $base_price, $net_price, $region_index = null, $include_opt = true ) {
		$base_price = (float) $base_price;
		$net_price  = (float) $net_price;
		if ( $base_price <= 0 ) {
			return array( 'fees' => 0.0, 'total' => 0.0, 'region' => '' );
		}

		$reg_data = VinhXD_Pricing::regions();
		$idx      = $region_index === null ? $reg_data['default'] : (int) $region_index;
		$region   = $reg_data['list'][ $idx ] ?? ( $reg_data['list'][ $reg_data['default'] ] ?? null );
		$ver      = $car['versions'][ $ver_index ] ?? array( 'ptb' => 0 );
		$mul      = isset( $car['ptbMul'] ) ? (float) $car['ptbMul'] : 1.0;

		$fee_sum = 0.0;
		foreach ( (array) ( $car['fees'] ?? array() ) as $f ) {
			if ( ! $include_opt && ! empty( $f['opt'] ) ) continue;
			if ( $f['key'] === 'ptb' && ! empty( $ver['ptb'] ) && (float) $ver['ptb'] > 0 ) {
				$amt = (float) $ver['ptb'];
			} elseif ( $f['kind'] === 'percent' ) {
				$rate = $f['key'] === 'ptb'
					? VinhXD_Pricing::ptb_rate( $f, $region, $mul )
					: (float) $f['value'];
				$amt = round( $base_price * $rate / 100 );
			} else {
				$amt = (float) $f['value'];
			}
			$fee_sum += $amt;
		}

		if ( $region ) {
			$fee_sum += VinhXD_Pricing::plate( $region, $car['bs'] ?? null );
		}

		return array(
			'fees'   => round( $fee_sum ),
			'total'  => round( $net_price + $fee_sum ),
			'region' => $region['name'] ?? '',
		);
	}

	/** Định dạng tiền đồng nhất với VinhXD Ô tô. */
	public static function money( $n ) {
		if ( function_exists( 'vinhxd_money' ) ) return vinhxd_money( $n );
		return number_format( round( (float) $n ), 0, ',', '.' ) . ' đ';
	}

	/** Định dạng tiền rút gọn cho tooltip. */
	public static function short_money( $n ) {
		if ( function_exists( 'vinhxd_short' ) ) return vinhxd_short( $n, true );
		return self::money( $n );
	}

	/** Thay thế biến động trong tiêu đề: {thang} -> 11/2026, {nam} -> 2026. */
	public static function replace_vars( $text ) {
		$text = (string) $text;
		if ( $text === '' ) return '';
		return str_replace(
			array( '{thang}', '{nam}', '{ngay}' ),
			array( wp_date( 'm/Y' ), wp_date( 'Y' ), wp_date( 'd/m/Y' ) ),
			$text
		);
	}
}
