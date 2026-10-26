<?php
/**
 * Server-side capability mapping for the editorial workflow.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Workflow;

/**
 * Locks author editing while an article is with the editorial team and blocks suspended accounts.
 */
final class Capabilities {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'map_meta_cap', array( self::class, 'map' ), 10, 4 );
		add_filter( 'user_has_cap', array( self::class, 'suspended' ), 10, 4 );
	}

	/**
	 * Meta capability mapping.
	 *
	 * @param array<int,string> $caps    Primitive caps required.
	 * @param string            $cap     Meta cap.
	 * @param int               $user_id User.
	 * @param array<int,mixed>  $args    Args (post ID first).
	 * @return array<int,string>
	 */
	public static function map( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post || 'post' !== $post->post_type ) {
			return $caps;
		}
		// Reviewers keep normal WordPress behaviour.
		if ( user_can( $user_id, 'tv_review_posts' ) ) {
			return $caps;
		}
		$own = (int) $post->post_author === $user_id;
		if ( ! $own ) {
			return array( 'do_not_allow' );
		}
		if ( ! in_array( $post->post_status, Statuses::AUTHOR_EDITABLE, true ) ) {
			return array( 'do_not_allow' );
		}
		if ( 'delete_post' === $cap && 'draft' !== $post->post_status && 'auto-draft' !== $post->post_status ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	/**
	 * Suspended accounts lose all capabilities except reading.
	 *
	 * @param array<string,bool> $allcaps All caps.
	 * @param array<int,string>  $caps    Requested.
	 * @param array<int,mixed>   $args    Args.
	 * @param \WP_User           $user    User.
	 * @return array<string,bool>
	 */
	public static function suspended( array $allcaps, array $caps, array $args, \WP_User $user ): array {
		if ( $user->ID && 'suspended' === get_user_meta( $user->ID, 'tv_account_status', true ) && empty( $allcaps['manage_options'] ) ) {
			return array( 'read' => true );
		}
		return $allcaps;
	}
}
