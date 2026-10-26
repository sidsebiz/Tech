<?php
/**
 * Captcha (Cloudflare Turnstile or Google reCAPTCHA v3) for public forms.
 *
 * Disabled unless a provider and both keys are configured (Settings → Forms or constants).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Support;

use Techvenza\Core\Settings\Options;

/**
 * Widget markup and server-side verification.
 */
final class Captcha {

	/**
	 * Active provider: turnstile|recaptcha|''.
	 */
	public static function provider(): string {
		$p = (string) Options::get( 'captcha_provider', '' );
		if ( 'turnstile' === $p && Options::get( 'turnstile_site_key' ) && Options::get( 'turnstile_secret' ) ) {
			return 'turnstile';
		}
		if ( 'recaptcha' === $p && Options::get( 'recaptcha_site_key' ) && Options::get( 'recaptcha_secret' ) ) {
			return 'recaptcha';
		}
		return '';
	}

	/**
	 * Widget markup for a form (enqueues the provider script).
	 *
	 * @param string $action Action name (reCAPTCHA v3 / Turnstile analytics).
	 */
	public static function widget( string $action ): string {
		$provider = self::provider();
		if ( 'turnstile' === $provider ) {
			wp_enqueue_script( 'techvenza-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return sprintf( '<div class="cf-turnstile" data-sitekey="%s" data-action="%s" data-size="flexible"></div>', esc_attr( (string) Options::get( 'turnstile_site_key' ) ), esc_attr( $action ) );
		}
		if ( 'recaptcha' === $provider ) {
			$key = (string) Options::get( 'recaptcha_site_key' );
			wp_enqueue_script( 'techvenza-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $key ), array(), null, array( 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return sprintf( '<input type="hidden" name="g-recaptcha-response" value="" data-recaptcha="%s" data-action="%s">', esc_attr( $key ), esc_attr( $action ) );
		}
		return '';
	}

	/**
	 * Verify the token from a submission.
	 *
	 * @param array<string,mixed> $data Submitted data.
	 * @return true|\WP_Error
	 */
	public static function verify( array $data ) {
		$provider = self::provider();
		if ( '' === $provider ) {
			return true;
		}
		$token = (string) ( 'turnstile' === $provider ? ( $data['cf-turnstile-response'] ?? '' ) : ( $data['g-recaptcha-response'] ?? '' ) );
		if ( '' === $token ) {
			return new \WP_Error( 'tv_captcha', __( 'Please complete the security check.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		$ok = self::check( $provider, (string) Options::get( 'turnstile' === $provider ? 'turnstile_secret' : 'recaptcha_secret' ), $token );
		return $ok ? true : new \WP_Error( 'tv_captcha', __( 'The security check failed. Please try again.', 'techvenza-core' ), array( 'status' => 400 ) );
	}

	/**
	 * Call the provider's verification endpoint.
	 *
	 * @param string $provider turnstile|recaptcha.
	 * @param string $secret   Secret key.
	 * @param string $token    Client token ('' = connection test with a dummy token).
	 */
	public static function check( string $provider, string $secret, string $token ): bool {
		$url  = 'turnstile' === $provider ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify' : 'https://www.google.com/recaptcha/api/siteverify';
		$res  = wp_remote_post(
			$url,
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				),
			)
		);
		$body = is_wp_error( $res ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['success'] ) ) {
			return false;
		}
		if ( 'recaptcha' === $provider && isset( $body['score'] ) ) {
			return (float) $body['score'] >= (float) apply_filters( 'techvenza/captcha/min_score', 0.5 );
		}
		return true;
	}

	/**
	 * Connection test for the settings screen: an invalid token must be rejected with
	 * "invalid-input-response" (a wrong secret yields "invalid-input-secret").
	 *
	 * @param string $provider turnstile|recaptcha.
	 * @param string $secret   Secret.
	 * @return true|\WP_Error
	 */
	public static function test( string $provider, string $secret ) {
		$url = 'turnstile' === $provider ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify' : 'https://www.google.com/recaptcha/api/siteverify';
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => 'techvenza-connection-test',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body  = (array) json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$codes = (array) ( $body['error-codes'] ?? array() );
		if ( in_array( 'invalid-input-secret', $codes, true ) || in_array( 'missing-input-secret', $codes, true ) ) {
			return new \WP_Error( 'tv_secret', __( 'The secret key was rejected.', 'techvenza-core' ) );
		}
		return true;
	}

	/**
	 * Honeypot check: the hidden "website" field must stay empty.
	 *
	 * @param array<string,mixed> $data Submitted data.
	 */
	public static function honeypot_ok( array $data ): bool {
		return '' === trim( (string) ( $data['website'] ?? '' ) );
	}

	/**
	 * Honeypot field markup.
	 */
	public static function honeypot(): string {
		return '<label class="tv-hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>';
	}
}
