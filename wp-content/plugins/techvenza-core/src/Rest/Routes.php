<?php
/**
 * REST API (namespace techvenza/v1) and the cache-safe session endpoint.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

/**
 * Registers every controller.
 */
final class Routes {

	public const NS = 'techvenza/v1';

	/**
	 * Controllers (each has a static routes() method).
	 *
	 * @var array<int,string>
	 */
	private const CONTROLLERS = array(
		'Techvenza\\Core\\Rest\\SessionController',
		'Techvenza\\Core\\Rest\\SearchController',
		'Techvenza\\Core\\Rest\\MeController',
		'Techvenza\\Core\\Rest\\ViewsController',
		'Techvenza\\Core\\Rest\\FragmentController',
		'Techvenza\\Core\\Rest\\WorkflowController',
		'Techvenza\\Core\\Rest\\NotificationsController',
		'Techvenza\\Core\\Rest\\NewsletterController',
		'Techvenza\\Core\\Rest\\FormsController',
		'Techvenza\\Core\\Rest\\PortalController',
		'Techvenza\\Core\\Rest\\AdminController',
	);

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		foreach ( self::CONTROLLERS as $controller ) {
			if ( class_exists( $controller ) && method_exists( $controller, 'hooks' ) ) {
				$controller::hooks();
			}
		}
	}

	/**
	 * Register all routes.
	 */
	public static function routes(): void {
		foreach ( self::CONTROLLERS as $controller ) {
			if ( class_exists( $controller ) && method_exists( $controller, 'routes' ) ) {
				$controller::routes();
			}
		}
	}

	/**
	 * Permission: any signed-in user.
	 */
	public static function logged_in(): bool {
		return is_user_logged_in();
	}

	/**
	 * Simple fixed-window rate limiter keyed by IP hash.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $max    Max hits per window.
	 * @param int    $window Window in seconds.
	 */
	public static function rate_limited( string $bucket, int $max, int $window ): bool {
		$key  = 'tv_rl_' . $bucket . '_' . substr( self::ip_hash(), 0, 20 );
		$hits = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return true;
		}
		set_transient( $key, $hits + 1, $window );
		return false;
	}

	/**
	 * Salted hash of the client IP (never store raw IPs).
	 */
	public static function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	}
}
