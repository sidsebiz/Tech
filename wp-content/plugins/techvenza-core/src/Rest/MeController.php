<?php
/**
 * Signed-in reader endpoints: bookmarks, followed topics, reading history, preferences.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

use Techvenza\Core\Database\History;

/**
 * /me/* routes.
 */
final class MeController {

	/**
	 * Hooks: record reading history on article views (logged-in pages are not page-cached).
	 */
	public static function hooks(): void {
		add_action(
			'template_redirect',
			static function () {
				if ( is_singular( 'post' ) && is_user_logged_in() && ! is_preview() ) {
					History::touch( get_current_user_id(), (int) get_queried_object_id() );
				}
			}
		);
	}

	/**
	 * Routes.
	 */
	public static function routes(): void {
		$auth = array( Routes::class, 'logged_in' );
		register_rest_route(
			Routes::NS,
			'/me/bookmarks',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'bookmarks' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/bookmarks/(?P<id>\d+)',
			array(
				'methods'             => array( 'PUT', 'POST', 'DELETE' ),
				'callback'            => array( self::class, 'toggle_bookmark' ),
				'permission_callback' => $auth,
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/bookmarks',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'clear_bookmarks' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/topics',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'topics' ),
					'permission_callback' => $auth,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( self::class, 'set_topics' ),
					'permission_callback' => $auth,
					'args'                => array(
						'topics' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/topics/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => array( 'PUT', 'DELETE' ),
				'callback'            => array( self::class, 'toggle_topic' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/history',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'history' ),
					'permission_callback' => $auth,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'clear_history' ),
					'permission_callback' => $auth,
				),
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/preferences',
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'preferences' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/profile',
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'profile' ),
				'permission_callback' => $auth,
			)
		);
	}

	/**
	 * Current bookmarks (published posts only).
	 *
	 * @param int $user_id User ID.
	 * @return array<int,int>
	 */
	private static function list_bookmarks( int $user_id ): array {
		$ids = array_map( 'intval', (array) get_user_meta( $user_id, 'tv_bookmarks', true ) );
		return array_values( array_filter( $ids, static fn( $id ) => 'publish' === get_post_status( $id ) ) );
	}

	/**
	 * GET /me/bookmarks.
	 */
	public static function bookmarks(): \WP_REST_Response {
		return rest_ensure_response( array( 'bookmarks' => self::list_bookmarks( get_current_user_id() ) ) );
	}

	/**
	 * PUT|DELETE /me/bookmarks/{id}.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function toggle_bookmark( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( 'publish' !== get_post_status( $id ) || 'post' !== get_post_type( $id ) ) {
			return new \WP_Error( 'tv_not_found', __( 'Story not found.', 'techvenza-core' ), array( 'status' => 404 ) );
		}
		$user = get_current_user_id();
		$list = self::list_bookmarks( $user );
		$list = array_values( array_diff( $list, array( $id ) ) );
		if ( 'DELETE' !== $request->get_method() ) {
			array_unshift( $list, $id );
		}
		update_user_meta( $user, 'tv_bookmarks', array_slice( $list, 0, 500 ) );
		return rest_ensure_response( array( 'bookmarks' => $list ) );
	}

	/**
	 * DELETE /me/bookmarks.
	 */
	public static function clear_bookmarks(): \WP_REST_Response {
		update_user_meta( get_current_user_id(), 'tv_bookmarks', array() );
		return rest_ensure_response( array( 'bookmarks' => array() ) );
	}

	/**
	 * GET /me/topics.
	 */
	public static function topics(): \WP_REST_Response {
		return rest_ensure_response( array( 'topics' => (array) get_user_meta( get_current_user_id(), 'tv_topics', true ) ) );
	}

	/**
	 * PUT /me/topics.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function set_topics( \WP_REST_Request $request ): \WP_REST_Response {
		$topics = array_values( array_filter( array_map( 'sanitize_title', (array) $request->get_param( 'topics' ) ), static fn( $s ) => (bool) get_term_by( 'slug', $s, 'category' ) ) );
		update_user_meta( get_current_user_id(), 'tv_topics', $topics );
		return rest_ensure_response( array( 'topics' => $topics ) );
	}

	/**
	 * PUT|DELETE /me/topics/{slug}.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function toggle_topic( \WP_REST_Request $request ) {
		$slug = sanitize_title( (string) $request['slug'] );
		if ( ! get_term_by( 'slug', $slug, 'category' ) ) {
			return new \WP_Error( 'tv_not_found', __( 'Topic not found.', 'techvenza-core' ), array( 'status' => 404 ) );
		}
		$list = array_values( array_diff( (array) get_user_meta( get_current_user_id(), 'tv_topics', true ), array( $slug ) ) );
		if ( 'DELETE' !== $request->get_method() ) {
			$list[] = $slug;
		}
		update_user_meta( get_current_user_id(), 'tv_topics', $list );
		return rest_ensure_response( array( 'topics' => $list ) );
	}

	/**
	 * GET /me/history.
	 */
	public static function history(): \WP_REST_Response {
		return rest_ensure_response( array( 'history' => History::recent( get_current_user_id() ) ) );
	}

	/**
	 * DELETE /me/history.
	 */
	public static function clear_history(): \WP_REST_Response {
		History::clear( get_current_user_id() );
		return rest_ensure_response( array( 'history' => array() ) );
	}

	/**
	 * PUT /me/preferences — newsletter and email preferences.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function preferences( \WP_REST_Request $request ): \WP_REST_Response {
		$user  = get_current_user_id();
		$prefs = array();
		foreach ( (array) $request->get_param( 'email' ) as $k => $v ) {
			$prefs[ sanitize_key( (string) $k ) ] = (bool) $v;
		}
		if ( $prefs ) {
			update_user_meta( $user, 'tv_email_prefs', $prefs );
		}
		$freq = sanitize_key( (string) $request->get_param( 'frequency' ) );
		if ( in_array( $freq, array( 'weekly', 'monthly', 'off' ), true ) ) {
			update_user_meta( $user, 'tv_nl_frequency', $freq );
			if ( class_exists( \Techvenza\Core\Newsletter\Subscribers::class ) ) {
				\Techvenza\Core\Newsletter\Subscribers::sync_user_frequency( $user, $freq );
			}
		}
		return rest_ensure_response(
			array(
				'email'     => get_user_meta( $user, 'tv_email_prefs', true ),
				'frequency' => get_user_meta( $user, 'tv_nl_frequency', true ),
			)
		);
	}

	/**
	 * PUT /me/profile — reader display name and bio.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function profile( \WP_REST_Request $request ) {
		$user = get_current_user_id();
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new \WP_Error( 'tv_invalid', __( 'Display name is required.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		wp_update_user(
			array(
				'ID'           => $user,
				'display_name' => $name,
				'description'  => sanitize_textarea_field( (string) $request->get_param( 'bio' ) ),
			)
		);
		\Techvenza\Core\Content\UserMeta::flush( $user );
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
