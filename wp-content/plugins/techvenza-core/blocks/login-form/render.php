<?php
/**
 * Branded login form.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Auth\Auth;
use Techvenza\Core\Auth\GoogleOAuth;
use Techvenza\Core\Auth\Views;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

$tv_next = isset( $_GET['next'] ) ? esc_url_raw( wp_unslash( $_GET['next'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tv_reg  = Auth::page( 'register' );
$tv_err  = isset( $_GET['tv_err'] ) ? sanitize_key( wp_unslash( $_GET['tv_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tv_google = '';
if ( GoogleOAuth::enabled() ) {
	$tv_google = sprintf(
		'<div class="divider-text mt-6"><span>%s</span></div><a class="btn btn-secondary btn-lg btn-block mt-5" href="%s">%s %s</a>',
		esc_html__( 'or', 'techvenza-core' ),
		esc_url( GoogleOAuth::start_url( $tv_next ) ),
		R::icon( 'google' ),
		esc_html__( 'Continue with Google', 'techvenza-core' )
	);
}
$tv_resend = '';
if ( 'tv_unverified' === $tv_err ) {
	$tv_resend = sprintf(
		'<form class="form mt-5" method="post" action="%1$s" data-tv-form data-endpoint="auth/resend" novalidate>%2$s%3$s<button class="btn btn-secondary btn-block" type="submit">%4$s</button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		Views::hidden( 'techvenza_resend' ),
		R::field(
			array(
				'id'           => 'rsEmail',
				'name'         => 'email',
				'label'        => __( 'Email for a new confirmation link', 'techvenza-core' ),
				'type'         => 'email',
				'required'     => true,
				'autocomplete' => 'email',
			)
		),
		esc_html__( 'Send a new confirmation link', 'techvenza-core' )
	);
}

$tv_form = sprintf(
	'<form class="form mt-6" method="post" action="%1$s" data-tv-form data-endpoint="auth/login" novalidate>%2$s<input type="hidden" name="next" value="%3$s">%4$s%5$s<div class="row between"><label class="check"><input type="checkbox" name="remember" value="1"> %6$s</label><a class="link" style="font-size:var(--t-meta)" href="%7$s">%8$s</a></div>%9$s<button class="btn btn-primary btn-lg btn-block" type="submit">%10$s</button></form>',
	esc_url( admin_url( 'admin-post.php' ) ),
	Views::hidden( 'techvenza_login' ),
	esc_attr( $tv_next ),
	R::field(
		array(
			'id'           => 'liEmail',
			'name'         => 'email',
			'label'        => __( 'Email', 'techvenza-core' ),
			'type'         => 'email',
			'required'     => true,
			'autocomplete' => 'username',
			'error'        => __( 'Enter a valid email address.', 'techvenza-core' ),
		)
	),
	R::field(
		array(
			'id'           => 'liPw',
			'name'         => 'password',
			'label'        => __( 'Password', 'techvenza-core' ),
			'type'         => 'password',
			'required'     => true,
			'autocomplete' => 'current-password',
			'error'        => __( 'Enter your password.', 'techvenza-core' ),
		)
	),
	esc_html__( 'Remember me on this device', 'techvenza-core' ),
	esc_url( Auth::page( 'forgot-password' ) ),
	esc_html__( 'Forgot password?', 'techvenza-core' ),
	Captcha::widget( 'login' ),
	esc_html__( 'Log in', 'techvenza-core' )
);

$tv_after = $tv_reg && \Techvenza\Core\Settings\Options::enabled( 'registration_open' )
	/* translators: %s: link to registration */
	? '<p class="muted mt-4" style="font-size:var(--t-small)">' . sprintf( esc_html__( 'New to Techvenza? %s', 'techvenza-core' ), '<a class="link" href="' . esc_url( $tv_reg ) . '">' . esc_html__( 'Create an account', 'techvenza-core' ) . '</a>' ) . '</p>'
	: '';
$tv_inner = R::crumbs(
	array(
		array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ),
		array( __( 'Log in', 'techvenza-core' ), '' ),
	)
) . Views::head( $attributes, $tv_after ) . Views::notice() . $tv_resend . '<div data-form-box>' . $tv_form . $tv_google . '</div>';

echo Views::layout( $attributes, $tv_inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
