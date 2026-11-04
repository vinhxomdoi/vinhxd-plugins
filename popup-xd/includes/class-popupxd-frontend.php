<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PopupXD_Frontend {

	public static $printed_ids = array();
	public static $force_ids   = array();

	/** True while rendering the admin preview frame (no other popups, no triggers). */
	public static $preview_mode = false;

	private static $assets_enqueued = false;

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		// Priority 5: popups must be printed BEFORE wp_print_footer_scripts
		// (wp_footer @ 20), so assets enqueued late by a shortcode or an
		// Elementor template inside a popup still get printed.
		add_action( 'wp_footer', array( $this, 'output_popups' ), 5 );
	}

	/* --------------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------------ */

	/**
	 * All published popups, fetched once per request. get_posts() primes the
	 * meta cache, so every later get_post_meta() call is served from memory.
	 *
	 * @return WP_Post[] keyed by ID.
	 */
	public static function get_popups() {
		static $popups = null;
		if ( null !== $popups ) {
			return $popups;
		}
		$popups = array();
		$posts  = get_posts( array(
			'post_type'              => POPUPXD_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'orderby'                => 'date',
			'order'                  => 'ASC',
		) );
		foreach ( $posts as $post ) {
			// Switched off in the list (công tắc Bật/Tắt) → behaves as if absent.
			if ( PopupXD_Helpers::is_enabled( $post->ID ) ) {
				$popups[ (int) $post->ID ] = $post;
			}
		}
		return $popups;
	}

	public static function manual_allowed( $popup_id ) {
		return 'no' !== PopupXD_Helpers::get( $popup_id, 'trigger_manual', 'yes' );
	}

	/**
	 * True inside the Elementor editor preview iframe — popups must not
	 * auto-open over the canvas the admin is designing.
	 */
	public static function is_builder_preview() {
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$el = \Elementor\Plugin::$instance;
			if ( isset( $el->preview ) && method_exists( $el->preview, 'is_preview_mode' ) && $el->preview->is_preview_mode() ) {
				return true;
			}
		}
		return false;
	}

	/* --------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueues frontend CSS/JS once. Safe to call late (from a shortcode
	 * rendered in the body): WordPress then prints them in the footer.
	 */
	public static function enqueue_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;

		$css = PopupXD_Helpers::asset_path( 'assets/frontend/frontend.css' );
		$js  = PopupXD_Helpers::asset_path( 'assets/frontend/frontend.js' );

		wp_enqueue_style( 'popupxd-frontend', POPUPXD_URL . $css, array(), PopupXD_Helpers::asset_version( $css ) );
		wp_enqueue_script( 'popupxd-frontend', POPUPXD_URL . $js, array(), PopupXD_Helpers::asset_version( $js ), true );
	}

	/**
	 * Enqueues the Elementor template CSS for a popup (if it uses one).
	 */
	public static function enqueue_popup_styles( $popup_id ) {
		if ( 'elementor' !== PopupXD_Helpers::get( $popup_id, 'content_type', 'editor' ) ) {
			return;
		}
		$template_id = (int) PopupXD_Helpers::get( $popup_id, 'elementor_template_id', '' );
		if ( $template_id ) {
			PopupXD_Elementor::ensure_styles( $template_id );
		}
	}

	public function assets() {
		if ( self::is_builder_preview() || self::$preview_mode ) {
			return;
		}
		$active_ids = self::get_active_popup_ids();
		if ( empty( $active_ids ) ) {
			return;
		}
		self::enqueue_assets();
		// Popup content is echoed in wp_footer — register each Elementor
		// template's CSS now so it lands in <head> (see ensure_styles()).
		foreach ( $active_ids as $popup_id ) {
			self::enqueue_popup_styles( $popup_id );
		}
	}

	/**
	 * IDs of popups that will appear on this request: auto-matched by
	 * targeting, or referenced by a [popupxd] shortcode in the current
	 * page's content / Elementor data. Shortcodes placed elsewhere (header,
	 * footer, widgets, archives) are still handled: the shortcode itself
	 * enqueues assets late. Cached per request.
	 */
	public static function get_active_popup_ids() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$cached = array();
		$popups = self::get_popups();
		if ( empty( $popups ) ) {
			return $cached;
		}
		$referenced = self::get_referenced_shortcode_ids();
		foreach ( $popups as $popup_id => $popup ) {
			$is_ref = in_array( $popup_id, $referenced, true ) && self::manual_allowed( $popup_id );
			if ( $is_ref || self::matches_current_page( $popup_id ) ) {
				$cached[] = $popup_id;
			}
		}
		return $cached;
	}

	/**
	 * Scans the current singular post's content (and Elementor data) for
	 * [popupxd id="X"] references.
	 */
	private static function get_referenced_shortcode_ids() {
		if ( ! is_singular() ) {
			return array();
		}
		$current_id = get_queried_object_id();
		if ( ! $current_id ) {
			return array();
		}

		$haystack = (string) get_post_field( 'post_content', $current_id );

		$elementor_data = get_post_meta( $current_id, '_elementor_data', true );
		if ( $elementor_data ) {
			$haystack .= ' ' . ( is_array( $elementor_data ) ? wp_json_encode( $elementor_data ) : $elementor_data );
		}

		if ( '' === $haystack || false === stripos( $haystack, 'popupxd' ) ) {
			return array();
		}

		$ids = array();
		// Plain shortcode ( id="123" ) and Elementor's JSON-escaped form ( id=\"123\" ).
		if ( preg_match_all( '/\[popupxd\s[^\]]*?id\s*=\s*\\\\?["\']?(\d+)/i', $haystack, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		// Links to "#popupxd-123" (Elementor Button, menu item, image link…).
		if ( preg_match_all( '/#popupxd-(\d+)/i', $haystack, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/* --------------------------------------------------------------------
	 * Targeting
	 * ------------------------------------------------------------------ */

	/**
	 * ID of the post/page being viewed, or 0 on archives/search/404 — so a
	 * category ID is never compared against post IDs.
	 */
	private static function current_singular_id() {
		if ( is_singular() || ( is_home() && ! is_front_page() ) ) {
			return (int) get_queried_object_id();
		}
		return 0;
	}

	/**
	 * Whether a popup should auto-display on the current request, based on
	 * its "Nơi hiển thị" settings. Cached per popup per request.
	 */
	public static function matches_current_page( $popup_id ) {
		static $cache = array();
		$popup_id = (int) $popup_id;
		if ( isset( $cache[ $popup_id ] ) ) {
			return $cache[ $popup_id ];
		}
		$cache[ $popup_id ] = self::compute_match( $popup_id );
		return $cache[ $popup_id ];
	}

	private static function compute_match( $popup_id ) {
		$current_id = self::current_singular_id();

		$exclude_ids = PopupXD_Helpers::get( $popup_id, 'exclude_ids', '' );
		if ( $exclude_ids && $current_id ) {
			$excluded = array_map( 'intval', explode( ',', $exclude_ids ) );
			if ( in_array( $current_id, $excluded, true ) ) {
				return false;
			}
		}

		if ( 'everywhere' === PopupXD_Helpers::get( $popup_id, 'display_scope', 'everywhere' ) ) {
			return true;
		}

		$target_pages = PopupXD_Helpers::get_array( $popup_id, 'target_pages' );
		$target_types = PopupXD_Helpers::get_array( $popup_id, 'target_post_types' );

		if ( $current_id && is_page() && in_array( (string) $current_id, $target_pages, true ) ) {
			return true;
		}
		if ( is_singular() ) {
			$current_type = get_post_type( get_queried_object_id() );
			if ( $current_type && in_array( $current_type, $target_types, true ) ) {
				return true;
			}
		}
		return false;
	}

	/* --------------------------------------------------------------------
	 * Output
	 * ------------------------------------------------------------------ */

	/**
	 * Prints popups queued by shortcodes. Loops because a popup's own content
	 * may contain another [popupxd] shortcode.
	 */
	private function print_forced() {
		$guard = 0;
		do {
			$printed_any = false;
			foreach ( array_unique( array_map( 'intval', self::$force_ids ) ) as $popup_id ) {
				if ( in_array( $popup_id, self::$printed_ids, true ) ) {
					continue;
				}
				$post = get_post( $popup_id );
				if ( ! $post || POPUPXD_CPT !== $post->post_type || 'publish' !== $post->post_status || ! self::manual_allowed( $popup_id ) || ! PopupXD_Helpers::is_enabled( $popup_id ) ) {
					self::$printed_ids[] = $popup_id;
					continue;
				}
				self::print_popup( $post );
				$printed_any = true;
			}
		} while ( $printed_any && ++$guard < 5 );
	}

	private static function print_popup( $post ) {
		self::$printed_ids[] = (int) $post->ID;
		// Late enqueue (no-op if already done in <head>) — prints in footer.
		self::enqueue_assets();
		self::enqueue_popup_styles( $post->ID );
		echo self::render_popup( $post ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function output_popups() {
		if ( self::is_builder_preview() || self::$preview_mode ) {
			return;
		}
		$this->print_forced();

		// Auto-targeted popups + popups referenced in this page's content.
		$popups = self::get_popups();
		foreach ( self::get_active_popup_ids() as $popup_id ) {
			if ( isset( $popups[ $popup_id ] ) && ! in_array( $popup_id, self::$printed_ids, true ) ) {
				self::print_popup( $popups[ $popup_id ] );
			}
		}

		// Shortcodes inside the popups just printed.
		$this->print_forced();
	}

	/**
	 * Full popup markup (overlay + dialog box + content).
	 *
	 * @param WP_Post $post
	 * @param array   $overrides Already-sanitized field values that replace the
	 *                           saved ones (used by the admin preview to show
	 *                           unsaved changes). Special key "__content" replaces
	 *                           the editor content.
	 */
	public static function render_popup( $post, $overrides = array() ) {
		$id = (int) $post->ID;
		$ov = is_array( $overrides ) ? $overrides : array();
		$g  = function ( $key, $default = '' ) use ( $id, $ov ) {
			if ( array_key_exists( $key, $ov ) ) {
				return ( '' === $ov[ $key ] || null === $ov[ $key ] ) ? $default : $ov[ $key ];
			}
			return PopupXD_Helpers::get( $id, $key, $default );
		};
		$saved = function ( $key, $default ) use ( $id, $ov ) {
			return array_key_exists( $key, $ov ) ? $ov[ $key ] : PopupXD_Helpers::get_saved( $id, $key, $default );
		};

		$content_type    = $g( 'content_type', 'editor' );
		$template_id     = (int) $g( 'elementor_template_id', '' );
		$max_width       = max( 100, (int) $g( 'max_width', '600' ) );
		$border_width    = max( 0, (int) $g( 'border_width', '0' ) );
		$border_style    = $g( 'border_style', 'none' );
		$border_color    = $g( 'border_color', '#dddddd' );
		$border_radius   = max( 0, (int) $g( 'border_radius', '8' ) );
		$content_padding = $g( 'content_padding', '28px' );
		$shadow          = $g( 'shadow', 'md' );
		$show_close      = $g( 'show_close', 'yes' );
		$close_padding   = $g( 'close_padding', '4px' );
		$close_bg        = $g( 'close_bg', 'transparent' );
		$close_color     = $g( 'close_color', '#333333' );
		$close_bwidth    = max( 0, (int) $g( 'close_border_width', '0' ) );
		$close_bstyle    = $g( 'close_border_style', 'none' );
		$close_bcolor    = $g( 'close_border_color', '#dddddd' );
		$close_radius    = max( 0, (int) $g( 'close_radius', '0' ) );
		$close_top       = $saved( 'close_offset_top', '8' );
		$close_right     = $saved( 'close_offset_right', '12' );
		$close_bottom    = $saved( 'close_offset_bottom', '' );
		$close_left      = $saved( 'close_offset_left', '' );

		$position       = $g( 'position', 'center' );
		$animation      = $g( 'animation', 'fade' );
		$overlay_color  = $g( 'overlay_color', '#000000' );
		$overlay_opac   = $g( 'overlay_opacity', '60' );
		$disable_scroll = $g( 'disable_scroll', 'yes' );
		$close_outside  = $g( 'close_outside', 'yes' );

		$trigger_time   = $g( 'trigger_time', 'no' );
		$time_delay     = max( 0, (int) $g( 'time_delay', '3' ) );
		$trigger_scroll = $g( 'trigger_scroll', 'no' );
		$scroll_percent = min( 100, max( 1, (int) $g( 'scroll_percent', '50' ) ) );
		$trigger_exit   = $g( 'trigger_exit', 'no' );
		$trigger_click  = $g( 'trigger_click', 'no' );
		$click_selector = $g( 'click_selector', '' );

		$cookie_enable  = $g( 'cookie_enable', 'no' );
		$cookie_days    = max( 0, (int) $g( 'cookie_days', '7' ) );
		$reopen_enable  = $g( 'reopen_enable', 'no' );
		$reopen_seconds = max( 1, (int) $g( 'reopen_seconds', '30' ) );

		$devices = array_key_exists( 'target_devices', $ov ) ? (array) $ov['target_devices'] : PopupXD_Helpers::get_array( $id, 'target_devices' );
		$devices = array_intersect( $devices, array( 'desktop', 'tablet', 'mobile' ) );
		if ( empty( $devices ) ) {
			$devices = array( 'desktop', 'tablet', 'mobile' );
		}

		if ( 'elementor' === $content_type && $template_id ) {
			$content_html = PopupXD_Elementor::render_template( $template_id );
		} else {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post );
			$content_html = apply_filters( 'the_content', array_key_exists( '__content', $ov ) ? $ov['__content'] : $post->post_content );
			wp_reset_postdata();
		}

		$offset_css = '';
		$offset_map = array( 'top' => 'offset_top', 'bottom' => 'offset_bottom', 'left' => 'offset_left', 'right' => 'offset_right' );
		if ( isset( $offset_map[ $position ] ) ) {
			$offset_css = $position . ':' . (int) $g( $offset_map[ $position ], '0' ) . 'px;';
		}

		$box_style = sprintf(
			'max-width:%dpx;border:%dpx %s %s;border-radius:%dpx;--pxd-radius:%dpx;%s',
			$max_width,
			$border_width,
			$border_style,
			$border_color,
			$border_radius,
			$border_radius,
			$offset_css
		);

		// Close button placement: a preset corner, or the 4 legacy offsets
		// ("custom" — also used by popups saved before 1.3, which have no preset).
		$close_pos    = $g( 'close_position', '' );
		$close_pos    = '' === $close_pos ? 'custom' : $close_pos;
		$close_gap    = max( -50, min( 100, (int) $g( 'close_offset', '10' ) ) );
		switch ( $close_pos ) {
			case 'top-left':
				$close_place = 'top:' . $close_gap . 'px;left:' . $close_gap . 'px;';
				break;
			case 'outside-top-right':
				$close_place = 'bottom:calc(100% + ' . $close_gap . 'px);right:0;';
				break;
			case 'outside-top-left':
				$close_place = 'bottom:calc(100% + ' . $close_gap . 'px);left:0;';
				break;
			case 'custom':
				// "Trên" wins over "Dưới", "Phải" over "Trái" when both are filled.
				$close_place = ( '' !== $close_top ? 'top:' . (int) $close_top . 'px;' : ( '' !== $close_bottom ? 'bottom:' . (int) $close_bottom . 'px;' : '' ) )
					. ( '' !== $close_right ? 'right:' . (int) $close_right . 'px;' : ( '' !== $close_left ? 'left:' . (int) $close_left . 'px;' : '' ) );
				break;
			default: // top-right
				$close_place = 'top:' . $close_gap . 'px;right:' . $close_gap . 'px;';
		}

		$close_style = sprintf(
			'padding:%s;background:%s;color:%s;border:%dpx %s %s;border-radius:%dpx;%s',
			$close_padding,
			$close_bg,
			$close_color,
			$close_bwidth,
			$close_bstyle,
			$close_bcolor,
			$close_radius,
			$close_place
		);

		$mobile_style     = $g( 'mobile_style', 'default' );
		$count_mode       = $g( 'count_mode', 'close' );
		$form_close       = $g( 'form_close', 'no' );
		$form_close_delay = max( 0, min( 60, (int) $g( 'form_close_delay', '3' ) ) );
		$form_hide        = $g( 'form_hide_enable', 'yes' );
		$form_hide_days   = max( 0, (int) $g( 'form_hide_days', '30' ) );

		$classes = array(
			'popupxd-overlay',
			'popupxd-pos-' . $position,
			'popupxd-anim-' . $animation,
			'popupxd-shadow-' . $shadow,
			'popupxd-close-' . $close_pos,
		);
		if ( 'sheet' === $mobile_style ) {
			$classes[] = 'popupxd-mobile-sheet';
		}
		foreach ( $devices as $d ) {
			$classes[] = 'pxd-dev-' . $d;
		}

		$title = array_key_exists( '__title', $ov ) ? $ov['__title'] : $post->post_title;
		$label = '' !== trim( $title ) ? $title : 'Popup';

		ob_start();
		?>
		<div id="popupxd-<?php echo esc_attr( $id ); ?>"
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-popupxd-id="<?php echo esc_attr( $id ); ?>"
			data-trigger-time="<?php echo esc_attr( $trigger_time ); ?>"
			data-time-delay="<?php echo esc_attr( $time_delay ); ?>"
			data-trigger-scroll="<?php echo esc_attr( $trigger_scroll ); ?>"
			data-scroll-percent="<?php echo esc_attr( $scroll_percent ); ?>"
			data-trigger-exit="<?php echo esc_attr( $trigger_exit ); ?>"
			data-trigger-click="<?php echo esc_attr( $trigger_click ); ?>"
			data-click-selector="<?php echo esc_attr( $click_selector ); ?>"
			data-disable-scroll="<?php echo 'yes' === $disable_scroll ? '1' : '0'; ?>"
			data-close-outside="<?php echo 'yes' === $close_outside ? '1' : '0'; ?>"
			data-cookie="<?php echo 'yes' === $cookie_enable ? '1' : '0'; ?>"
			data-cookie-days="<?php echo esc_attr( $cookie_days ); ?>"
			data-reopen="<?php echo 'yes' === $reopen_enable ? '1' : '0'; ?>"
			data-reopen-seconds="<?php echo esc_attr( $reopen_seconds ); ?>"
			data-count-mode="<?php echo esc_attr( $count_mode ); ?>"
			data-form-close="<?php echo 'yes' === $form_close ? esc_attr( $form_close_delay ) : '-1'; ?>"
			data-form-hide-days="<?php echo 'yes' === $form_hide ? esc_attr( $form_hide_days ) : '-1'; ?>"
			data-popupxd-title="<?php echo esc_attr( $label ); ?>"
			aria-hidden="true"
			style="background-color:<?php echo esc_attr( PopupXD_Helpers::hex_to_rgba( $overlay_color, (int) $overlay_opac ) ); ?>;display:none;">
			<div class="popupxd-box" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $label ); ?>" tabindex="-1" style="<?php echo esc_attr( $box_style ); ?>">
				<?php if ( 'yes' === $show_close ) : ?>
					<button type="button" class="popupxd-close" aria-label="<?php echo esc_attr__( 'Đóng', 'popup-xd' ); ?>" style="<?php echo esc_attr( $close_style ); ?>">&times;</button>
				<?php endif; ?>
				<div class="popupxd-content" style="padding:<?php echo esc_attr( $content_padding ); ?>;">
					<?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
