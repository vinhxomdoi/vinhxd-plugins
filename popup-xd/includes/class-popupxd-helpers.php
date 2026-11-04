<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers: meta access + per-field sanitizing.
 *
 * Every field Popup XD stores is declared here once, together with how it
 * must be cleaned. The same rules are used when saving the metabox, when
 * importing JSON and when rendering on the frontend (so values saved by
 * older versions are also cleaned before they reach a style="" attribute).
 */
class PopupXD_Helpers {

	const COLOR      = 'color';
	const BOX        = 'box';      // CSS length list, 1–4 values: "12px 24px".
	const INT        = 'int';      // Integer, may be empty.
	const TEXT       = 'text';
	const YESNO      = 'yesno';
	const ENUM       = 'enum';
	const ID_LIST    = 'id_list';  // "12, 45, 78".
	const CLASSES    = 'classes';  // Space separated CSS class names.
	const ARR        = 'array';

	/**
	 * Field map: key => array( type, extra ).
	 */
	public static function fields() {
		static $fields = null;
		if ( null !== $fields ) {
			return $fields;
		}
		$border = array( 'none', 'solid', 'dashed', 'dotted' );
		$fields = array(
			'enabled'               => array( self::YESNO ),
			'content_type'          => array( self::ENUM, array( 'editor', 'elementor' ) ),
			'elementor_template_id' => array( self::INT ),
			'max_width'             => array( self::INT ),
			'border_width'          => array( self::INT ),
			'border_style'          => array( self::ENUM, $border ),
			'border_color'          => array( self::COLOR ),
			'border_radius'         => array( self::INT ),
			'content_padding'       => array( self::BOX ),
			'shadow'                => array( self::ENUM, array( 'none', 'sm', 'md', 'lg' ) ),
			'show_close'            => array( self::YESNO ),
			'close_padding'         => array( self::BOX ),
			'close_bg'              => array( self::COLOR ),
			'close_color'           => array( self::COLOR ),
			'close_border_width'    => array( self::INT ),
			'close_border_style'    => array( self::ENUM, $border ),
			'close_border_color'    => array( self::COLOR ),
			'close_radius'          => array( self::INT ),
			'close_offset_top'      => array( self::INT ),
			'close_offset_right'    => array( self::INT ),
			'close_offset_bottom'   => array( self::INT ),
			'close_offset_left'     => array( self::INT ),
			'close_position'        => array( self::ENUM, array( 'top-right', 'top-left', 'outside-top-right', 'outside-top-left', 'custom' ) ),
			'close_offset'          => array( self::INT ),
			'mobile_style'          => array( self::ENUM, array( 'default', 'sheet' ) ),
			'position'              => array( self::ENUM, array( 'center', 'top', 'bottom', 'left', 'right' ) ),
			'offset_top'            => array( self::INT ),
			'offset_right'          => array( self::INT ),
			'offset_bottom'         => array( self::INT ),
			'offset_left'           => array( self::INT ),
			'animation'             => array( self::ENUM, array( 'fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom-in', 'zoom-out' ) ),
			'overlay_color'         => array( self::COLOR ),
			'overlay_opacity'       => array( self::INT ),
			'disable_scroll'        => array( self::YESNO ),
			'close_outside'         => array( self::YESNO ),
			'trigger_time'          => array( self::YESNO ),
			'time_delay'            => array( self::INT ),
			'trigger_scroll'        => array( self::YESNO ),
			'scroll_percent'        => array( self::INT ),
			'trigger_exit'          => array( self::YESNO ),
			'trigger_click'         => array( self::YESNO ),
			'click_selector'        => array( self::TEXT ),
			'trigger_manual'        => array( self::YESNO ),
			'cookie_enable'         => array( self::YESNO ),
			'cookie_days'           => array( self::INT ),
			'count_mode'            => array( self::ENUM, array( 'close', 'show' ) ),
			'form_close'            => array( self::YESNO ),
			'form_close_delay'      => array( self::INT ),
			'form_hide_enable'      => array( self::YESNO ),
			'form_hide_days'        => array( self::INT ),
			'reopen_enable'         => array( self::YESNO ),
			'reopen_seconds'        => array( self::INT ),
			'display_scope'         => array( self::ENUM, array( 'everywhere', 'specific' ) ),
			'target_pages'          => array( self::ARR ),
			'target_post_types'     => array( self::ARR ),
			'target_devices'        => array( self::ARR ),
			'exclude_ids'           => array( self::ID_LIST ),
			'button_text'           => array( self::TEXT ),
			'button_bg'             => array( self::COLOR ),
			'button_color'          => array( self::COLOR ),
			'button_radius'         => array( self::INT ),
			'button_padding'        => array( self::BOX ),
			'button_font_size'      => array( self::INT ),
			'button_css_class'      => array( self::CLASSES ),
		);
		return $fields;
	}

