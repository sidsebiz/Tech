<?php
/**
 * Personalisation bootstrap: returns the signed-in user, a fresh REST nonce, bookmarks and topics.
 *
 * Served through admin-ajax (cookie auth, never page-cached) so public HTML can stay anonymous.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Support\Render;
use Techvenza\Core\Workflow\Roles;

/**
 * Session endpoint.
 */
final class SessionController {

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		add_action( 'wp_ajax_techvenza_session', array( self::class, 'session' ) );
		add_action( 'wp_ajax_nopriv_techvenza_session', array( self::class, 'session' ) );
	}

	/**
	 * Data for the current visitor.
	 *
	 * @return array<string,mixed>
	 */
	public static function data(): array {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return array( 'user' => null );
		}
		$author = UserMeta::author( $user );
		$links  = array(
			'dashboard' => Render::page_url( 'dashboard' ),
			'logout'    => wp_logout_url( home_url( '/' ) ),
			'portal'    => user_can( $user, 'tv_use_portal' ) ? Render::page_url( 'author-portal' ) : '',
			'cms'       => user_can( $user, 'tv_review_posts' ) ? admin_url( 'admin.php?page=techvenza' ) : '',
		);
		return array(
			'user'      => array(
				'id'        => $user->ID,
				'name'      => $user->display_name,
				'first'     => $author['first'] ?? $user->display_name,
				'initials'  => UserMeta::initials( $user->display_name ),
				'photo'     => $author['photo'] ?? '',
				'roleLabel' => class_exists( Roles::class ) ? Roles::label_for( $user ) : '',
				'links'     => $links,
			),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'bookmarks' => array_map( 'intval', UserMeta::get_list( $user->ID, 'tv_bookmarks' ) ),
			'topics'    => UserMeta::get_list( $user->ID, 'tv_topics' ),
		);
	}

	/**
	 * AJAX handler.
	 */
	public static function session(): void {
		nocache_headers();
		wp_send_json_success( self::data() );
	}
}
