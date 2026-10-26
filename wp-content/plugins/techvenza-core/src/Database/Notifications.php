<?php
/**
 * In-app notifications storage.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Database;

/**
 * {prefix}tv_notifications accessor.
 */
final class Notifications {

	/**
	 * Add a notification.
	 *
	 * @param int    $user_id   Recipient.
	 * @param string $type      revision|status|approved|published|account|application.
	 * @param string $title     Title.
	 * @param string $message   Message.
	 * @param string $link      Link.
	 * @param int    $object_id Related object.
	 * @param string $at        Created at (site time).
	 */
	public static function add( int $user_id, string $type, string $title, string $message = '', string $link = '', int $object_id = 0, string $at = '' ): int {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'notifications' ),
			array(
				'user_id'    => $user_id,
				'type'       => sanitize_key( $type ),
				'title'      => wp_strip_all_tags( $title ),
				'message'    => wp_strip_all_tags( $message ),
				'link'       => esc_url_raw( $link ),
				'object_id'  => $object_id,
				'is_read'    => 0,
				'created_at' => $at ? $at : current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * List notifications.
	 *
	 * @param int    $user_id User.
	 * @param string $filter  all|unread|<type>.
	 * @param int    $limit   Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list( int $user_id, string $filter = 'all', int $limit = 50 ): array {
		global $wpdb;
		$table = Schema::table( 'notifications' );
		$where = $wpdb->prepare( 'user_id = %d', $user_id );
		if ( 'unread' === $filter ) {
			$where .= ' AND is_read = 0';
		} elseif ( 'approved' === $filter ) {
			$where .= " AND type IN ('approved','published','status')";
		} elseif ( 'all' !== $filter ) {
			$where .= $wpdb->prepare( ' AND type = %s', $filter );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $where built with prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d", $limit ), ARRAY_A );
		return array_map(
			static fn( $r ) => array(
				'id'      => (int) $r['id'],
				'type'    => (string) $r['type'],
				'title'   => (string) $r['title'],
				'message' => (string) $r['message'],
				'link'    => (string) $r['link'],
				'unread'  => ! (int) $r['is_read'],
				'at'      => (string) $r['created_at'],
			),
			(array) $rows
		);
	}

	/**
	 * Unread count.
	 *
	 * @param int $user_id User.
	 */
	public static function unread( int $user_id ): int {
		global $wpdb;
		$table = Schema::table( 'notifications' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0", $user_id ) );
	}

	/**
	 * Mark read (one or all).
	 *
	 * @param int      $user_id User.
	 * @param int|null $id      Notification (null = all).
	 */
	public static function mark_read( int $user_id, ?int $id = null ): void {
		global $wpdb;
		$where = array( 'user_id' => $user_id );
		if ( null !== $id ) {
			$where['id'] = $id;
		}
		$wpdb->update( Schema::table( 'notifications' ), array( 'is_read' => 1 ), $where );
	}

	/**
	 * Delete all notifications for a user.
	 *
	 * @param int $user_id User.
	 */
	public static function delete_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'notifications' ), array( 'user_id' => $user_id ), array( '%d' ) );
	}
}
