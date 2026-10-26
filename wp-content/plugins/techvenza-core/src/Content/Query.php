<?php
/**
 * Read-side queries used by blocks (latest, featured, picks, trending, most read, related…).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Content;

/**
 * Thin, cache-friendly wrappers around WP_Query.
 */
final class Query {

	/**
	 * Post IDs already rendered on this request (for "exclude already shown").
	 *
	 * @var array<int,int>
	 */
	private static array $shown = array();

	/**
	 * Remember posts as shown.
	 *
	 * @param array<int,\WP_Post|int> $posts Posts or IDs.
	 */
	public static function mark_shown( array $posts ): void {
		foreach ( $posts as $p ) {
			self::$shown[] = $p instanceof \WP_Post ? $p->ID : (int) $p;
		}
		self::$shown = array_values( array_unique( self::$shown ) );
	}

	/**
	 * IDs shown so far.
	 *
	 * @return array<int,int>
	 */
	public static function shown(): array {
		return self::$shown;
	}

	/**
	 * Base query.
	 *
	 * @param array<string,mixed> $args WP_Query args.
	 * @return array<int,\WP_Post>
	 */
	public static function posts( array $args ): array {
		$defaults = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 6,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		$q        = new \WP_Query( array_merge( $defaults, $args ) );
		return array_values( array_filter( $q->posts, static fn( $p ) => $p instanceof \WP_Post ) );
	}

