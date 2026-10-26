<?php
/**
 * Uninstall: removes Techvenza data only when Settings → Advanced → "Delete all data" is on.
 *
 * @package Techvenza\Core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$techvenza_settings = get_option( 'techvenza_settings', array() );
if ( empty( $techvenza_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'subscribers', 'post_views', 'reading_history', 'notifications' ) as $techvenza_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'tv_' . $techvenza_table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
foreach ( array( 'tv_application', 'tv_campaign', 'tv_message', 'tv_redirect' ) as $techvenza_type ) {
	$techvenza_ids = get_posts(
		array(
			'post_type'      => $techvenza_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $techvenza_ids as $techvenza_id ) {
		wp_delete_post( $techvenza_id, true );
	}
}
delete_option( 'techvenza_settings' );
delete_option( 'techvenza_db_version' );
foreach ( array( 'tv_author', 'tv_editor', 'tv_managing_editor', 'tv_applicant' ) as $techvenza_role ) {
	remove_role( $techvenza_role );
}
