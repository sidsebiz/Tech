<?php
/**
 * Author portal views: dashboard, my articles, submission details, author profile, analytics,
 * notifications and account settings. Writing happens in the block editor (wp-admin), which
 * carries the same checklist and workflow panel.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Portal;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Database\Notifications;
use Techvenza\Core\Database\Views;
use Techvenza\Core\Support\Charts;
use Techvenza\Core\Support\Render as R;
use Techvenza\Core\Workflow\Statuses;
use Techvenza\Core\Workflow\Workflow;

/**
 * Author portal.
 */
final class Author {

	/**
	 * Statuses an author can see in "My articles".
	 */
	private const STATUSES = array( 'draft', 'submitted', 'in_review', 'changes_requested', 'resubmitted', 'approved', 'future', 'publish', 'rejected', 'archived' );

	/**
	 * Render the portal for the current user.
	 */
	public static function render(): string {
		if ( ! current_user_can( 'tv_use_portal' ) ) {
			return App::denied( __( 'The author portal is for accepted contributors. Apply to write for us and an editor will review your application.', 'techvenza-core' ) );
		}
		$view = App::view( array( 'dash', 'articles', 'submission', 'profile', 'analytics', 'notifications', 'settings' ), 'dash' );
		switch ( $view ) {
			case 'articles':
				$html = self::articles();
				break;
			case 'submission':
				$html = self::submission();
				$view = 'articles';
				break;
			case 'profile':
				$html = self::profile();
				break;
			case 'analytics':
				$html = self::analytics();
				break;
			case 'notifications':
				$html = self::notifications();
				break;
			case 'settings':
				$html = self::settings();
				break;
			default:
				$html = self::dashboard();
		}
		return App::shell( 'portal', $view, $html );
	}

