<?php
/**
 * Latest in category with subcategory filter and pagination (URL-driven, live-enhanced).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Blocks\Params;
use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Support\Render as R;

$tv_c = CategoryMeta::current();
if ( ! $tv_c || ! $tv_c['count'] ) {
	return;
}
$tv_sub      = sanitize_title( Params::get( 'sub' ) );
$tv_page     = Params::int( 'cpg' );
$tv_per      = max( 3, (int) $attributes['perPage'] );
$tv_args     = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => $tv_per,
	'paged'               => $tv_page,
	'ignore_sticky_posts' => true,
	'cat'                 => $tv_c['id'],
);
$tv_sub_name = '';
if ( $tv_sub ) {
	foreach ( $tv_c['sub'] as $tv_s ) {
		if ( $tv_s['slug'] === $tv_sub ) {
			$tv_sub_name = $tv_s['name'];
			$tv_words    = array_filter( preg_split( '/\s+/', strtolower( $tv_s['name'] ) ) ? preg_split( '/\s+/', strtolower( $tv_s['name'] ) ) : array(), static fn( $w ) => strlen( $w ) > 2 );
			$tv_tag_ids  = array();
			foreach ( $tv_words as $tv_w ) {
				$tv_found   = get_terms(
					array(
						'taxonomy'   => 'post_tag',
						'name__like' => rtrim( $tv_w, 's' ),
						'fields'     => 'ids',
						'hide_empty' => true,
					)
				);
				$tv_tag_ids = array_merge( $tv_tag_ids, is_array( $tv_found ) ? $tv_found : array() );
			}
			$tv_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'relation' => 'OR',
				array(
					'taxonomy' => 'category',
					'terms'    => array( (int) $tv_s['id'] ),
				),
				array(
					'taxonomy' => 'post_tag',
					'terms'    => $tv_tag_ids ? array_map( 'intval', $tv_tag_ids ) : array( 0 ),
				),
			);
		}
	}
}
$tv_q     = new WP_Query( $tv_args );
$tv_posts = array_values( array_filter( $tv_q->posts, static fn( $p ) => $p instanceof WP_Post ) );
$tv_keep  = $tv_sub ? array( 'sub' => $tv_sub ) : array();

if ( $tv_posts ) {
	$tv_body = '<div class="grid g-3">' . R::cards( $tv_posts ) . '</div>' . R::pager( (int) $tv_q->found_posts, $tv_page, $tv_per, 'cpg', $tv_keep );
} else {
	$tv_body = R::empty_state(
		'filter',
		/* translators: %s: subcategory */
		sprintf( __( 'No stories in “%s” yet', 'techvenza-core' ), $tv_sub_name ),
		__( 'Try another subcategory or view all stories in this category.', 'techvenza-core' ),
		sprintf( '<a class="btn btn-secondary btn-sm" href="%s" data-live-link>%s</a>', esc_url( Params::url( array() ) ), esc_html__( 'Show all', 'techvenza-core' ) )
	);
}
$tv_head  = R::sec_head(
	array(
		'eyebrow' => __( 'Latest', 'techvenza-core' ),
		/* translators: %s: category short name */
		'heading' => sprintf( __( 'Latest in %s', 'techvenza-core' ), $tv_c['short'] ),
		'accent'  => $tv_c['color'],
	)
);
$tv_inner = R::live( 'category-latest', $attributes, $tv_head . '<div data-live-top data-live-status aria-live="polite">' . $tv_body . '</div>', array( 'term' => $tv_c['id'] ) );
$tv_attrs = array_merge(
	array(
		'surface' => 'alt',
		'spacing' => 'tight',
	),
	array_filter( $attributes, static fn( $v, $k ) => ! ( in_array( $k, array( 'surface', 'spacing' ), true ) && 'default' === $v ), ARRAY_FILTER_USE_BOTH )
);
echo R::section( '<div id="latest" style="scroll-margin-top:90px">' . $tv_inner . '</div>', $tv_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
