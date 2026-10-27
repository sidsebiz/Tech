<?php
/**
 * Review queue (tabs per workflow stage, bulk actions) and the single-submission review screen.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Portal\Author;
use Techvenza\Core\Support\Render as R;
use Techvenza\Core\Workflow\Statuses;
use Techvenza\Core\Workflow\Workflow;

/**
 * Review screens.
 */
final class Review {

	/**
	 * Queue tabs.
	 *
	 * @return array<string,string>
	 */
	private static function tabs(): array {
		return array(
			'submitted'         => __( 'New submissions', 'techvenza-core' ),
			'in_review'         => __( 'In review', 'techvenza-core' ),
			'resubmitted'       => __( 'Resubmitted', 'techvenza-core' ),
			'changes_requested' => __( 'Changes requested', 'techvenza-core' ),
			'approved'          => __( 'Approved', 'techvenza-core' ),
			'future'            => __( 'Scheduled', 'techvenza-core' ),
			'rejected'          => __( 'Rejected', 'techvenza-core' ),
		);
	}

	/**
	 * Editorial checklist (Settings → Editorial; filterable).
	 *
	 * @return array<int,string>
	 */
	public static function checklist(): array {
		$items = array_values( array_filter( array_map( 'trim', (array) preg_split( '/\R/', (string) \Techvenza\Core\Settings\Options::get( 'checklist', '' ) ) ) ) );
		/**
		 * Review checklist items shown to editors.
		 *
		 * @param array<int,string> $items Items.
		 */
		return (array) apply_filters( 'techvenza/review/checklist', $items );
	}

	/**
	 * Route: queue or detail.
	 */
	public static function render(): string {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $id ? self::detail( $id ) : self::queue();
	}

