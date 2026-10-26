<?php
/**
 * Inline SVG icon set (stroke icons on a 24px grid), printed once per page as a sprite.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Support;

/**
 * Icon registry and renderer.
 */
final class Icons {

	/**
	 * Whether the sprite has been printed in this request.
	 *
	 * @var bool
	 */
	private static bool $printed = false;

	/**
	 * Icon paths keyed by name.
	 *
	 * @return array<string,string>
	 */
	public static function paths(): array {
		return array(
			'search'        => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'x'             => '<path d="M18 6 6 18M6 6l12 12"/>',
			'menu'          => '<path d="M4 7h16M4 12h16M4 17h16"/>',
			'chevDown'      => '<path d="m6 9 6 6 6-6"/>',
			'chevRight'     => '<path d="m9 6 6 6-6 6"/>',
			'chevLeft'      => '<path d="m15 6-6 6 6 6"/>',
			'arrowRight'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'arrowLeft'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
			'arrowUp'       => '<path d="M12 19V5M6 11l6-6 6 6"/>',
			'bookmark'      => '<path d="M6 4h12v17l-6-4-6 4z"/>',
			'share'         => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
			'link'          => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
			'copy'          => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
			'linkedin'      => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7M8 7v.01M12 17v-4a2 2 0 0 1 4 0v4M12 10v7"/>',
			'xlogo'         => '<path d="M4 4l16 16M20 4 4 20"/>',
			'rss'           => '<path d="M5 11a8 8 0 0 1 8 8M5 5a14 14 0 0 1 14 14"/><circle cx="6" cy="18" r="1.5"/>',
			'globe'         => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
			'star'          => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
			'trend'         => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
			'layers'        => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
			'book'          => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h7"/>',
			'chart'         => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
			'compass'       => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
			'sliders'       => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
			'check'         => '<path d="m5 12 5 5L20 7"/>',
			'checkCircle'   => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
			'xCircle'       => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
			'alert'         => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17v.01"/>',
			'info'          => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
			'user'          => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
			'users'         => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0M16 4.5a3.5 3.5 0 0 1 0 7M18 13.5a7 7 0 0 1 4 6.5"/>',
			'edit'          => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
			'eye'           => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
			'eyeOff'        => '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.2M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
			'trash'         => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
			'archive'       => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11h14V8M10 12h4"/>',
			'calendar'      => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
			'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'bell'          => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
			'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
			'image'         => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-9 8"/>',
			'upload'        => '<path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
			'home'          => '<path d="m3 11 9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
			'file'          => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
			'filePlus'      => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M12 12v6M9 15h6"/>',
			'send'          => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
			'lock'          => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
			'shield'        => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
			'key'           => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3"/>',
			'cpu'           => '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
			'sparkle'       => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
			'briefcase'     => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18"/>',
			'code'          => '<path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 4l-4 16"/>',
			'factory'       => '<path d="M3 21V10l6 4V10l6 4V6l6 4v11z"/><path d="M7 17h2M13 17h2M17 17h2"/>',
			'cloud'         => '<path d="M7 18a5 5 0 1 1 .9-9.9A6 6 0 0 1 19 10a4 4 0 0 1 0 8z"/>',
			'database'      => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
			'rocket'        => '<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2M9 15l-3-3c1-4 4-8 10-9 0 6-4 9-8 10l-1 2"/><circle cx="15" cy="9" r="1.5"/>',
			'zap'           => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
			'heart'         => '<path d="M12 20s-8-4.5-8-10.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 2.5C20 15.5 12 20 12 20z"/><path d="M3 12h4l2-3 3 6 2-3h7"/>',
			'wallet'        => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M16 15h2"/>',
			'bot'           => '<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M9 13v1M15 13v1M9 17h6"/>',
			'target'        => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
			'leaf'          => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15"/><path d="M5 19c3-4 6-6 10-8"/>',
			'megaphone'     => '<path d="M3 11v2a1 1 0 0 0 1 1h3l8 5V5L7 10H4a1 1 0 0 0-1 1zM18 9a3 3 0 0 1 0 6"/>',
			'refresh'       => '<path d="M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4"/>',
			'bold'          => '<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/>',
			'italic'        => '<path d="M19 5h-9M14 19H5M15 5 9 19"/>',
			'list'          => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
			'listOl'        => '<path d="M10 6h10M10 12h10M10 18h10M4 5h1v4M4 9h2M4 15h2l-2 3h2"/>',
			'quote'         => '<path d="M7 7h4v4c0 3-2 5-4 5M14 7h4v4c0 3-2 5-4 5"/>',
			'codeBlock'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m9 10-2 2 2 2M15 10l2 2-2 2"/>',
			'table'         => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 4v16"/>',
			'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
			'moon'          => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
			'logout'        => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
			'filter'        => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
			'sort'          => '<path d="m7 15 5 5 5-5M7 9l5-5 5 5"/>',
			'grid'          => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
			'plus'          => '<path d="M12 5v14M5 12h14"/>',
			'external'      => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
			'panel'         => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/>',
			'tag'           => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
			'msg'           => '<path d="M4 5h16v11H9l-5 4z"/>',
			'dots'          => '<circle cx="5" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="19" cy="12" r="1.2"/>',
			'pen'           => '<path d="m12 19 7-7 3 3-7 7zM18 13l-1.5-7.5L2 2l3.5 14.5L13 18zM2 2l7.6 7.6"/><circle cx="11" cy="11" r="2"/>',
			'award'         => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
			'heading'       => '<path d="M6 4v16M18 4v16M6 12h12"/>',
			'sitemap'       => '<rect x="9" y="3" width="6" height="5" rx="1"/><rect x="2" y="16" width="6" height="5" rx="1"/><rect x="16" y="16" width="6" height="5" rx="1"/><path d="M12 8v4M5 16v-4h14v4"/>',
			'calendarCheck' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M9 15l2 2 4-4"/>',
		);
	}

	/**
	 * Icon names (for selects).
	 *
	 * @return array<int,string>
	 */
	public static function names(): array {
		return array_keys( self::paths() );
	}

	/**
	 * Reference an icon from the sprite.
	 *
	 * @param string $name       Icon name.
	 * @param string $class_name Optional class.
	 */
	public static function svg( string $name, string $class_name = '' ): string {
		if ( ! isset( self::paths()[ $name ] ) ) {
			$name = 'info';
		}
		return sprintf(
			'<svg%s viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#tv-i-%s"></use></svg>',
			$class_name ? ' class="' . esc_attr( $class_name ) . '"' : '',
			esc_attr( $name )
		);
	}

	/**
	 * Fully inline icon (for contexts without the sprite, e.g. emails).
	 *
	 * @param string $name       Icon name.
	 * @param string $class_name Optional class.
	 */
	public static function inline( string $name, string $class_name = '' ): string {
		$paths = self::paths();
		$path  = $paths[ $name ] ?? $paths['info'];
		return sprintf(
			'<svg%s viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
			$class_name ? ' class="' . esc_attr( $class_name ) . '"' : '',
			$path
		);
	}

	/**
	 * The full sprite markup.
	 */
	public static function sprite(): string {
		$symbols = '';
		foreach ( self::paths() as $name => $path ) {
			$symbols .= '<symbol id="tv-i-' . $name . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</symbol>';
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">' . $symbols . '</svg>';
	}

	/**
	 * Print the sprite once.
	 */
	public static function print_sprite(): void {
		if ( self::$printed ) {
			return;
		}
		self::$printed = true;
		echo self::sprite(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
	}
}
