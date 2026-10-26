<?php
/**
 * Editorial post statuses.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Workflow;

/**
 * Registers submitted → in_review → changes_requested → resubmitted → approved → (future) → publish → archived, plus rejected.
 */
final class Statuses {

	public const CUSTOM = array( 'submitted', 'in_review', 'changes_requested', 'resubmitted', 'approved', 'rejected', 'archived' );

	/**
	 * Statuses in which the author can still edit content.
	 */
	public const AUTHOR_EDITABLE = array( 'draft', 'auto-draft', 'changes_requested', 'rejected' );

	/**
	 * Statuses that are "with the editorial team".
	 */
	public const IN_PROCESS = array( 'submitted', 'in_review', 'changes_requested', 'resubmitted', 'approved' );

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_statuses' ), 1 );
		add_filter( 'display_post_states', array( self::class, 'post_states' ), 10, 2 );
		add_action( 'admin_footer-post.php', array( self::class, 'classic_status_dropdown' ) );
	}

	/**
	 * Human labels for all workflow statuses (incl. core ones).
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			'draft'             => __( 'Draft', 'techvenza-core' ),
			'auto-draft'        => __( 'Draft', 'techvenza-core' ),
			'pending'           => __( 'Pending review', 'techvenza-core' ),
			'submitted'         => __( 'New submission', 'techvenza-core' ),
			'in_review'         => __( 'In review', 'techvenza-core' ),
			'changes_requested' => __( 'Changes requested', 'techvenza-core' ),
			'resubmitted'       => __( 'Resubmitted', 'techvenza-core' ),
			'approved'          => __( 'Approved', 'techvenza-core' ),
			'future'            => __( 'Scheduled', 'techvenza-core' ),
			'publish'           => __( 'Published', 'techvenza-core' ),
			'rejected'          => __( 'Rejected', 'techvenza-core' ),
			'archived'          => __( 'Archived', 'techvenza-core' ),
		);
	}

	/**
	 * Register the custom statuses.
	 */
	public static function register_statuses(): void {
		$labels = self::labels();
		foreach ( self::CUSTOM as $status ) {
			register_post_status(
				$status,
				array(
					'label'                     => $labels[ $status ],
					'public'                    => false,
					'protected'                 => true,
					'internal'                  => false,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => 'archived' !== $status && 'rejected' !== $status,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of posts */
					'label_count'               => _n_noop( $labels[ $status ] . ' <span class="count">(%s)</span>', $labels[ $status ] . ' <span class="count">(%s)</span>', 'techvenza-core' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingular, WordPress.WP.I18n.NonSingularStringLiteralPlural
					'date_floating'             => true,
				)
			);
		}
	}

	/**
	 * Show the status next to titles in the posts list.
	 *
	 * @param array<string,string> $states States.
	 * @param \WP_Post             $post   Post.
	 * @return array<string,string>
	 */
	public static function post_states( array $states, \WP_Post $post ): array {
		if ( in_array( $post->post_status, self::CUSTOM, true ) ) {
			$states[ $post->post_status ] = self::labels()[ $post->post_status ];
		}
		return $states;
	}

	/**
	 * Classic editor fallback: show the custom status label in the status box.
	 */
	public static function classic_status_dropdown(): void {
		global $post;
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_status, self::CUSTOM, true ) ) {
			return;
		}
		printf( '<script>document.addEventListener("DOMContentLoaded",function(){var s=document.getElementById("post-status-display");if(s){s.textContent=%s;}});</script>', wp_json_encode( self::labels()[ $post->post_status ] ) );
	}
}
