<?php
/**
 * Columns of compact cards grouped by article format.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_cols = array_filter( (array) $attributes['columns'], static fn( $c ) => ! empty( $c['title'] ) );
if ( ! $tv_cols ) {
	return;
}
$tv_html = '';
foreach ( $tv_cols as $tv_c ) {
	$tv_posts = Query::by_type( (array) ( $tv_c['types'] ?? array() ), (int) $attributes['count'] * 4, (string) ( $tv_c['orderby'] ?? 'views' ) );
	if ( ! empty( $tv_c['categories'] ) ) {
		$tv_ids   = Query::cat_ids( (array) $tv_c['categories'] );
		$tv_posts = array_values( array_filter( $tv_posts, static fn( $p ) => (bool) array_intersect( wp_get_post_categories( $p->ID ), $tv_ids ) ) );
	}
	$tv_posts = array_slice( $tv_posts, 0, (int) $attributes['count'] );
	if ( ! $tv_posts ) {
		continue;
	}
	$tv_html .= sprintf(
		'<div><h2 class="h3 row mb-5">%s %s</h2><div class="grid" style="gap:var(--s5)">%s</div></div>',
		! empty( $tv_c['icon'] ) ? R::icon( (string) $tv_c['icon'] ) : '',
		esc_html( $tv_c['title'] ),
		R::cards( $tv_posts, 'row' )
	);
}
if ( ! $tv_html ) {
	return;
}
$tv_n = count( $tv_cols );
echo R::section( sprintf( '<div class="grid g-%d" style="gap:var(--s%d)">%s</div>', min( 3, max( 2, $tv_n ) ), 2 === $tv_n ? 8 : 7, $tv_html ), $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
