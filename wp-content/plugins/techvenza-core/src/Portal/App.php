<?php
/**
 * App shell shared by the reader dashboard and the author portal (sidebar, top bar, page head),
 * plus access control and no-cache handling for those pages.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Portal;

use Techvenza\Core\Auth\Auth;
use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Database\Notifications;
use Techvenza\Core\Frontend\Brand;
use Techvenza\Core\Support\Render as R;
use Techvenza\Core\Workflow\Roles;

/**
 * Shell + helpers.
 */
final class App {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'guard' ), 5 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * Page slug → required capability.
	 *
	 * @return array<string,string>
	 */
	public static function pages(): array {
		return array(
			'dashboard'     => 'read',
			'author-portal' => 'tv_use_portal',
		);
	}

	/**
	 * Current app page slug ('' when not an app page).
	 */
	public static function current(): string {
		if ( ! is_page() ) {
			return '';
		}
		$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
		return isset( self::pages()[ $slug ] ) ? $slug : '';
	}

	/**
	 * Logged-out visitors go to log in; private pages are never cached.
	 */
	public static function guard(): void {
		$slug = self::current();
		if ( ! $slug ) {
			return;
		}
		nocache_headers();
		if ( ! is_user_logged_in() ) {
			$self = (string) get_permalink( get_queried_object_id() );
			$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( wp_login_url( $view ? add_query_arg( 'view', $view, $self ) : $self ) );
			exit;
		}
	}

	/**
	 * Body class for app pages (hides the site header/footer via CSS).
	 *
	 * @param array<int,string> $classes Classes.
	 * @return array<int,string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::current() ) {
			$classes[] = 'app-mode';
		}
		return $classes;
	}

	/**
	 * Current view key from the URL.
	 *
	 * @param array<int,string> $allowed Allowed views.
	 * @param string            $fallback Default.
	 */
	public static function view( array $allowed, string $fallback ): string {
		$v = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : $fallback; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $v, $allowed, true ) ? $v : $fallback;
	}

	/**
	 * URL of an app view.
	 *
	 * @param string                   $page Page slug (dashboard|author-portal).
	 * @param string                   $view View ('' = home).
	 * @param array<string,string|int> $args Extra args.
	 */
	public static function url( string $page, string $view = '', array $args = array() ): string {
		$base = R::page_url( $page );
		return add_query_arg( array_filter( array_merge( array( 'view' => $view ), $args ), static fn( $v ) => '' !== $v && 0 !== $v ), $base );
	}

	/**
	 * Full shell.
	 *
	 * @param string $kind    reader|portal.
	 * @param string $active  Active nav key.
	 * @param string $content Pre-escaped content.
	 */
	public static function shell( string $kind, string $active, string $content ): string {
		$user   = wp_get_current_user();
		$unread = Notifications::unread( $user->ID );
		$nav    = 'reader' === $kind
			? array(
				__( 'Reading', 'techvenza-core' ) => array(
					array( 'dashboard', __( 'Overview', 'techvenza-core' ), 'home', self::url( 'dashboard' ), 0 ),
					array( 'saved', __( 'Saved articles', 'techvenza-core' ), 'bookmark', self::url( 'dashboard', 'saved' ), 0 ),
					array( 'history', __( 'Reading history', 'techvenza-core' ), 'clock', self::url( 'dashboard', 'history' ), 0 ),
					array( 'settings', __( 'Preferences & profile', 'techvenza-core' ), 'settings', self::url( 'dashboard', 'settings' ), 0 ),
				),
			)
			: array(
				__( 'Workspace', 'techvenza-core' ) => array(
					array( 'dash', __( 'Dashboard', 'techvenza-core' ), 'home', self::url( 'author-portal' ), 0 ),
					array( 'articles', __( 'My articles', 'techvenza-core' ), 'file', self::url( 'author-portal', 'articles' ), 0 ),
					array( 'editor', __( 'New article', 'techvenza-core' ), 'filePlus', admin_url( 'post-new.php' ), 0 ),
					array( 'analytics', __( 'Analytics', 'techvenza-core' ), 'chart', self::url( 'author-portal', 'analytics' ), 0 ),
					array( 'notifications', __( 'Notifications', 'techvenza-core' ), 'bell', self::url( 'author-portal', 'notifications' ), $unread ),
				),
				__( 'Account', 'techvenza-core' )   => array(
					array( 'profile', __( 'Author profile', 'techvenza-core' ), 'user', self::url( 'author-portal', 'profile' ), 0 ),
					array( 'settings', __( 'Account settings', 'techvenza-core' ), 'settings', self::url( 'author-portal', 'settings' ), 0 ),
				),
			);
		$label  = 'reader' === $kind ? __( 'Reader', 'techvenza-core' ) : __( 'Author portal', 'techvenza-core' );

		$links = '';
		foreach ( $nav as $group => $items ) {
			$links .= '<div class="sb-tag">' . esc_html( $group ) . '</div>';
			foreach ( $items as [ $key, $text, $icon, $href, $count ] ) {
				$on     = $key === $active;
				$links .= sprintf(
					'<a class="sb-link%1$s" href="%2$s"%3$s title="%4$s">%5$s<span class="sb-label">%4$s</span>%6$s</a>',
					$on ? ' active' : '',
					esc_url( $href ),
					$on ? ' aria-current="page"' : '',
					esc_attr( $text ),
					R::icon( $icon ),
					/* translators: %d: number of unread items */
					$count ? sprintf( '<span class="sb-count" aria-label="%s">%d</span>', esc_attr( sprintf( __( '%d unread', 'techvenza-core' ), $count ) ), $count ) : ''
				);
			}
		}
		$foot = '';
		if ( 'reader' !== $kind ) {
			$foot .= sprintf( '<a class="sb-link" href="%s" title="%2$s">%3$s<span class="sb-label">%2$s</span></a>', esc_url( self::url( 'dashboard' ) ), esc_attr__( 'Reader dashboard', 'techvenza-core' ), R::icon( 'bookmark' ) );
		}
		if ( 'portal' !== $kind && current_user_can( 'tv_use_portal' ) ) {
			$foot .= sprintf( '<a class="sb-link" href="%s" title="%2$s">%3$s<span class="sb-label">%2$s</span></a>', esc_url( self::url( 'author-portal' ) ), esc_attr__( 'Author portal', 'techvenza-core' ), R::icon( 'pen' ) );
		}
		if ( current_user_can( 'tv_review_posts' ) || current_user_can( 'manage_options' ) ) {
			$foot .= sprintf( '<a class="sb-link" href="%s" title="%2$s">%3$s<span class="sb-label">%2$s</span></a>', esc_url( admin_url( 'admin.php?page=techvenza' ) ), esc_attr__( 'Editorial CMS', 'techvenza-core' ), R::icon( 'grid' ) );
		}
		$foot .= sprintf( '<a class="sb-link" href="%s" title="%2$s">%3$s<span class="sb-label">%2$s</span></a>', esc_url( home_url( '/' ) ), esc_attr__( 'View site', 'techvenza-core' ), R::icon( 'external' ) );

		$person = UserMeta::author( $user );
		$bell   = 'reader' !== $kind
			? sprintf(
				'<a class="icon-btn" href="%s" aria-label="%s" style="position:relative">%s%s</a>',
				esc_url( self::url( 'author-portal', 'notifications' ) ),
				/* translators: %d: unread count */
				esc_attr( $unread ? sprintf( _n( 'Notifications, %d unread', 'Notifications, %d unread', $unread, 'techvenza-core' ), $unread ) : __( 'Notifications', 'techvenza-core' ) ),
				R::icon( 'bell' ),
				$unread ? '<span style="position:absolute;top:8px;right:9px;width:8px;height:8px;border-radius:50%;background:var(--coral)" aria-hidden="true"></span>' : ''
			)
			: '';

		return sprintf(
			'<div class="app" id="appShell" data-app="%1$s">
			<div class="sb-backdrop" data-action="sbClose"></div>
			<aside class="sidebar" aria-label="%2$s"><div class="sb-head">%3$s<button class="icon-btn sb-collapse" type="button" data-action="sbCollapse" aria-label="%4$s" aria-pressed="false">%5$s</button></div><nav aria-label="%2$s">%6$s</nav><div class="sb-foot">%7$s</div></aside>
			<div class="app-main"><header class="topbar"><button class="icon-btn sb-open" type="button" data-action="sbOpen" aria-label="%8$s">%9$s</button><span class="demo-label hide-sm">%10$s</span>
			<div style="margin-left:auto" class="row">%11$s<span class="badge hide-sm">%12$s</span>%13$s<span class="hide-sm" style="font-size:.875rem;font-weight:600;color:var(--ink)">%14$s</span><a class="icon-btn" href="%15$s" aria-label="%16$s">%17$s</a></div></header>
			<div class="app-content">%18$s</div></div></div>',
			esc_attr( $kind ),
			/* translators: %s: area label */
			esc_attr( sprintf( __( '%s navigation', 'techvenza-core' ), $label ) ),
			Brand::logo( 'light' ),
			esc_attr__( 'Collapse sidebar', 'techvenza-core' ),
			R::icon( 'panel' ),
			$links,
			$foot,
			esc_attr__( 'Open navigation', 'techvenza-core' ),
			R::icon( 'menu' ),
			esc_html( $label ),
			$bell,
			esc_html( Roles::label_for( $user ) ),
			R::avatar( $person ? $person : array( 'initials' => UserMeta::initials( $user->display_name ) ), 'sm' ),
			esc_html( $user->display_name ),
			esc_url( wp_logout_url( add_query_arg( 'tv_msg', 'logged_out', Auth::page( 'login' ) ? Auth::page( 'login' ) : home_url( '/' ) ) ) ),
			esc_attr__( 'Log out', 'techvenza-core' ),
			R::icon( 'logout' ),
			$content
		);
	}

	/**
	 * Page head (breadcrumbs, title, description, actions).
	 *
	 * @param array<int,array{0:string,1:string}> $crumbs  Trail.
	 * @param string                              $title   Title.
	 * @param string                              $desc    Pre-escaped description.
	 * @param string                              $actions Pre-escaped actions.
	 */
	public static function head( array $crumbs, string $title, string $desc = '', string $actions = '' ): string {
		return sprintf(
			'<div class="app-head"><div>%s<h1>%s</h1>%s</div>%s</div>',
			R::crumbs( $crumbs ),
			esc_html( $title ),
			$desc ? '<p>' . $desc . '</p>' : '',
			$actions ? '<div class="row">' . $actions . '</div>' : ''
		);
	}

	/**
	 * Stat tile.
	 *
	 * @param string $label Label.
	 * @param string $value Value (pre-escaped).
	 * @param string $color CSS colour.
	 * @param string $note  Pre-escaped note.
	 */
	public static function stat( string $label, string $value, string $color = 'var(--ink)', string $note = '' ): string {
		return sprintf( '<div class="stat" style="--c:%s"><span class="k"><i></i>%s</span><span class="v">%s</span>%s</div>', esc_attr( $color ), esc_html( $label ), $value, $note ? '<span class="d">' . $note . '</span>' : '' );
	}

	/**
	 * Compact article row with a bookmark toggle.
	 *
	 * @param \WP_Post|int $post  Post.
	 * @param string       $extra Pre-escaped extra meta.
	 */
	public static function row( $post, string $extra = '' ): string {
		$a = R::article( $post );
		if ( ! $a ) {
			return '';
		}
		return sprintf(
			'<div class="row" style="gap:var(--s4);padding:var(--s3) 0;border-top:1px solid var(--border);flex-wrap:nowrap" data-row="%1$d"><a href="%2$s" style="width:88px;flex:none" tabindex="-1" aria-hidden="true">%3$s</a><div style="flex:1;min-width:0">%4$s<h3 class="card-title" style="font-size:.975rem;margin-top:6px"><a href="%2$s">%5$s</a></h3><div class="meta">%6$s <span class="dot"></span> %7$s %8$s</div></div><button class="icon-btn" type="button" data-action="bookmark" data-id="%1$d" aria-pressed="false" aria-label="%9$s">%10$s</button></div>',
			(int) $a['id'],
			esc_url( $a['url'] ),
			R::media(
				$a,
				array(
					'ratio' => 'r-43',
					'sizes' => '90px',
					'size'  => 'techvenza-square',
				)
			),
			R::chip( $a['cat'], false ),
			esc_html( $a['title'] ),
			esc_html( $a['author']['name'] ?? '' ),
			/* translators: %d: minutes */
			esc_html( sprintf( __( '%d min', 'techvenza-core' ), $a['minutes'] ) ),
			$extra,
			/* translators: %s: article title */
			esc_attr( sprintf( __( 'Toggle saved: %s', 'techvenza-core' ), $a['title'] ) ),
			R::icon( 'bookmark' )
		);
	}

	/**
	 * Access restricted state.
	 *
	 * @param string $need What is required.
	 */
	public static function denied( string $need ): string {
		return sprintf(
			'<section class="err-hero"><div class="container narrow"><div class="success-state" style="padding:0"><span class="icon-circle warn" style="width:80px;height:80px">%s</span><h1 class="page-title">%s</h1><p class="lead" style="margin-inline:auto">%s</p><div class="row" style="justify-content:center"><a class="btn btn-primary" href="%s">%s</a><a class="btn btn-secondary" href="%s">%s</a></div></div></div></section>',
			R::icon( 'lock' ),
			esc_html__( 'Access restricted', 'techvenza-core' ),
			esc_html( $need ),
			esc_url( Auth::home_for( wp_get_current_user() ) ),
			esc_html__( 'Go to your dashboard', 'techvenza-core' ),
			esc_url( R::page_url( 'become-an-author' ) ),
			esc_html__( 'Become an author', 'techvenza-core' )
		);
	}
}
