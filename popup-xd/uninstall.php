<?php
/**
 * Popup XD uninstall.
 *
 * Popups are user content, so by default they are KEPT when the plugin is
 * deleted (reinstalling brings everything back). To wipe all popups and
 * their settings on delete, add this to wp-config.php before deleting:
 *
 *     define( 'POPUPXD_REMOVE_DATA_ON_UNINSTALL', true );
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( ! defined( 'POPUPXD_REMOVE_DATA_ON_UNINSTALL' ) || ! POPUPXD_REMOVE_DATA_ON_UNINSTALL ) {
	return;
}

$popupxd_ids = get_posts( array(
	'post_type'      => 'popupxd',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
) );
foreach ( $popupxd_ids as $popupxd_id ) {
	wp_delete_post( $popupxd_id, true ); // also removes its _pxd_ meta
}
