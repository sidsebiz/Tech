<?php
/**
 * Front-end script loading and the icon sprite.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Frontend;

use Techvenza\Core\Support\Icons;
use Techvenza\Core\Support\Render;

/**
 * Enqueues the shared front-end script (no jQuery) and prints the SVG sprite.
 */
final class Assets {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_body_open', array( Icons::class, 'print_sprite' ), 1 );
		add_action( 'wp_footer', array( Icons::class, 'print_sprite' ), 1 );
		add_action( 'admin_footer', array( self::class, 'admin_sprite' ) );
	}

	/**
	 * Shared front-end behaviour and configuration.
	 */
	public static function enqueue(): void {
		wp_enqueue_script( 'techvenza-frontend' );
		$post_id = is_singular( 'post' ) ? (int) get_queried_object_id() : 0;
		wp_localize_script(
			'techvenza-frontend',
			'techvenzaFront',
			array(
				'rest'      => esc_url_raw( rest_url( 'techvenza/v1/' ) ),
				'ajax'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'home'      => esc_url_raw( home_url( '/' ) ),
				'loginUrl'  => esc_url_raw( Render::page_url( 'login' ) ),
				'postId'    => $post_id,
				'trackView' => $post_id && ! is_preview(),
				'consent'   => Consent::enabled(),
				'searchUrl' => esc_url_raw( home_url( '/' ) ),
				'i18n'      => array(
					'saved'       => __( 'Saved to your reading list.', 'techvenza-core' ),
					'removed'     => __( 'Removed from your reading list.', 'techvenza-core' ),
					'signInSave'  => __( 'Sign in to save stories', 'techvenza-core' ),
					'signInText'  => __( 'Create a free account or sign in to build your reading list and follow topics.', 'techvenza-core' ),
					'signIn'      => __( 'Sign in', 'techvenza-core' ),
					'register'    => __( 'Create account', 'techvenza-core' ),
					'cancel'      => __( 'Cancel', 'techvenza-core' ),
					'close'       => __( 'Close', 'techvenza-core' ),
					'copied'      => __( 'Link copied to clipboard.', 'techvenza-core' ),
					'copyFailed'  => __( 'Copy failed — select the address bar to copy manually.', 'techvenza-core' ),
					'fixFields'   => __( 'Please fix the highlighted fields.', 'techvenza-core' ),
					'error'       => __( 'Something went wrong. Please try again.', 'techvenza-core' ),
					'popular'     => __( 'Popular topics', 'techvenza-core' ),
					'trendingNow' => __( 'Trending now', 'techvenza-core' ),
					'seeAll'      => __( 'See all results for', 'techvenza-core' ),
					'noQuick'     => __( 'No quick matches. Press Enter to search everything.', 'techvenza-core' ),
					'article'     => __( 'Article', 'techvenza-core' ),
					'author'      => __( 'Author', 'techvenza-core' ),
					'topic'       => __( 'Topic', 'techvenza-core' ),
					'all'         => __( 'All', 'techvenza-core' ),
					'logIn'       => __( 'Log in', 'techvenza-core' ),
					'logOut'      => __( 'Log out', 'techvenza-core' ),
					'dashboard'   => __( 'Reader dashboard', 'techvenza-core' ),
					'portal'      => __( 'Author portal', 'techvenza-core' ),
					'cms'         => __( 'Editorial CMS', 'techvenza-core' ),
					'accountMenu' => __( 'Account menu', 'techvenza-core' ),
					'shareTitle'  => __( 'Share this article', 'techvenza-core' ),
					'copyLink'    => __( 'Copy link', 'techvenza-core' ),
					'shareNote'   => __( 'Share links open the service in a new tab. Nothing is posted automatically.', 'techvenza-core' ),
					'pwHints'     => array(
						__( 'Use 10+ characters with a mix of letters, numbers and symbols.', 'techvenza-core' ),
						__( 'Weak — add length and variety.', 'techvenza-core' ),
						__( 'Fair — add numbers or symbols.', 'techvenza-core' ),
						__( 'Good — a little longer would be stronger.', 'techvenza-core' ),
						__( 'Strong password.', 'techvenza-core' ),
					),
					'showPw'      => __( 'Show password', 'techvenza-core' ),
					'hidePw'      => __( 'Hide password', 'techvenza-core' ),
					'textSize'    => __( 'Text size', 'techvenza-core' ),
					'following'   => __( 'Following', 'techvenza-core' ),
					'follow'      => __( 'Follow topic', 'techvenza-core' ),
					'saveLabel'   => __( 'Save', 'techvenza-core' ),
					'savedLabel'  => __( 'Saved', 'techvenza-core' ),
					'followed'    => __( 'Topic followed. Manage topics in your reader dashboard.', 'techvenza-core' ),
					'unfollowed'  => __( 'Topic unfollowed.', 'techvenza-core' ),
					'darkOn'      => __( 'Light reading theme', 'techvenza-core' ),
					'darkOff'     => __( 'Dark reading theme', 'techvenza-core' ),
				),
			)
		);
	}

	/**
	 * Sprite for admin screens that render Techvenza markup (dashboard widgets, review page).
	 */
	public static function admin_sprite(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ( str_contains( (string) $screen->id, 'techvenza' ) || 'dashboard' === $screen->id ) ) {
			Icons::print_sprite();
		}
	}
}
