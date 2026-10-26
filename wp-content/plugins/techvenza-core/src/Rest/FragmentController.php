<?php
/**
 * Server-rendered fragments for in-place filtering/pagination (progressive enhancement).
 *
 * The same block render code serves full page loads (crawlable query-string URLs) and this
 * endpoint, so results are identical with or without JavaScript.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

use Techvenza\Core\Blocks\Params;
use Techvenza\Core\Support\Icons;

/**
 * GET /fragment/{block}.
 */
final class FragmentController {

	/**
	 * Blocks allowed to render through the public fragment endpoint.
	 */
	public const ALLOWED = array( 'explore', 'author-directory', 'category-latest', 'search-results', 'trending-page', 'guides-grid', 'editors-picks', 'archive-grid' );

	/**
	 * Routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/fragment/(?P<block>[a-z-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'render' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Render a block with the given attributes and query parameters.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function render( \WP_REST_Request $request ) {
		$block = (string) $request['block'];
		if ( ! in_array( $block, self::ALLOWED, true ) ) {
			return new \WP_Error( 'tv_forbidden', __( 'Unknown fragment.', 'techvenza-core' ), array( 'status' => 404 ) );
		}
		if ( Routes::rate_limited( 'fragment', 240, MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'tv_rate', __( 'Too many requests.', 'techvenza-core' ), array( 'status' => 429 ) );
		}
		$type  = \WP_Block_Type_Registry::get_instance()->get_registered( 'techvenza/' . $block );
		$attrs = json_decode( (string) $request->get_param( 'attrs' ), true );
		$attrs = is_array( $attrs ) && $type ? array_intersect_key( $attrs, $type->attributes ) : array();

		$params = array();
		foreach ( $request->get_query_params() as $k => $v ) {
			if ( is_scalar( $v ) && ! in_array( $k, array( 'attrs', 'rest_route', '_locale' ), true ) ) {
				$params[ sanitize_key( (string) $k ) ] = sanitize_text_field( (string) $v );
			}
		}
		Params::use( $params );
		if ( isset( $params['_term'] ) ) {
			$GLOBALS['techvenza_fragment_term'] = (int) $params['_term']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		Icons::use_inline( false );
		$html = render_block(
			array(
				'blockName'    => 'techvenza/' . $block,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		Params::use( null );
		$response = rest_ensure_response( array( 'html' => $html ) );
		$response->header( 'Cache-Control', 'public, max-age=120' );
		return $response;
	}
}
