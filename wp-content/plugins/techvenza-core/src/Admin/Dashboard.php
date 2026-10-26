<?php
/**
 * Editorial dashboard: pipeline counts, publishing trend, notices, activity and popular content.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Charts;
use Techvenza\Core\Support\Render as R;
use Techvenza\Core\Workflow\Statuses;
use Techvenza\Core\Workflow\Workflow;

/**
 * Dashboard screen.
 */
final class Dashboard {

	/**
	 * Recent workflow events across articles.
	 *
	 * @param int $limit Max events.
	 * @return array<int,array<string,mixed>>
	 */
	public static function activity( int $limit = 8 ): array {
		$q      = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 40,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_key'       => 'tv_timeline', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		$events = array();
		foreach ( $q->posts as $p ) {
			if ( ! $p instanceof \WP_Post ) {
				continue;
			}
			foreach ( Workflow::timeline( $p->ID ) as $e ) {
				$e['post']  = $p->ID;
				$e['title'] = $p->post_title;
				$events[]   = $e;
			}
		}
		usort( $events, static fn( $a, $b ) => strcmp( (string) ( $b['at'] ?? '' ), (string) ( $a['at'] ?? '' ) ) );
		return array_slice( $events, 0, $limit );
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		$c       = wp_count_posts( 'post' );
		$n       = static fn( string $s ): int => (int) ( $c->$s ?? 0 );
		$pending = $n( 'submitted' ) + $n( 'resubmitted' ) + $n( 'in_review' );
		$apps    = Applications::pending_count();

		// Articles published per week, last 12 weeks.
		$weeks  = array();
		$labels = array();
		$start  = strtotime( 'monday this week', current_time( 'timestamp' ) ) - 11 * WEEK_IN_SECONDS; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		for ( $i = 0; $i < 12; $i++ ) {
			$from     = $start + $i * WEEK_IN_SECONDS;
			$labels[] = 'W' . gmdate( 'W', $from );
			$weeks[]  = count(
				Query::ids(
					array(
						'posts_per_page' => 100,
						'date_query'     => array(
							array(
								'after'     => gmdate( 'Y-m-d 00:00:00', $from ),
								'before'    => gmdate( 'Y-m-d 23:59:59', $from + 6 * DAY_IN_SECONDS ),
								'inclusive' => true,
							),
						),
					)
				)
			);
		}

		$notices = array();
		if ( $pending ) {
			/* translators: 1: count, 2: days */
			$notices[] = array( 'clock', '#F3F0FF', '#6D28D9', sprintf( _n( '%1$d submission awaiting review', '%1$d submissions awaiting review', $pending, 'techvenza-core' ), $pending ), sprintf( __( 'Target first response: %d working days', 'techvenza-core' ), (int) Options::get( 'review_sla_days', 5 ) ), Admin::url( 'techvenza-review' ) );
		}
		if ( $apps ) {
			/* translators: %d: count */
			$notices[] = array( 'users', '#EFF6FF', '#1D4ED8', sprintf( _n( '%d author application pending', '%d author applications pending', $apps, 'techvenza-core' ), $apps ), __( 'Review applications', 'techvenza-core' ), Admin::url( 'techvenza-applications' ) );
		}
		if ( ! Options::get( 'smtp_host' ) && ! defined( 'TECHVENZA_SMTP_HOST' ) ) {
			$notices[] = array( 'mail', '#FEF3C7', '#92400E', __( 'Outgoing email uses the server default', 'techvenza-core' ), __( 'Configure SMTP so notifications and verification emails are delivered reliably.', 'techvenza-core' ), admin_url( 'admin.php?page=techvenza-settings&tab=email' ) );
		}
		if ( $n( 'approved' ) ) {
			/* translators: %d: count */
			$notices[] = array( 'calendar', '#FFFBEB', '#B45309', sprintf( _n( '%d approved article is not scheduled', '%d approved articles are not scheduled', $n( 'approved' ), 'techvenza-core' ), $n( 'approved' ) ), __( 'Open the editorial calendar', 'techvenza-core' ), Admin::url( 'techvenza-calendar' ) );
		}
		$notice_html = '';
		foreach ( $notices as [ $icon, $bg, $fg, $title, $sub, $url ] ) {
			$notice_html .= sprintf( '<li><span class="act-ico" style="background:%s;color:%s">%s</span><span><b style="color:var(--ink)">%s</b><span class="when"><a href="%s">%s</a></span></span></li>', esc_attr( $bg ), esc_attr( $fg ), R::icon( $icon ), esc_html( $title ), esc_url( $url ), esc_html( $sub ) );
		}
		if ( ! $notice_html ) {
			$notice_html = '<li><span class="act-ico">' . R::icon( 'checkCircle' ) . '</span><span><b style="color:var(--ink)">' . esc_html__( 'All clear', 'techvenza-core' ) . '</b><span class="when">' . esc_html__( 'Nothing needs attention right now.', 'techvenza-core' ) . '</span></span></li>';
		}

		$icons    = array(
			'submit'   => 'send',
			'resubmit' => 'send',
			'start'    => 'eye',
			'approve'  => 'checkCircle',
			'request'  => 'edit',
			'reject'   => 'xCircle',
			'schedule' => 'calendar',
			'publish'  => 'send',
			'archive'  => 'archive',
		);
		$labels_s = Statuses::labels();
		$act      = '';
		foreach ( self::activity( 7 ) as $e ) {
			$who  = get_userdata( (int) ( $e['user'] ?? 0 ) );
			$act .= sprintf(
				'<li><span class="act-ico">%s</span><span><b>%s</b> — %s: <a href="%s">“%s”</a><span class="when">%s</span></span></li>',
				R::icon( $icons[ $e['action'] ?? '' ] ?? 'info' ),
				esc_html( $who ? $who->display_name : __( 'System', 'techvenza-core' ) ),
				esc_html( strtolower( $labels_s[ $e['to'] ?? '' ] ?? (string) ( $e['to'] ?? '' ) ) ),
				esc_url( Admin::url( 'techvenza-review', array( 'id' => (int) $e['post'] ) ) ),
				esc_html( (string) $e['title'] ),
				esc_html( R::date( substr( (string) ( $e['at'] ?? '' ), 0, 10 ) ) )
			);
		}

		$pop  = Query::trending( 5, '30d', array(), array(), false );
		$rows = array_map(
			static function ( $p ) {
				$cat = CategoryMeta::for_post( $p->ID );
				return array( wp_html_excerpt( get_the_title( $p ), 24, '…' ), Query::views( $p->ID, '30d' ), $cat['color'] ?? '#2563EB' );
			},
			$pop
		);
		$cats = array();
		foreach ( CategoryMeta::all() as $cat ) {
			$ids = Query::ids(
				array(
					'cat'            => $cat['id'],
					'posts_per_page' => 200,
				)
			);
			$sum = array_sum( array_map( static fn( $id ) => Query::views( (int) $id, '30d' ), $ids ) );
			if ( $sum ) {
				$cats[] = array( $cat['short'], $sum, $cat['color'] );
			}
		}
		usort( $cats, static fn( $a, $b ) => $b[1] <=> $a[1] );

		$stat = static fn( string $url, string $label, int $value, string $color, string $note ): string => sprintf( '<a class="stat" href="%s" style="--c:%s"><span class="k"><i></i>%s</span><span class="v">%d</span><span class="d">%s</span></a>', esc_url( $url ), esc_attr( $color ), esc_html( $label ), $value, esc_html( $note ) );

		return Admin::head(
			array( array( __( 'Editorial', 'techvenza-core' ), '' ) ),
			__( 'Editorial dashboard', 'techvenza-core' ),
			esc_html__( 'Content pipeline and performance.', 'techvenza-core' ),
			/* translators: %d: count */
			sprintf( '<a class="button button-primary" href="%s">%s %s</a>', esc_url( Admin::url( 'techvenza-review' ) ), R::icon( 'checkCircle' ), esc_html( sprintf( __( 'Review queue (%d)', 'techvenza-core' ), $pending ) ) )
		)
		. '<div class="stats">'
		. $stat( admin_url( 'edit.php?post_status=publish' ), __( 'Published', 'techvenza-core' ), $n( 'publish' ), 'var(--emerald)', __( 'Live articles', 'techvenza-core' ) )
		. $stat( admin_url( 'edit.php?post_status=draft' ), __( 'Drafts', 'techvenza-core' ), $n( 'draft' ), '#94A3B8', __( 'Not yet submitted', 'techvenza-core' ) )
		/* translators: %d: count */
		. $stat( Admin::url( 'techvenza-review' ), __( 'Pending submissions', 'techvenza-core' ), $pending, 'var(--violet)', sprintf( __( '%d awaiting author changes', 'techvenza-core' ), $n( 'changes_requested' ) ) )
		. $stat( Admin::url( 'techvenza-applications' ), __( 'Author applications', 'techvenza-core' ), $apps, 'var(--blue)', __( 'Pending decision', 'techvenza-core' ) )
		/* translators: %d: count */
		. $stat( Admin::url( 'techvenza-calendar' ), __( 'Scheduled', 'techvenza-core' ), $n( 'future' ), 'var(--amber)', sprintf( __( '%d approved, unscheduled', 'techvenza-core' ), $n( 'approved' ) ) )
		. '</div>'
		. sprintf(
			'<div class="dash-grid"><section class="panel"><h2 class="panel-title">%s</h2>%s</section><section class="panel"><h2 class="panel-title">%s</h2><ul class="activity">%s</ul></section></div>',
			esc_html__( 'Publishing trend · articles per week', 'techvenza-core' ),
			Charts::bars( $weeks, $labels, __( 'Articles published per week', 'techvenza-core' ), array( '#4F46E5' ) ),
			esc_html__( 'Needs attention', 'techvenza-core' ),
			$notice_html
		)
		. sprintf(
			'<div class="dash-grid"><section class="panel"><h2 class="panel-title">%s</h2>%s</section><section class="panel"><h2 class="panel-title">%s</h2>%s<h3 class="panel-title" style="margin-top:20px">%s</h3>%s</section></div>',
			esc_html__( 'Recent editorial activity', 'techvenza-core' ),
			$act ? '<ul class="activity">' . $act . '</ul>' : R::empty_state( 'clock', __( 'No activity yet', 'techvenza-core' ), __( 'Workflow changes appear here.', 'techvenza-core' ) ),
			esc_html__( 'Popular articles · 30 days', 'techvenza-core' ),
			Charts::hbars( $rows ),
			esc_html__( 'Top categories by views', 'techvenza-core' ),
			Charts::hbars( array_slice( $cats, 0, 6 ) )
		);
	}
}
