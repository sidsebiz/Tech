<?php
/**
 * Settings storage with wp-config constant overrides and encrypted secrets.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Settings;

/**
 * Reads and writes the single `techvenza_settings` option.
 */
final class Options {

	public const OPTION = 'techvenza_settings';

	/**
	 * Request-level cache of the raw option.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Hooks: brand colour overrides, site icon sync, cache reset.
	 */
	public static function register(): void {
		add_action( 'update_option_' . self::OPTION, array( self::class, 'flush' ) );
		add_action( 'wp_head', array( self::class, 'print_palette_overrides' ), 5 );
		add_action( 'admin_head', array( self::class, 'print_palette_overrides' ), 5 );
		add_action( 'enqueue_block_assets', array( self::class, 'editor_palette_overrides' ) );
		add_filter( 'pre_option_blogdescription', array( self::class, 'filter_tagline' ) );
	}

	/**
	 * Clear the request cache.
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Constant name for a key.
	 *
	 * @param string $key Setting key.
	 */
	public static function constant_name( string $key ): string {
		return 'TECHVENZA_' . strtoupper( $key );
	}

	/**
	 * Whether a key is locked by a wp-config.php constant.
	 *
	 * @param string $key Setting key.
	 */
	public static function is_constant( string $key ): bool {
		return defined( self::constant_name( $key ) );
	}

	/**
	 * Get a setting (constant → stored value → schema default → $fallback).
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value when not defined anywhere.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		if ( self::is_constant( $key ) ) {
			return constant( self::constant_name( $key ) );
		}
		$raw = self::raw();
		if ( array_key_exists( $key, $raw ) && '' !== $raw[ $key ] && null !== $raw[ $key ] ) {
			$value = $raw[ $key ];
			$field = Fields::all()[ $key ] ?? array();
			if ( 'secret' === ( $field['type'] ?? '' ) && is_string( $value ) ) {
				return Crypto::decrypt( $value );
			}
			return $value;
		}
		$field = Fields::all()[ $key ] ?? null;
		if ( $field && array_key_exists( 'default', $field ) ) {
			return $field['default'];
		}
		return $fallback;
	}

	/**
	 * Boolean helper.
	 *
	 * @param string $key Setting key.
	 */
	public static function enabled( string $key ): bool {
		return filter_var( self::get( $key, false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Whether a homepage section is enabled in Settings → Homepage.
	 *
	 * @param string $block Block slug without namespace.
	 */
	public static function section_enabled( string $block ): bool {
		$hidden = (array) self::get( 'home_hidden', array() );
		return ! in_array( $block, $hidden, true );
	}

	/**
	 * Save several values. Secrets are encrypted; empty secret submissions keep the stored value.
	 *
	 * @param array<string,mixed> $values Key → value.
	 */
	public static function update( array $values ): void {
		$raw    = self::raw();
		$fields = Fields::all();
		foreach ( $values as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}
			if ( 'secret' === $fields[ $key ]['type'] ) {
				if ( '' === $value || null === $value ) {
					continue;
				}
				if ( '__clear__' === $value ) {
					unset( $raw[ $key ] );
					continue;
				}
				$value = Crypto::encrypt( (string) $value );
			}
			$raw[ $key ] = $value;
		}
		update_option( self::OPTION, $raw, true );
		self::flush();
	}

	/**
	 * Stored option array.
	 *
	 * @return array<string,mixed>
	 */
	public static function raw(): array {
		if ( null === self::$cache ) {
			$value       = get_option( self::OPTION, array() );
			self::$cache = is_array( $value ) ? $value : array();
		}
		return self::$cache;
	}

	/**
	 * Whether a secret has a value (without revealing it).
	 *
	 * @param string $key Setting key.
	 */
	public static function has_secret( string $key ): bool {
		return self::is_constant( $key ) || ! empty( self::raw()[ $key ] );
	}

	/**
	 * Tagline from settings (falls back to WordPress' own option).
	 *
	 * @param mixed $pre Short-circuit value.
	 * @return mixed
	 */
	public static function filter_tagline( $pre ) {
		$raw = self::raw();
		if ( ! empty( $raw['tagline'] ) ) {
			return $raw['tagline'];
		}
		return $pre;
	}

	/**
	 * Palette overrides from Settings → Brand, applied on top of theme.json presets.
	 */
	public static function palette_css(): string {
		$map = array( 'ink', 'blue', 'violet', 'coral', 'teal', 'amber' );
		$css = '';
		foreach ( $map as $slug ) {
			$value = sanitize_hex_color( (string) self::get( 'color_' . $slug, '' ) );
			if ( $value ) {
				$css .= '--wp--preset--color--' . $slug . ':' . $value . ';';
			}
		}
		return $css ? 'body{' . $css . '}' : '';
	}

	/**
	 * Print brand overrides.
	 */
	public static function print_palette_overrides(): void {
		$css = self::palette_css();
		if ( $css ) {
			echo '<style id="techvenza-brand-overrides">' . wp_strip_all_tags( $css ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hex colours only.
		}
	}

	/**
	 * Same overrides inside the block editor iframe.
	 */
	public static function editor_palette_overrides(): void {
		$css = self::palette_css();
		if ( $css && is_admin() ) {
			wp_register_style( 'techvenza-brand-overrides', false, array(), TECHVENZA_CORE_VERSION );
			wp_enqueue_style( 'techvenza-brand-overrides' );
			wp_add_inline_style( 'techvenza-brand-overrides', str_replace( 'body{', '.editor-styles-wrapper,body{', $css ) );
		}
	}
}
