<?php
/**
 * Shortcodes bảng giá chuẩn SEO (HTML table thuần để Google Featured Snippets nhận diện):
 *   1. [xd_bang_gia_seo]       – Bảng giá theo từng phiên bản + Ưu đãi lên đến + Giá sau ưu đãi (+ Schema ItemList)
 *   2. [xd_bang_lan_banh_seo]  – Bảng giá lăn bánh tự động theo khu vực đăng ký từ VinhXD_Pricing
 *   3. [xd_bang_uu_dai_seo]    – Bảng danh sách chương trình khuyến mãi đang chạy từ taxonomy khuyen-mai
 */

defined( 'ABSPATH' ) || exit;

final class XD_BGSEO_Shortcodes {

	public static function init() {
		add_shortcode( 'xd_bang_gia_seo', array( __CLASS__, 'render_bang_gia' ) );
		add_shortcode( 'xd_bang_lan_banh_seo', array( __CLASS__, 'render_lan_banh' ) );
		add_shortcode( 'xd_bang_uu_dai_seo', array( __CLASS__, 'render_uu_dai' ) );
	}

	private static function flag( $val, $default = false ) {
		if ( $val === null || $val === '' ) return $default;
		if ( is_bool( $val ) ) return $val;
		return in_array( strtolower( (string) $val ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/** Ghép tên xe + phiên bản gọn gàng, tránh lặp chữ (VD: "VinFast VF 5 Premium" + "Premium", "VinFast EC Van" + "EC Van Tiêu chuẩn"). */
	private static function format_car_version_label( $title, $ver ) {
		$title = trim( (string) $title );
		$ver   = trim( (string) $ver );
		if ( $ver === '' ) return '';
		$t_low = mb_strtolower( $title );
		$v_low = mb_strtolower( $ver );
		// Nếu tiêu đề đã chứa trọn tên phiên bản (VD: "VinFast VF 5 Premium" đã có "Premium")
		if ( mb_strpos( $t_low, $v_low ) !== false ) return '';
		// Nếu tên phiên bản bắt đầu bằng phần sau hãng của tiêu đề (VD: "VinFast EC Van" & "EC Van Tiêu chuẩn")
		$short_title = trim( preg_replace( '/^\S+\s+/u', '', $title ) );
		if ( $short_title !== '' && mb_strlen( $short_title ) >= 3 ) {
			$st_low = mb_strtolower( $short_title );
			if ( mb_strpos( $v_low, $st_low ) === 0 ) {
				$rest = trim( mb_substr( $ver, mb_strlen( $short_title ) ) );
				return $rest !== '' ? ' ' . $rest : '';
			}
		}
		if ( mb_strpos( $v_low, $t_low ) === 0 ) {
			$rest = trim( mb_substr( $ver, mb_strlen( $title ) ) );
			return $rest !== '' ? ' ' . $rest : '';
		}
		return ' ' . $ver;
	}

	private static function cached( $kind, $args, $builder ) {
		if ( function_exists( 'vinhxd_cached' ) ) {
			$s = XD_BGSEO_Calc::settings();
			$args['_bgseo_v'] = XD_BGSEO_VERSION . '.' . (int) ( $s['settings_ver'] ?? 1 );
			return vinhxd_cached( $kind, $args, $builder );
		}
		return call_user_func( $builder );
	}

	/* =====================================================================
	 * 1. [xd_bang_gia_seo]
	 * ===================================================================== */
	public static function render_bang_gia( $atts = array() ) {
		$s = XD_BGSEO_Calc::settings();
		$a = shortcode_atts( array(
			'title'            => '',
			'dong_xe'          => '',
			'group_by_dong'    => 'no',
			'include'          => '',
			'exclude'          => '',
			'orderby'          => $s['orderby'],
			'mode'             => $s['mode'],
			'exclude_nhom'     => $s['exclude_nhom'],
			'skip_ended_title' => $s['skip_ended_title'],
			'show_img'         => $s['show_img'] === '1' ? 'yes' : 'no',
			'show_uudai'       => $s['show_uudai'] === '1' ? 'yes' : 'no',
			'show_after'       => $s['show_after'] === '1' ? 'yes' : 'no',
			'show_lanbanh'     => $s['show_lanbanh'] === '1' ? 'yes' : 'no',
			'lanbanh_region'   => '', // chỉ số khu vực trong VinhXD_Pricing::regions(); trống = mặc định
			'lanbanh_from'     => 'net', // net (sau ưu đãi) | list (giá niêm yết)
			'show_detail_tip'  => $s['show_detail_tip'] === '1' ? 'yes' : 'no',
			'schema'           => $s['show_schema'] === '1' ? 'yes' : 'no',
			'note'             => $s['show_note'] === '1' ? 'yes' : 'no',
			'heading_tag'      => 'h3',
			'class'            => '',
		), (array) $atts, 'xd_bang_gia_seo' );

		wp_enqueue_style( 'xd-banggia-seo' );

		return self::cached( 'bgseo_price', $a, function () use ( $a ) {
			return self::build_bang_gia( $a );
		} );
	}

	private static function build_bang_gia( $a ) {
		$group_by_dong = self::flag( $a['group_by_dong'] );
		$show_schema   = self::flag( $a['schema'], true );
		$show_note     = self::flag( $a['note'], true );
		$heading_tag   = in_array( strtolower( $a['heading_tag'] ), array( 'h2', 'h3', 'h4' ), true ) ? strtolower( $a['heading_tag'] ) : 'h3';

		$schema_items = array();
		$pos          = 1;
		$out          = '<div class="xd-bgseo-root ' . esc_attr( $a['class'] ) . '">';

		if ( $group_by_dong ) {
			$term_args = array(
				'taxonomy'   => 'dong-xe',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
			);
			$filter_dong = XD_BGSEO_Calc::csv_list( $a['dong_xe'] );
			if ( $filter_dong ) {
				$all_num = count( array_filter( $filter_dong, 'is_numeric' ) ) === count( $filter_dong );
				$term_args[ $all_num ? 'include' : 'slug' ] = $all_num ? array_map( 'intval', $filter_dong ) : $filter_dong;
				$term_args['orderby'] = 'include';
			}
			$terms = get_terms( $term_args );
			if ( is_wp_error( $terms ) || ! $terms ) {
				$cars = XD_BGSEO_Calc::get_cars( $a );
				if ( ! $cars ) return '';
				$out .= self::render_price_table_html( $cars, $a, XD_BGSEO_Calc::replace_vars( $a['title'] ), $schema_items, $pos );
			} else {
				// Nếu truyền slug theo thứ tự mong muốn, giữ đúng thứ tự đó
				if ( $filter_dong && ! is_numeric( $filter_dong[0] ) ) {
					$order_map = array_flip( $filter_dong );
					usort( $terms, function ( $x, $y ) use ( $order_map ) {
						return ( $order_map[ $x->slug ] ?? 999 ) <=> ( $order_map[ $y->slug ] ?? 999 );
					} );
				}
				foreach ( $terms as $t ) {
					$sub_args            = $a;
					$sub_args['dong_xe'] = $t->slug;
					$cars                = XD_BGSEO_Calc::get_cars( $sub_args );
					if ( ! $cars ) continue;
					$out .= '<' . $heading_tag . ' class="xd-bgseo-group-title">' . esc_html( $t->name ) . '</' . $heading_tag . '>';
					$cap  = $a['title'] !== '' ? XD_BGSEO_Calc::replace_vars( $a['title'] ) . ' – ' . $t->name : '';
					$out .= self::render_price_table_html( $cars, $a, $cap, $schema_items, $pos );
				}
			}
		} else {
			$cars = XD_BGSEO_Calc::get_cars( $a );
			if ( ! $cars ) return '';
			$out .= self::render_price_table_html( $cars, $a, XD_BGSEO_Calc::replace_vars( $a['title'] ), $schema_items, $pos );
		}

		if ( $show_note ) {
			$mode_label = 'tổng các nhóm ưu đãi đang áp dụng';
			if ( $a['mode'] === 'max' ) $mode_label = 'chương trình ưu đãi cao nhất đang áp dụng';
			elseif ( $a['mode'] === 'mac_dinh' ) $mode_label = 'chương trình ưu đãi mặc định đang áp dụng';

			$out .= '<p class="xd-bgseo-note"><em>* Giá niêm yết đã bao gồm VAT. Cột "Ưu đãi lên đến" tự động tính theo ' . esc_html( $mode_label ) . ' cho từng phiên bản (tự cập nhật ngày ' . esc_html( wp_date( 'd/m/Y' ) ) . '). Điều kiện áp dụng cụ thể theo chính sách tại thời điểm xuất hóa đơn; vui lòng chọn phiên bản trên trang chi tiết xe để xem bảng tính và lăn bánh chính xác.</em></p>';
		}

		if ( $show_schema && $schema_items ) {
			$list_name = $a['title'] !== '' ? XD_BGSEO_Calc::replace_vars( $a['title'] ) : 'Bảng giá xe cập nhật ' . wp_date( 'm/Y' );
			$ld = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'ItemList',
				'name'            => $list_name,
				'numberOfItems'   => count( $schema_items ),
				'itemListElement' => $schema_items,
			);
			if ( function_exists( 'vinhxd_jsonld' ) ) {
				$out .= vinhxd_jsonld( $ld );
			} else {
				$out .= '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
			}
		}

		$out .= '</div>';
		return $out;
	}

	private static function render_price_table_html( $cars, $a, $caption, &$schema_items, &$pos ) {
		$show_img     = self::flag( $a['show_img'], true );
		$show_uudai   = self::flag( $a['show_uudai'], true );
		$show_after   = self::flag( $a['show_after'], true );
		$show_lanbanh = self::flag( $a['show_lanbanh'], false );
		$show_tip     = self::flag( $a['show_detail_tip'], true );
		$lb_region    = $a['lanbanh_region'] !== '' ? (int) $a['lanbanh_region'] : null;
		$lb_from_list = ( $a['lanbanh_from'] === 'list' );

		$brand = trim( (string) get_option( 'xd_schema_brand', '' ) );
		if ( $brand === '' ) {
			$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			if ( stripos( $host, 'vinfast' ) !== false ) $brand = 'VinFast';
		}

		$promo_opts = array(
			'mode'             => $a['mode'],
			'exclude_nhom'     => $a['exclude_nhom'],
			'skip_ended_title' => $a['skip_ended_title'],
		);

		$reg_label = '';
		if ( $show_lanbanh ) {
			$rd = VinhXD_Pricing::regions();
			$ri = $lb_region === null ? $rd['default'] : $lb_region;
			$reg_label = isset( $rd['list'][ $ri ]['name'] ) ? ' (' . $rd['list'][ $ri ]['name'] . ')' : '';
		}

		$h  = '<div class="xd-bgseo-wrap">';
		$h .= '<table class="xd-bgseo-table">';
		if ( $caption !== '' ) {
			$h .= '<caption>' . esc_html( $caption ) . '</caption>';
		}
		$h .= '<thead><tr>';
		$h .= '<th scope="col" class="xd-col-car">Dòng xe</th>';
		$h .= '<th scope="col" class="xd-col-ver">Phiên bản</th>';
		$h .= '<th scope="col" class="xd-col-num">Giá niêm yết</th>';
		if ( $show_uudai )   $h .= '<th scope="col" class="xd-col-num">Ưu đãi lên đến</th>';
		if ( $show_after )   $h .= '<th scope="col" class="xd-col-num">Giá sau ưu đãi</th>';
		if ( $show_lanbanh ) $h .= '<th scope="col" class="xd-col-num">Lăn bánh tạm tính' . esc_html( $reg_label ) . '</th>';
		$h .= '</tr></thead><tbody>';

		foreach ( $cars as $n => $car ) {
			$vers  = $car['versions'];
			$rc    = count( $vers );
			$title = $car['title'];
			$url   = $car['url'];
			$img   = $car['_thumb'];

			foreach ( $vers as $vi => $v ) {
				$vname = $v['name'] !== '' ? $v['name'] : 'Tiêu chuẩn';
				$gia   = (float) $v['price'];
				$promo = XD_BGSEO_Calc::calc_version_promo( $car, $vi, $gia, $promo_opts );
				$disc  = $promo['discount'];
				$net   = $promo['net'];

				// Schema ListItem
				$full_name = trim( $title . self::format_car_version_label( $title, $vname ) );
				$p_schema  = array(
					'@type' => 'Product',
					'name'  => $full_name,
					'url'   => $url,
				);
				if ( $img ) $p_schema['image'] = $img;
				if ( $brand ) $p_schema['brand'] = array( '@type' => 'Brand', 'name' => $brand );
				if ( $gia > 0 ) {
					$offer = array(
						'@type'         => 'Offer',
						'price'         => (string) (int) round( $gia ),
						'priceCurrency' => 'VND',
						'availability'  => 'https://schema.org/InStock',
						'url'           => $url,
					);
					$p_schema['offers'] = $offer;
				}
				$schema_items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'item'     => $p_schema,
				);

				$row_cls = 'xd-row-' . ( $n % 2 ) . ( $vi === 0 ? ' xd-car-first' : '' ) . ( $vi === $rc - 1 ? ' xd-car-last' : '' );
				$h .= '<tr class="' . esc_attr( $row_cls ) . '">';

				if ( $vi === 0 ) {
					$h .= '<td class="xd-bgseo-car" rowspan="' . $rc . '">';
					$h .= '<a href="' . esc_url( $url ) . '" class="xd-bgseo-car-link">';
					if ( $show_img && $img ) {
						$h .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" decoding="async" width="120" height="70">';
					}
					$h .= '<span class="xd-bgseo-car-name">' . esc_html( $title ) . '</span>';
					$h .= '</a></td>';
				}

				$h .= '<td class="xd-bgseo-ver" data-label="Phiên bản">' . esc_html( $vname ) . '</td>';
				$h .= '<td class="xd-bgseo-num xd-bgseo-price" data-label="Giá niêm yết">' . esc_html( $gia > 0 ? XD_BGSEO_Calc::money( $gia ) : 'Liên hệ' ) . '</td>';

				if ( $show_uudai ) {
					if ( $gia > 0 && $disc > 0 ) {
						$tip = '';
						if ( $show_tip && ! empty( $promo['items'] ) ) {
							$parts = array();
							foreach ( $promo['items'] as $it ) {
								$parts[] = $it['name'] . ' (-' . XD_BGSEO_Calc::short_money( $it['amt'] ) . ')';
							}
							$tip = ' title="' . esc_attr( 'Gồm: ' . implode( ' + ', $parts ) ) . '"';
						}
						$h .= '<td class="xd-bgseo-num xd-bgseo-promo" data-label="Ưu đãi lên đến"' . $tip . '><span class="xd-bgseo-badge">-' . esc_html( XD_BGSEO_Calc::money( $disc ) ) . '</span></td>';
					} else {
						$h .= '<td class="xd-bgseo-num xd-bgseo-promo is-empty" data-label="Ưu đãi lên đến">Liên hệ</td>';
					}
				}

				if ( $show_after ) {
					$display_net = ( $gia > 0 ) ? XD_BGSEO_Calc::money( $disc > 0 ? $net : $gia ) : 'Liên hệ';
					$h .= '<td class="xd-bgseo-num xd-bgseo-net" data-label="Giá sau ưu đãi"><strong>' . esc_html( $display_net ) . '</strong></td>';
				}

				if ( $show_lanbanh ) {
					if ( $gia > 0 ) {
						$rb = XD_BGSEO_Calc::calc_rolling( $car, $vi, $gia, $lb_from_list ? $gia : $net, $lb_region );
						$h .= '<td class="xd-bgseo-num xd-bgseo-rb" data-label="Lăn bánh tạm tính">' . esc_html( XD_BGSEO_Calc::money( $rb['total'] ) ) . '</td>';
					} else {
						$h .= '<td class="xd-bgseo-num xd-bgseo-rb" data-label="Lăn bánh tạm tính">Liên hệ</td>';
					}
				}

				$h .= '</tr>';
			}
		}

		$h .= '</tbody></table></div>';
		return $h;
	}

	/* =====================================================================
	 * 2. [xd_bang_lan_banh_seo]
	 * ===================================================================== */
	public static function render_lan_banh( $atts = array() ) {
		$s = XD_BGSEO_Calc::settings();
		$a = shortcode_atts( array(
			'title'            => '',
			'dong_xe'          => '',
			'include'          => '',
			'exclude'          => '',
			'orderby'          => $s['orderby'],
			'versions'         => 'lowest', // lowest (1 dòng/xe phiên bản thấp nhất) | all (tất cả phiên bản)
			'use_net'          => 'no',     // no (tính theo giá niêm yết) | yes (tính theo giá sau ưu đãi)
			'mode'             => $s['mode'],
			'exclude_nhom'     => $s['exclude_nhom'],
			'skip_ended_title' => $s['skip_ended_title'],
			'include_opt'      => 'yes',    // bao gồm phí tùy chọn đang bật trong cài đặt VinhXD Ô tô
			'note'             => 'yes',
			'class'            => '',
		), (array) $atts, 'xd_bang_lan_banh_seo' );

		wp_enqueue_style( 'xd-banggia-seo' );

		return self::cached( 'bgseo_lb', $a, function () use ( $a ) {
			return self::build_lan_banh( $a );
		} );
	}

	private static function build_lan_banh( $a ) {
		$cars = XD_BGSEO_Calc::get_cars( $a );
		if ( ! $cars ) return '';

		$reg_data    = VinhXD_Pricing::regions();
		$regions     = $reg_data['list'];
		if ( ! $regions ) return '';

		$use_net     = self::flag( $a['use_net'], false );
		$all_vers    = ( $a['versions'] === 'all' );
		$include_opt = self::flag( $a['include_opt'], true );
		$promo_opts  = array(
			'mode'             => $a['mode'],
			'exclude_nhom'     => $a['exclude_nhom'],
			'skip_ended_title' => $a['skip_ended_title'],
		);

		// Dựng danh sách dòng dữ liệu trước để gộp cột khu vực có cùng mức lăn bánh (VD: xe điện HN == TP.HCM)
		$rows = array();
		foreach ( $cars as $car ) {
			$vers = $car['versions'];
			if ( ! $all_vers ) {
				// Lấy phiên bản giá thấp nhất (> 0)
				$best_vi = 0;
				$best_p  = PHP_FLOAT_MAX;
				foreach ( $vers as $vi => $v ) {
					$p = (float) $v['price'];
					if ( $p > 0 && $p < $best_p ) {
						$best_p  = $p;
						$best_vi = $vi;
					}
				}
				$pick = array( $best_vi => $vers[ $best_vi ] );
			} else {
				$pick = $vers;
			}

			foreach ( $pick as $vi => $v ) {
				$gia = (float) $v['price'];
				if ( $gia <= 0 ) continue;
				$net = $gia;
				if ( $use_net ) {
					$pr  = XD_BGSEO_Calc::calc_version_promo( $car, $vi, $gia, $promo_opts );
					$net = $pr['net'];
				}
				$rb_vals = array();
				foreach ( $regions as $ri => $r ) {
					$calc          = XD_BGSEO_Calc::calc_rolling( $car, $vi, $gia, $net, $ri, $include_opt );
					$rb_vals[ $ri ] = $calc['total'];
				}
				$rows[] = array(
					'title' => $car['title'],
					'url'   => $car['url'],
					'ver'   => $v['name'],
					'gia'   => $gia,
					'net'   => $net,
					'rb'    => $rb_vals,
				);
			}
		}

		if ( ! $rows ) return '';

		// Gộp nhóm khu vực nếu mọi xe đều ra cùng 1 số tiền (VD: Hà Nội & TP.HCM trên web xe điện miễn trước bạ)
		$col_groups = array();
		foreach ( $regions as $ri => $r ) {
			$sig = array();
			foreach ( $rows as $row ) $sig[] = $row['rb'][ $ri ];
			$key = implode( '|', $sig );
			if ( ! isset( $col_groups[ $key ] ) ) {
				$col_groups[ $key ] = array( 'names' => array(), 'ri' => $ri );
			}
			$col_groups[ $key ]['names'][] = $r['name'];
		}
		$cols = array_values( $col_groups );

		$cap = XD_BGSEO_Calc::replace_vars( $a['title'] );
		$h   = '<div class="xd-bgseo-root ' . esc_attr( $a['class'] ) . '"><div class="xd-bgseo-wrap"><table class="xd-bgseo-table xd-bgseo-lb-table">';
		if ( $cap !== '' ) $h .= '<caption>' . esc_html( $cap ) . '</caption>';
		$h .= '<thead><tr>';
		$h .= '<th scope="col">' . ( $all_vers ? 'Mẫu xe & Phiên bản' : 'Phiên bản tiêu chuẩn' ) . '</th>';
		$h .= '<th scope="col" class="xd-col-num">' . ( $use_net ? 'Giá sau ưu đãi' : 'Giá niêm yết' ) . '</th>';
		foreach ( $cols as $c ) {
			$h .= '<th scope="col" class="xd-col-num">Lăn bánh ' . esc_html( implode( ', ', $c['names'] ) ) . '</th>';
		}
		$h .= '</tr></thead><tbody>';

		foreach ( $rows as $i => $row ) {
			$label_ver = self::format_car_version_label( $row['title'], $row['ver'] );
			$h .= '<tr class="xd-row-' . ( $i % 2 ) . '">';
			$h .= '<td class="xd-bgseo-ver" data-label="Mẫu xe"><a href="' . esc_url( $row['url'] ) . '"><strong>' . esc_html( $row['title'] ) . '</strong></a>' . esc_html( $label_ver ) . '</td>';
			$h .= '<td class="xd-bgseo-num xd-bgseo-price" data-label="' . esc_attr( $use_net ? 'Giá sau ưu đãi' : 'Giá niêm yết' ) . '">' . esc_html( XD_BGSEO_Calc::money( $use_net ? $row['net'] : $row['gia'] ) ) . '</td>';
			foreach ( $cols as $c ) {
				$cname = 'Lăn bánh ' . implode( ', ', $c['names'] );
				$h .= '<td class="xd-bgseo-num xd-bgseo-net" data-label="' . esc_attr( $cname ) . '"><strong>' . esc_html( XD_BGSEO_Calc::money( $row['rb'][ $c['ri'] ] ) ) . '</strong></td>';
			}
			$h .= '</tr>';
		}

		$h .= '</tbody></table></div>';
		if ( self::flag( $a['note'], true ) ) {
			$h .= '<p class="xd-bgseo-note"><em>* Giá lăn bánh tạm tính được hệ thống tự động tính theo biểu phí đăng ký đang cấu hình trên VinhXD Ô tô (cập nhật ngày ' . esc_html( wp_date( 'd/m/Y' ) ) . ').</em></p>';
		}
		$h .= '</div>';
		return $h;
	}

	/* =====================================================================
	 * 3. [xd_bang_uu_dai_seo]
	 * ===================================================================== */
	public static function render_uu_dai( $atts = array() ) {
		$s = XD_BGSEO_Calc::settings();
		$a = shortcode_atts( array(
			'title'            => '',
			'exclude_nhom'     => $s['exclude_nhom'],
			'skip_ended_title' => $s['skip_ended_title'],
			'class'            => '',
		), (array) $atts, 'xd_bang_uu_dai_seo' );

		wp_enqueue_style( 'xd-banggia-seo' );

		return self::cached( 'bgseo_km', $a, function () use ( $a ) {
			return self::build_uu_dai( $a );
		} );
	}

	private static function build_uu_dai( $a ) {
		if ( ! defined( 'VINHXD_KM_TAX' ) ) return '';
		$terms = get_terms( array( 'taxonomy' => VINHXD_KM_TAX, 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) || ! $terms ) return '';

		$exclude_nhom = array_map( 'strtolower', XD_BGSEO_Calc::csv_list( $a['exclude_nhom'] ) );
		$skip_ended   = self::flag( $a['skip_ended_title'], true );

		$active = array();
		foreach ( $terms as $t ) {
			if ( function_exists( 'vinhxd_km_status' ) && vinhxd_km_status( $t->term_id ) !== 'active' ) continue;
			if ( $skip_ended && preg_match( '/(đã\s*hết|hết\s*hạn|đã\s*kết\s*thúc)/iu', $t->name ) ) continue;
			$nhom = strtolower( trim( (string) get_term_meta( $t->term_id, 'nhom', true ) ) );
			if ( $nhom !== '' && in_array( $nhom, $exclude_nhom, true ) ) continue;
			$active[] = $t;
		}
		if ( ! $active ) return '';

		if ( function_exists( 'vinhxd_km_cmp' ) ) {
			usort( $active, 'vinhxd_km_cmp' );
		}

		$total_cars = (int) wp_count_posts( 'xe' )->publish;
		$cap        = XD_BGSEO_Calc::replace_vars( $a['title'] );

		$h  = '<div class="xd-bgseo-root ' . esc_attr( $a['class'] ) . '"><div class="xd-bgseo-wrap"><table class="xd-bgseo-table xd-bgseo-km-table">';
		if ( $cap !== '' ) $h .= '<caption>' . esc_html( $cap ) . '</caption>';
		$h .= '<thead><tr><th scope="col">Chương trình ưu đãi</th><th scope="col">Mức ưu đãi</th><th scope="col">Dòng xe áp dụng</th><th scope="col">Thời hạn</th></tr></thead><tbody>';

		foreach ( $active as $i => $t ) {
			$muc = function_exists( 'vinhxd_km_muc_label' ) ? vinhxd_km_muc_label( $t->term_id ) : array( 'label' => '' );
			$cs  = function_exists( 'vinhxd_km_chinh_sach' ) ? vinhxd_km_chinh_sach( $t->term_id ) : '';
			$muc_txt = $muc['label'] !== '' ? 'Giảm ' . $muc['label'] : ( $cs !== '' ? $cs : 'Theo chính sách' );

			$ids = function_exists( 'vinhxd_km_xe_ids' ) ? vinhxd_km_xe_ids( $t->term_id ) : array();
			if ( $total_cars > 0 && count( $ids ) >= max( 1, $total_cars - 2 ) ) {
				$xe_txt = 'Tất cả dòng xe';
			} else {
				$names = array();
				foreach ( $ids as $pid ) {
					if ( get_post_status( $pid ) === 'publish' ) $names[] = get_the_title( $pid );
				}
				$xe_txt = $names ? implode( ', ', $names ) : 'Liên hệ';
			}

			$end_ts  = function_exists( 'vinhxd_km_date' ) ? vinhxd_km_date( get_term_meta( $t->term_id, 'ket-thuc', true ) ) : 0;
			$th_meta = trim( (string) get_term_meta( $t->term_id, 'thoi-gian', true ) );
			if ( $end_ts > 0 ) {
				$time_txt = 'Đến hết ' . wp_date( 'd/m/Y', $end_ts );
			} elseif ( $th_meta !== '' ) {
				$time_txt = $th_meta;
			} else {
				$time_txt = 'Theo chính sách hiện hành';
			}

			$h .= '<tr class="xd-row-' . ( $i % 2 ) . '">';
			$h .= '<td class="xd-bgseo-ver" data-label="Chương trình"><strong>' . esc_html( $t->name ) . '</strong></td>';
			$h .= '<td class="xd-bgseo-promo" data-label="Mức ưu đãi"><span class="xd-bgseo-badge">' . esc_html( $muc_txt ) . '</span></td>';
			$h .= '<td data-label="Dòng xe áp dụng">' . esc_html( $xe_txt ) . '</td>';
			$h .= '<td data-label="Thời hạn">' . esc_html( $time_txt ) . '</td>';
			$h .= '</tr>';
		}

		$h .= '</tbody></table></div></div>';
		return $h;
	}
}
