<?php
/**
 * Reader registration with email verification.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Auth\Auth;
use Techvenza\Core\Auth\GoogleOAuth;
use Techvenza\Core\Auth\Views;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

$tv_login = Auth::page( 'login' );
$tv_after = '<p class="muted mt-4" style="font-size:var(--t-small)">' . sprintf(
	/* translators: %s: login link */
	esc_html__( 'Already have one? %s', 'techvenza-core' ),
	'<a class="link" href="' . esc_url( $tv_login ? $tv_login : wp_login_url() ) . '">' . esc_html__( 'Log in', 'techvenza-core' ) . '</a>'
) . '</p>';

if ( ! Options::enabled( 'registration_open' ) ) {
	$tv_body = '<div class="form-note mt-6">' . R::icon( 'info' ) . '<span>' . esc_html__( 'Registration is currently closed. Existing readers can still log in.', 'techvenza-core' ) . '</span></div>';
} else {
	$tv_body = sprintf(
		'<form class="form mt-6" method="post" action="%1$s" data-tv-form data-endpoint="auth/register" novalidate>%2$s%3$s%4$s%5$s%6$s<div class="field" data-field="terms" data-required-group><label class="check"><input type="checkbox" name="terms" value="1" required> <span>%7$s</span></label><span class="error" role="alert">%8$s <span>%9$s</span></span></div>%10$s<button class="btn btn-primary btn-lg btn-block" type="submit">%11$s</button></form>%12$s',
		esc_url( admin_url( 'admin-post.php' ) ),
		Views::hidden( 'techvenza_register' ),
		R::field(
			array(
				'id'           => 'rgName',
				'name'         => 'name',
				'label'        => __( 'Full name', 'techvenza-core' ),
				'required'     => true,
				'autocomplete' => 'name',
			)
		),
		R::field(
			array(
				'id'           => 'rgEmail',
				'name'         => 'email',
				'label'        => __( 'Email', 'techvenza-core' ),
				'type'         => 'email',
				'required'     => true,
				'autocomplete' => 'email',
				'error'        => __( 'Enter a valid email address.', 'techvenza-core' ),
			)
		),
		str_replace(
			'type="password"',
			'type="password" data-min="10" pattern="(?=.*\d).{10,}"',
			R::field(
				array(
					'id'           => 'rgPw',
					'name'         => 'password',
					'label'        => __( 'Password', 'techvenza-core' ),
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
			'type="password" data-match="rgPw"',
			R::field(
				array(
					'id'           => 'rgPw2',
					'name'         => 'confirm',
					'label'        => __( 'Confirm password', 'techvenza-core' ),
					'type'         => 'password',
					'required'     => true,
					'autocomplete' => 'new-password',
					'error'        => __( 'Passwords do not match.', 'techvenza-core' ),
				)
			)
		),
		sprintf(
			/* translators: 1: terms link, 2: privacy link */
			esc_html__( 'I agree to the %1$s and acknowledge the %2$s.', 'techvenza-core' ),
			'<a class="link" href="' . esc_url( R::page_url( 'terms' ) ) . '">' . esc_html__( 'terms', 'techvenza-core' ) . '</a>',
			'<a class="link" href="' . esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : R::page_url( 'privacy-policy' ) ) . '">' . esc_html__( 'privacy policy', 'techvenza-core' ) . '</a>'
		),
		R::icon( 'alert' ),
		esc_html__( 'You need to accept the terms to continue.', 'techvenza-core' ),
		Captcha::widget( 'register' ),
		esc_html__( 'Create account', 'techvenza-core' ),
		GoogleOAuth::enabled() ? sprintf( '<a class="btn btn-secondary btn-lg btn-block mt-5" href="%s">%s %s</a>', esc_url( GoogleOAuth::start_url() ), R::icon( 'google' ), esc_html__( 'Sign up with Google', 'techvenza-core' ) ) : ''
	);
	$tv_body .= '<p class="hint mt-5">' . esc_html__( 'We will email you a link to confirm your address before you can sign in.', 'techvenza-core' ) . '</p>';
}

$tv_inner = R::crumbs(
	array(
		array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ),
		array( __( 'Register', 'techvenza-core' ), '' ),
	)
) . Views::head( $attributes, $tv_after ) . Views::notice() . '<div data-form-box>' . $tv_body . '</div>';

echo Views::layout( $attributes, $tv_inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
