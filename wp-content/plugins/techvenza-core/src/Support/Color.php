<?php
/**
 * Colour maths for category palettes and WCAG contrast checks.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Support;

/**
 * Hex colour helpers.
 */
final class Color {

	/**
	 * Hex → [r,g,b].
	 *
	 * @param string $hex Hex colour.
	 * @return array{0:int,1:int,2:int}
	 */
	public static function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$int = (int) hexdec( substr( str_pad( $hex, 6, '0' ), 0, 6 ) );
		return array( ( $int >> 16 ) & 255, ( $int >> 8 ) & 255, $int & 255 );
	}

	/**
	 * [r,g,b] → hex.
	 *
	 * @param array<int,int|float> $rgb Channels.
	 */
	public static function hex( array $rgb ): string {
		return sprintf( '#%02X%02X%02X', ...array_map( static fn( $c ) => (int) max( 0, min( 255, round( $c ) ) ), $rgb ) );
	}

	/**
	 * Mix with white.
	 *
	 * @param string $hex    Colour.
	 * @param float  $amount 0 (same) … 1 (white).
	 */
	public static function tint( string $hex, float $amount ): string {
		return self::hex( array_map( static fn( $c ) => $c + ( 255 - $c ) * $amount, self::rgb( $hex ) ) );
	}

	/**
	 * Mix with black.
	 *
	 * @param string $hex    Colour.
	 * @param float  $amount 0 (same) … 1 (black).
	 */
	public static function darken( string $hex, float $amount ): string {
		return self::hex( array_map( static fn( $c ) => $c * ( 1 - $amount ), self::rgb( $hex ) ) );
	}

	/**
	 * Relative luminance (WCAG 2).
	 *
	 * @param string $hex Colour.
	 */
	public static function luminance( string $hex ): float {
		$lin = array_map(
			static function ( $c ) {
				$c /= 255;
				return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			},
			self::rgb( $hex )
		);
		return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	}

	/**
	 * Contrast ratio between two colours.
	 *
	 * @param string $a Colour A.
	 * @param string $b Colour B.
	 */
	public static function contrast( string $a, string $b ): float {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}
}
