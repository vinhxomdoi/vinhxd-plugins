<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PopupXD_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
	}

	public function register_cpt() {
		$labels = array(
			'name'               => 'Popup XD',
			'singular_name'      => 'Popup',
			'menu_name'          => 'Popup XD',
			'add_new'            => 'Thêm Popup',
			'add_new_item'       => 'Thêm Popup mới',
			'edit_item'          => 'Sửa Popup',
			'new_item'           => 'Popup mới',
			'view_item'          => 'Xem Popup',
			'search_items'       => 'Tìm Popup',
			'not_found'          => 'Không tìm thấy popup nào',
			'not_found_in_trash' => 'Không có popup trong thùng rác',
			'all_items'          => 'Tất cả Popup',
		);

		register_post_type( POPUPXD_CPT, array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-align-center',
			'menu_position'      => 58,
			'supports'           => array( 'title', 'editor' ),
			'capability_type'    => 'page',
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'exclude_from_search'=> true,
			'publicly_queryable' => false,
			'show_in_rest'       => false,
		) );
	}
}
