<?php
/**
 * Menu rendering for header (dropdowns + categories mega-menu), footer columns and the mobile drawer.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Frontend;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Support\Icons;
use Techvenza\Core\Support\Render;

/**
 * Classic menu locations rendered with prototype markup.
 */
final class Menus {

	public const MEGA_CLASS = 'tv-mega-categories';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'wp_nav_menu_item_custom_fields', array( self::class, 'item_hint' ), 10, 2 );
	}

	/**
	 * Hint in Appearance → Menus about special classes.
	 *
	 * @param int      $item_id Item ID.
	 * @param \WP_Post $item    Item.
	 */
	public static function item_hint( $item_id, $item ): void {
		if ( 0 === (int) get_post_meta( $item->ID, '_menu_item_menu_item_parent', true ) ) {
			echo '<p class="description description-wide">' . esc_html__( 'Techvenza: add the CSS class “tv-mega-categories” to turn this item into the categories mega-menu. Item descriptions appear under dropdown links; a class “tv-icon-NAME” sets the dropdown icon.', 'techvenza-core' ) . '</p>';
		}
	}

	/**
	 * Menu items tree for a location.
	 *
	 * @param string $location Location.
	 * @return array{name:string,items:array<int,array<string,mixed>>}|null
	 */
	public static function tree( string $location ): ?array {
		$locations = get_nav_menu_locations();
		if ( empty( $locations[ $location ] ) ) {
			return null;
		}
		$menu  = wp_get_nav_menu_object( $locations[ $location ] );
		$items = $menu ? wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) ) : array();
		if ( ! $menu || ! $items ) {
			return null;
		}
		_wp_menu_item_classes_by_context( $items );
		$by_parent = array();
		foreach ( $items as $item ) {
			$by_parent[ (int) $item->menu_item_parent ][] = $item;
		}
		$build = static function ( int $parent_id ) use ( &$build, $by_parent ): array {
			$out = array();
			foreach ( $by_parent[ $parent_id ] ?? array() as $item ) {
				$classes = array_filter( (array) $item->classes );
				$icon    = '';
				foreach ( $classes as $c ) {
					if ( str_starts_with( $c, 'tv-icon-' ) ) {
						$icon = substr( $c, 8 );
					}
				}
				$out[] = array(
					'id'       => (int) $item->ID,
					'title'    => $item->title,
					'url'      => $item->url,
					'desc'     => $item->description,
					'target'   => $item->target,
					'classes'  => $classes,
					'icon'     => $icon,
					'current'  => (bool) array_intersect( $classes, array( 'current-menu-item', 'current_page_item' ) ),
					'ancestor' => (bool) array_intersect( $classes, array( 'current-menu-ancestor', 'current-menu-parent', 'current-page-ancestor' ) ),
					'mega'     => in_array( self::MEGA_CLASS, $classes, true ),
					'children' => $build( (int) $item->ID ),
				);
			}
			return $out;
		};
		return array(
			'name'  => $menu->name,
			'items' => $build( 0 ),
		);
	}

	/**
	 * Fallback header items when no menu is assigned (so a fresh install is navigable).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function fallback(): array {
		$mk = static fn( string $title, string $url, array $extra = array() ) => array_merge(
			array(
				'id'       => crc32( $title ),
				'title'    => $title,
				'url'      => $url,
				'desc'     => '',
				'target'   => '',
				'classes'  => array(),
				'icon'     => '',
				'current'  => false,
				'ancestor' => false,
				'mega'     => false,
				'children' => array(),
			),
			$extra
		);
		return array(
			$mk( __( 'Home', 'techvenza-core' ), home_url( '/' ), array( 'current' => is_front_page() ) ),
			$mk( __( 'Explore', 'techvenza-core' ), Render::page_url( 'explore' ) ),
			$mk( __( 'Categories', 'techvenza-core' ), '#', array( 'mega' => true ) ),
			$mk( __( 'Authors', 'techvenza-core' ), Render::page_url( 'authors' ) ),
			$mk( __( 'About', 'techvenza-core' ), Render::page_url( 'about' ) ),
		);
	}

	/**
	 * Render a menu block.
	 *
	 * @param array<string,mixed> $attributes location, variant, showTitle, title.
	 */
	public static function render( array $attributes ): string {
		$location = (string) ( $attributes['location'] ?? 'primary' );
		$variant  = (string) ( $attributes['variant'] ?? 'header' );
		$tree     = self::tree( $location );
		if ( ! $tree ) {
			if ( 'header' === $variant ) {
				$tree = array(
					'name'  => '',
					'items' => self::fallback(),
				);
			} elseif ( current_user_can( 'edit_theme_options' ) && 'bare' !== $variant ) {
				/* translators: %s: menu location */
				return '<div><p class="caption">' . esc_html( sprintf( __( 'Assign a menu to “%s” in Appearance → Menus.', 'techvenza-core' ), $location ) ) . '</p></div>';
			} else {
				return '';
			}
		}

		if ( 'footer' === $variant ) {
			$title = (string) ( $attributes['title'] ?? '' );
			$title = '' !== $title ? $title : $tree['name'];
			$lis   = '';
			foreach ( $tree['items'] as $it ) {
				$lis .= sprintf( '<li><a href="%s"%s%s>%s</a></li>', esc_url( $it['url'] ), $it['target'] ? ' target="' . esc_attr( $it['target'] ) . '" rel="noopener"' : '', $it['current'] ? ' aria-current="page"' : '', esc_html( $it['title'] ) );
			}
			return sprintf(
				'<nav aria-label="%s">%s<ul>%s</ul></nav>',
				esc_attr( $title ? $title : __( 'Footer', 'techvenza-core' ) ),
				! empty( $attributes['showTitle'] ) && $title ? '<h2>' . esc_html( $title ) . '</h2>' : '',
				$lis
			);
		}

		if ( 'bare' === $variant || 'inline' === $variant ) {
			$links = '';
			foreach ( $tree['items'] as $it ) {
				$links .= sprintf( '<a href="%s">%s</a>', esc_url( $it['url'] ), esc_html( $it['title'] ) );
			}
			return 'bare' === $variant ? $links : '<nav class="row" aria-label="' . esc_attr( $tree['name'] ) . '">' . $links . '</nav>';
		}

		$html = '';
		foreach ( $tree['items'] as $it ) {
			$active = $it['current'] || $it['ancestor'];
			if ( $it['mega'] ) {
				$html .= self::mega( $it, $active );
			} elseif ( $it['children'] ) {
				$id    = 'dd-' . $it['id'];
				$html .= sprintf(
					'<div class="dropdown"><button class="nav-link%s" type="button" aria-expanded="false" aria-controls="%s" data-dd>%s %s</button><div class="dropdown-menu" id="%s" role="menu" aria-label="%s">%s</div></div>',
					$active ? ' active' : '',
					esc_attr( $id ),
					esc_html( $it['title'] ),
					Icons::svg( 'chevDown' ),
					esc_attr( $id ),
					esc_attr( $it['title'] ),
					implode( '', array_map( array( self::class, 'dropdown_item' ), $it['children'] ) )
				);
			} else {
				$html .= sprintf( '<a class="nav-link" href="%s"%s>%s</a>', esc_url( $it['url'] ), $it['current'] ? ' aria-current="page"' : '', esc_html( $it['title'] ) );
			}
		}
		return '<nav class="main-nav" aria-label="' . esc_attr__( 'Primary', 'techvenza-core' ) . '">' . $html . '</nav>';
	}

	/**
	 * Dropdown item with optional icon and description.
	 *
	 * @param array<string,mixed> $it Item.
	 */
	public static function dropdown_item( array $it ): string {
		$icon = $it['icon'] ? '<span class="dd-ico">' . Icons::svg( (string) $it['icon'] ) . '</span>' : '';
		return sprintf(
			'<a class="dropdown-item" role="menuitem" href="%s">%s<span>%s%s</span></a>',
			esc_url( $it['url'] ),
			$icon,
			esc_html( $it['title'] ),
			$it['desc'] ? '<small>' . esc_html( $it['desc'] ) . '</small>' : ''
		);
	}

	/**
	 * Categories mega-menu (dynamic: icon, colour and count per category).
	 *
	 * @param array<string,mixed> $it     Menu item.
	 * @param bool                $active Active state.
	 */
	private static function mega( array $it, bool $active ): string {
		$cats  = CategoryMeta::all();
		$items = '';
		foreach ( array_slice( $cats, 0, 12 ) as $c ) {
			$items .= sprintf(
				'<a class="dropdown-item" role="menuitem" href="%s"><span class="dd-ico" style="%s">%s</span><span>%s<small>%s</small></span></a>',
				esc_url( $c['url'] ),
				esc_attr( CategoryMeta::style( $c ) ),
				Icons::svg( $c['icon'] ),
				esc_html( $c['name'] ),
				/* translators: %d: number of stories */
				esc_html( sprintf( _n( '%d story', '%d stories', $c['count'], 'techvenza-core' ), $c['count'] ) )
			);
		}
		$id = 'dd-cats';
		return sprintf(
			'<div class="dropdown"><button class="nav-link%1$s" type="button" aria-expanded="false" aria-controls="%2$s" data-dd>%3$s %4$s</button><div class="dropdown-menu mega" id="%2$s" role="menu" aria-label="%3$s">%5$s<div class="mega-foot"><span class="muted">%6$s</span><a class="sec-link" href="%7$s" role="menuitem">%8$s %9$s</a></div></div></div>',
			( $active || is_category() ) ? ' active' : '',
			esc_attr( $id ),
			esc_html( $it['title'] ),
			Icons::svg( 'chevDown' ),
			$items,
			/* translators: %d: number of categories */
			esc_html( sprintf( _n( '%d topic in total', '%d topics in total', count( $cats ), 'techvenza-core' ), count( $cats ) ) ),
			esc_url( '#' !== $it['url'] && $it['url'] ? $it['url'] : Render::page_url( 'explore' ) ),
			esc_html__( 'Browse everything', 'techvenza-core' ),
			Icons::svg( 'arrowRight' )
		);
	}

	/**
	 * Mobile drawer markup.
	 *
	 * @param string $location Menu location (falls back to primary, then defaults).
	 */
	public static function drawer( string $location ): string {
		$tree  = self::tree( $location );
		$tree  = $tree ? $tree : self::tree( 'primary' );
		$items = $tree ? $tree['items'] : self::fallback();
		$html  = '';
		foreach ( $items as $it ) {
			if ( $it['mega'] ) {
				$sub = '';
				foreach ( CategoryMeta::all() as $c ) {
					$sub .= sprintf( '<a href="%s"><span class="dot-c" style="--c:%s"></span>%s</a>', esc_url( $c['url'] ), esc_attr( $c['color'] ), esc_html( $c['name'] ) );
				}
				$html .= sprintf( '<details><summary class="drawer-link">%s %s</summary><div class="drawer-sub">%s</div></details>', esc_html( $it['title'] ), Icons::svg( 'chevDown' ), $sub );
			} elseif ( $it['children'] ) {
				$sub = '';
				foreach ( $it['children'] as $ch ) {
					$sub .= sprintf( '<a href="%s">%s</a>', esc_url( $ch['url'] ), esc_html( $ch['title'] ) );
				}
				$html .= sprintf( '<details><summary class="drawer-link">%s %s</summary><div class="drawer-sub">%s</div></details>', esc_html( $it['title'] ), Icons::svg( 'chevDown' ), $sub );
			} else {
				$html .= sprintf( '<a class="drawer-link" href="%s"%s>%s %s</a>', esc_url( $it['url'] ), $it['current'] ? ' aria-current="page"' : '', esc_html( $it['title'] ), Icons::svg( 'chevRight' ) );
			}
		}
		$cta_label = (string) \Techvenza\Core\Settings\Options::get( 'header_cta_label' );
		$cta_url   = Render::url( (string) \Techvenza\Core\Settings\Options::get( 'header_cta_url' ) );
		return sprintf(
			'<div class="drawer-backdrop" data-close-drawer></div><aside class="drawer" id="drawer" aria-label="%1$s" aria-modal="true" role="dialog"><div class="drawer-head">%2$s<button class="icon-btn" type="button" aria-label="%3$s" data-close-drawer>%4$s</button></div><div class="drawer-body"><form class="input-icon mb-4" role="search" method="get" action="%5$s">%6$s<label class="sr-only" for="drawerQ">%7$s</label><input class="input" id="drawerQ" name="s" type="search" placeholder="%8$s"></form>%9$s<div class="grid mt-5" style="gap:10px" data-drawer-account>%10$s<a class="btn btn-secondary btn-block" href="%11$s" data-login-link>%12$s</a></div></div></aside>',
			esc_attr__( 'Mobile navigation', 'techvenza-core' ),
			Brand::logo(),
			esc_attr__( 'Close menu', 'techvenza-core' ),
			Icons::svg( 'x' ),
			esc_url( home_url( '/' ) ),
			Icons::svg( 'search' ),
			esc_html__( 'Search', 'techvenza-core' ),
			esc_attr__( 'Search', 'techvenza-core' ),
			$html,
			$cta_label ? sprintf( '<a class="btn btn-primary btn-block" href="%s">%s</a>', esc_url( $cta_url ), esc_html( $cta_label ) ) : '',
			esc_url( Render::page_url( 'login' ) ),
			esc_html__( 'Log in', 'techvenza-core' )
		);
	}
}