	/** Fields rendered as checkboxes: not submitted = "no". */
	public static function checkbox_fields() {
		return array(
			'enabled', 'show_close', 'disable_scroll', 'close_outside', 'trigger_time', 'trigger_scroll',
			'trigger_exit', 'trigger_click', 'trigger_manual', 'cookie_enable', 'reopen_enable',
			'form_close', 'form_hide_enable',
		);
	}

	/**
	 * Reads every Popup XD field from a submitted metabox form ($_POST with
	 * "pxd_" prefixed names, already unslashed) and returns clean values.
	 * Shared by the metabox save and the live preview.
	 */
	public static function from_request( array $src ) {
		$out        = array();
		$checkboxes = self::checkbox_fields();
		foreach ( self::fields() as $field => $def ) {
			$key = 'pxd_' . $field;
			if ( in_array( $field, $checkboxes, true ) ) {
				$out[ $field ] = ( isset( $src[ $key ] ) && 'yes' === $src[ $key ] ) ? 'yes' : 'no';
			} elseif ( self::ARR === $def[0] ) {
				$out[ $field ] = self::sanitize( $field, isset( $src[ $key ] ) && is_array( $src[ $key ] ) ? $src[ $key ] : array() );
			} else {
				$out[ $field ] = self::sanitize( $field, isset( $src[ $key ] ) ? $src[ $key ] : '' );
			}
		}
		return $out;
	}

	/**
	 * Raw meta value with default for empty strings.
	 */
	public static function meta( $post_id, $key, $default = '' ) {
		$val = get_post_meta( $post_id, POPUPXD_META_PREFIX . $key, true );
		return ( '' === $val || null === $val ) ? $default : $val;
	}

	/**
	 * Meta value, cleaned with the field's rule; falls back to $default when
	 * the stored value is empty or invalid.
	 */
	public static function get( $post_id, $key, $default = '' ) {
		$raw = self::meta( $post_id, $key, $default );
		$val = self::sanitize( $key, $raw );
		return ( '' === $val || null === $val ) ? $default : $val;
	}

	/**
	 * Like get(), but a value the admin deliberately left empty stays empty
	 * (the default only applies to popups that never saved this field).
	 * Used for the close-button offsets, where "empty" means "not this side".
	 */
	public static function get_saved( $post_id, $key, $default = '' ) {
		if ( ! metadata_exists( 'post', $post_id, POPUPXD_META_PREFIX . $key ) ) {
			return $default;
		}
		return self::sanitize( $key, get_post_meta( $post_id, POPUPXD_META_PREFIX . $key, true ) );
	}

