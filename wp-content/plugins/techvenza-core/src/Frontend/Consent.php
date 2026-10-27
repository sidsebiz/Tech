<?php
/**
 * Privacy choices: a small consent banner (essential only / allow all), the footer
 * "Cookie preferences" re-opener, and analytics (GA4 or Plausible) loaded only after consent.
 *
 * The choice lives in a first-party `tv_consent` cookie read in the browser, so pages stay
 * fully cacheable. Optional browser storage (reading preferences, dashboard layout) is only
 * persisted when the visitor allowed it.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Frontend;

use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

/**
 * Consent module.
 */
final class Consent {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 20 );
		add_action( 'wp_footer', array( self::class, 'banner' ), 20 );
	}

	/**
	 * Banner enabled.
	 */
	public static function enabled(): bool {
		return Options::enabled( 'consent_enabled' );
	}

	/**
	 * Analytics configuration for the browser ('' provider when none is configured).
	 *
	 * @return array{provider:string,id:string,src:string}
	 */
	public static function analytics(): array {
		$provider = (string) Options::get( 'analytics_provider', 'none' );
		if ( 'ga4' === $provider && preg_match( '/^G-[A-Z0-9]+$/', (string) Options::get( 'ga4_id', '' ) ) ) {
			return array(
				'provider' => 'ga4',
				'id'       => (string) Options::get( 'ga4_id' ),
				'src'      => 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( (string) Options::get( 'ga4_id' ) ),
			);
		}
		if ( 'plausible' === $provider && Options::get( 'plausible_domain' ) ) {
			return array(
				'provider' => 'plausible',
				'id'       => (string) Options::get( 'plausible_domain' ),
				'src'      => esc_url_raw( (string) Options::get( 'plausible_src', 'https://plausible.io/js/script.js' ) ),
			);
		}
		return array(
			'provider' => '',
			'id'       => '',
			'src'      => '',
		);
	}

	/**
	 * Script + config.
	 */
	public static function enqueue(): void {
		$src = TECHVENZA_CORE_URL . 'assets/js/consent.js';
		$ver = (string) filemtime( TECHVENZA_CORE_DIR . 'assets/js/consent.js' );
		wp_enqueue_script(
			'techvenza-consent',
			$src,
			array( 'techvenza-frontend' ),
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'techvenza-consent',
			'techvenzaConsent',
			array(
				'enabled'   => self::enabled(),
				'analytics' => is_user_logged_in() && current_user_can( 'edit_posts' ) ? array( 'provider' => '' ) : self::analytics(),
				'secure'    => is_ssl(),
				'i18n'      => array(
					'all'       => __( 'Preferences and anonymous measurement allowed.', 'techvenza-core' ),
					'essential' => __( 'Only essential storage will be used.', 'techvenza-core' ),
				),
			)
		);
	}

	/**
	 * Banner markup (hidden until the script decides to show it).
	 */
	public static function banner(): void {
		if ( ! self::enabled() ) {
			return;
		}
		printf(
			'<div class="cookie" id="cookieBanner" role="region" aria-labelledby="cookieBannerTitle" hidden><h2 id="cookieBannerTitle">%1$s</h2><p>%2$s</p><div class="row mt-4"><button class="btn btn-primary btn-sm" type="button" data-cookie="essential">%3$s</button><button class="btn btn-secondary btn-sm" type="button" data-cookie="all">%4$s</button><a class="link" href="%5$s" style="font-size:var(--t-meta)">%6$s</a></div></div>',
			esc_html( (string) Options::get( 'consent_title', __( 'Your privacy choices', 'techvenza-core' ) ) ),
			esc_html( (string) Options::get( 'consent_text', '' ) ),
			esc_html__( 'Essential only', 'techvenza-core' ),
			esc_html__( 'Allow all', 'techvenza-core' ),
			esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : R::page_url( 'privacy-policy' ) ),
			esc_html__( 'Privacy policy', 'techvenza-core' )
		);
	}
}
