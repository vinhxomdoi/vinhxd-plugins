<?php
/**
 * Dọn dữ liệu khi XOÁ plugin (không chạy khi chỉ tắt plugin).
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

foreach ( [ '_xd_mega', '_xd_mega_width', '_xd_mega_template', '_xd_mega_content' ] as $key ) {
	delete_post_meta_by_key( $key );
}

foreach ( [ 'xd_mm_cache_ver', 'xd_mm_grid_track', 'xd_mm_version' ] as $opt ) {
	delete_option( $opt );
}

$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_xd\_mm\_%'
	    OR option_name LIKE '\_transient\_timeout\_xd\_mm\_%'"
);
