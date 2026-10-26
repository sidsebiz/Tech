<?php
/**
 * Page-level UI roots printed once: toasts, modal root, back-to-top.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Frontend;

use Techvenza\Core\Support\Icons;

/**
 * Footer UI containers used by frontend.js.
 */
final class GlobalUi {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'wp_footer', array( self::class, 'print' ), 5 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * Containers.
	 */
	public static function print(): void {
		if ( is_admin() ) {
			return;
		}
		printf(
			'<button class="to-top" type="button" aria-label="%1$s" data-to-top>%2$s</button><div class="toasts" aria-live="polite" aria-atomic="false" id="toasts"></div><div class="modal-root" id="modalRoot" aria-hidden="true"></div>',
			esc_attr__( 'Back to top', 'techvenza-core' ),
			Icons::svg( 'arrowUp' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		);
	}

	/**
	 * Body classes.
	 *
	 * @param array<int,string> $classes Classes.
	 * @return array<int,string>
	 */
	public static function body_class( array $classes ): array {
		$classes[] = 'techvenza';
		return $classes;
	}
}
