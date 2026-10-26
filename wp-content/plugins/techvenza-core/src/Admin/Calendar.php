<?php
/**
 * Editorial calendar: month grid of published and scheduled articles with drag-to-reschedule,
 * an unscheduled "approved" tray and an accessible agenda list.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Rest\Routes;
use Techvenza\Core\Support\Render as R;
use Techvenza\Core\Workflow\Workflow;

/**
 * Calendar screen + REST reschedule endpoint.
 */
final class Calendar {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * REST: move an article to a date.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/calendar/move',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'move' ),
				'permission_callback' => static fn(): bool => current_user_can( 'tv_publish_posts' ),
				'args'                => array(
					'id'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'date' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^\d{4}-\d{2}-\d{2}$',
					),
				),
			)
		);
	}

	/**
	 * Reschedule (or schedule an approved article) keeping the original time of day.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function move( \WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || ! in_array( $post->post_status, array( 'future', 'approved' ), true ) ) {
			return new \WP_Error( 'tv_invalid', __( 'Only approved or scheduled articles can be moved.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		$time   = 'future' === $post->post_status ? get_the_date( 'H:i', $post ) : '09:00';
		$result = Workflow::transition(
			$post->ID,
			'schedule',
			array( 'date' => (string) $request['date'] . ' ' . $time )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'id'      => $post->ID,
				'date'    => (string) $request['date'],
				/* translators: %s: date */
				'message' => sprintf( __( 'Scheduled for %s.', 'techvenza-core' ), R::date( (string) $request['date'], true ) ),
			)
		);
	}

	/**
	 * Calendar item.
	 *
	 * @param \WP_Post $p    Post.
	 * @param bool     $drag Draggable.
	 */
	private static function item( \WP_Post $p, bool $drag ): string {
		$cat = CategoryMeta::for_post( $p->ID );
		return sprintf(
			'<a class="cal-item %1$s" href="%2$s" style="%3$s" data-id="%4$d" data-title="%5$s"%6$s title="%5$s · %7$s">%8$s</a>',
			esc_attr( 'publish' === $p->post_status ? 'published' : $p->post_status ),
			esc_url( 'publish' === $p->post_status ? (string) get_permalink( $p ) : Admin::url( 'techvenza-review', array( 'id' => $p->ID ) ) ),
			esc_attr( CategoryMeta::style( $cat ) ),
			$p->ID,
			esc_attr( $p->post_title ),
			$drag ? ' draggable="true"' : '',
			esc_attr( wp_strip_all_tags( R::badge( $p->post_status ) ) ),
			esc_html( $p->post_title )
		);
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		$m     = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : current_time( 'Y-m' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$first = strtotime( ( preg_match( '/^\d{4}-\d{2}$/', $m ) ? $m : current_time( 'Y-m' ) ) . '-01' );
		$first = $first ? $first : strtotime( current_time( 'Y-m' ) . '-01' );
		$ym    = gmdate( 'Y-m', (int) $first );
		$days  = (int) gmdate( 't', (int) $first );
		$lead  = ( (int) gmdate( 'N', (int) $first ) ) - 1;
		$start = (int) $first - $lead * DAY_IN_SECONDS;
		$cells = (int) ceil( ( $lead + $days ) / 7 ) * 7;
		$end   = $start + ( $cells - 1 ) * DAY_IN_SECONDS;
		$can   = current_user_can( 'tv_publish_posts' );

		$q      = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future' ),
				'posts_per_page' => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'after'     => gmdate( 'Y-m-d 00:00:00', $start ),
						'before'    => gmdate( 'Y-m-d 23:59:59', $end ),
						'inclusive' => true,
					),
				),
			)
		);
		$by     = array();
		$agenda = array();
		foreach ( $q->posts as $p ) {
			if ( $p instanceof \WP_Post ) {
				$by[ get_the_date( 'Y-m-d', $p ) ][] = $p;
				if ( gmdate( 'Y-m', (int) strtotime( $p->post_date ) ) === $ym ) {
					$agenda[] = $p;
				}
			}
		}
		$today = current_time( 'Y-m-d' );
		$grid  = '';
		foreach ( array( __( 'Mon', 'techvenza-core' ), __( 'Tue', 'techvenza-core' ), __( 'Wed', 'techvenza-core' ), __( 'Thu', 'techvenza-core' ), __( 'Fri', 'techvenza-core' ), __( 'Sat', 'techvenza-core' ), __( 'Sun', 'techvenza-core' ) ) as $dow ) {
			$grid .= '<div class="dow" aria-hidden="true">' . esc_html( $dow ) . '</div>';
		}
		for ( $i = 0; $i < $cells; $i++ ) {
			$ts    = $start + $i * DAY_IN_SECONDS;
			$d     = gmdate( 'Y-m-d', $ts );
			$items = '';
			foreach ( $by[ $d ] ?? array() as $p ) {
				$items .= self::item( $p, $can && 'future' === $p->post_status );
			}
			$grid .= sprintf(
				'<div class="day%s%s" data-date="%s"%s><span class="dn" aria-label="%s">%s</span>%s</div>',
				gmdate( 'Y-m', $ts ) !== $ym ? ' out' : '',
				$d === $today ? ' today' : '',
				esc_attr( $d ),
				$can && $d >= $today ? ' data-drop' : '',
				esc_attr( R::date( $d, true ) ),
				esc_html( gmdate( 'j', $ts ) ),
				$items
			);
		}

		$approved = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'approved',
				'posts_per_page' => 50,
			)
		);
		$tray     = '';
		foreach ( $approved as $p ) {
			$tray .= '<li style="margin:0 0 6px">' . self::item( $p, $can ) . '</li>';
		}

		usort( $agenda, static fn( $a, $b ) => strcmp( $a->post_date, $b->post_date ) );
		$list = '';
		foreach ( $agenda as $p ) {
			$list .= sprintf( '<tr><td>%s</td><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>', esc_html( R::date( get_the_date( 'Y-m-d', $p ) ) . ' ' . get_the_date( 'H:i', $p ) ), esc_url( 'publish' === $p->post_status ? (string) get_permalink( $p ) : Admin::url( 'techvenza-review', array( 'id' => $p->ID ) ) ), esc_html( $p->post_title ), R::badge( $p->post_status ), esc_html( get_the_author_meta( 'display_name', (int) $p->post_author ) ) );
		}

		$prev = gmdate( 'Y-m', (int) strtotime( '-1 month', (int) $first ) );
		$next = gmdate( 'Y-m', (int) strtotime( '+1 month', (int) $first ) );
		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Editorial calendar', 'techvenza-core' ), '' ),
			),
			date_i18n( 'F Y', (int) $first ),
			$can ? esc_html__( 'Drag a scheduled or approved article onto a day to (re)schedule it. Times of day are kept.', 'techvenza-core' ) : esc_html__( 'Scheduling requires the Managing Editor role.', 'techvenza-core' ),
			sprintf(
				'<a class="button" href="%s" aria-label="%s">‹</a><a class="button" href="%s">%s</a><a class="button" href="%s" aria-label="%s">›</a>',
				esc_url( Admin::url( 'techvenza-calendar', array( 'm' => $prev ) ) ),
				esc_attr__( 'Previous month', 'techvenza-core' ),
				esc_url( Admin::url( 'techvenza-calendar' ) ),
				esc_html__( 'Today', 'techvenza-core' ),
				esc_url( Admin::url( 'techvenza-calendar', array( 'm' => $next ) ) ),
				esc_attr__( 'Next month', 'techvenza-core' )
			)
		) . sprintf(
			'<div class="review-grid"><div><div class="cal" data-calendar role="group" aria-label="%1$s">%2$s</div><p class="cal-legend"><span>%3$s</span><span>%4$s</span></p><div role="status" aria-live="polite" class="sr-only" data-cal-status></div></div>
			<aside><section class="panel"><h2 class="panel-title">%5$s</h2>%6$s</section></aside></div><section class="panel"><h2 class="panel-title">%7$s</h2>%8$s</section>',
			esc_attr( date_i18n( 'F Y', (int) $first ) ),
			$grid,
			esc_html__( 'Grey items are already published.', 'techvenza-core' ),
			esc_html__( 'Keyboard: open an item and use “Schedule” on its review screen.', 'techvenza-core' ),
			esc_html__( 'Approved, not scheduled', 'techvenza-core' ),
			$tray ? '<ul style="margin:0" data-tray>' . $tray . '</ul>' : '<p class="muted">' . esc_html__( 'Nothing waiting.', 'techvenza-core' ) . '</p>',
			esc_html__( 'This month', 'techvenza-core' ),
			$list ? '<div class="table-wrap"><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Date', 'techvenza-core' ) . '</th><th scope="col">' . esc_html__( 'Article', 'techvenza-core' ) . '</th><th scope="col">' . esc_html__( 'Status', 'techvenza-core' ) . '</th><th scope="col">' . esc_html__( 'Author', 'techvenza-core' ) . '</th></tr></thead><tbody>' . $list . '</tbody></table></div>' : '<p class="muted">' . esc_html__( 'Nothing published or scheduled this month.', 'techvenza-core' ) . '</p>'
		);
	}
}
