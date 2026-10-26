<?php
/**
 * Shared markup for the branded account pages (used by the login/register/forgot/reset blocks).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Auth;

use Techvenza\Core\Frontend\Brand;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

/**
 * Account page layout helpers.
 */
final class Views {

	/**
	 * Two-column auth layout.
	 *
	 * @param array<string,mixed> $attributes Block attributes (sideTitle, sideText).
	 * @param string              $inner      Pre-escaped card content.
	 */
	public static function layout( array $attributes, string $inner ): string {
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block auth-wrap' ) );
		return sprintf(
			'<div %1$s><aside class="auth-side">%2$s<div><h2>%3$s</h2><p class="mt-4" style="max-width:40ch">%4$s</p></div><p class="caption" style="color:#94A3B8">%5$s</p></aside><div class="auth-main"><div class="auth-card">%6$s</div></div></div>',
			$wrapper,
			Brand::logo( 'dark' ),
			esc_html( (string) ( $attributes['sideTitle'] ?? '' ) ),
			esc_html( (string) ( $attributes['sideText'] ?? '' ) ),
			esc_html__( 'Your account is protected with rate limiting and never shares your data with advertisers.', 'techvenza-core' ),
			$inner
		);
	}

	/**
	 * Notice from a no-JS round trip (?tv_msg / ?tv_err).
	 */
	public static function notice(): string {
		$code = '';
		foreach ( array( 'tv_err', 'tv_msg' ) as $param ) {
			if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$code = sanitize_key( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			}
		}
		$notice = $code ? Auth::notice( $code ) : null;
		$html   = '';
		if ( $notice ) {
			$html = sprintf( '<div class="form-note %s mt-5" role="%s">%s<span>%s</span></div>', 'warn' === $notice[0] ? 'warn' : '', 'warn' === $notice[0] ? 'alert' : 'status', R::icon( 'warn' === $notice[0] ? 'alert' : 'checkCircle' ), esc_html( $notice[1] ) );
		}
		return $html . '<div class="form-note warn mt-5" data-form-error hidden role="alert">' . R::icon( 'alert' ) . '<span></span></div>';
	}

	/**
	 * Hidden fields common to every account form.
	 *
	 * @param string $action techvenza_* action.
	 */
	public static function hidden( string $action ): string {
		return sprintf( '<input type="hidden" name="action" value="%s">', esc_attr( $action ) ) . Captcha::honeypot();
	}

	/**
	 * Heading + intro.
	 *
	 * @param array<string,mixed> $attributes Attributes.
	 * @param string              $after      Pre-escaped HTML after the intro.
	 */
	public static function head( array $attributes, string $after = '' ): string {
		return sprintf(
			'<h1 class="mt-5">%s</h1>%s%s',
			esc_html( (string) ( $attributes['heading'] ?? '' ) ),
			! empty( $attributes['intro'] ) ? '<p class="muted mt-4" style="font-size:var(--t-small)">' . esc_html( (string) $attributes['intro'] ) . '</p>' : '',
			$after
		);
	}
}
