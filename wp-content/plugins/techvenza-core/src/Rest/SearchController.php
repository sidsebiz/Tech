<?php
/**
 * Live search suggestions and weighted search across posts, authors and categories.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Rest;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render;

/**
 * Search endpoints and WP search extensions.
 */
final class SearchController {

	/**
	 * Hooks: extend core search to tags, categories and author names with relevance weighting.
	 */
	public static function hooks(): void {
		add_filter( 'posts_search', array( self::class, 'extend_search' ), 10, 2 );
		add_filter( 'posts_search_orderby', array( self::class, 'search_orderby' ), 10, 2 );
	}

	/**
	 * Routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/search/suggest',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'suggest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Whether a third-party search plugin handles relevance.
	 */
	private static function external_search(): bool {
		return class_exists( 'Relevanssi' ) || function_exists( 'relevanssi_do_query' ) || class_exists( 'SearchWP' ) || class_exists( '\\SearchWP\\Query' );
	}

	/**
	 * Add matches on tag/category names and author display names to the main search.
	 *
	 * @param string    $search SQL.
	 * @param \WP_Query $query  Query.
	 */
	public static function extend_search( string $search, \WP_Query $query ): string {
		global $wpdb;
		if ( ! $search || ! $query->is_search() || is_admin() || self::external_search() ) {
			return $search;
		}
		$term = trim( (string) $query->get( 's' ) );
		if ( '' === $term ) {
			return $search;
		}
		$like  = '%' . $wpdb->esc_like( $term ) . '%';
		$extra = $wpdb->prepare(
			" OR {$wpdb->posts}.ID IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy IN ('post_tag','category') AND t.name LIKE %s ) OR {$wpdb->posts}.post_author IN ( SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s )",
			$like,
			$like
		);
		// Insert before the closing parenthesis of the core search clause.
		$pos = strrpos( $search, ')' );
		return false === $pos ? $search : substr( $search, 0, $pos ) . $extra . substr( $search, $pos );
	}

	/**
	 * Relevance: title matches first, then excerpt, then the rest by date.
	 *
	 * @param string    $orderby SQL.
	 * @param \WP_Query $query   Query.
	 */
	public static function search_orderby( string $orderby, \WP_Query $query ): string {
		global $wpdb;
		if ( ! $query->is_search() || is_admin() || self::external_search() ) {
			return $orderby;
		}
		$like = '%' . $wpdb->esc_like( trim( (string) $query->get( 's' ) ) ) . '%';
		return $wpdb->prepare( "(CASE WHEN {$wpdb->posts}.post_title LIKE %s THEN 3 WHEN {$wpdb->posts}.post_excerpt LIKE %s THEN 2 ELSE 1 END) DESC, {$wpdb->posts}.post_date DESC", $like, $like );
	}

	/**
	 * Article search used by suggestions and the search page.
	 *
	 * @param string $q     Query.
	 * @param int    $count Max results.
	 * @return array<int,\WP_Post>
	 */
	public static function articles( string $q, int $count = 20 ): array {
		$query = new \WP_Query(
			array(
				's'              => $q,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $count,
				'no_found_rows'  => true,
			)
		);
		return array_values( array_filter( $query->posts, static fn( $p ) => $p instanceof \WP_Post ) );
	}

	/**
	 * Matching authors.
	 *
	 * @param string $q Query.
	 * @return array<int,array<string,mixed>>
	 */
	public static function authors( string $q ): array {
		$q = mb_strtolower( $q );
		return array_values(
			array_filter(
				Query::authors(),
				static fn( $a ) => str_contains( mb_strtolower( $a['name'] . ' ' . $a['title'] . ' ' . implode( ' ', $a['expertise'] ) ), $q )
			)
		);
	}

	/**
	 * Matching categories.
	 *
	 * @param string $q Query.
	 * @return array<int,array<string,mixed>>
	 */
	public static function categories( string $q ): array {
		$q = mb_strtolower( $q );
		return array_values(
			array_filter(
				CategoryMeta::all(),
				static fn( $c ) => str_contains( mb_strtolower( $c['name'] . ' ' . implode( ' ', wp_list_pluck( $c['sub'], 'name' ) ) ), $q )
			)
		);
	}

	/**
	 * Suggestions.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function suggest( \WP_REST_Request $request ): \WP_REST_Response {
		$q = trim( (string) $request->get_param( 'q' ) );
		if ( '' === $q ) {
			$topics = array_map(
				static fn( $c ) => array(
					'name' => $c['short'],
					'url'  => $c['url'],
				),
				array_slice( CategoryMeta::all( true ), 0, 6 )
			);
			$arts   = array_map(
				static fn( $p ) => array(
					'title' => get_the_title( $p ),
					'url'   => get_permalink( $p ),
				),
				Query::trending( 4 )
			);
			$res    = rest_ensure_response(
				array(
					'topics'   => $topics,
					'articles' => $arts,
				)
			);
			$res->header( 'Cache-Control', 'public, max-age=300' );
			return $res;
		}
		if ( Routes::rate_limited( 'suggest', 120, MINUTE_IN_SECONDS ) ) {
			return new \WP_REST_Response( array( 'message' => __( 'Too many requests.', 'techvenza-core' ) ), 429 );
		}
		$articles = array_map(
			static fn( $p ) => array(
				'url'  => get_permalink( $p ),
				'html' => Render::hl( get_the_title( $p ), $q ),
			),
			self::articles( $q, 5 )
		);
		$authors  = array_map(
			static fn( $a ) => array(
				'url'   => $a['url'],
				'html'  => Render::hl( $a['name'], $q ),
				'title' => $a['title'],
			),
			array_slice( self::authors( $q ), 0, 2 )
		);
		$topics   = array_map(
			static fn( $c ) => array(
				'url'  => $c['url'],
				'html' => Render::hl( $c['name'], $q ),
			),
			array_slice( self::categories( $q ), 0, 2 )
		);
		return rest_ensure_response(
			array(
				'articles' => $articles,
				'authors'  => $authors,
				'topics'   => $topics,
				'all'      => add_query_arg( 's', rawurlencode( $q ), home_url( '/' ) ),
			)
		);
	}
}