	/**
	 * IDs-only query.
	 *
	 * @param array<string,mixed> $args WP_Query args.
	 * @return array<int,int>
	 */
	public static function ids( array $args ): array {
		$args['fields'] = 'ids';
		$q              = new \WP_Query(
			array_merge(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => 6,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				),
				$args
			)
		);
		return array_map( 'intval', (array) $q->posts );
	}

	/**
	 * Category slugs → IDs (including children).
	 *
	 * @param array<int,string>|string $slugs Slugs.
	 * @return array<int,int>
	 */
	public static function cat_ids( $slugs ): array {
		$ids = array();
		foreach ( (array) $slugs as $slug ) {
			$term = get_term_by( 'slug', (string) $slug, 'category' );
			if ( $term ) {
				$ids[] = $term->term_id;
			}
		}
		return $ids;
	}

	/**
	 * Latest posts.
	 *
	 * @param int               $count   Number.
	 * @param array<int,int>    $exclude IDs to skip.
	 * @param array<int,string> $cats    Category slugs.
	 * @param int               $offset  Offset.
	 * @return array<int,\WP_Post>
	 */
	public static function latest( int $count, array $exclude = array(), array $cats = array(), int $offset = 0 ): array {
		$args = array(
			'posts_per_page' => $count,
			'post__not_in'   => $exclude,
			'offset'         => $offset,
		);
		if ( $cats ) {
			$args['category__in'] = self::cat_ids( $cats );
		}
		return self::posts( $args );
	}

	/**
	 * Posts with a boolean flag (featured, editors pick).
	 *
	 * @param string            $flag    tv_is_featured|tv_is_editors_pick.
	 * @param int               $count   Number.
	 * @param array<int,int>    $exclude Exclude IDs.
	 * @param array<int,string> $cats    Category slugs.
	 * @return array<int,\WP_Post>
	 */
	public static function flagged( string $flag, int $count, array $exclude = array(), array $cats = array() ): array {
		$args = array(
			'posts_per_page' => $count,
			'post__not_in'   => $exclude,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => $flag,
					'value' => '1',
				),
			),
		);
		if ( $cats ) {
			$args['category__in'] = self::cat_ids( $cats );
		}
		return self::posts( $args );
	}

	/**
	 * Ranked by a views meta key (tv_views_24h|7d|30d|tv_view_count), manual-trending posts first.
	 *
	 * @param int               $count   Number.
	 * @param string            $period  24h|7d|30d|all.
	 * @param array<int,string> $cats    Category slugs.
	 * @param array<int,int>    $exclude Exclude IDs.
	 * @param bool              $manual  Put manually trending posts first.
	 * @return array<int,\WP_Post>
	 */
	public static function trending( int $count, string $period = '7d', array $cats = array(), array $exclude = array(), bool $manual = true ): array {
		$key  = self::views_key( $period );
		$args = array(
			'posts_per_page' => $count,
			'post__not_in'   => $exclude,
			'meta_key'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => array(
				'meta_value_num' => 'DESC',
				'date'           => 'DESC',
			),
		);
		if ( $cats ) {
			$args['category__in'] = self::cat_ids( $cats );
		}
		$ranked = self::posts( $args );
		if ( count( $ranked ) < $count ) {
			// Posts without the meta yet: fill by date.
			$ranked = array_merge( $ranked, self::latest( $count - count( $ranked ), array_merge( $exclude, wp_list_pluck( $ranked, 'ID' ) ), $cats ) );
		}
		if ( $manual ) {
			$pinned = self::flagged( 'tv_is_trending_manual', $count, $exclude, $cats );
			if ( $pinned ) {
				$ids    = wp_list_pluck( $pinned, 'ID' );
				$ranked = array_merge( $pinned, array_filter( $ranked, static fn( $p ) => ! in_array( $p->ID, $ids, true ) ) );
			}
		}
		return array_slice( array_values( $ranked ), 0, $count );
	}

	/**
	 * Meta key for a views period.
	 *
	 * @param string $period 24h|7d|30d|all.
	 */
	public static function views_key( string $period ): string {
		return match ( $period ) {
			'24h', 'day' => 'tv_views_24h',
			'30d', 'month' => 'tv_views_30d',
			'all' => 'tv_view_count',
			default => 'tv_views_7d',
		};
	}

	/**
	 * Views for a post in a period.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $period  Period.
	 */
	public static function views( int $post_id, string $period = 'all' ): int {
		return (int) get_post_meta( $post_id, self::views_key( $period ), true );
	}

	/**
	 * Posts by article type.
	 *
	 * @param array<int,string> $types   Types.
	 * @param int               $count   Number.
	 * @param string            $orderby date|views.
	 * @param array<int,int>    $exclude Exclude.
	 * @return array<int,\WP_Post>
	 */
	public static function by_type( array $types, int $count, string $orderby = 'date', array $exclude = array() ): array {
		$args = array(
			'posts_per_page' => $count,
			'post__not_in'   => $exclude,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'tv_article_type',
					'value'   => $types,
					'compare' => 'IN',
				),
			),
		);
		if ( 'views' === $orderby ) {
			$args['meta_key'] = 'tv_view_count'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['orderby']  = 'meta_value_num';
		}
		return self::posts( $args );
	}

	/**
	 * Related posts (same category or shared tags).
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   Number.
	 * @return array<int,\WP_Post>
	 */
	public static function related( int $post_id, int $count = 3 ): array {
		$cats = wp_get_post_categories( $post_id );
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );
		$args = array(
			'posts_per_page' => $count,
			'post__not_in'   => array( $post_id ),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'relation' => 'OR',
				array(
					'taxonomy' => 'category',
					'terms'    => $cats ? $cats : array( 0 ),
				),
				array(
					'taxonomy' => 'post_tag',
					'terms'    => $tags ? $tags : array( 0 ),
				),
			),
		);
		$out  = self::posts( $args );
		if ( count( $out ) < $count ) {
			$out = array_merge( $out, self::latest( $count - count( $out ), array_merge( array( $post_id ), wp_list_pluck( $out, 'ID' ) ) ) );
		}
		return $out;
	}

	/**
	 * Count of published posts by an author.
	 *
	 * @param int $user_id User ID.
	 */
	public static function author_count( int $user_id ): int {
		return (int) count_user_posts( $user_id, 'post', true );
	}

	/**
	 * Visible author users (Author / Editor / Managing Editor roles with a profile).
	 *
	 * @param array<string,mixed> $args Extra args (search, tv filters).
	 * @return array<int,array<string,mixed>>
	 */
	public static function authors( array $args = array() ): array {
		$users = get_users(
			array(
				'role__in' => array( 'tv_author', 'tv_editor', 'tv_managing_editor', 'administrator', 'author', 'editor' ),
				'orderby'  => 'display_name',
				'number'   => 200,
			)
		);
		$out   = array();
		foreach ( $users as $u ) {
			$a = UserMeta::author( $u );
			if ( ! $a || ! $a['visible'] || 'suspended' === $a['status'] || ! $a['title'] ) {
				continue;
			}
			$a['count'] = self::author_count( $u->ID );
			if ( $a['count'] < 1 && empty( $args['include_empty'] ) ) {
				continue;
			}
			if ( ! empty( $args['featured'] ) && ! $a['featured'] ) {
				continue;
			}
			if ( ! empty( $args['q'] ) && false === mb_stripos( $a['name'], (string) $args['q'] ) ) {
				continue;
			}
			if ( ! empty( $args['expertise'] ) && ! in_array( $args['expertise'], $a['expertise'], true ) ) {
				continue;
			}
			if ( ! empty( $args['cat'] ) && ! in_array( $args['cat'], $a['cats'], true ) ) {
				continue;
			}
			$out[] = $a;
		}
		if ( ! empty( $args['featured'] ) || ! empty( $args['by_count'] ) ) {
			usort( $out, static fn( $x, $y ) => $y['count'] <=> $x['count'] );
		}
		return $out;
	}
	/**
	 * Views in the current and the previous window of the same length, per post.
	 *
	 * @param array<int,int> $ids  Post IDs.
	 * @param int            $days Window length in days.
	 * @return array<int,array{0:int,1:int}> post ID => [current, previous]
	 */
	public static function period_views( array $ids, int $days ): array {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$table = \Techvenza\Core\Database\Schema::table( 'post_views' );
		$today = current_time( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', (int) strtotime( $today . ' -' . ( $days - 1 ) . ' days' ) );
		$prev  = gmdate( 'Y-m-d', (int) strtotime( $start . ' -' . $days . ' days' ) );
		$in    = implode( ',', $ids );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, SUM(CASE WHEN view_date >= %s THEN views ELSE 0 END) AS cur, SUM(CASE WHEN view_date < %s THEN views ELSE 0 END) AS prev FROM {$table} WHERE view_date >= %s AND post_id IN ({$in}) GROUP BY post_id", $start, $start, $prev ) );
		$out  = array_fill_keys( $ids, array( 0, 0 ) );
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->post_id ] = array( (int) $r->cur, (int) $r->prev );
		}
		return $out;
	}
}
