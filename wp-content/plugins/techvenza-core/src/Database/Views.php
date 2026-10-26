<?php
/**
 * Daily view counts and period aggregation (24h / 7d / 30d / all).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Database;

/**
 * Lightweight counter backed by {prefix}tv_post_views.
 */
final class Views {

	/**
	 * Increment today's views for a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $by      Increment.
	 */
	public static function record( int $post_id, int $by = 1 ): void {
		global $wpdb;
		$table = Schema::table( 'post_views' );
		$today = current_time( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (post_id, view_date, views) VALUES (%d, %s, %d) ON DUPLICATE KEY UPDATE views = views + %d", $post_id, $today, $by, $by ) );
		update_post_meta( $post_id, 'tv_view_count', (int) get_post_meta( $post_id, 'tv_view_count', true ) + $by );
	}

	/**
	 * Set a specific day's count (seeding / imports).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $date    Y-m-d.
	 * @param int    $views   Views.
	 */
	public static function set_day( int $post_id, string $date, int $views ): void {
		global $wpdb;
		$table = Schema::table( 'post_views' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE post_id = %d AND view_date = %s", $post_id, $date ) );
		$wpdb->insert(
			$table,
			array(
				'post_id'   => $post_id,
				'view_date' => $date,
				'views'     => $views,
			),
			array( '%d', '%s', '%d' )
		);
	}

	/**
	 * Recalculate period meta for all posts with views (cron, hourly).
	 */
	public static function aggregate(): void {
		global $wpdb;
		$table = Schema::table( 'post_views' );
		$today = current_time( 'Y-m-d' );
		$map   = array(
			'tv_views_24h' => 1,
			'tv_views_7d'  => 7,
			'tv_views_30d' => 30,
		);
		foreach ( $map as $key => $days ) {
			$from = gmdate( 'Y-m-d', strtotime( $today . ' -' . ( $days - 1 ) . ' days' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, SUM(views) AS v FROM {$table} WHERE view_date >= %s GROUP BY post_id", $from ) );
			$seen = array();
			foreach ( (array) $rows as $row ) {
				update_post_meta( (int) $row->post_id, $key, (int) $row->v );
				$seen[] = (int) $row->post_id;
			}
			// Zero posts that dropped out of the window.
			$stale = ( new \WP_Query(
				array(
					'post_type'      => 'post',
					'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
					'fields'         => 'ids',
					'post__not_in'   => $seen ? $seen : array( 0 ),
					'meta_key'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_compare'   => '>',
					'meta_value'     => 0, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'no_found_rows'  => true,
				)
			) )->posts;
			foreach ( $stale as $id ) {
				update_post_meta( (int) $id, $key, 0 );
			}
		}
		// Totals.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$totals = $wpdb->get_results( "SELECT post_id, SUM(views) AS v FROM {$table} GROUP BY post_id" );
		foreach ( (array) $totals as $row ) {
			update_post_meta( (int) $row->post_id, 'tv_view_count', (int) $row->v );
		}
	}

	/**
	 * Daily series for charts.
	 *
	 * @param int            $days     Number of days.
	 * @param array<int,int> $post_ids Restrict to posts (empty = all).
	 * @return array<string,int> Y-m-d → views.
	 */
	public static function series( int $days, array $post_ids = array() ): array {
		global $wpdb;
		$table = Schema::table( 'post_views' );
		$from  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . ( $days - 1 ) . ' days' ) );
		$where = $wpdb->prepare( 'view_date >= %s', $from );
		if ( $post_ids ) {
			$where .= ' AND post_id IN (' . implode( ',', array_map( 'intval', $post_ids ) ) . ')';
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT view_date AS d, SUM(views) AS v FROM {$table} WHERE {$where} GROUP BY view_date" );
		$out  = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$out[ gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " -{$i} days" ) ) ] = 0;
		}
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->d ] = (int) $row->v;
		}
		return $out;
	}
}
