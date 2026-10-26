<?php
/**
 * Optional "Sign in with Google" (OpenID Connect authorisation-code flow).
 *
 * Off unless enabled with a client ID and secret (Settings → Login or constants). Accounts are
 * matched by verified email; new readers are created only when registration is open.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Auth;

use Techvenza\Core\Rest\Routes;
use Techvenza\Core\Settings\Options;

/**
 * Google OAuth.
 */
final class GoogleOAuth {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Enabled and configured.
	 */
	public static function enabled(): bool {
		return Options::enabled( 'google_login_enabled' ) && Options::get( 'google_client_id' ) && Options::get( 'google_client_secret' );
	}

	/**
	 * Redirect URI registered in Google Cloud.
	 */
	public static function callback_url(): string {
		return rest_url( Routes::NS . '/auth/google/callback' );
	}

	/**
	 * Start URL for the button.
	 *
	 * @param string $next Where to go afterwards.
	 */
	public static function start_url( string $next = '' ): string {
		return add_query_arg( $next ? array( 'next' => rawurlencode( $next ) ) : array(), rest_url( Routes::NS . '/auth/google' ) );
	}

	/**
	 * Routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/auth/google',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'start' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Routes::NS,
			'/auth/google/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Redirect to Google.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function start( \WP_REST_Request $request ): void {
		if ( ! self::enabled() ) {
			self::fail();
		}
		$state = wp_generate_password( 32, false );
		set_transient( 'tv_goauth_' . $state, array( 'next' => (string) $request->get_param( 'next' ) ), 10 * MINUTE_IN_SECONDS );
		$url = add_query_arg(
			array(
				'client_id'     => rawurlencode( (string) Options::get( 'google_client_id' ) ),
				'redirect_uri'  => rawurlencode( self::callback_url() ),
				'response_type' => 'code',
				'scope'         => rawurlencode( 'openid email profile' ),
				'state'         => $state,
				'prompt'        => 'select_account',
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External identity provider.
		exit;
	}

	/**
	 * Handle Google's redirect.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function callback( \WP_REST_Request $request ): void {
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$saved = $state ? get_transient( 'tv_goauth_' . $state ) : false;
		delete_transient( 'tv_goauth_' . $state );
		$code = (string) $request->get_param( 'code' );
		if ( ! self::enabled() || ! is_array( $saved ) || '' === $code ) {
			self::fail();
		}
		$res    = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 10,
				'body'    => array(
					'code'          => $code,
					'client_id'     => (string) Options::get( 'google_client_id' ),
					'client_secret' => (string) Options::get( 'google_client_secret' ),
					'redirect_uri'  => self::callback_url(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		$token  = is_wp_error( $res ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$claims = self::claims( (string) ( $token['id_token'] ?? '' ) );
		if ( ! $claims || empty( $claims['email'] ) || empty( $claims['email_verified'] ) || 'false' === $claims['email_verified'] ) {
			self::fail();
		}
		$email = sanitize_email( (string) $claims['email'] );
		$user  = get_user_by( 'email', $email );
		if ( ! $user ) {
			if ( ! Options::enabled( 'registration_open' ) ) {
				self::fail();
			}
			$login = sanitize_user( strtolower( strtok( $email, '@' ) ), true );
			$base  = $login ? $login : 'reader';
			$n     = 1;
			while ( username_exists( $login ) || '' === $login ) {
				$login = $base . ( ++$n );
			}
			$id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 32 ),
					'display_name' => sanitize_text_field( (string) ( $claims['name'] ?? $login ) ),
					'first_name'   => sanitize_text_field( (string) ( $claims['given_name'] ?? '' ) ),
					'last_name'    => sanitize_text_field( (string) ( $claims['family_name'] ?? '' ) ),
					'role'         => 'subscriber',
				)
			);
			if ( is_wp_error( $id ) ) {
				self::fail();
			}
			$user = get_userdata( (int) $id );
		}
		if ( ! $user instanceof \WP_User ) {
			self::fail();
		}
		update_user_meta( $user->ID, 'tv_email_verified', '1' );
		update_user_meta( $user->ID, 'tv_google_sub', sanitize_text_field( (string) ( $claims['sub'] ?? '' ) ) );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		$next = wp_validate_redirect( esc_url_raw( (string) ( $saved['next'] ?? '' ) ), '' );
		wp_safe_redirect( $next ? $next : Auth::home_for( $user ) );
		exit;
	}

	/**
	 * Validate the ID token with Google's tokeninfo endpoint and check the audience.
	 *
	 * @param string $id_token ID token.
	 * @return array<string,mixed>|null
	 */
	private static function claims( string $id_token ): ?array {
		if ( '' === $id_token ) {
			return null;
		}
		$res    = wp_remote_get( add_query_arg( 'id_token', rawurlencode( $id_token ), 'https://oauth2.googleapis.com/tokeninfo' ), array( 'timeout' => 10 ) );
		$claims = is_wp_error( $res ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ( $claims['aud'] ?? '' ) !== (string) Options::get( 'google_client_id' ) ) {
			return null;
		}
		if ( ! in_array( $claims['iss'] ?? '', array( 'accounts.google.com', 'https://accounts.google.com' ), true ) ) {
			return null;
		}
		return $claims;
	}

	/**
	 * Back to the login page with an error.
	 */
	private static function fail(): never {
		$login = Auth::page( 'login' );
		wp_safe_redirect( add_query_arg( 'tv_err', 'google', $login ? $login : wp_login_url() ) );
		exit;
	}
}
