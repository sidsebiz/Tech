<?php
/**
 * Posts list (edit.php) additions: workflow status, assigned editor and views columns, a "Review" row
 * action and sortable views. Workflow statuses appear in the core status links (show_in_admin_status_list).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;
use Techvenza\Core\Workflow\Statuses;

/**
 * Posts list table customisations.
 */
final class PostsList {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'manage_post_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_post_posts_custom_column', array( self::class, 'cell' ), 10, 2 );
		add_filter( 'manage_edit-post_sortable_columns', array( self::class, 'sortable' ) );
		add_action( 'pre_get_posts', array( self::class, 'sort' ) );
		add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		add_filter( 'display_post_states', array( self::class, 'states' ), 10, 2 );
	}

	/**
	 * Columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function columns( array $cols ): array {
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['tv_status'] = __( 'Workflow', 'techvenza-core' );
				$out['tv_editor'] = __( 'Editor', 'techvenza-core' );
			}
		}
		unset( $out['comments'] );
		$out['tv_views'] = __( 'Views', 'techvenza-core' );
		return $out;
	}

	/**
	 * Cell output.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post.
	 */
	public static function cell( string $col, int $post_id ): void {
		if ( 'tv_status' === $col ) {
			echo '<span class="tv-admin">' . R::badge( (string) get_post_status( $post_id ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in badge().
		} elseif ( 'tv_editor' === $col ) {
			$ed = get_userdata( (int) get_post_meta( $post_id, 'tv_assigned_editor', true ) );
			echo $ed ? esc_html( $ed->display_name ) : '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Unassigned', 'techvenza-core' ) . '</span>';
		} elseif ( 'tv_views' === $col ) {
			echo esc_html( number_format_i18n( Query::views( $post_id ) ) );
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function sortable( array $cols ): array {
		$cols['tv_views'] = 'tv_views';
		return $cols;
	}

	/**
	 * Apply views sorting.
	 *
	 * @param \WP_Query $q Query.
	 */
	public static function sort( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || 'tv_views' !== $q->get( 'orderby' ) ) {
			return;
		}
		$q->set( 'meta_key', Query::views_key( 'all' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$q->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * "Review" row action for posts in the editorial pipeline.
	 *
	 * @param array<string,string> $actions Actions.
	 * @param \WP_Post             $post    Post.
	 * @return array<string,string>
	 */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( 'post' === $post->post_type && current_user_can( 'tv_review_posts' ) && in_array( $post->post_status, array( 'submitted', 'resubmitted', 'in_review', 'changes_requested', 'approved', 'future', 'pending' ), true ) ) {
			$actions = array(
				'tv_review' => sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					esc_url( Admin::url( 'techvenza-review', array( 'id' => $post->ID ) ) ),
					/* translators: %s: title */
					esc_attr( sprintf( __( 'Review “%s”', 'techvenza-core' ), get_the_title( $post ) ) ),
					esc_html__( 'Review', 'techvenza-core' )
				),
			) + $actions;
		}
		return $actions;
	}

	/**
	 * Hide the core state label for our statuses (the Workflow column shows it).
	 *
	 * @param array<string,string> $states States.
	 * @param \WP_Post             $post   Post.
	 * @return array<string,string>
	 */
	public static function states( array $states, \WP_Post $post ): array {
		if ( isset( Statuses::labels()[ $post->post_status ] ) ) {
			unset( $states[ $post->post_status ], $states['draft'], $states['pending'], $states['scheduled'] );
		}
		return $states;
	}
}
