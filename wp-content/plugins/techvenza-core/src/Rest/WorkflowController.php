<?php
/**
 * REST: editorial workflow state and transitions for an article.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

use Techvenza\Core\Workflow\Statuses;
use Techvenza\Core\Workflow\Workflow;

/**
 * GET /posts/{id}/workflow, POST /posts/{id}/transition.
 */
final class WorkflowController {

	/**
	 * Routes.
	 */
	public static function routes(): void {
		$id = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);
		register_rest_route(
			Routes::NS,
			'/posts/(?P<id>\d+)/workflow',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'state' ),
				'permission_callback' => array( self::class, 'can_view' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			Routes::NS,
			'/posts/(?P<id>\d+)/transition',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'transition' ),
				'permission_callback' => array( self::class, 'can_view' ),
				'args'                => $id + array(
					'action' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array_keys( Workflow::transitions() ),
					),
					'note'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'date'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * The author (own posts) or anyone who may review.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function can_view( \WP_REST_Request $request ): bool {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'post' !== $post->post_type || ! is_user_logged_in() ) {
			return false;
		}
		return get_current_user_id() === (int) $post->post_author || current_user_can( 'tv_review_posts' ) || current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Workflow state.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function state( \WP_REST_Request $request ): \WP_REST_Response {
		$post = get_post( (int) $request['id'] );
		return rest_ensure_response( self::payload( $post instanceof \WP_Post ? $post : null ) );
	}

	/**
	 * State payload.
	 *
	 * @param \WP_Post|null $post Post.
	 * @return array<string,mixed>
	 */
	public static function payload( ?\WP_Post $post ): array {
		if ( ! $post ) {
			return array();
		}
		$actions = array();
		foreach ( Workflow::available( $post ) as $key => $t ) {
			$actions[] = array(
				'key'   => $key,
				'label' => $t['label'],
				'note'  => ! empty( $t['note'] ),
				'date'  => ! empty( $t['date'] ),
			);
		}
		$timeline = array_map(
			static function ( $e ) {
				$u           = get_userdata( (int) ( $e['user'] ?? 0 ) );
				$e['author'] = $u ? $u->display_name : '';
				return $e;
			},
			Workflow::timeline( $post->ID )
		);
		return array(
			'id'          => $post->ID,
			'status'      => $post->post_status,
			'statusLabel' => Statuses::labels()[ $post->post_status ] ?? $post->post_status,
			'actions'     => $actions,
			'checklist'   => Workflow::checklist( $post ),
			'timeline'    => $timeline,
			'editor'      => (int) get_post_meta( $post->ID, 'tv_assigned_editor', true ),
		);
	}

	/**
	 * Perform a transition.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function transition( \WP_REST_Request $request ) {
		if ( Routes::rate_limited( 'transition', 60, MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'tv_rate', __( 'Too many requests. Please wait a moment.', 'techvenza-core' ), array( 'status' => 429 ) );
		}
		$result = Workflow::transition(
			(int) $request['id'],
			(string) $request['action'],
			array(
				'note' => (string) $request['note'],
				'date' => (string) $request['date'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		clean_post_cache( (int) $request['id'] );
		$post = get_post( (int) $request['id'] );
		return rest_ensure_response( self::payload( $post instanceof \WP_Post ? $post : null ) );
	}
}
