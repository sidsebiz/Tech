<?php
/**
 * Reading history for signed-in readers.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Database;

/**
 * {prefix}tv_reading_history accessor.
 */
final class History {

	/**
	 * Record (or refresh) a view.
	 *
	 * @param int $user_id User.
	 * @param int $post_id Post.
	 */
	public static function touch( int $user_id, int $post_id ): void {
		global $wpdb;
		$table = Schema::table( 'reading_history' );
		$now   = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (user_id, post_id, viewed_at) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE viewed_at = %s", $user_id, $post_id, $now, $now ) );
	}

	/**
	 * Recent items.
	 *
	 * @param int $user_id User.
	 * @param int $limit   Max rows.
	 * @return array<int,array{post_id:int,viewed_at:string}>
	 */
	public static function recent( int $user_id, int $limit = 20 ): array {
		global $wpdb;
		$table = Schema::table( 'reading_history' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, viewed_at FROM {$table} WHERE user_id = %d ORDER BY viewed_at DESC LIMIT %d", $user_id, $limit ), ARRAY_A );
		return array_map(
			static fn( $r ) => array(
				'post_id'   => (int) $r['post_id'],
				'viewed_at' => (string) $r['viewed_at'],
			),
			(array) $rows
		);
	}

	/**
	 * Clear a user's history.
	 *
	 * @param int $user_id User.
	 */
	public static function clear( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'reading_history' ), array( 'user_id' => $user_id ), array( '%d' ) );
	}
}
