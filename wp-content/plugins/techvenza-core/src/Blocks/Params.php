<?php
/**
 * Request parameters for blocks that filter/paginate (Explore, Authors, Search…).
 *
 * Blocks read query-string values through this class so the same render code serves
 * full page loads (crawlable URLs) and the JSON fragment endpoint used for in-place updates.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Blocks;

/**
 * Query parameter access with an override layer.
 */
final class Params {

	/**
	 * Overrides set by the fragment endpoint.
	 *
	 * @var array<string,string>|null
	 */
	private static ?array $override = null;

	/**
	 * Replace the parameter source (REST fragment rendering).
	 *
	 * @param array<string,string>|null $params Params or null to restore $_GET.
	 */
	public static function use( ?array $params ): void {
		self::$override = $params;
	}

	/**
	 * Get a sanitised string parameter.
	 *
	 * @param string $key      Key.
	 * @param string $fallback Default.
	 */
	public static function get( string $key, string $fallback = '' ): string {
		if ( null !== self::$override ) {
			$value = self::$override[ $key ] ?? $fallback;
		} else {
			$value = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : $fallback; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- public read-only filters, sanitised below.
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $fallback;
	}

	/**
	 * Integer parameter (min 1 for pages).
	 *
	 * @param string $key      Key.
	 * @param int    $fallback Default.
	 */
	public static function int( string $key, int $fallback = 1 ): int {
		return max( 1, absint( self::get( $key, (string) $fallback ) ) );
	}

	/**
	 * Base URL used for building filter/pagination links in fragments.
	 */
	public static function base_url(): string {
		if ( null !== self::$override && isset( self::$override['_base'] ) ) {
			return esc_url_raw( self::$override['_base'] );
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	}

	/**
	 * URL with filter params (empty values removed).
	 *
	 * @param array<string,string|int> $params Params.
	 */
	public static function url( array $params ): string {
		return add_query_arg( array_filter( $params, static fn( $v ) => '' !== $v && null !== $v && 0 !== $v ), self::base_url() );
	}
}
