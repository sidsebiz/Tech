<?php
/**
 * Editorial CMS inside wp-admin: menu, assets, shared screen chrome and form handlers.
 *
 * Articles, categories, tags, media and pages use the core WordPress screens (with workflow
 * columns and filters added); the screens below cover what core does not.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Support\Icons;
use Techvenza\Core\Support\Render as R;
use Techvenza\Core\Workflow\Workflow;

/**
 * Admin bootstrap.
 */
final class Admin {

	/**
	 * Top-level menu slug.
	 */
	public const SLUG = 'techvenza';

	/**
	 * Screens: slug => [title, capability, renderer].
	 *
	 * @return array<string,array{0:string,1:string,2:callable}>
	 */
	public static function screens(): array {
		return array(
			'techvenza'              => array( __( 'Dashboard', 'techvenza-core' ), 'tv_review_posts', array( Dashboard::class, 'render' ) ),
			'techvenza-review'       => array( __( 'Review queue', 'techvenza-core' ), 'tv_review_posts', array( Review::class, 'render' ) ),
			'techvenza-calendar'     => array( __( 'Editorial calendar', 'techvenza-core' ), 'tv_review_posts', array( Calendar::class, 'render' ) ),
			'techvenza-applications' => array( __( 'Author applications', 'techvenza-core' ), 'tv_manage_applications', array( Applications::class, 'render' ) ),
			'techvenza-authors'      => array( __( 'Authors', 'techvenza-core' ), 'tv_manage_authors', array( Authors::class, 'render' ) ),
			'techvenza-analytics'    => array( __( 'Analytics', 'techvenza-core' ), 'tv_view_analytics', array( Analytics::class, 'render' ) ),
			'techvenza-roles'        => array( __( 'Roles & permissions', 'techvenza-core' ), 'manage_options', array( RolesScreen::class, 'render' ) ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_post_techvenza_transition', array( Review::class, 'handle' ) );
		add_action( 'admin_post_techvenza_application', array( Applications::class, 'handle' ) );
		add_action( 'admin_post_techvenza_author', array( Authors::class, 'handle' ) );
		add_action( 'admin_post_techvenza_roles', array( RolesScreen::class, 'handle' ) );
		add_action( 'admin_post_techvenza_analytics_csv', array( Analytics::class, 'csv' ) );
		PostsList::register();
		Calendar::register();
	}

	/**
	 * Register the menu.
	 */
	public static function menu(): void {
		$pending = self::pending_count();
		$apps    = Applications::pending_count();
		add_menu_page(
			__( 'Techvenza editorial CMS', 'techvenza-core' ),
			__( 'Editorial', 'techvenza-core' ) . ( $pending + $apps ? ' <span class="awaiting-mod">' . (int) ( $pending + $apps ) . '</span>' : '' ),
			'tv_review_posts',
			self::SLUG,
			array( self::class, 'dispatch' ),
			'dashicons-welcome-write-blog',
			3
		);
		foreach ( self::screens() as $slug => [ $title, $cap ] ) {
			$label = $title;
			if ( 'techvenza-review' === $slug && $pending ) {
				$label .= ' <span class="awaiting-mod">' . (int) $pending . '</span>';
			}
			if ( 'techvenza-applications' === $slug && $apps ) {
				$label .= ' <span class="awaiting-mod">' . (int) $apps . '</span>';
			}
			add_submenu_page( self::SLUG, $title, $label, $cap, $slug, array( self::class, 'dispatch' ) );
		}
	}

	/**
	 * Render the current screen.
	 */
	public static function dispatch(): void {
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screens = self::screens();
		if ( ! isset( $screens[ $page ] ) || ! current_user_can( $screens[ $page ][1] ) ) {
			wp_die( esc_html__( 'You do not have permission to view this screen.', 'techvenza-core' ), 403 );
		}
		echo '<div class="tv-admin">' . self::notice() . call_user_func( $screens[ $page ][2] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Screens escape their own output.
	}

	/**
	 * Styles and scripts for Techvenza screens (and the posts list).
	 *
	 * @param string $hook Admin hook suffix.
	 */
	public static function assets( string $hook ): void {
		$ours = str_contains( $hook, 'techvenza' ) || 'edit.php' === $hook;
		if ( ! $ours ) {
			return;
		}
		$ver = static fn( string $f ): string => (string) filemtime( TECHVENZA_CORE_DIR . $f );
		wp_enqueue_style( 'techvenza-admin', TECHVENZA_CORE_URL . 'assets/css/admin.css', array(), $ver( 'assets/css/admin.css' ) );
		if ( str_contains( $hook, 'techvenza' ) ) {
			wp_enqueue_script( 'techvenza-admin', TECHVENZA_CORE_URL . 'assets/js/admin.js', array( 'wp-api-fetch' ), $ver( 'assets/js/admin.js' ), true );
			wp_localize_script(
				'techvenza-admin',
				'techvenzaAdmin',
				array(
					'i18n' => array(
						'confirmApproveIncomplete' => __( 'Some checklist items are not ticked. Approve anyway?', 'techvenza-core' ),
						'moved'                    => __( 'Rescheduled.', 'techvenza-core' ),
						'moveFailed'               => __( 'Could not reschedule this article.', 'techvenza-core' ),
						/* translators: 1: title, 2: date */
						'moveTo'                   => __( 'Move “%1$s” to %2$s?', 'techvenza-core' ),
					),
				)
			);
		}
	}

	/**
	 * Print the icon sprite on our screens.
	 */
	public static function sprite(): void {
		Icons::print_sprite();
	}

	/**
	 * Screen header.
	 *
	 * @param array<int,array{0:string,1:string}> $crumbs  Trail (label, url).
	 * @param string                              $title   Title.
	 * @param string                              $desc    Pre-escaped description.
	 * @param string                              $actions Pre-escaped actions.
	 */
	public static function head( array $crumbs, string $title, string $desc = '', string $actions = '' ): string {
		$trail = implode(
			' / ',
			array_map(
				static fn( $c ) => $c[1] ? '<a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a>' : '<span aria-current="page">' . esc_html( $c[0] ) . '</span>',
				$crumbs
			)
		);
		return sprintf(
			'<div class="tva-head"><div><nav class="tva-crumbs" aria-label="%s">%s</nav><h1>%s</h1>%s</div>%s</div><hr class="wp-header-end">',
			esc_attr__( 'Breadcrumb', 'techvenza-core' ),
			$trail,
			esc_html( $title ),
			$desc ? '<p>' . $desc . '</p>' : '',
			$actions ? '<div class="tva-actions">' . $actions . '</div>' : ''
		);
	}

	/**
	 * Admin URL of a Techvenza screen.
	 *
	 * @param string                   $page Page slug.
	 * @param array<string,string|int> $args Args.
	 */
	public static function url( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Notice after a redirect (?tv_notice=code&tv_n=count).
	 */
	public static function notice(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$code = isset( $_GET['tv_notice'] ) ? sanitize_key( wp_unslash( $_GET['tv_notice'] ) ) : '';
		$msg  = isset( $_GET['tv_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['tv_msg'] ) ) : '';
		// phpcs:enable
		if ( ! $code ) {
			return '';
		}
		$texts = array(
			'done'    => __( 'Saved.', 'techvenza-core' ),
			'moved'   => __( 'Article status updated.', 'techvenza-core' ),
			'decided' => __( 'Application updated and the applicant notified.', 'techvenza-core' ),
			'author'  => __( 'Author updated.', 'techvenza-core' ),
			'roles'   => __( 'Role capabilities saved.', 'techvenza-core' ),
			'error'   => __( 'That did not work.', 'techvenza-core' ),
			'none'    => __( 'Nothing was selected.', 'techvenza-core' ),
			'reset'   => __( 'Roles were reset to the Techvenza defaults.', 'techvenza-core' ),
		);
		$type  = 'error' === $code || 'none' === $code ? 'error' : 'success';
		return sprintf( '<div class="notice notice-%s is-dismissible"><p>%s%s</p></div>', esc_attr( $type ), esc_html( $texts[ $code ] ?? $texts['done'] ), $msg ? ' ' . esc_html( $msg ) : '' );
	}

	/**
	 * Redirect back with a notice.
	 *
	 * @param string $code Notice code.
	 * @param string $msg  Extra message.
	 * @param string $to   Target URL (default referer).
	 */
	public static function back( string $code, string $msg = '', string $to = '' ): void {
		$to = $to ? $to : (string) wp_get_referer();
		$to = $to ? $to : admin_url( 'admin.php?page=' . self::SLUG );
		wp_safe_redirect(
			add_query_arg(
				array_filter(
					array(
						'tv_notice' => $code,
						'tv_msg'    => rawurlencode( $msg ),
					)
				),
				remove_query_arg( array( 'tv_notice', 'tv_msg' ), $to )
			)
		);
		exit;
	}

	/**
	 * Number of submissions waiting for an editor.
	 */
	public static function pending_count(): int {
		$counts = wp_count_posts( 'post' );
		return (int) ( $counts->submitted ?? 0 ) + (int) ( $counts->resubmitted ?? 0 ) + (int) ( $counts->in_review ?? 0 );
	}

	/**
	 * Transition button styles.
	 *
	 * @param string $key Transition.
	 * @return array{0:string,1:string} [button class, icon]
	 */
	public static function action_style( string $key ): array {
		return array(
			'start'      => array( 'button', 'eye' ),
			'approve'    => array( 'button button-primary', 'checkCircle' ),
			'request'    => array( 'button', 'edit' ),
			'reject'     => array( 'button tva-danger', 'xCircle' ),
			'schedule'   => array( 'button', 'calendar' ),
			'publish'    => array( 'button button-primary', 'send' ),
			'unschedule' => array( 'button', 'clock' ),
			'archive'    => array( 'button', 'archive' ),
			'restore'    => array( 'button', 'refresh' ),
			'submit'     => array( 'button', 'send' ),
			'resubmit'   => array( 'button', 'send' ),
		)[ $key ] ?? array( 'button', 'info' );
	}

	/**
	 * Transition form for one article (no-JS friendly; admin.js adds confirmations).
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function transition_form( \WP_Post $post ): string {
		$avail = Workflow::available( $post );
		if ( ! $avail ) {
			return '<p class="muted">' . esc_html__( 'No actions for this status (or for your role).', 'techvenza-core' ) . '</p>';
		}
		$buttons = '';
		$needs   = array();
		foreach ( $avail as $key => $t ) {
			[ $cls, $icon ] = self::action_style( $key );
			$buttons       .= sprintf( '<button type="submit" name="transition" value="%s" class="%s" style="width:100%%;justify-content:center;display:flex;align-items:center;gap:6px"%s>%s %s</button>', esc_attr( $key ), esc_attr( $cls ), ! empty( $t['note'] ) ? ' data-needs-note' : '', R::icon( $icon ), esc_html( $t['label'] ) );
			if ( ! empty( $t['date'] ) ) {
				$needs[] = 'date';
			}
		}
		$date = in_array( 'date', $needs, true )
			? sprintf( '<p><label for="tvDate"><strong>%s</strong></label><br><input type="datetime-local" id="tvDate" name="date" value="%s" min="%s" style="width:100%%"></p>', esc_html__( 'Publication date (for scheduling)', 'techvenza-core' ), esc_attr( 'future' === $post->post_status ? get_the_date( 'Y-m-d\TH:i', $post ) : gmdate( 'Y-m-d\T09:00', (int) strtotime( '+2 days', current_time( 'timestamp' ) ) ) ), esc_attr( current_time( 'Y-m-d\TH:i' ) ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			: '';
		return sprintf(
			'<form method="post" action="%1$s" data-transition-form><input type="hidden" name="action" value="techvenza_transition"><input type="hidden" name="ids[]" value="%2$d">%3$s<p><label for="tvNote"><strong>%4$s</strong></label><br><textarea id="tvNote" name="note" rows="4" style="width:100%%" placeholder="%5$s"></textarea></p>%6$s<div style="display:grid;gap:8px">%7$s</div></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$post->ID,
			wp_nonce_field( 'techvenza_transition', '_wpnonce', true, false ),
			esc_html__( 'Note for the author', 'techvenza-core' ),
			esc_attr__( 'Required when requesting changes or rejecting. Shared with the author.', 'techvenza-core' ),
			$date,
			$buttons
		);
	}
}
