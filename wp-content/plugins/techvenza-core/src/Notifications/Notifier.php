<?php
/**
 * Turns workflow events into in-app notifications and (opt-out-able) emails.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Notifications;

use Techvenza\Core\Database\Notifications;
use Techvenza\Core\Email\Mailer;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render;

/**
 * Workflow notification dispatcher.
 */
final class Notifier {

	/**
	 * Action → [email event, recipient, in-app type].
	 */
	private const MAP = array(
		'submit'   => array( 'submitted', 'editors', 'status' ),
		'resubmit' => array( 'resubmitted', 'editors', 'status' ),
		'start'    => array( 'in_review', 'author', 'status' ),
		'approve'  => array( 'approved', 'author', 'approved' ),
		'request'  => array( 'changes_requested', 'author', 'revision' ),
		'reject'   => array( 'rejected', 'author', 'status' ),
		'schedule' => array( 'scheduled', 'author', 'approved' ),
		'publish'  => array( 'published', 'author', 'published' ),
	);

	/**
	 * Suppress dispatch (seeding).
	 *
	 * @var bool
	 */
	public static bool $muted = false;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'techvenza/workflow/transition', array( self::class, 'on_transition' ), 10, 6 );
	}

	/**
	 * Whether a user wants emails for an event (default yes).
	 *
	 * @param int    $user_id User.
	 * @param string $event   Event.
	 */
	public static function wants_email( int $user_id, string $event ): bool {
		$prefs = get_user_meta( $user_id, 'tv_email_prefs', true );
		return ! is_array( $prefs ) || ! array_key_exists( $event, $prefs ) || (bool) $prefs[ $event ];
	}

	/**
	 * Handle a transition.
	 *
	 * @param int    $post_id Post.
	 * @param string $action  Action.
	 * @param string $from    From.
	 * @param string $to      To.
	 * @param int    $actor   Acting user.
	 * @param string $note    Note.
	 */
	public static function on_transition( int $post_id, string $action, string $from, string $to, int $actor, string $note ): void {
		if ( self::$muted || ! isset( self::MAP[ $action ] ) ) {
			return;
		}
		[ $event, $who, $type ] = self::MAP[ $action ];
		$post                   = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$author = get_user_by( 'id', (int) $post->post_author );
		$editor = get_user_by( 'id', $actor );
		$vars   = array(
			'post_title'        => get_the_title( $post ),
			'post_url'          => get_permalink( $post ),
			'author_name'       => $author ? $author->display_name : '',
			'author_first_name' => $author ? ( $author->first_name ? $author->first_name : $author->display_name ) : '',
			'editor_name'       => $editor ? $editor->display_name : __( 'The editorial team', 'techvenza-core' ),
			'note'              => $note,
			'scheduled_date'    => 'future' === $to ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $post->post_date_gmt . ' UTC' ) ) : '',
			'submission_url'    => add_query_arg(
				array(
					'view' => 'submission',
					'id'   => $post_id,
				),
				Render::page_url( 'author-portal' )
			),
			'review_url'        => admin_url( 'admin.php?page=techvenza-review&post=' . $post_id ),
			'site_name'         => get_bloginfo( 'name' ),
		);
		$titles = array(
			/* translators: %s: article title */
			'submitted'         => __( 'New submission: %s', 'techvenza-core' ),
			/* translators: %s: article title */
			'resubmitted'       => __( 'Resubmitted: %s', 'techvenza-core' ),
			/* translators: %s: article title */
			'in_review'         => __( 'Now in review: %s', 'techvenza-core' ),
			/* translators: %s: article title */
			'approved'          => __( 'Approved: %s', 'techvenza-core' ),
			/* translators: %s: article title */
			'changes_requested' => __( 'Revision requested: %s', 'techvenza-core' ),
			/* translators: %s: article title */
			'rejected'          => __( 'Not accepted: %s', 'techvenza-core' ),
			/* translators: %s: article title */
			'scheduled'         => __( 'Scheduled: %s', 'techvenza-core' ),
			/* translators: %s: article title */
			'published'         => __( 'Published: %s', 'techvenza-core' ),
		);
		$title  = sprintf( $titles[ $event ], $vars['post_title'] );

		if ( 'author' === $who ) {
			if ( ! $author || $author->ID === $actor ) {
				return;
			}
			$recipients = array( $author );
			$link       = 'published' === $event ? $vars['post_url'] : $vars['submission_url'];
		} else {
			$assigned   = (int) get_post_meta( $post_id, 'tv_assigned_editor', true );
			$recipients = $assigned && 'resubmitted' === $event
				? array_filter( array( get_user_by( 'id', $assigned ) ) )
				: get_users( array( 'capability' => 'tv_review_posts' ) );
			$link       = $vars['review_url'];
		}
		foreach ( $recipients as $user ) {
			if ( ! $user instanceof \WP_User || $user->ID === $actor ) {
				continue;
			}
			Notifications::add( $user->ID, $type, $title, $note ? $note : (string) ( $vars['editor_name'] ), $link, $post_id );
			if ( self::wants_email( $user->ID, $event ) ) {
				Mailer::send_template( $event, $user->user_email, $vars );
			}
		}
	}

	/**
	 * Review SLA in days (for templates).
	 */
	public static function sla(): int {
		return (int) Options::get( 'review_sla_days', 5 );
	}
}