	/**
	 * Queue.
	 */
	private static function queue(): string {
		$tabs   = self::tabs();
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'submitted'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $tabs[ $status ] ) ? $status : 'submitted';
		$counts = wp_count_posts( 'post' );
		$nav    = '';
		foreach ( $tabs as $k => $l ) {
			$nav .= sprintf( '<a class="tab" href="%s"%s>%s<span class="count">%d</span></a>', esc_url( Admin::url( 'techvenza-review', array( 'status' => $k ) ) ), $k === $status ? ' aria-current="page"' : '', esc_html( $l ), (int) ( $counts->$k ?? 0 ) );
		}
		$q     = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => $status,
				'posts_per_page' => 50,
				'meta_key'       => 'tv_submitted_at', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => array(
					'meta_value' => 'ASC',
					'date'       => 'ASC',
				),
				'no_found_rows'  => true,
			)
		);
		$posts = array_values( array_filter( $q->posts, static fn( $p ) => $p instanceof \WP_Post ) );
		if ( ! $posts ) {
			$q2    = new \WP_Query(
				array(
					'post_type'      => 'post',
					'post_status'    => $status,
					'posts_per_page' => 50,
					'no_found_rows'  => true,
				)
			);
			$posts = array_values( array_filter( $q2->posts, static fn( $p ) => $p instanceof \WP_Post ) );
		}
		$items = '';
		$sla   = (int) \Techvenza\Core\Settings\Options::get( 'review_sla_days', 5 );
		foreach ( $posts as $p ) {
			$au     = UserMeta::author( (int) $p->post_author );
			$sub    = (string) get_post_meta( $p->ID, 'tv_submitted_at', true );
			$age    = $sub ? (int) floor( ( current_time( 'timestamp' ) - strtotime( $sub ) ) / DAY_IN_SECONDS ) : 0; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$ed     = (int) get_post_meta( $p->ID, 'tv_assigned_editor', true );
			$editor = $ed ? get_userdata( $ed ) : null;
			$meta   = array_filter(
				array(
					esc_html( $au['name'] ?? '' ),
					R::chip( CategoryMeta::for_post( $p->ID ), false ),
					/* translators: %s: date */
					$sub ? esc_html( sprintf( __( 'Submitted %s', 'techvenza-core' ), R::date( substr( $sub, 0, 10 ) ) ) ) : '',
					/* translators: %s: editor */
					$editor ? esc_html( sprintf( __( 'Editor: %s', 'techvenza-core' ), $editor->display_name ) ) : '',
					/* translators: %s: date */
					'future' === $p->post_status ? esc_html( sprintf( __( 'Publishes %s', 'techvenza-core' ), R::date( get_the_date( 'Y-m-d', $p ) ) ) ) : '',
					/* translators: %d: days */
					in_array( $status, array( 'submitted', 'resubmitted', 'in_review' ), true ) && $age > $sla ? '<span style="color:#be123c;font-weight:600">' . esc_html( sprintf( _n( 'Waiting %d day', 'Waiting %d days', $age, 'techvenza-core' ), $age ) ) . '</span>' : '',
				)
			);
			$items .= sprintf(
				'<div class="queue-item"><input type="checkbox" name="ids[]" value="%1$d" aria-label="%2$s">%3$s<div><a class="t" href="%4$s">%5$s</a><div class="meta row" style="gap:8px;margin-top:4px">%6$s</div></div><div class="row" style="gap:6px">%7$s<a class="button" href="%4$s">%8$s</a></div></div>',
				$p->ID,
				/* translators: %s: title */
				esc_attr( sprintf( __( 'Select %s', 'techvenza-core' ), $p->post_title ) ),
				R::avatar( $au, 'md' ),
				esc_url( Admin::url( 'techvenza-review', array( 'id' => $p->ID ) ) ),
				esc_html( $p->post_title ? $p->post_title : __( '(untitled)', 'techvenza-core' ) ),
				implode( ' · ', $meta ),
				R::badge( $p->post_status ),
				esc_html__( 'Review', 'techvenza-core' )
			);
		}
		$bulk_opts = '';
		foreach ( Workflow::transitions() as $k => $t ) {
			if ( in_array( $status, $t['from'], true ) && empty( $t['note'] ) && empty( $t['date'] ) && empty( $t['own'] ) && current_user_can( $t['cap'] ) ) {
				$bulk_opts .= sprintf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $t['label'] ) );
			}
		}
		$body = $items
			? sprintf(
				'<form method="post" action="%s"><input type="hidden" name="action" value="techvenza_transition">%s%s<div class="queue">%s</div></form>',
				esc_url( admin_url( 'admin-post.php' ) ),
				wp_nonce_field( 'techvenza_transition', '_wpnonce', true, false ),
				$bulk_opts ? sprintf( '<div class="bulk"><label class="sr-only" for="tvBulk">%1$s</label><select id="tvBulk" name="transition"><option value="">%1$s</option>%2$s</select><button class="button" type="submit">%3$s</button><span class="meta">%4$s</span></div>', esc_html__( 'Bulk actions', 'techvenza-core' ), $bulk_opts, esc_html__( 'Apply', 'techvenza-core' ), esc_html__( 'Actions that need a note or a date are available on each submission.', 'techvenza-core' ) ) : '',
				$items
			)
			: R::empty_state( 'checkCircle', __( 'Nothing in this stage', 'techvenza-core' ), __( 'When articles reach this status, they will appear here.', 'techvenza-core' ) );

		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Review queue', 'techvenza-core' ), '' ),
			),
			__( 'Editorial review queue', 'techvenza-core' ),
			/* translators: %d: days */
			esc_html( sprintf( __( 'Move submissions through review, revision, approval and publication. Target first response: %d days.', 'techvenza-core' ), $sla ) )
		) . sprintf( '<div class="table-wrap"><nav class="tabs" aria-label="%s">%s</nav>%s</div>', esc_attr__( 'Queue status', 'techvenza-core' ), $nav, $body );
	}

	/**
	 * Single submission.
	 *
	 * @param int $id Post ID.
	 */
	private static function detail( int $id ): string {
		$p = get_post( $id );
		if ( ! $p || 'post' !== $p->post_type ) {
			return R::empty_state( 'file', __( 'Article not found', 'techvenza-core' ), __( 'It may have been deleted.', 'techvenza-core' ) );
		}
		$au      = UserMeta::author( (int) $p->post_author );
		$cat     = CategoryMeta::for_post( $p->ID );
		$seo     = (string) get_post_meta( $p->ID, 'tv_seo_title', true );
		$meta    = (string) get_post_meta( $p->ID, 'tv_seo_description', true );
		$ed      = (int) get_post_meta( $p->ID, 'tv_assigned_editor', true );
		$editor  = $ed ? get_userdata( $ed ) : null;
		$tags    = wp_get_post_tags( $p->ID, array( 'fields' => 'names' ) );
		$fb_html = '';
		foreach ( Workflow::feedback( $p->ID ) as $f ) {
			$who      = get_userdata( (int) ( $f['user'] ?? 0 ) );
			$fb_html .= sprintf( '<div class="feedback"><div class="who">%s · %s</div><p>%s</p></div>', esc_html( $who ? $who->display_name : '—' ), esc_html( R::date( substr( (string) ( $f['at'] ?? '' ), 0, 10 ) ) ), esc_html( (string) $f['note'] ) );
		}
		$checks = '';
		foreach ( self::checklist() as $i => $item ) {
			$checks .= sprintf( '<label><input type="checkbox" data-review-check name="ck%d"%s> %s</label>', $i, in_array( $p->post_status, array( 'approved', 'future', 'publish' ), true ) ? ' checked' : '', esc_html( $item ) );
		}
		$problems = Workflow::checklist( $p );
		$content  = apply_filters( 'the_content', $p->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
		$count    = $au ? Query::author_count( (int) $au['id'] ) : 0;

		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Review queue', 'techvenza-core' ), Admin::url( 'techvenza-review', array( 'status' => $p->post_status ) ) ),
				array( __( 'Submission', 'techvenza-core' ), '' ),
			),
			$p->post_title ? $p->post_title : __( '(untitled)', 'techvenza-core' ),
			'',
			R::badge( $p->post_status ) . sprintf( '<a class="button" href="%s">%s</a>', esc_url( (string) get_edit_post_link( $p->ID, 'raw' ) ), esc_html__( 'Open in editor', 'techvenza-core' ) ) . sprintf( '<a class="button" href="%s" target="_blank" rel="noopener">%s</a>', esc_url( 'publish' === $p->post_status ? (string) get_permalink( $p ) : (string) get_preview_post_link( $p ) ), esc_html__( 'Preview on site', 'techvenza-core' ) )
		) . sprintf(
			'<div class="review-grid"><article class="preview" aria-label="%1$s">%2$s<h1>%3$s</h1>%4$s<div class="meta">%5$s</div>%6$s<hr><div class="body">%7$s</div></article>
			<aside><section class="panel"><h2 class="panel-title">%8$s</h2>%9$s</section>
			<section class="panel"><h2 class="panel-title">%10$s</h2><div class="checklist">%11$s</div>%12$s</section>
			<section class="panel"><h2 class="panel-title">%13$s</h2><div class="row" style="flex-wrap:nowrap">%14$s<div><b style="color:var(--ink)">%15$s</b><div class="meta">%16$s</div></div></div><p class="meta">%17$s</p>%18$s</section>
			<section class="panel"><h2 class="panel-title">%19$s</h2><dl class="kv"><dt>%20$s</dt><dd>%21$s</dd><dt>%22$s</dt><dd>%23$s</dd><dt>%24$s</dt><dd>/%25$s</dd><dt>%26$s</dt><dd>%27$s</dd><dt>%28$s</dt><dd>%29$s</dd><dt>%30$s</dt><dd>%31$s</dd></dl></section>
			<section class="panel"><h2 class="panel-title">%32$s</h2>%33$s</section>
			<section class="panel"><h2 class="panel-title">%34$s</h2>%35$s</section></aside></div>',
			esc_attr__( 'Article preview', 'techvenza-core' ),
			R::chip( $cat, false ),
			esc_html( $p->post_title ),
			$p->post_excerpt ? '<p class="lead">' . esc_html( $p->post_excerpt ) . '</p>' : '',
			/* translators: %s: author */
			esc_html( sprintf( __( 'By %s', 'techvenza-core' ), $au['name'] ?? '—' ) ),
			has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'large', array( 'style' => 'max-width:100%;height:auto;border-radius:10px;margin-top:16px' ) ) : '',
			wp_kses_post( $content ),
			esc_html__( 'Actions', 'techvenza-core' ),
			Admin::transition_form( $p ),
			esc_html__( 'Review checklist', 'techvenza-core' ),
			$checks,
			$problems ? '<p class="meta" style="color:#b45309;margin-top:10px">' . esc_html__( 'Submission checks:', 'techvenza-core' ) . ' ' . esc_html( implode( ' ', $problems ) ) . '</p>' : '',
			esc_html__( 'Author', 'techvenza-core' ),
			R::avatar( $au, 'md' ),
			esc_html( $au['name'] ?? '—' ),
			esc_html( $au['title'] ?? '' ),
			/* translators: 1: count, 2: date */
			esc_html( sprintf( __( '%1$d published · joined %2$s', 'techvenza-core' ), $count, R::date( (string) ( $au['joined'] ?? '' ) ) ) ),
			$au ? '<a href="' . esc_url( $au['url'] ) . '">' . esc_html__( 'Public profile', 'techvenza-core' ) . '</a>' : '',
			esc_html__( 'Metadata', 'techvenza-core' ),
			esc_html__( 'Category', 'techvenza-core' ),
			esc_html( $cat['name'] ?? '—' ),
			esc_html__( 'Tags', 'techvenza-core' ),
			esc_html( $tags ? implode( ', ', $tags ) : '—' ),
			esc_html__( 'Slug', 'techvenza-core' ),
			esc_html( $p->post_name ? $p->post_name : sanitize_title( $p->post_title ) ),
			esc_html__( 'SEO title', 'techvenza-core' ),
			esc_html( $seo ? $seo : '—' ) . ( $seo ? sprintf( ' <span class="meta"%s>(%d/60)</span>', mb_strlen( $seo ) > 60 ? ' style="color:#be123c"' : '', mb_strlen( $seo ) ) : '' ),
			esc_html__( 'Meta', 'techvenza-core' ),
			esc_html( $meta ? $meta : '—' ) . ( $meta ? sprintf( ' <span class="meta"%s>(%d/160)</span>', mb_strlen( $meta ) > 160 ? ' style="color:#be123c"' : '', mb_strlen( $meta ) ) : '' ),
			esc_html__( 'Editor', 'techvenza-core' ),
			esc_html( $editor ? $editor->display_name : __( 'Unassigned', 'techvenza-core' ) ),
			esc_html__( 'Feedback history', 'techvenza-core' ),
			$fb_html ? $fb_html : '<p class="muted">' . esc_html__( 'No feedback yet.', 'techvenza-core' ) . '</p>',
			esc_html__( 'History', 'techvenza-core' ),
			Author::timeline( $p )
		);
	}

	/**
	 * Apply a transition to one or many articles (admin-post).
	 */
	public static function handle(): void {
		check_admin_referer( 'techvenza_transition' );
		$key  = isset( $_POST['transition'] ) ? sanitize_key( wp_unslash( $_POST['transition'] ) ) : '';
		$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		if ( ! $key || ! $ids ) {
			Admin::back( 'none' );
		}
		$ok     = 0;
		$errors = array();
		foreach ( $ids as $id ) {
			$res = Workflow::transition(
				$id,
				$key,
				array(
					'note' => $note,
					'date' => $date ? str_replace( 'T', ' ', $date ) : '',
				)
			);
			if ( is_wp_error( $res ) ) {
				$errors[] = $res->get_error_message();
			} else {
				++$ok;
			}
		}
		if ( ! $ok ) {
			Admin::back( 'error', implode( ' ', array_unique( $errors ) ) );
		}
		/* translators: %d: count */
		Admin::back( 'moved', $errors ? implode( ' ', array_unique( $errors ) ) : sprintf( _n( '%d article updated.', '%d articles updated.', $ok, 'techvenza-core' ), $ok ) );
	}

	/**
	 * Status label helper.
	 *
	 * @param string $status Status.
	 */
	public static function label( string $status ): string {
		return Statuses::labels()[ $status ] ?? $status;
	}
}
