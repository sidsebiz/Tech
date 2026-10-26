<?php
/**
 * REST: in-app notifications for the signed-in user.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

use Techvenza\Core\Database\Notifications;

/**
 * GET /me/notifications, POST /me/notifications/read.
 */
final class NotificationsController {

	/**
	 * Routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/me/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => array( Routes::class, 'logged_in' ),
				'args'                => array(
					'filter' => array(
						'type'    => 'string',
						'default' => 'all',
						'enum'    => array( 'all', 'unread', 'approved', 'feedback', 'status', 'system' ),
					),
				),
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/notifications/read',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'read' ),
				'permission_callback' => array( Routes::class, 'logged_in' ),
				'args'                => array(
					'id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * List.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function index( \WP_REST_Request $request ): \WP_REST_Response {
		$user = get_current_user_id();
		return rest_ensure_response(
			array(
				'items'  => Notifications::list( $user, (string) $request['filter'] ),
				'unread' => Notifications::unread( $user ),
			)
		);
	}

	/**
	 * Mark one (id) or all (id = 0) as read.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function read( \WP_REST_Request $request ): \WP_REST_Response {
		$user = get_current_user_id();
		$id   = (int) $request['id'];
		Notifications::mark_read( $user, $id ? $id : null );
		return rest_ensure_response( array( 'unread' => Notifications::unread( $user ) ) );
	}
}
