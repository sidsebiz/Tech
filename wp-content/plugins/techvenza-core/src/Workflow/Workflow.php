<?php
/**
 * Editorial state machine: allowed transitions, permissions, validation, timeline and hooks.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Workflow;

use Techvenza\Core\Content\ReadingTime;
use Techvenza\Core\Settings\Options;

/**
 * Every status change made through Techvenza goes through Workflow::transition().
 * Changes made with core UI (e.g. a Managing Editor pressing Publish) are logged too.
 */
final class Workflow {

	/**
	 * True while our own transition is writing the post (bypasses the status guard).
	 *
	 * @var bool
	 */
	private static bool $transitioning = false;


	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'guard_status' ), 20, 2 );
		add_action( 'transition_post_status', array( self::class, 'log_core_transition' ), 10, 3 );
	}

	/**
	 * Transition definitions.
	 *
	 * @return array<string,array{from:array<int,string>,to:string,cap:string,label:string,note?:bool,date?:bool,own?:bool,validate?:bool}>
	 */
	public static function transitions(): array {
		return array(
			'submit'     => array(
				'from'     => array( 'draft', 'auto-draft', 'pending', 'rejected' ),
				'to'       => 'submitted',
				'cap'      => 'edit_post',
				'own'      => true,
				'validate' => true,
				'label'    => __( 'Submit for review', 'techvenza-core' ),
			),
			'resubmit'   => array(
				'from'     => array( 'changes_requested' ),
				'to'       => 'resubmitted',
				'cap'      => 'edit_post',
				'own'      => true,
				'validate' => true,
				'label'    => __( 'Resubmit for review', 'techvenza-core' ),
			),
			'start'      => array(
				'from'  => array( 'submitted', 'resubmitted', 'pending' ),
				'to'    => 'in_review',
				'cap'   => 'tv_review_posts',
				'label' => __( 'Start review', 'techvenza-core' ),
			),
			'approve'    => array(
				'from'  => array( 'submitted', 'in_review', 'resubmitted', 'pending' ),
				'to'    => 'approved',
				'cap'   => 'tv_approve_posts',
				'label' => __( 'Approve', 'techvenza-core' ),
			),
			'request'    => array(
				'from'  => array( 'submitted', 'in_review', 'resubmitted', 'pending' ),
				'to'    => 'changes_requested',
				'cap'   => 'tv_review_posts',
				'note'  => true,
				'label' => __( 'Request changes', 'techvenza-core' ),
			),
			'reject'     => array(
				'from'  => array( 'submitted', 'in_review', 'resubmitted', 'approved', 'pending' ),
				'to'    => 'rejected',
				'cap'   => 'tv_review_posts',
				'note'  => true,
				'label' => __( 'Reject', 'techvenza-core' ),
			),
			'schedule'   => array(
				'from'  => array( 'approved', 'future' ),
				'to'    => 'future',
				'cap'   => 'tv_publish_posts',
				'date'  => true,
				'label' => __( 'Schedule', 'techvenza-core' ),
			),
			'publish'    => array(
				'from'  => array( 'approved', 'future' ),
				'to'    => 'publish',
				'cap'   => 'tv_publish_posts',
				'label' => __( 'Publish now', 'techvenza-core' ),
			),
			'unschedule' => array(
				'from'  => array( 'future' ),
				'to'    => 'approved',
				'cap'   => 'tv_publish_posts',
				'label' => __( 'Unschedule', 'techvenza-core' ),
			),
			'archive'    => array(
				'from'  => array( 'publish' ),
				'to'    => 'archived',
				'cap'   => 'tv_publish_posts',
				'label' => __( 'Archive', 'techvenza-core' ),
			),
			'restore'    => array(
				'from'  => array( 'archived', 'rejected' ),
				'to'    => 'draft',
				'cap'   => 'tv_review_posts',
				'label' => __( 'Return to draft', 'techvenza-core' ),
			),
		);
	}

	/**
	 * Transitions available to a user for a post.
	 *
	 * @param \WP_Post $post    Post.
	 * @param int      $user_id User (default current).
	 * @return array<string,array<string,mixed>>
	 */
	public static function available( \WP_Post $post, int $user_id = 0 ): array {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$out     = array();
		foreach ( self::transitions() as $key => $t ) {
			if ( in_array( $post->post_status, $t['from'], true ) && self::can( $key, $post, $user_id ) ) {
				$out[ $key ] = $t;
			}
		}
		return $out;
	}

	/**
	 * Permission check for one transition.
	 *
	 * @param string   $action  Transition key.
	 * @param \WP_Post $post    Post.
	 * @param int      $user_id User.
	 */
	public static function can( string $action, \WP_Post $post, int $user_id ): bool {
		$t = self::transitions()[ $action ] ?? null;
		if ( ! $t ) {
			return false;
		}
		if ( ! empty( $t['own'] ) ) {
			// Authors submit their own work; reviewers may submit on behalf of authors.
			return ( (int) $post->post_author === $user_id && user_can( $user_id, 'edit_post', $post->ID ) ) || user_can( $user_id, 'tv_review_posts' );
		}
		return user_can( $user_id, $t['cap'] );
	}

	/**
	 * Submission checklist (server-side twin of the editor sidebar checklist).
	 *
	 * @param \WP_Post $post Post.
	 * @return array<int,string> Problems (empty = OK).
	 */
	public static function checklist( \WP_Post $post ): array {
		$problems = array();
		$min      = (int) Options::get( 'min_words', 150 );
		if ( '' === trim( $post->post_title ) ) {
			$problems[] = __( 'Add a title.', 'techvenza-core' );
		}
		if ( mb_strlen( trim( $post->post_excerpt ) ) < 40 ) {
			$problems[] = __( 'Write an excerpt of at least 40 characters.', 'techvenza-core' );
		}
		$cats = array_diff( wp_get_post_categories( $post->ID ), array( (int) get_option( 'default_category' ) ) );
		if ( ! $cats ) {
			$problems[] = __( 'Choose a category.', 'techvenza-core' );
		}
		$words = ReadingTime::words( $post->post_content );
		if ( $words < $min ) {
			/* translators: 1: minimum words, 2: current words */
			$problems[] = sprintf( __( 'Write at least %1$d words (currently %2$d).', 'techvenza-core' ), $min, $words );
		}
		$thumb = (int) get_post_thumbnail_id( $post );
		if ( $thumb && '' === trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ) ) {
			$problems[] = __( 'Add alt text to the cover image.', 'techvenza-core' );
		}
		$meta = (string) get_post_meta( $post->ID, 'tv_seo_description', true );
		$meta = '' !== $meta ? $meta : $post->post_excerpt;
		if ( mb_strlen( $meta ) > 160 ) {
			$problems[] = __( 'Keep the meta description under 160 characters.', 'techvenza-core' );
		}
		/**
		 * Filter submission checklist problems.
		 *
		 * @param array<int,string> $problems Problems.
		 * @param \WP_Post          $post     Post.
		 */
		return (array) apply_filters( 'techvenza/workflow/checklist', $problems, $post );
	}

	/**
	 * Perform a transition.
	 *
	 * @param int                 $post_id Post ID.
	 * @param string              $action  Transition key.
	 * @param array<string,mixed> $args    note, date (Y-m-d H:i site time), user_id, skip_checks.
	 * @return true|\WP_Error
	 */
	public static function transition( int $post_id, string $action, array $args = array() ) {
		$post = get_post( $post_id );
		$t    = self::transitions()[ $action ] ?? null;
		$user = (int) ( $args['user_id'] ?? get_current_user_id() );
		if ( ! $post || 'post' !== $post->post_type || ! $t ) {
			return new \WP_Error( 'tv_invalid', __( 'Unknown article or action.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( $post->post_status, $t['from'], true ) ) {
			/* translators: 1: action label, 2: current status */
			return new \WP_Error( 'tv_bad_transition', sprintf( __( '“%1$s” is not possible while the article is %2$s.', 'techvenza-core' ), $t['label'], Statuses::labels()[ $post->post_status ] ?? $post->post_status ), array( 'status' => 409 ) );
		}
		if ( empty( $args['skip_checks'] ) && ! self::can( $action, $post, $user ) ) {
			return new \WP_Error( 'tv_forbidden', __( 'You do not have permission to do that.', 'techvenza-core' ), array( 'status' => 403 ) );
		}
		$note = trim( (string) ( $args['note'] ?? '' ) );
		if ( ! empty( $t['note'] ) && '' === $note && empty( $args['skip_checks'] ) ) {
			return new \WP_Error( 'tv_note_required', __( 'Please add a note for the author.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $t['validate'] ) && empty( $args['skip_checks'] ) ) {
			$problems = self::checklist( $post );
			if ( $problems ) {
				return new \WP_Error(
					'tv_checklist',
					implode( ' ', $problems ),
					array(
						'status'   => 422,
						'problems' => $problems,
					)
				);
			}
		}

		$update = array(
			'ID'          => $post->ID,
			'post_status' => $t['to'],
		);
		if ( 'future' === $t['to'] ) {
			$date = (string) ( $args['date'] ?? '' );
			$ts   = $date ? strtotime( $date ) : false;
			if ( ! $ts || $ts <= current_time( 'timestamp' ) ) { // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				return new \WP_Error( 'tv_date', __( 'Choose a publication date in the future.', 'techvenza-core' ), array( 'status' => 400 ) );
			}
			$update['post_date']     = gmdate( 'Y-m-d H:i:s', $ts );
			$update['post_date_gmt'] = get_gmt_from_date( $update['post_date'] );
			$update['edit_date']     = true;
		}
		if ( 'publish' === $t['to'] ) {
			$update['post_date']     = current_time( 'mysql' );
			$update['post_date_gmt'] = current_time( 'mysql', true );
			$update['edit_date']     = true;
		}
		if ( 'start' === $action && ! (int) get_post_meta( $post->ID, 'tv_assigned_editor', true ) ) {
			update_post_meta( $post->ID, 'tv_assigned_editor', $user );
		}

		$from                = $post->post_status;
		self::$transitioning = true;
		$result              = wp_update_post( wp_slash( $update ), true );
		self::$transitioning = false;
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( in_array( $action, array( 'submit', 'resubmit' ), true ) ) {
			update_post_meta( $post->ID, 'tv_submitted_at', current_time( 'mysql' ) );
		}
		self::log( $post->ID, $from, $t['to'], $action, $user, $note, $args['date'] ?? '' );

		/**
		 * Fires after a workflow transition.
		 *
		 * @param int    $post_id Post ID.
		 * @param string $action  Transition key.
		 * @param string $from    Previous status.
		 * @param string $to      New status.
		 * @param int    $user    Acting user.
		 * @param string $note    Note.
		 */
		do_action( 'techvenza/workflow/transition', $post->ID, $action, $from, $t['to'], $user, $note );
		do_action( 'techvenza/workflow/' . $action, $post->ID, $user, $note );
		return true;
	}

	/**
	 * Append to the timeline.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $from    From status.
	 * @param string $to      To status.
	 * @param string $action  Action key.
	 * @param int    $user    User.
	 * @param string $note    Note.
	 * @param string $date    Scheduled date (optional).
	 * @param string $at      Timestamp override (Y-m-d H:i:s, site time).
	 */
	public static function log( int $post_id, string $from, string $to, string $action, int $user, string $note = '', string $date = '', string $at = '' ): void {
		$timeline   = self::timeline( $post_id );
		$timeline[] = array(
			'at'     => $at ? $at : current_time( 'mysql' ),
			'user'   => $user,
			'from'   => $from,
			'to'     => $to,
			'action' => $action,
			'note'   => $note,
			'date'   => $date,
		);
		update_post_meta( $post_id, 'tv_timeline', $timeline );
	}

	/**
	 * Timeline entries.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function timeline( int $post_id ): array {
		$t = get_post_meta( $post_id, 'tv_timeline', true );
		return is_array( $t ) ? $t : array();
	}

	/**
	 * Feedback entries (notes) from the timeline.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function feedback( int $post_id ): array {
		return array_values( array_filter( self::timeline( $post_id ), static fn( $e ) => '' !== (string) ( $e['note'] ?? '' ) ) );
	}

	/**
	 * Keep workflow statuses when a reviewer saves content in the block editor
	 * (Gutenberg would otherwise send "draft" for unknown statuses), and turn core
	 * "Submit for Review" (pending) into our "submitted" status.
	 *
	 * @param array<string,mixed> $data    Post data.
	 * @param array<string,mixed> $postarr Raw input.
	 * @return array<string,mixed>
	 */
	public static function guard_status( array $data, array $postarr ): array {
		if ( self::$transitioning || 'post' !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}
		$id      = (int) ( $postarr['ID'] ?? 0 );
		$current = $id ? get_post_status( $id ) : '';
		if ( $current && in_array( $current, Statuses::CUSTOM, true ) && in_array( $data['post_status'], array( 'draft', 'pending' ), true ) ) {
			$data['post_status'] = $current;
		}
		if ( 'pending' === $data['post_status'] ) {
			$data['post_status'] = 'submitted';
		}
		return $data;
	}

	/**
	 * Log transitions that happened outside the engine (core Publish button, cron publishing scheduled posts).
	 *
	 * @param string   $to   New status.
	 * @param string   $from Old status.
	 * @param \WP_Post $post Post.
	 */
	public static function log_core_transition( string $to, string $from, \WP_Post $post ): void {
		if ( self::$transitioning || 'post' !== $post->post_type || $to === $from || 'auto-draft' === $to || 'inherit' === $to || 'trash' === $to ) {
			return;
		}
		if ( 'new' === $from || ( 'auto-draft' === $from && 'draft' === $to ) ) {
			return;
		}
		$map    = array(
			'submitted' => 'submit',
			'publish'   => 'publish',
			'future'    => 'schedule',
			'draft'     => 'restore',
		);
		$action = $map[ $to ] ?? 'status';
		self::log( $post->ID, $from, $to, $action, get_current_user_id() );
		do_action( 'techvenza/workflow/transition', $post->ID, $action, $from, $to, get_current_user_id(), '' );
	}
}