	/**
	 * Array meta (target pages / types / devices).
	 */
	public static function get_array( $post_id, $key ) {
		$val = get_post_meta( $post_id, POPUPXD_META_PREFIX . $key, true );
		if ( ! is_array( $val ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $val ), 'strlen' ) );
	}

	public static function is_known( $key ) {
		$fields = self::fields();
		return isset( $fields[ $key ] );
	}

	/**
	 * Cleans a value for a field. Unknown fields are treated as plain text.
	 */
	public static function sanitize( $key, $value ) {
		$fields = self::fields();
		$def    = isset( $fields[ $key ] ) ? $fields[ $key ] : array( self::TEXT );
		$type   = $def[0];

		if ( self::ARR === $type ) {
			$value = is_array( $value ) ? $value : array();
			return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $value ) ), 'strlen' ) );
		}
		if ( is_array( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );

		switch ( $type ) {
			case self::COLOR:
				return self::color( $value );
			case self::BOX:
				return self::css_box( $value );
			case self::INT:
				return ( '' === $value || ! is_numeric( $value ) ) ? '' : (string) intval( $value );
			case self::YESNO:
				return 'yes' === $value ? 'yes' : ( 'no' === $value ? 'no' : '' );
			case self::ENUM:
				return in_array( $value, $def[1], true ) ? $value : '';
			case self::ID_LIST:
				$ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $value ) ) );
				return implode( ', ', array_unique( $ids ) );
			case self::CLASSES:
				return self::classes( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Hex (#fff / #ffffff / #ffffffaa), rgb()/rgba() or "transparent".
	 */
	public static function color( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( 'transparent' === $value ) {
			return $value;
		}
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * 1–4 CSS lengths, e.g. "28px", "20px 24px", "1em 2%". Bare numbers get "px".
	 */
	public static function css_box( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$parts = preg_split( '/\s+/', $value );
		if ( count( $parts ) > 4 ) {
			return '';
		}
		$out = array();
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^(-?\d*\.?\d+)(px|em|rem|%|vw|vh)?$/i', $part, $m ) ) {
				return '';
			}
			$unit  = isset( $m[2] ) && '' !== $m[2] ? strtolower( $m[2] ) : ( 0.0 === (float) $m[1] ? '' : 'px' );
			$out[] = $m[1] . $unit;
		}
		return implode( ' ', $out );
	}

	public static function classes( $value ) {
		$list = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $value ) ) );
		return implode( ' ', array_unique( $list ) );
	}

	/**
	 * Popup is switched on (popups saved before 1.3 have no flag = on).
	 */
	public static function is_enabled( $post_id ) {
		return 'no' !== self::get( $post_id, 'enabled', 'yes' );
	}

	/**
	 * One-line, human readable description of when/where/how often a popup
	 * shows — used in the popup list. (admin.js builds the same text live.)
	 */
	public static function summary( $post_id ) {
		$g    = function ( $k, $d = '' ) use ( $post_id ) {
			return self::get( $post_id, $k, $d );
		};
		$when = array();
		if ( 'yes' === $g( 'trigger_time', 'no' ) ) {
			$when[] = 'sau ' . (int) $g( 'time_delay', '3' ) . ' giây';
		}
		if ( 'yes' === $g( 'trigger_scroll', 'no' ) ) {
			$when[] = 'khi cuộn ' . (int) $g( 'scroll_percent', '50' ) . '%';
		}
		if ( 'yes' === $g( 'trigger_exit', 'no' ) ) {
			$when[] = 'khi định thoát trang';
		}
		if ( 'yes' === $g( 'trigger_click', 'no' ) && '' !== $g( 'click_selector', '' ) ) {
			$when[] = 'khi click ' . $g( 'click_selector', '' );
		}
		$manual = 'no' !== $g( 'trigger_manual', 'yes' );

		if ( $when ) {
			$text = 'Hiện ' . implode( ' hoặc ', $when );
		} elseif ( $manual ) {
			$text = 'Chỉ mở bằng nút / link';
		} else {
			$text = 'Không có điều kiện mở';
		}

		$devices = array_intersect( array( 'desktop', 'tablet', 'mobile' ), self::get_array( $post_id, 'target_devices' ) );
		if ( $devices && count( $devices ) < 3 ) {
			$names = array( 'desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile' );
			$text .= ' · chỉ ' . implode( ', ', array_map( function ( $d ) use ( $names ) {
				return $names[ $d ];
			}, $devices ) );
		}

		if ( $when && 'yes' === $g( 'cookie_enable', 'no' ) ) {
			$days  = (int) $g( 'cookie_days', '7' );
			$text .= ' · mỗi người 1 lần/' . ( $days > 0 ? $days . ' ngày' : 'phiên' );
		}
		return $text;
	}

	/**
	 * Converts a stored colour + opacity (0–100) to rgba() for the overlay.
	 */
	public static function hex_to_rgba( $hex, $opacity_percent ) {
		if ( 'transparent' === $hex ) {
			return 'transparent';
		}
		if ( 0 === strpos( (string) $hex, 'rgb' ) ) {
			return $hex;
		}
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) || 4 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$hex = substr( $hex, 0, 6 );
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			$hex = '000000';
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$a = max( 0, min( 100, (int) $opacity_percent ) ) / 100;
		return "rgba($r, $g, $b, $a)";
	}

	/**
	 * Asset version: plugin version + file mtime, so every plugin update
	 * busts browser / LiteSpeed / CDN caches automatically.
	 */
	public static function asset_version( $relative_path ) {
		$file = POPUPXD_PATH . $relative_path;
		return POPUPXD_VERSION . ( file_exists( $file ) ? '.' . filemtime( $file ) : '' );
	}

	/**
	 * Picks the .min file in production, the readable one under SCRIPT_DEBUG.
	 */
	public static function asset_path( $relative_path ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $relative_path;
		}
		$min = preg_replace( '/\.(js|css)$/', '.min.$1', $relative_path );
		return file_exists( POPUPXD_PATH . $min ) ? $min : $relative_path;
	}
}
