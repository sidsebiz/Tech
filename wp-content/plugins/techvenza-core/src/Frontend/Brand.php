<?php
/**
 * Brand output helpers: logo, wordmark, social links.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Frontend;

use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Icons;

/**
 * Logo and social links driven by Settings → Brand / Header & Footer.
 */
final class Brand {

	/**
	 * The built-in mark (gradient tile with a T).
	 */
	public static function mark(): string {
		return '<span class="logo-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M8.5 9.5h15v3.6h-5.6V23h-3.8v-9.9H8.5z" fill="#fff"/><circle cx="23.2" cy="22" r="2.4" fill="#fff" opacity=".9"/></svg></span>';
	}

	/**
	 * Logo link (uploaded image or wordmark).
	 *
	 * @param string $variant auto|light|dark.
	 * @param string $attrs   Pre-escaped wrapper attributes (must include class).
	 */
	public static function logo( string $variant = 'auto', string $attrs = 'class="logo"' ): string {
		$key   = 'dark' === $variant ? 'logo_dark' : 'logo_light';
		$media = (int) Options::get( $key, 0 );
		if ( ! $media && 'dark' === $variant ) {
			$media = 0; // Wordmark adapts to dark backgrounds via .on-dark.
		}
		if ( ! $media && 'auto' === $variant ) {
			$media = (int) get_theme_mod( 'custom_logo' );
		}
		$name  = get_bloginfo( 'name' );
		$inner = '';
		if ( $media ) {
			$inner = (string) wp_get_attachment_image(
				$media,
				'medium',
				false,
				array(
					'alt'      => $name,
					'class'    => 'logo-img',
					'loading'  => 'eager',
					'decoding' => 'async',
					'style'    => 'height:32px;width:auto',
				)
			);
		}
		if ( ! $inner ) {
			$word  = (string) Options::get( 'wordmark_text', $name );
			$word  = '' !== $word ? $word : $name;
			$split = max( 0, mb_strlen( $word ) - 5 );
			$inner = self::mark() . sprintf(
				'<span class="logo-text">%s<span class="wm-v">%s</span></span>',
				esc_html( mb_substr( $word, 0, $split ) ),
				esc_html( mb_substr( $word, $split ) )
			);
		}
		if ( 'dark' === $variant && ! str_contains( $attrs, 'on-dark' ) ) {
			$attrs = str_replace( 'class="', 'class="on-dark ', $attrs );
		}
		return sprintf(
			'<a %s href="%s" aria-label="%s" rel="home">%s</a>',
			$attrs,
			esc_url( home_url( '/' ) ),
			/* translators: %s: site name */
			esc_attr( sprintf( __( '%s home', 'techvenza-core' ), $name ) ),
			$inner
		);
	}

	/**
	 * Social profile links from settings.
	 */
	public static function social(): string {
		$map   = array(
			'social_linkedin'  => array( 'linkedin', 'LinkedIn' ),
			'social_x'         => array( 'xlogo', 'X' ),
			'social_youtube'   => array( 'play', 'YouTube' ),
			'social_facebook'  => array( 'globe', 'Facebook' ),
			'social_instagram' => array( 'image', 'Instagram' ),
		);
		$links = '';
		foreach ( $map as $key => [ $icon, $label ] ) {
			$url = (string) Options::get( $key, '' );
			if ( $url ) {
				$links .= sprintf(
					'<a href="%s" target="_blank" rel="noopener me" aria-label="%s">%s</a>',
					esc_url( $url ),
					/* translators: %s: social network */
					esc_attr( sprintf( __( '%s (opens in a new tab)', 'techvenza-core' ), $label ) ),
					Icons::svg( $icon )
				);
			}
		}
		if ( Options::enabled( 'social_rss' ) ) {
			$links .= sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( get_feed_link() ), esc_attr__( 'RSS feed', 'techvenza-core' ), Icons::svg( 'rss' ) );
		}
		return $links ? '<div class="social" aria-label="' . esc_attr__( 'Social links', 'techvenza-core' ) . '">' . $links . '</div>' : '';
	}
}
