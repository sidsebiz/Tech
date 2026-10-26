<?php
/**
 * Reset password from the emailed key (shows an expired state for invalid links).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Auth\Auth;
use Techvenza\Core\Auth\Views;
use Techvenza\Core\Support\Render as R;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The reset key is the credential.
$tv_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$tv_login = isset( $_GET['login'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['login'] ) ) ) : '';
// phpcs:enable
$tv_valid = $tv_key && $tv_login && ! is_wp_error( check_password_reset_key( $tv_key, $tv_login ) );
$tv_page  = Auth::page( 'login' );
$tv_page  = $tv_page ? $tv_page : wp_login_url();
$tv_ok    = isset( $_GET['tv_msg'] ) && 'password_updated' === sanitize_key( wp_unslash( $_GET['tv_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( $tv_ok ) {
	$tv_body = Auth::success( 'check', __( 'Password updated', 'techvenza-core' ), esc_html__( 'Other sessions were signed out. Log in with your new password.', 'techvenza-core' ), sprintf( '<a class="btn btn-primary" href="%s">%s</a>', esc_url( $tv_page ), esc_html__( 'Log in', 'techvenza-core' ) ) );
} elseif ( ! $tv_valid ) {
	$tv_forgot = Auth::page( 'forgot-password' );
	$tv_body   = sprintf(
		'<div class="success-state" style="padding-inline:0"><span class="icon-circle warn">%1$s</span><h1 class="h3">%2$s</h1><p>%3$s</p><a class="btn btn-primary" href="%4$s">%5$s</a><a class="link" style="font-size:var(--t-meta)" href="%6$s">%7$s</a></div>',
		R::icon( 'clock' ),
		esc_html__( 'This reset link has expired', 'techvenza-core' ),
		esc_html__( 'For your security, reset links work once and expire after a short time. Request a new link to continue.', 'techvenza-core' ),
		esc_url( $tv_forgot ? $tv_forgot : wp_lostpassword_url() ),
		esc_html__( 'Request a new link', 'techvenza-core' ),
		esc_url( $tv_page ),
		esc_html__( 'Back to log in', 'techvenza-core' )
	);
} else {
	$tv_body = R::crumbs(
		array(
			array( __( 'Log in', 'techvenza-core' ), $tv_page ),
			array( __( 'Reset password', 'techvenza-core' ), '' ),
		)
	) . '<div data-form-box>' . Views::head( $attributes ) . Views::notice() . sprintf(
		'<form class="form mt-6" method="post" action="%1$s" data-tv-form data-endpoint="auth/reset" novalidate>%2$s<input type="hidden" name="key" value="%3$s"><input type="hidden" name="login" value="%4$s"><input type="text" name="username" value="%4$s" autocomplete="username" hidden>%5$s%6$s<button class="btn btn-primary btn-lg btn-block" type="submit">%7$s</button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		Views::hidden( 'techvenza_reset' ),
		esc_attr( $tv_key ),
		esc_attr( $tv_login ),
		str_replace(
			'type="password"',
			'type="password" data-min="10"',
			R::field(
				array(
					'id'           => 'rpPw',
					'name'         => 'password',
					'label'        => __( 'New password', 'techvenza-core' ),
					'type'         => 'password',
					'required'     => true,
					'strength'     => true,
					'autocomplete' => 'new-password',
					'error'        => __( 'Use at least 10 characters including a number.', 'techvenza-core' ),
				)
			)
		),
		str_replace(
			'type="password"',
			'type="password" data-match="rpPw"',
			R::field(
				array(
					'id'           => 'rpPw2',
					'name'         => 'confirm',
					'label'        => __( 'Confirm new password', 'techvenza-core' ),
					'type'         => 'password',
					'required'     => true,
					'autocomplete' => 'new-password',
					'error'        => __( 'Passwords do not match.', 'techvenza-core' ),
				)
			)
		),
		esc_html__( 'Update password', 'techvenza-core' )
	) . '</div>';
}

echo Views::layout( $attributes, $tv_body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
