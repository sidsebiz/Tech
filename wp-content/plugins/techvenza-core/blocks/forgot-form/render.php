<?php
/**
 * Forgot password (sends the branded core reset email).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Auth\Auth;
use Techvenza\Core\Auth\Views;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

$tv_login = Auth::page( 'login' );
$tv_login = $tv_login ? $tv_login : wp_login_url();
$tv_form  = sprintf(
	'<form class="form mt-6" method="post" action="%1$s" data-tv-form data-endpoint="auth/forgot" novalidate>%2$s%3$s%4$s<button class="btn btn-primary btn-lg btn-block" type="submit">%5$s</button><a class="link" style="font-size:var(--t-meta);justify-self:center" href="%6$s">%7$s</a></form>',
	esc_url( admin_url( 'admin-post.php' ) ),
	Views::hidden( 'techvenza_forgot' ),
	R::field(
		array(
			'id'           => 'fpEmail',
			'name'         => 'email',
			'label'        => __( 'Email', 'techvenza-core' ),
			'type'         => 'email',
			'required'     => true,
			'autocomplete' => 'email',
			'error'        => __( 'Enter a valid email address.', 'techvenza-core' ),
		)
	),
	Captcha::widget( 'forgot' ),
	esc_html__( 'Send reset link', 'techvenza-core' ),
	esc_url( $tv_login ),
	esc_html__( 'Back to log in', 'techvenza-core' )
);
$tv_inner = R::crumbs(
	array(
		array( __( 'Log in', 'techvenza-core' ), $tv_login ),
		array( __( 'Forgot password', 'techvenza-core' ), '' ),
	)
) . '<div data-form-box>' . Views::head( $attributes ) . Views::notice() . $tv_form . '</div>';

echo Views::layout( $attributes, $tv_inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
