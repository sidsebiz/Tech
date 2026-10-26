<?php
/**
 * View beacon: bot-filtered, de-duplicated per visitor/post/30 minutes, cache-friendly (POST only).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

use Techvenza\Core\Database\Views;

/**
 * POST /views/{id}.
 */
final class ViewsController {

	private const BOTS = '/bot|crawl|spider|slurp|bing|google|yandex|baidu|duckduck|facebookexternalhit|embedly|quora|pinterest|preview|headless|lighthouse|pagespeed|monitor|curl|wget|python|httpclient/i';

	/**
	 * Routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/views/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'record' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
	}

	/**
	 * Record a view.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function record( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request['id'];
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( ! $ua || preg_match( self::BOTS, $ua ) || 'publish' !== get_post_status( $id ) || 'post' !== get_post_type( $id ) ) {
			return new \WP_REST_Response( null, 204 );
		}
		$key = 'tv_v_' . substr( hash( 'sha256', Routes::ip_hash() . $ua . $id ), 0, 32 );
		if ( get_transient( $key ) ) {
			return new \WP_REST_Response( null, 204 );
		}
		set_transient( $key, 1, 30 * MINUTE_IN_SECONDS );
		if ( current_user_can( 'edit_post', $id ) && get_current_user_id() === (int) get_post_field( 'post_author', $id ) ) {
			return new \WP_REST_Response( null, 204 ); // Authors viewing their own work don't count.
		}
		Views::record( $id );
		return new \WP_REST_Response( null, 204 );
	}
}
