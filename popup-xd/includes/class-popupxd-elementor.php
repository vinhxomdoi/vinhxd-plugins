<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PopupXD_Elementor {

	public function __construct() {
		// Reserved for future hooks (e.g. registering a native Elementor popup widget).
	}

	public static function is_active() {
		return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Returns saved Elementor library templates (elementor_library CPT).
	 * Works with Elementor free — no Pro dependency.
	 */
	public static function get_saved_templates() {
		if ( ! self::is_active() ) {
			return array();
		}
		$query = new WP_Query( array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );
		return $query->posts;
	}

	/**
	 * Creates an empty, published "Page" template in Elementor's library and
	 * returns its ID (0 on failure). Uses Elementor's Documents API so the
	 * template is set up exactly like one made from Templates → Add New.
	 */
	public static function create_template( $title ) {
		if ( ! self::is_active() || ! post_type_exists( 'elementor_library' ) ) {
			return 0;
		}

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$el = \Elementor\Plugin::$instance;
			if ( isset( $el->documents ) && method_exists( $el->documents, 'create' ) ) {
				try {
					$document = $el->documents->create(
						'page',
						array(
							'post_title'  => $title,
							'post_status' => 'publish',
						)
					);
					if ( $document && ! is_wp_error( $document ) && method_exists( $document, 'get_main_id' ) ) {
						return (int) $document->get_main_id();
					}
				} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
					// Fall through to the manual method below.
				}
			}
		}

		// Fallback: create the library post by hand.
		$id = wp_insert_post( array(
			'post_type'   => 'elementor_library',
			'post_status' => 'publish',
			'post_title'  => $title,
		), true );
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'page' );
		update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
		if ( taxonomy_exists( 'elementor_library_type' ) ) {
			wp_set_object_terms( $id, 'page', 'elementor_library_type' );
		}
		return (int) $id;
	}

	/**
	 * Render an Elementor template's frontend HTML by template ID.
	 */
	public static function render_template( $template_id ) {
		$template_id = (int) $template_id;
		if ( ! $template_id || ! self::is_active() ) {
			return '';
		}
		$template = get_post( $template_id );
		if ( ! $template || 'publish' !== $template->post_status ) {
			return '';
		}
		if ( class_exists( '\Elementor\Plugin' ) ) {
			return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id );
		}
		return '';
	}

	/**
	 * Force-registers the per-template CSS file so it prints in <head>.
	 *
	 * Popup XD echoes its markup (including the Elementor template content)
	 * in wp_footer — by that point WordPress has already printed <head>
	 * styles, so Elementor's own per-template stylesheet (colors, spacing,
	 * custom widget styles set in the Style tab) would silently never load.
	 * Calling this early (wp_enqueue_scripts) registers/enqueues that
	 * stylesheet in time, the same way Elementor would if the template were
	 * rendered normally in the page body.
	 */
	public static function ensure_styles( $template_id ) {
		static $done = array();
		$template_id = (int) $template_id;
		if ( ! $template_id || isset( $done[ $template_id ] ) || ! self::is_active() ) {
			return;
		}
		$done[ $template_id ] = true;
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			$css_file = new \Elementor\Core\Files\CSS\Post( $template_id );
			$css_file->enqueue();
			return;
		}
		if ( class_exists( '\Elementor\Post_CSS_File' ) ) {
			$css_file = new \Elementor\Post_CSS_File( $template_id );
			$css_file->enqueue();
		}
	}
}
