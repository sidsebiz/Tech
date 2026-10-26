<?php
/**
 * Account security shared by the reader dashboard and author portal: password change,
 * email change with confirmation, notification email preferences and deletion requests.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Portal;

use Techvenza\Core\Auth\Auth;
use Techvenza\Core\Email\Mailer;
use Techvenza\Core\Rest\Routes;
use Techvenza\Core\Support\Render as R;

/**
 * Account operations.
 */
final class Account {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'template_redirect', array( self::class, 'confirm_email_link' ), 4 );
	}

	/**
	 * Routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/me/password',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'change_password' ),
				'permission_callback' => array( Routes::class, 'logged_in' ),
			)
		);
		register_rest_route(
			Routes::NS,
			'/me/deletion-request',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'deletion_request' ),
				'permission_callback' => array( Routes::class, 'logged_in' ),
			)
		);
	}

	/**
	 * Change password (requires the current one).
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function change_password( \WP_REST_Request $request ) {
		$user = wp_get_current_user();
		if ( Routes::rate_limited( 'pw_' . $user->ID, 5, 15 * MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'tv_rate', __( 'Too many attempts. Please wait a few minutes.', 'techvenza-core' ), array( 'status' => 429 ) );
		}
		$current = (string) $request->get_param( 'current' );
		$pass    = (string) $request->get_param( 'password' );
		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			return new \WP_Error(
				'tv_invalid',
				__( 'Your current password is not right.', 'techvenza-core' ),
				array(
					'status' => 400,
					'errors' => array( 'current' => __( 'Your current password is not right.', 'techvenza-core' ) ),
				)
			);
		}
		if ( ! Auth::strong( $pass ) || (string) $request->get_param( 'confirm' ) !== $pass ) {
			return new \WP_Error(
				'tv_invalid',
				__( 'Please fix the highlighted fields.', 'techvenza-core' ),
				array(
					'status' => 400,
					'errors' => Auth::strong( $pass ) ? array( 'confirm' => __( 'Passwords do not match.', 'techvenza-core' ) ) : array( 'password' => __( 'Use at least 10 characters including a number.', 'techvenza-core' ) ),
				)
			);
		}
		wp_set_password( $pass, $user->ID );
		// Sign out every other session (after_reset also emails a confirmation), then keep this browser signed in.
		Auth::after_reset( $user );
		wp_set_auth_cookie( $user->ID, false, is_ssl() );
		return rest_ensure_response(
			array(
				'message' => __( 'Password updated. Other sessions were signed out.', 'techvenza-core' ),
				'html'    => Auth::success( 'check', __( 'Password updated', 'techvenza-core' ), esc_html__( 'Other devices were signed out. We emailed you a confirmation.', 'techvenza-core' ) ),
			)
		);
	}

	/**
	 * Start a GDPR erasure request (core personal-data request, confirmed by email).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function deletion_request() {
		$user = wp_get_current_user();
		$id   = wp_create_user_request( $user->user_email, 'remove_personal_data' );
		if ( is_wp_error( $id ) && 'duplicate_request' !== $id->get_error_code() ) {
			return $id;
		}
		if ( ! is_wp_error( $id ) ) {
			wp_send_user_request( (string) $id );
		}
		return rest_ensure_response( array( 'message' => __( 'We sent a confirmation link to your email. Your request is reviewed by an administrator after you confirm it.', 'techvenza-core' ) ) );
	}

	/**
	 * Begin an email change (called from the profile endpoint).
	 *
	 * @param int    $user_id User.
	 * @param string $email   New email.
	 * @return true|\WP_Error
	 */
	public static function request_email_change( int $user_id, string $email ) {
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'tv_invalid', __( 'Enter a valid email address.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		$owner = email_exists( $email );
		if ( $owner && (int) $owner !== $user_id ) {
			return new \WP_Error( 'tv_email_taken', __( 'That email address is already used by another account.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		$token = wp_generate_password( 32, false );
		update_user_meta( $user_id, 'tv_pending_email', $email );
		update_user_meta( $user_id, 'tv_pending_email_hash', wp_hash_password( $token ) );
		update_user_meta( $user_id, 'tv_pending_email_expires', time() + DAY_IN_SECONDS );
		$url  = add_query_arg(
			array(
				'tv_confirm_email' => $token,
				'u'                => $user_id,
			),
			home_url( '/' )
		);
		$user = get_userdata( $user_id );
		Mailer::send(
			$email,
			/* translators: %s: site name */
			sprintf( __( 'Confirm your new %s email address', 'techvenza-core' ), get_bloginfo( 'name' ) ),
			/* translators: 1: name, 2: link */
			sprintf( __( "Hi %1\$s,\n\nConfirm that you want to use this address for your account:\n\n%2\$s\n\nThe link expires in 24 hours. If you did not ask for this, ignore this email.", 'techvenza-core' ), $user ? $user->display_name : '', $url )
		);
		return true;
	}

	/**
	 * Handle the confirmation link.
	 */
	public static function confirm_email_link(): void {
		if ( ! isset( $_GET['tv_confirm_email'], $_GET['u'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$user_id = absint( $_GET['u'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = sanitize_text_field( wp_unslash( $_GET['tv_confirm_email'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$hash    = (string) get_user_meta( $user_id, 'tv_pending_email_hash', true );
		$email   = (string) get_user_meta( $user_id, 'tv_pending_email', true );
		$ok      = $hash && $email && (int) get_user_meta( $user_id, 'tv_pending_email_expires', true ) > time() && wp_check_password( $token, $hash, $user_id ) && ! ( email_exists( $email ) && (int) email_exists( $email ) !== $user_id );
		if ( $ok ) {
			wp_update_user(
				array(
					'ID'         => $user_id,
					'user_email' => $email,
				)
			);
			update_user_meta( $user_id, 'tv_email_verified', '1' );
		}
		delete_user_meta( $user_id, 'tv_pending_email' );
		delete_user_meta( $user_id, 'tv_pending_email_hash' );
		delete_user_meta( $user_id, 'tv_pending_email_expires' );
		$dest = get_userdata( $user_id ) ? Auth::home_for( get_userdata( $user_id ) ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'tv_msg', $ok ? 'email_changed' : 'email_failed', $dest ) );
		exit;
	}

	/**
	 * Change-password panel.
	 */
	public static function password_panel(): string {
		$swap = static fn( string $html, string $extra ) => str_replace( 'type="password"', 'type="password" ' . $extra, $html );
		return sprintf(
			'<section class="panel mt-5" id="password"><h2 class="panel-title">%1$s</h2><div data-form-box><form class="form" data-tv-form data-endpoint="me/password" novalidate style="max-width:520px"><input type="text" name="username" value="%2$s" autocomplete="username" hidden>%3$s%4$s%5$s<div class="form-note warn" data-form-error hidden role="alert">%6$s<span></span></div><button class="btn btn-primary" type="submit" style="justify-self:start">%7$s</button></form></div></section>',
			esc_html__( 'Change password', 'techvenza-core' ),
			esc_attr( wp_get_current_user()->user_login ),
			R::field(
				array(
					'id'           => 'pwCur',
					'name'         => 'current',
					'label'        => __( 'Current password', 'techvenza-core' ),
					'type'         => 'password',
					'required'     => true,
					'autocomplete' => 'current-password',
					'error'        => __( 'Enter your current password.', 'techvenza-core' ),
				)
			),
			$swap(
				R::field(
					array(
						'id'           => 'pwNew',
						'name'         => 'password',
						'label'        => __( 'New password', 'techvenza-core' ),
						'type'         => 'password',
						'required'     => true,
						'strength'     => true,
						'autocomplete' => 'new-password',
						'error'        => __( 'Use at least 10 characters including a number.', 'techvenza-core' ),
					)
				),
				'data-min="10"'
			),
			$swap(
				R::field(
					array(
						'id'           => 'pwNew2',
						'name'         => 'confirm',
						'label'        => __( 'Confirm new password', 'techvenza-core' ),
						'type'         => 'password',
						'required'     => true,
						'autocomplete' => 'new-password',
						'error'        => __( 'Passwords do not match.', 'techvenza-core' ),
					)
				),
				'data-match="pwNew"'
			),
			R::icon( 'alert' ),
			esc_html__( 'Update password', 'techvenza-core' )
		);
	}

	/**
	 * Danger zone panel.
	 */
	public static function danger_panel(): string {
		return sprintf(
			'<section class="panel mt-5" style="border-color:#FECDD3"><h2 class="panel-title" style="color:#BE123C">%s</h2><p style="font-size:var(--t-small)">%s</p><button class="btn btn-danger btn-sm mt-4" type="button" data-action="deleteAccount">%s</button></section>',
			esc_html__( 'Danger zone', 'techvenza-core' ),
			esc_html__( 'Request deletion of your account. We confirm the request by email; published articles remain subject to the content licence in the terms.', 'techvenza-core' ),
			esc_html__( 'Request account deletion', 'techvenza-core' )
		);
	}
}
