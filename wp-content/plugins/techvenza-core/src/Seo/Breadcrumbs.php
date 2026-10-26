<?php
/**
 * Holds the breadcrumb trail rendered on the current page (for BreadcrumbList JSON-LD).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Seo;

/**
 * Request-scoped breadcrumb store.
 */
final class Breadcrumbs {

	/**
	 * Trail of [label, url].
	 *
	 * @var array<int,array{0:string,1:string}>
	 */
	private static array $trail = array();

	/**
	 * Store the trail (first one rendered wins).
	 *
	 * @param array<int,array{0:string,1:string}> $items Items.
	 */
	public static function set( array $items ): void {
		if ( ! self::$trail ) {
			self::$trail = $items;
		}
	}

	/**
	 * Current trail.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function get(): array {
		return self::$trail;
	}
}
