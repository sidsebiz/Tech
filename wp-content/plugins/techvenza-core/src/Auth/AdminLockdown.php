<?php
/**
 * Keeps readers and applicants out of wp-admin and hides their admin bar; limits authors
 * to the screens they need (editor, media, profile).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Auth;

use Techvenza\Core\Settings\Options;

/**
 * Access rules for wp-admin.
 */
final class AdminLockdown {

	/**
	 * Admin screens authors may open.
	 */
	private const AUTHOR_SCREENS = array( 'post.php', 'post-new.php', 'edit.php', 'upload.php', 'media-new.php', 'async-upload.php', 'profile.php', 'index.php', 'admin-ajax.php', 'admin-post.php' );

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'guard' ), 1 );
		add_filter( 'show_admin_bar', array( self::class, 'admin_bar' ) );
		add_action( 'admin_menu', array( self::class, 'author_menu' ), 999 );
	}

	/**
	 * Whether the user is a reader-level account (no editing rights).
	 *
	 * @param \WP_User $user User.
	 */
	public static function is_reader( \WP_User $user ): bool {
		return ! user_can( $user, 'edit_posts' ) && ! user_can( $user, 'manage_options' );
	}

	/**
	 * Redirect readers to their dashboard; keep authors on allowed screens.
	 */
	public static function guard(): void {
		if ( wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! Options::enabled( 'admin_lockdown' ) || ! is_user_logged_in() ) {
			return;
		}
		global $pagenow;
		$user = wp_get_current_user();
		if ( in_array( $pagenow, array( 'admin-post.php', 'admin-ajax.php', 'async-upload.php' ), true ) ) {
			return;
		}
		if ( self::is_reader( $user ) ) {
			wp_safe_redirect( Auth::home_for( $user ) );
			exit;
		}
		if ( ! user_can( $user, 'tv_review_posts' ) && ! user_can( $user, 'manage_options' ) && ! in_array( $pagenow, self::AUTHOR_SCREENS, true ) ) {
			wp_safe_redirect( Auth::home_for( $user ) );
			exit;
		}
		if ( 'index.php' === $pagenow && ! user_can( $user, 'tv_review_posts' ) && ! user_can( $user, 'manage_options' ) ) {
			wp_safe_redirect( Auth::home_for( $user ) );
			exit;
		}
	}

	/**
	 * Readers never see the toolbar.
	 *
	 * @param bool $show Show.
	 */
	public static function admin_bar( bool $show ): bool {
		if ( ! is_user_logged_in() || ! Options::enabled( 'admin_lockdown' ) ) {
			return $show;
		}
		return self::is_reader( wp_get_current_user() ) ? false : $show;
	}

	/**
	 * Trim the admin menu for authors.
	 */
	public static function author_menu(): void {
		if ( current_user_can( 'tv_review_posts' ) || current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( array( 'index.php', 'edit-comments.php', 'tools.php' ) as $slug ) {
			remove_menu_page( $slug );
		}
	}
}