	/**
	 * Current user's articles.
	 *
	 * @param array<string,mixed> $args Extra query args.
	 * @return array<int,\WP_Post>
	 */
	private static function mine( array $args = array() ): array {
		$q = new \WP_Query(
			array_merge(
				array(
					'post_type'      => 'post',
					'author'         => get_current_user_id(),
					'post_status'    => self::STATUSES,
					'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				),
				$args
			)
		);
		return array_values( array_filter( $q->posts, static fn( $p ) => $p instanceof \WP_Post ) );
	}

	/**
	 * URL of the submission view.
	 *
	 * @param int $id Post ID.
	 */
	private static function submission_url( int $id ): string {
		return App::url( 'author-portal', 'submission', array( 'id' => $id ) );
	}

	/**
	 * Primary link for an article in lists.
	 *
	 * @param \WP_Post $p Post.
	 */
	private static function link( \WP_Post $p ): string {
		return 'publish' === $p->post_status ? (string) get_permalink( $p ) : self::submission_url( $p->ID );
	}

	/**
	 * Whether the author may edit the article now.
	 *
	 * @param \WP_Post $p Post.
	 */
	private static function editable( \WP_Post $p ): bool {
		return in_array( $p->post_status, Statuses::AUTHOR_EDITABLE, true ) && current_user_can( 'edit_post', $p->ID );
	}

	/**
	 * Dashboard.
	 */
	private static function dashboard(): string {
		$user  = wp_get_current_user();
		$mine  = self::mine();
		$count = static fn( array $s ): int => count( array_filter( $mine, static fn( $p ) => in_array( $p->post_status, $s, true ) ) );
		$pub   = array_values( array_filter( $mine, static fn( $p ) => 'publish' === $p->post_status ) );

		// Weekly views for the last 12 weeks (from the views table).
		$series = Views::series( 84, wp_list_pluck( $pub, 'ID' ) );
		$weeks  = array_chunk( array_values( $series ), 7 );
		$keys   = array_chunk( array_keys( $series ), 7 );
		$data   = array_map( 'array_sum', $weeks );
		$labels = array_map( static fn( $k ) => 'W' . gmdate( 'W', (int) strtotime( (string) end( $k ) ) ), $keys );

		$feedback = '';
		foreach ( $mine as $p ) {
			$fb = array_reverse( Workflow::feedback( $p->ID ) );
			foreach ( $fb as $f ) {
				if ( (int) ( $f['user'] ?? 0 ) === $user->ID ) {
					continue;
				}
				$who       = get_userdata( (int) ( $f['user'] ?? 0 ) );
				$feedback .= sprintf(
					'<div class="feedback mb-4"><div class="who">%s %s · %s</div><p>%s</p><a class="link" style="display:inline-block;font-size:var(--t-meta);margin-top:8px" href="%s">%s</a></div>',
					R::icon( 'msg' ),
					esc_html( $who ? $who->display_name : __( 'Editor', 'techvenza-core' ) ),
					esc_html( R::date( substr( (string) ( $f['at'] ?? '' ), 0, 10 ) ) ),
					esc_html( (string) $f['note'] ),
					esc_url( self::submission_url( $p->ID ) ),
					/* translators: %s: title */
					esc_html( sprintf( __( 'Open “%s”', 'techvenza-core' ), get_the_title( $p ) ) )
				);
				break;
			}
			if ( substr_count( $feedback, 'class="feedback' ) >= 2 ) {
				break;
			}
		}
		$recent = '';
		foreach ( array_slice( $mine, 0, 5 ) as $p ) {
			$recent .= sprintf(
				'<div class="row between" style="padding:12px 0;border-top:1px solid var(--border);flex-wrap:nowrap"><div style="min-width:0"><a href="%s" style="font-weight:600;color:var(--ink);font-size:var(--t-small)">%s</a><div class="meta">%s</div></div>%s</div>',
				esc_url( self::link( $p ) ),
				esc_html( get_the_title( $p ) ? get_the_title( $p ) : __( '(untitled)', 'techvenza-core' ) ),
				/* translators: %s: date */
				esc_html( sprintf( __( 'Updated %s', 'techvenza-core' ), R::date( get_the_modified_date( 'Y-m-d', $p ) ) ) ),
				R::badge( $p->post_status )
			);
		}
		$icons    = array(
			'feedback'  => 'edit',
			'revision'  => 'edit',
			'status'    => 'eye',
			'approved'  => 'checkCircle',
			'published' => 'send',
			'account'   => 'user',
		);
		$activity = '';
		foreach ( Notifications::list( $user->ID, 'all', 4 ) as $n ) {
			$activity .= sprintf( '<li><span class="act-ico">%s</span><span><a href="%s" style="color:var(--ink);font-weight:500">%s</a><span class="when">%s</span></span></li>', R::icon( $icons[ $n['type'] ] ?? 'bell' ), esc_url( $n['link'] ? $n['link'] : App::url( 'author-portal', 'notifications' ) ), esc_html( $n['title'] ), esc_html( R::date( substr( $n['at'], 0, 10 ) ) ) );
		}

		$changes = $count( array( 'changes_requested' ) );
		$first   = strtok( $user->display_name, ' ' );
		return App::head(
			array(
				array( __( 'Portal', 'techvenza-core' ), App::url( 'author-portal' ) ),
				array( __( 'Dashboard', 'techvenza-core' ), '' ),
			),
			/* translators: %s: first name */
			sprintf( __( 'Good to see you, %s', 'techvenza-core' ), $first ? $first : $user->display_name ),
			esc_html__( 'Your drafts, submissions and recent performance at a glance.', 'techvenza-core' ),
			sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s %s</a>', esc_url( admin_url( 'post-new.php' ) ), R::icon( 'plus' ), esc_html__( 'New article', 'techvenza-core' ) )
		)
		. '<div class="stats">'
		. App::stat( __( 'Total articles', 'techvenza-core' ), (string) count( $mine ), 'var(--ink)', esc_html__( 'Across all statuses', 'techvenza-core' ) )
		. App::stat( __( 'Drafts', 'techvenza-core' ), (string) $count( array( 'draft' ) ), '#94A3B8', sprintf( '<a class="link" href="%s">%s</a>', esc_url( App::url( 'author-portal', 'articles', array( 'status' => 'draft' ) ) ), esc_html__( 'View drafts', 'techvenza-core' ) ) )
		/* translators: %d: number of articles that need changes */
		. App::stat( __( 'In review', 'techvenza-core' ), (string) $count( array( 'submitted', 'in_review', 'resubmitted', 'changes_requested' ) ), 'var(--violet)', esc_html( sprintf( _n( '%d needs your changes', '%d need your changes', $changes, 'techvenza-core' ), $changes ) ) )
		/* translators: %d: scheduled/approved count */
		. App::stat( __( 'Published', 'techvenza-core' ), (string) count( $pub ), 'var(--emerald)', esc_html( sprintf( __( '%d scheduled/approved', 'techvenza-core' ), $count( array( 'future', 'approved' ) ) ) ) )
		. '</div>'
		. sprintf(
			'<div class="dash-grid"><section class="panel"><h2 class="panel-title">%s</h2>%s</section><section class="panel"><h2 class="panel-title">%s</h2>%s</section></div>',
			esc_html__( 'Views over the last 12 weeks', 'techvenza-core' ),
			array_sum( $data ) ? Charts::line(
				array(
					array(
						'name'  => __( 'Views', 'techvenza-core' ),
						'color' => '#4F46E5',
						'data'  => $data,
					),
				),
				$labels,
				__( 'Weekly article views', 'techvenza-core' )
			) : R::empty_state( 'chart', __( 'No views yet', 'techvenza-core' ), __( 'Views appear once your first article is published.', 'techvenza-core' ) ),
			esc_html__( 'Editorial feedback', 'techvenza-core' ),
			$feedback ? $feedback : R::empty_state( 'msg', __( 'No feedback yet', 'techvenza-core' ), __( 'Editor comments will appear here.', 'techvenza-core' ) )
		)
		. sprintf(
			'<div class="dash-grid"><section class="panel"><h2 class="panel-title">%s <a class="sec-link" href="%s" style="font-size:var(--t-meta)">%s %s</a></h2>%s</section><section class="panel"><h2 class="panel-title">%s</h2>%s</section></div>',
			esc_html__( 'Recent articles', 'techvenza-core' ),
			esc_url( App::url( 'author-portal', 'articles' ) ),
			esc_html__( 'All articles', 'techvenza-core' ),
			R::icon( 'arrowRight' ),
			$recent ? $recent : R::empty_state( 'file', __( 'No articles yet', 'techvenza-core' ), __( 'Start your first draft.', 'techvenza-core' ) ),
			esc_html__( 'Recent activity', 'techvenza-core' ),
			$activity ? '<ul class="activity">' . $activity . '</ul>' : R::empty_state( 'bell', __( 'Nothing new', 'techvenza-core' ), __( 'Updates about your submissions appear here.', 'techvenza-core' ) )
		)
		. sprintf(
			'<div class="cta-band mt-5" style="padding:var(--s6)"><div class="row between"><div><h2 class="h3">%s</h2><p class="mt-4" style="font-size:var(--t-small)">%s</p></div><a class="btn btn-primary" href="%s">%s %s</a></div></div>',
			esc_html__( 'Have an idea worth writing about?', 'techvenza-core' ),
			esc_html__( 'Start a draft — you can save and return to it any time.', 'techvenza-core' ),
			esc_url( admin_url( 'post-new.php' ) ),
			R::icon( 'pen' ),
			esc_html__( 'Start writing', 'techvenza-core' )
		);
	}

	/**
	 * My articles (filterable, sortable table).
	 */
	private static function articles(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$sort   = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'modified';
		$dir    = isset( $_GET['dir'] ) && 'asc' === $_GET['dir'] ? 'asc' : 'desc';
		$page   = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
		// phpcs:enable
		$status = in_array( $status, self::STATUSES, true ) ? $status : '';
		$rows   = self::mine( array_filter( array( 's' => $q ) ) );
		if ( $status ) {
			$rows = array_values( array_filter( $rows, static fn( $p ) => $p->post_status === $status ) );
		}
		$order = array_flip( self::STATUSES );
		usort(
			$rows,
			static function ( $a, $b ) use ( $sort, $dir, $order ) {
				$va = match ( $sort ) {
					'title' => strtolower( $a->post_title ),
					'status' => $order[ $a->post_status ] ?? 99,
					'category' => CategoryMeta::for_post( $a->ID )['name'] ?? '',
					default => $a->post_modified,
				};
				$vb = match ( $sort ) {
					'title' => strtolower( $b->post_title ),
					'status' => $order[ $b->post_status ] ?? 99,
					'category' => CategoryMeta::for_post( $b->ID )['name'] ?? '',
					default => $b->post_modified,
				};
				return 'asc' === $dir ? $va <=> $vb : $vb <=> $va;
			}
		);
		$per   = 8;
		$total = count( $rows );
		$slice = array_slice( $rows, ( $page - 1 ) * $per, $per );
		$base  = array_filter(
			array(
				'view'   => 'articles',
				'q'      => $q,
				'status' => $status,
			)
		);
		$th    = static function ( string $key, string $label ) use ( $sort, $dir, $base ): string {
			$on   = $sort === $key;
			$next = $on && 'desc' === $dir ? 'asc' : 'desc';
			return sprintf(
				'<th scope="col" aria-sort="%s"><a class="th-sort" href="%s">%s %s</a></th>',
				$on ? ( 'asc' === $dir ? 'ascending' : 'descending' ) : 'none',
				esc_url(
					add_query_arg(
						array_merge(
							$base,
							array(
								'sort' => $key,
								'dir'  => $next,
							)
						),
						R::page_url( 'author-portal' )
					)
				),
				esc_html( $label ),
				R::icon( 'chevDown' )
			);
		};
		$body  = '';
		foreach ( $slice as $p ) {
			$editor = (int) get_post_meta( $p->ID, 'tv_assigned_editor', true );
			$ed     = $editor ? get_userdata( $editor ) : null;
			$acts   = '';
			if ( self::editable( $p ) ) {
				/* translators: %s: title */
				$acts .= sprintf( '<a class="icon-btn" href="%s" aria-label="%s" title="%s">%s</a>', esc_url( (string) get_edit_post_link( $p->ID, 'raw' ) ), esc_attr( sprintf( __( 'Edit %s', 'techvenza-core' ), $p->post_title ) ), esc_attr__( 'Edit', 'techvenza-core' ), R::icon( 'edit' ) );
			}
			/* translators: %s: title */
			$acts .= sprintf( '<a class="icon-btn" href="%s" aria-label="%s" title="%s">%s</a>', esc_url( 'publish' === $p->post_status ? (string) get_permalink( $p ) : (string) get_preview_post_link( $p ) ), esc_attr( sprintf( __( 'Preview %s', 'techvenza-core' ), $p->post_title ) ), esc_attr__( 'Preview', 'techvenza-core' ), R::icon( 'eye' ) );
			/* translators: %s: title */
			$acts .= sprintf( '<a class="icon-btn" href="%s" aria-label="%s" title="%s">%s</a>', esc_url( self::submission_url( $p->ID ) ), esc_attr( sprintf( __( 'Submission details for %s', 'techvenza-core' ), $p->post_title ) ), esc_attr__( 'Details', 'techvenza-core' ), R::icon( 'info' ) );
			if ( 'draft' === $p->post_status && current_user_can( 'delete_post', $p->ID ) ) {
				/* translators: %s: title */
				$acts .= sprintf( '<button class="icon-btn" type="button" data-action="deleteDraft" data-id="%d" data-title="%s" aria-label="%s" title="%s">%s</button>', $p->ID, esc_attr( $p->post_title ), esc_attr( sprintf( __( 'Delete draft %s', 'techvenza-core' ), $p->post_title ) ), esc_attr__( 'Delete', 'techvenza-core' ), R::icon( 'trash' ) );
			}
			$body .= sprintf(
				'<tr data-row="%1$d"><td class="t-title" data-label="%2$s"><a href="%3$s">%4$s</a></td><td data-label="%5$s">%6$s</td><td data-label="%7$s">%8$s</td><td data-label="%9$s">%10$s</td><td data-label="%11$s">%12$s</td><td class="actions-cell"><div class="actions">%13$s</div></td></tr>',
				$p->ID,
				esc_attr__( 'Title', 'techvenza-core' ),
				esc_url( self::link( $p ) ),
				esc_html( $p->post_title ? $p->post_title : __( '(untitled)', 'techvenza-core' ) ),
				esc_attr__( 'Category', 'techvenza-core' ),
				R::chip( CategoryMeta::for_post( $p->ID ), false ),
				esc_attr__( 'Last updated', 'techvenza-core' ),
				esc_html( R::date( get_the_modified_date( 'Y-m-d', $p ) ) ),
				esc_attr__( 'Status', 'techvenza-core' ),
				R::badge( $p->post_status ),
				esc_attr__( 'Editor', 'techvenza-core' ),
				esc_html( $ed ? $ed->display_name : __( 'Unassigned', 'techvenza-core' ) ),
				$acts
			);
		}
		if ( ! $body ) {
			$body = '<tr><td colspan="6" style="padding:0">' . R::empty_state( 'file', __( 'No articles match', 'techvenza-core' ), $q || $status ? __( 'Try clearing the search or status filter.', 'techvenza-core' ) : __( 'Start your first draft.', 'techvenza-core' ), sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s</a>', esc_url( admin_url( 'post-new.php' ) ), esc_html__( 'New article', 'techvenza-core' ) ) ) . '</td></tr>';
		}
		$opts = '<option value="">' . esc_html__( 'All statuses', 'techvenza-core' ) . '</option>';
		foreach ( self::STATUSES as $s ) {
			$opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $s ), selected( $status, $s, false ), esc_html( wp_strip_all_tags( R::badge( $s ) ) ) );
		}
		$pages = (int) ceil( $total / $per );
		$pager = '';
		if ( $pages > 1 ) {
			$pager = '<nav class="pagination" aria-label="' . esc_attr__( 'Pagination', 'techvenza-core' ) . '">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$pager .= $i === $page ? sprintf( '<span class="pg" aria-current="page">%d</span>', $i ) : sprintf(
					'<a class="pg" href="%s">%d</a>',
					esc_url(
						add_query_arg(
							array_merge(
								$base,
								array(
									'sort' => $sort,
									'dir'  => $dir,
									'pg'   => $i,
								)
							),
							R::page_url( 'author-portal' )
						)
					),
					$i
				);
			}
			$pager .= '</nav>';
		}
		return App::head(
			array(
				array( __( 'Portal', 'techvenza-core' ), App::url( 'author-portal' ) ),
				array( __( 'My articles', 'techvenza-core' ), '' ),
			),
			__( 'My articles', 'techvenza-core' ),
			esc_html__( 'Search, filter and manage everything you have written.', 'techvenza-core' ),
			sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s %s</a>', esc_url( admin_url( 'post-new.php' ) ), R::icon( 'plus' ), esc_html__( 'New article', 'techvenza-core' ) )
		) . sprintf(
			'<div class="table-wrap"><form class="table-tools" method="get" action="%1$s" role="search"><input type="hidden" name="view" value="articles"><div class="input-icon">%2$s<label class="sr-only" for="maQ">%3$s</label><input class="input input-sm" id="maQ" name="q" type="search" value="%4$s" placeholder="%5$s"></div><label class="sr-only" for="maStatus">%6$s</label><select class="select" id="maStatus" name="status" data-autosubmit>%7$s</select><button class="btn btn-secondary btn-sm" type="submit">%8$s</button></form><div class="table-scroll"><table class="dt cards"><caption class="sr-only">%9$s</caption><thead><tr>%10$s%11$s%12$s%13$s<th scope="col">%14$s</th><th scope="col"><span class="sr-only">%15$s</span></th></tr></thead><tbody>%16$s</tbody></table></div><div class="table-foot"><span>%17$s</span>%18$s</div></div>',
			esc_url( R::page_url( 'author-portal' ) ),
			R::icon( 'search' ),
			esc_html__( 'Search my articles', 'techvenza-core' ),
			esc_attr( $q ),
			esc_attr__( 'Search titles…', 'techvenza-core' ),
			esc_html__( 'Status', 'techvenza-core' ),
			$opts,
			esc_html__( 'Filter', 'techvenza-core' ),
			esc_html__( 'My articles', 'techvenza-core' ),
			$th( 'title', __( 'Title', 'techvenza-core' ) ),
			$th( 'category', __( 'Category', 'techvenza-core' ) ),
			$th( 'modified', __( 'Last updated', 'techvenza-core' ) ),
			$th( 'status', __( 'Status', 'techvenza-core' ) ),
			esc_html__( 'Editor', 'techvenza-core' ),
			esc_html__( 'Actions', 'techvenza-core' ),
			$body,
			/* translators: 1: first row, 2: last row, 3: total */
			esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d', 'techvenza-core' ), $slice ? ( $page - 1 ) * $per + 1 : 0, ( $page - 1 ) * $per + count( $slice ), $total ) ),
			$pager
		);
	}

	/**
	 * Workflow timeline markup.
	 *
	 * @param \WP_Post $p Post.
	 */
	public static function timeline( \WP_Post $p ): string {
		$colors = array(
			'draft'             => '#94A3B8',
			'submitted'         => '#2563EB',
			'in_review'         => '#7C3AED',
			'changes_requested' => '#EA580C',
			'resubmitted'       => '#0E7490',
			'approved'          => '#059669',
			'future'            => '#F59E0B',
			'publish'           => '#16A34A',
			'rejected'          => '#E11D48',
			'archived'          => '#64748B',
		);
		$labels = Statuses::labels();
		$items  = sprintf( '<li style="--c:%s"><b style="color:var(--ink)">%s</b><div class="when">%s</div></li>', $colors['draft'], esc_html__( 'Draft created', 'techvenza-core' ), esc_html( R::date( get_the_date( 'Y-m-d', $p ) ) ) );
		foreach ( Workflow::timeline( $p->ID ) as $e ) {
			$to   = (string) ( $e['to'] ?? '' );
			$who  = get_userdata( (int) ( $e['user'] ?? 0 ) );
			$text = $labels[ $to ] ?? ucfirst( str_replace( '_', ' ', $to ) );
			if ( 'future' === $to && ! empty( $e['date'] ) ) {
				/* translators: %s: date */
				$text = sprintf( __( 'Scheduled for %s', 'techvenza-core' ), R::date( substr( (string) $e['date'], 0, 10 ) ) );
			}
			$items .= sprintf(
				'<li style="--c:%s"><b style="color:var(--ink)">%s</b>%s<div class="when">%s</div></li>',
				esc_attr( $colors[ $to ] ?? '#94A3B8' ),
				esc_html( $text ),
				/* translators: %s: person */
				$who ? ' <span class="muted" style="font-size:var(--t-meta)">' . esc_html( sprintf( __( 'by %s', 'techvenza-core' ), $who->display_name ) ) . '</span>' : '',
				esc_html( R::date( substr( (string) ( $e['at'] ?? '' ), 0, 10 ) ) )
			);
		}
		return '<ol class="timeline">' . $items . '</ol>';
	}

	/**
	 * Submission details.
	 */
	private static function submission(): string {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$p  = get_post( $id );
		if ( ! $p || 'post' !== $p->post_type || ( get_current_user_id() !== (int) $p->post_author && ! current_user_can( 'tv_review_posts' ) ) ) {
			return R::empty_state( 'file', __( 'Article not found', 'techvenza-core' ), __( 'It may have been deleted, or it belongs to someone else.', 'techvenza-core' ), sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s</a>', esc_url( App::url( 'author-portal', 'articles' ) ), esc_html__( 'Back to my articles', 'techvenza-core' ) ) );
		}
		$fb      = Workflow::feedback( $p->ID );
		$fb_html = '';
		foreach ( $fb as $f ) {
			$mine     = (int) ( $f['user'] ?? 0 ) === (int) $p->post_author;
			$who      = get_userdata( (int) ( $f['user'] ?? 0 ) );
			$fb_html .= sprintf(
				'<div class="feedback mb-4"%s><div class="who"%s>%s %s · %s</div><p>%s</p></div>',
				$mine ? ' style="background:var(--soft-blue);border-color:#BFDBFE;color:#1E3A8A"' : '',
				$mine ? ' style="color:#1E3A8A"' : '',
				R::icon( 'msg' ),
				esc_html( $mine ? __( 'You', 'techvenza-core' ) : ( $who ? $who->display_name : __( 'Editor', 'techvenza-core' ) ) ),
				esc_html( R::date( substr( (string) ( $f['at'] ?? '' ), 0, 10 ) ) ),
				esc_html( (string) $f['note'] )
			);
		}
		$actions = R::badge( $p->post_status ) . sprintf( '<a class="btn btn-secondary btn-sm" href="%s">%s %s</a>', esc_url( 'publish' === $p->post_status ? (string) get_permalink( $p ) : (string) get_preview_post_link( $p ) ), R::icon( 'eye' ), esc_html__( 'Preview', 'techvenza-core' ) );
		if ( self::editable( $p ) ) {
			$actions .= sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s %s</a>', esc_url( (string) get_edit_post_link( $p->ID, 'raw' ) ), R::icon( 'edit' ), esc_html__( 'Edit', 'techvenza-core' ) );
		}
		$resubmit = '';
		$avail    = Workflow::available( $p );
		$key      = isset( $avail['resubmit'] ) ? 'resubmit' : ( isset( $avail['submit'] ) ? 'submit' : '' );
		if ( $key ) {
			$problems = Workflow::checklist( $p );
			$resubmit = sprintf(
				'<section class="panel"><h2 class="panel-title">%1$s</h2><div data-form-box>%2$s<form class="form" data-tv-form data-endpoint="posts/%3$d/transition" novalidate><input type="hidden" name="action" value="%4$s">%5$s<div class="form-note warn" data-form-error hidden role="alert">%6$s<span></span></div><div class="row"><a class="btn btn-secondary" href="%7$s">%8$s</a><button class="btn btn-primary" type="submit"%9$s>%10$s %11$s</button></div></form></div></section>',
				esc_html( 'resubmit' === $key ? __( 'Revision notes', 'techvenza-core' ) : __( 'Submit for review', 'techvenza-core' ) ),
				$problems ? '<div class="form-note warn mb-5">' . R::icon( 'info' ) . '<span>' . esc_html__( 'Before submitting:', 'techvenza-core' ) . ' ' . esc_html( implode( ' ', $problems ) ) . '</span></div>' : '',
				$p->ID,
				esc_attr( $key ),
				R::field(
					array(
						'id'       => 'rvNotes',
						'name'     => 'note',
						'type'     => 'textarea',
						'rows'     => 4,
						'required' => 'resubmit' === $key,
						'opt'      => 'submit' === $key,
						'label'    => __( 'Notes for your editor', 'techvenza-core' ),
						'hint'     => 'resubmit' === $key ? __( 'Summarise what you changed in response to the feedback.', 'techvenza-core' ) : __( 'Anything the editor should know (sources, embargoes, conflicts of interest).', 'techvenza-core' ),
						'error'    => __( 'Add a short note describing your revisions.', 'techvenza-core' ),
					)
				),
				R::icon( 'alert' ),
				esc_url( (string) get_edit_post_link( $p->ID, 'raw' ) ),
				esc_html__( 'Edit article first', 'techvenza-core' ),
				$problems ? ' disabled' : '',
				R::icon( 'send' ),
				esc_html( 'resubmit' === $key ? __( 'Resubmit for review', 'techvenza-core' ) : __( 'Submit for review', 'techvenza-core' ) )
			);
		}
		$editor = (int) get_post_meta( $p->ID, 'tv_assigned_editor', true );
		$ed     = $editor ? get_userdata( $editor ) : null;
		$sub_at = (string) get_post_meta( $p->ID, 'tv_submitted_at', true );
		$cat    = CategoryMeta::for_post( $p->ID );
		$a      = R::article( $p );
		return App::head(
			array(
				array( __( 'Portal', 'techvenza-core' ), App::url( 'author-portal' ) ),
				array( __( 'My articles', 'techvenza-core' ), App::url( 'author-portal', 'articles' ) ),
				array( __( 'Submission', 'techvenza-core' ), '' ),
			),
			$p->post_title ? $p->post_title : __( '(untitled)', 'techvenza-core' ),
			'',
			$actions
		) . sprintf(
			'<div class="review-grid"><div class="grid" style="gap:var(--s5)"><section class="panel"><h2 class="panel-title">%1$s</h2>%2$s</section>%3$s<section class="panel"><h2 class="panel-title">%4$s</h2><p style="font-size:var(--t-small)">%5$s</p></section></div>
			<aside class="grid" style="gap:var(--s5);align-content:start"><section class="panel"><h2 class="panel-title">%6$s</h2><dl class="kv"><dt>%7$s</dt><dd>%8$s</dd><dt>%9$s</dt><dd>%10$s</dd><dt>%11$s</dt><dd>%12$s</dd><dt>%13$s</dt><dd>%14$s</dd>%15$s<dt>%16$s</dt><dd>%17$s</dd></dl></section><section class="panel"><h2 class="panel-title">%18$s</h2>%19$s</section></aside></div>',
			esc_html__( 'Editorial feedback', 'techvenza-core' ),
			$fb_html ? $fb_html : R::empty_state( 'msg', __( 'No feedback yet', 'techvenza-core' ), 'draft' === $p->post_status ? __( 'Submit the article to receive editorial feedback.', 'techvenza-core' ) : __( 'Your editor will leave notes here.', 'techvenza-core' ) ),
			$resubmit,
			esc_html__( 'Article summary', 'techvenza-core' ),
			$p->post_excerpt ? esc_html( $p->post_excerpt ) : '<span class="muted">' . esc_html__( 'No excerpt yet.', 'techvenza-core' ) . '</span>',
			esc_html__( 'Details', 'techvenza-core' ),
			esc_html__( 'Status', 'techvenza-core' ),
			R::badge( $p->post_status ),
			esc_html__( 'Submitted', 'techvenza-core' ),
			esc_html( $sub_at ? R::date( substr( $sub_at, 0, 10 ) ) : __( 'Not yet', 'techvenza-core' ) ),
			esc_html__( 'Editor', 'techvenza-core' ),
			esc_html( $ed ? $ed->display_name : __( 'Not yet assigned', 'techvenza-core' ) ),
			esc_html__( 'Category', 'techvenza-core' ),
			esc_html( $cat['name'] ?? '—' ),
			'future' === $p->post_status ? '<dt>' . esc_html__( 'Scheduled', 'techvenza-core' ) . '</dt><dd>' . esc_html( R::date( get_the_date( 'Y-m-d', $p ) ) ) . '</dd>' : '',
			esc_html__( 'Reading time', 'techvenza-core' ),
			/* translators: %d: minutes */
			esc_html( sprintf( __( '%d min', 'techvenza-core' ), $a ? $a['minutes'] : 1 ) ),
			esc_html__( 'History', 'techvenza-core' ),
			self::timeline( $p )
		);
	}

	/**
	 * Author profile.
	 */
	private static function profile(): string {
		$user = wp_get_current_user();
		$au   = UserMeta::author( $user );
		$m    = static fn( string $k ): string => (string) get_user_meta( $user->ID, $k, true );
		$exp  = implode( ', ', UserMeta::get_list( $user->ID, 'tv_expertise' ) );
		return App::head(
			array(
				array( __( 'Portal', 'techvenza-core' ), App::url( 'author-portal' ) ),
				array( __( 'Author profile', 'techvenza-core' ), '' ),
			),
			__( 'Author profile', 'techvenza-core' ),
			esc_html__( 'This information appears on your public author page and article bylines.', 'techvenza-core' ),
			sprintf( '<a class="btn btn-secondary btn-sm" href="%s">%s %s</a>', esc_url( get_author_posts_url( $user->ID ) ), R::icon( 'external' ), esc_html__( 'View public profile', 'techvenza-core' ) )
		) . sprintf(
			'<div class="editor-layout"><form class="panel form" data-tv-form data-endpoint="portal/profile" data-profile-form novalidate>
			<div class="row" style="gap:var(--s5)"><span data-avatar-preview>%1$s</span><div><label class="btn btn-secondary btn-sm" for="pfImg">%2$s %3$s</label><input class="sr-only" id="pfImg" type="file" accept="image/jpeg,image/png,image/webp" data-photo-input><input type="hidden" name="photo" value="%4$d"><p class="hint mt-4">%5$s</p></div></div>
			<div class="form-grid">%6$s%7$s</div>%8$s%9$s<div class="form-grid">%10$s%11$s</div>%12$s
			<div class="form-note warn" data-form-error hidden role="alert">%13$s<span></span></div><div class="row"><button class="btn btn-primary" type="submit">%14$s</button></div></form>
			<aside><div class="panel"><h2 class="panel-title">%15$s</h2><div data-profile-preview>%16$s</div></div></aside></div>',
			R::avatar( $au, 'lg' ),
			R::icon( 'upload' ),
			esc_html__( 'Upload photo', 'techvenza-core' ),
			(int) get_user_meta( $user->ID, 'tv_photo', true ),
			esc_html__( 'Square JPG, PNG or WebP, at least 400×400, up to 3 MB.', 'techvenza-core' ),
			R::field(
				array(
					'id'       => 'pfName',
					'name'     => 'name',
					'label'    => __( 'Name', 'techvenza-core' ),
					'value'    => $user->display_name,
					'required' => true,
				)
			),
			R::field(
				array(
					'id'       => 'pfTitle',
					'name'     => 'title',
					'label'    => __( 'Professional title', 'techvenza-core' ),
					'value'    => $m( 'tv_title' ),
					'required' => true,
				)
			),
			str_replace(
				'<textarea',
				'<textarea data-min="80"',
				R::field(
					array(
						'id'       => 'pfBio',
						'name'     => 'bio',
						'type'     => 'textarea',
						'rows'     => 5,
						'label'    => __( 'Biography', 'techvenza-core' ),
						'value'    => $user->description,
						'max'      => 600,
						'required' => true,
						'error'    => __( 'Add a biography of at least 80 characters.', 'techvenza-core' ),
					)
				)
			),
			R::field(
				array(
					'id'       => 'pfExp',
					'name'     => 'expertise',
					'label'    => __( 'Expertise', 'techvenza-core' ),
					'value'    => $exp,
					'required' => true,
					'hint'     => __( 'Comma-separated, up to 5.', 'techvenza-core' ),
				)
			),
			R::field(
				array(
					'id'    => 'pfWeb',
					'name'  => 'website',
					'type'  => 'url',
					'label' => __( 'Website', 'techvenza-core' ),
					'value' => $m( 'tv_website' ) ? $m( 'tv_website' ) : $user->user_url,
					'opt'   => true,
					'error' => __( 'Enter a full URL starting with https://', 'techvenza-core' ),
				)
			),
			R::field(
				array(
					'id'    => 'pfLi',
					'name'  => 'linkedin',
					'type'  => 'url',
					'label' => 'LinkedIn',
					'value' => $m( 'tv_linkedin' ),
					'opt'   => true,
					'error' => __( 'Enter a full URL starting with https://', 'techvenza-core' ),
				)
			),
			R::field(
				array(
					'id'          => 'pfX',
					'name'        => 'x',
					'type'        => 'url',
					'label'       => 'X / Twitter',
					'value'       => $m( 'tv_x' ),
					'opt'         => true,
					'placeholder' => 'https://x.com/…',
					'error'       => __( 'Enter a full URL starting with https://', 'techvenza-core' ),
				)
			),
			R::icon( 'alert' ),
			esc_html__( 'Save profile', 'techvenza-core' ),
			esc_html__( 'Profile preview', 'techvenza-core' ),
			$au ? R::author_card( $au ) : ''
		);
	}

	/**
	 * Analytics from the views table.
	 */
	private static function analytics(): string {
		$pub   = self::mine( array( 'post_status' => 'publish' ) );
		$ids   = wp_list_pluck( $pub, 'ID' );
		$cur   = $ids ? array_sum( Views::series( 30, $ids ) ) : 0;
		$prev  = 0;
		$delta = '';
		if ( $ids ) {
			$all  = Views::series( 60, $ids );
			$prev = array_sum( array_slice( $all, 0, 30 ) );
			if ( $prev > 0 ) {
				$pct = (int) round( ( $cur - $prev ) / $prev * 100 );
				/* translators: %d: percent */
				$delta = sprintf( '<span class="%s">%s</span>', $pct >= 0 ? 'up' : 'down', esc_html( sprintf( __( '%1$s %2$d%% vs previous 30 days', 'techvenza-core' ), $pct >= 0 ? '▲' : '▼', abs( $pct ) ) ) );
			}
		}
		$saves    = 0;
		$users    = get_users(
			array(
				'meta_key'     => 'tv_bookmarks', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
				'fields'       => 'ID',
				'number'       => 2000,
			)
		);
		$per_post = array_fill_keys( $ids, 0 );
		foreach ( $users as $uid ) {
			foreach ( UserMeta::get_list( (int) $uid, 'tv_bookmarks' ) as $bid ) {
				if ( isset( $per_post[ (int) $bid ] ) ) {
					++$per_post[ (int) $bid ];
					++$saves;
				}
			}
		}
		$total = array_sum( array_map( static fn( $id ) => Query::views( (int) $id ), $ids ) );

		$series = $ids ? Views::series( 84, $ids ) : array();
		$weeks  = array_map( 'array_sum', array_chunk( array_values( $series ), 7 ) );
		$labels = array_map( static fn( $k ) => 'W' . gmdate( 'W', (int) strtotime( (string) end( $k ) ) ), array_chunk( array_keys( $series ), 7 ) );

		$rows = '';
		$bars = array();
		usort( $pub, static fn( $a, $b ) => Query::views( $b->ID ) <=> Query::views( $a->ID ) );
		foreach ( $pub as $p ) {
			$v      = Query::views( $p->ID );
			$c      = CategoryMeta::for_post( $p->ID );
			$bars[] = array( wp_html_excerpt( get_the_title( $p ), 26, '…' ), Query::views( $p->ID, '30d' ), $c['color'] ?? '#2563EB', number_format_i18n( Query::views( $p->ID, '30d' ) ) );
			$rows  .= sprintf( '<tr><td class="t-title" data-label="%s"><a href="%s">%s</a></td><td data-label="%s">%s</td><td data-label="%s">%s</td><td data-label="%s">%s</td></tr>', esc_attr__( 'Article', 'techvenza-core' ), esc_url( (string) get_permalink( $p ) ), esc_html( get_the_title( $p ) ), esc_attr__( 'Views', 'techvenza-core' ), esc_html( number_format_i18n( $v ) ), esc_attr__( '30 days', 'techvenza-core' ), esc_html( number_format_i18n( Query::views( $p->ID, '30d' ) ) ), esc_attr__( 'Saves', 'techvenza-core' ), esc_html( number_format_i18n( $per_post[ $p->ID ] ?? 0 ) ) );
		}
		return App::head(
			array(
				array( __( 'Portal', 'techvenza-core' ), App::url( 'author-portal' ) ),
				array( __( 'Analytics', 'techvenza-core' ), '' ),
			),
			__( 'Analytics', 'techvenza-core' ),
			esc_html__( 'Performance of your published articles. Views are counted on the site, with bots filtered out.', 'techvenza-core' )
		) . '<div class="stats">'
		. App::stat( __( 'Total views', 'techvenza-core' ), esc_html( number_format_i18n( $total ) ) )
		. App::stat( __( 'Last 30 days', 'techvenza-core' ), esc_html( number_format_i18n( $cur ) ), 'var(--violet)', $delta )
		. App::stat( __( 'Published articles', 'techvenza-core' ), (string) count( $pub ), 'var(--emerald)' )
		. App::stat( __( 'Saves', 'techvenza-core' ), esc_html( number_format_i18n( $saves ) ), 'var(--coral)', esc_html__( 'Readers who bookmarked your work', 'techvenza-core' ) )
		. '</div>'
		. ( $pub ? sprintf(
			'<div class="dash-grid"><section class="panel"><h2 class="panel-title">%s</h2>%s</section><section class="panel"><h2 class="panel-title">%s</h2>%s</section></div><section class="panel mt-5"><h2 class="panel-title">%s</h2><div class="table-scroll"><table class="dt cards"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>%s</tbody></table></div></section>',
			esc_html__( 'Weekly views', 'techvenza-core' ),
			Charts::line(
				array(
					array(
						'name'  => __( 'Views', 'techvenza-core' ),
						'color' => '#4F46E5',
						'data'  => $weeks,
					),
				),
				$labels,
				__( 'Weekly views of your articles', 'techvenza-core' )
			),
			esc_html__( 'Views by article · 30 days', 'techvenza-core' ),
			Charts::hbars( array_slice( $bars, 0, 8 ) ),
			esc_html__( 'Popular articles', 'techvenza-core' ),
			esc_html__( 'Article', 'techvenza-core' ),
			esc_html__( 'Views', 'techvenza-core' ),
			esc_html__( '30 days', 'techvenza-core' ),
			esc_html__( 'Saves', 'techvenza-core' ),
			$rows
		) : R::empty_state( 'chart', __( 'No published articles yet', 'techvenza-core' ), __( 'Analytics appear after your first article is published.', 'techvenza-core' ) ) );
	}

	/**
	 * Notifications.
	 */
	private static function notifications(): string {
		$filter  = isset( $_GET['f'] ) ? sanitize_key( wp_unslash( $_GET['f'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filters = array(
			'all'      => __( 'All', 'techvenza-core' ),
			'unread'   => __( 'Unread', 'techvenza-core' ),
			'revision' => __( 'Revisions', 'techvenza-core' ),
			'approved' => __( 'Approvals', 'techvenza-core' ),
			'account'  => __( 'Account', 'techvenza-core' ),
		);
		$filter  = isset( $filters[ $filter ] ) ? $filter : 'all';
		$tabs    = '';
		foreach ( $filters as $k => $l ) {
			$tabs .= sprintf( '<a class="tab" href="%s" aria-current="%s">%s</a>', esc_url( App::url( 'author-portal', 'notifications', array( 'f' => 'all' === $k ? '' : $k ) ) ), $k === $filter ? 'true' : 'false', esc_html( $l ) );
		}
		$icons = array(
			'feedback'  => 'edit',
			'revision'  => 'edit',
			'status'    => 'eye',
			'approved'  => 'checkCircle',
			'published' => 'send',
			'account'   => 'user',
		);
		$list  = Notifications::list( get_current_user_id(), $filter, 100 );
		$items = '';
		foreach ( $list as $n ) {
			$items .= sprintf(
				'<div class="notif%s" data-notif="%d"><span class="act-ico">%s</span><div><div class="n-title">%s</div><p>%s</p>%s</div><span class="meta">%s</span></div>',
				$n['unread'] ? ' unread' : '',
				(int) $n['id'],
				R::icon( $icons[ $n['type'] ] ?? 'bell' ),
				esc_html( $n['title'] ),
				esc_html( $n['message'] ),
				$n['link'] ? sprintf( '<a class="link" style="font-size:var(--t-meta)" href="%s" data-read-notif="%d">%s</a>', esc_url( $n['link'] ), (int) $n['id'], esc_html__( 'Open', 'techvenza-core' ) ) : '',
				esc_html( R::date( substr( $n['at'], 0, 10 ) ) )
			);
		}
		return App::head(
			array(
				array( __( 'Portal', 'techvenza-core' ), App::url( 'author-portal' ) ),
				array( __( 'Notifications', 'techvenza-core' ), '' ),
			),
			__( 'Notifications', 'techvenza-core' ),
			esc_html__( 'Submission updates, revision requests, approvals and account messages.', 'techvenza-core' ),
			sprintf( '<button class="btn btn-secondary btn-sm" type="button" data-action="readAll">%s %s</button>', R::icon( 'check' ), esc_html__( 'Mark all as read', 'techvenza-core' ) )
		) . sprintf(
			'<div class="table-wrap"><nav class="tabs" aria-label="%s" style="padding:0 var(--s3)">%s</nav><div aria-live="polite">%s</div></div>',
			esc_attr__( 'Filter notifications', 'techvenza-core' ),
			$tabs,
			$items ? $items : '<div style="padding:var(--s5)">' . R::empty_state( 'bell', __( 'You are all caught up', 'techvenza-core' ), __( 'No notifications in this view.', 'techvenza-core' ) ) . '</div>'
		);
	}

	/**
	 * Account settings (email notification preferences, password, deletion).
	 */
	private static function settings(): string {
		$prefs   = (array) get_user_meta( get_current_user_id(), 'tv_email_prefs', true );
		$events  = array(
			'status'    => __( 'Submission status changes', 'techvenza-core' ),
			'feedback'  => __( 'Revision requests from editors', 'techvenza-core' ),
			'published' => __( 'Article published', 'techvenza-core' ),
			'monthly'   => __( 'Monthly performance summary', 'techvenza-core' ),
			'product'   => __( 'Product updates from Techvenza', 'techvenza-core' ),
		);
		$toggles = '';
		foreach ( $events as $k => $l ) {
			$on       = array_key_exists( $k, $prefs ) ? (bool) $prefs[ $k ] : ! in_array( $k, array( 'monthly', 'product' ), true );
			$toggles .= sprintf( '<input type="hidden" name="email[%1$s]" value="0"><label class="toggle"><input type="checkbox" name="email[%1$s]" value="1"%2$s> %3$s</label>', esc_attr( $k ), checked( $on, true, false ), esc_html( $l ) );
		}
		return App::head(
			array(
				array( __( 'Portal', 'techvenza-core' ), App::url( 'author-portal' ) ),
				array( __( 'Account settings', 'techvenza-core' ), '' ),
			),
			__( 'Account settings', 'techvenza-core' ),
			esc_html__( 'Notifications and security.', 'techvenza-core' )
		) . sprintf(
			'<section class="panel"><h2 class="panel-title">%s</h2><form class="form" data-tv-form data-method="PUT" data-endpoint="me/preferences">%s<button class="btn btn-primary btn-sm" type="submit" style="justify-self:start">%s</button></form><p class="hint mt-4">%s</p></section>',
			esc_html__( 'Email notifications', 'techvenza-core' ),
			$toggles,
			esc_html__( 'Save notification settings', 'techvenza-core' ),
			sprintf(
				/* translators: %s: link to reader dashboard */
				esc_html__( 'Your email address and newsletter choices are managed in the %s.', 'techvenza-core' ),
				'<a class="link" href="' . esc_url( App::url( 'dashboard', 'settings' ) ) . '">' . esc_html__( 'reader dashboard', 'techvenza-core' ) . '</a>'
			)
		) . Account::password_panel() . Account::danger_panel();
	}
}
