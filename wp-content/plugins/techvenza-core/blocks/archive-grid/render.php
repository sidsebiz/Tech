<?php
/**
 * Archive grid for tag, date, author-less archives and the blog index (uses the main query).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Support\Render as R;

global $wp_query;
$tv_posts = array();
$tv_total = 0;
$tv_paged = max( 1, (int) get_query_var( 'paged' ) );
if ( $wp_query instanceof WP_Query && ( is_archive() || is_home() ) ) {
	$tv_posts = array_values( array_filter( $wp_query->posts, static fn( $p ) => $p instanceof WP_Post ) );
	$tv_total = (int) $wp_query->max_num_pages;
} else {
	$tv_q     = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $attributes['perPage'],
			'paged'          => $tv_paged,
		)
	);
	$tv_posts = array_values( array_filter( $tv_q->posts, static fn( $p ) => $p instanceof WP_Post ) );
	$tv_total = (int) $tv_q->max_num_pages;
}
if ( ! $tv_posts ) {
	echo R::section( R::empty_state( 'file', __( 'Nothing here yet', 'techvenza-core' ), __( 'There are no stories in this archive.', 'techvenza-core' ), sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s</a>', esc_url( R::page_url( 'explore' ) ), esc_html__( 'Explore all stories', 'techvenza-core' ) ) ), $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
$tv_links = paginate_links(
	array(
		'total'     => $tv_total,
		'current'   => $tv_paged,
		'type'      => 'array',
		'prev_text' => R::icon( 'chevLeft' ) . '<span class="sr-only">' . esc_html__( 'Previous page', 'techvenza-core' ) . '</span>',
		'next_text' => R::icon( 'chevRight' ) . '<span class="sr-only">' . esc_html__( 'Next page', 'techvenza-core' ) . '</span>',
	)
);
$tv_pager = '';
if ( is_array( $tv_links ) && $tv_links ) {
	$tv_pager = '<nav class="pagination" aria-label="' . esc_attr__( 'Pagination', 'techvenza-core' ) . '">' . implode( '', array_map( static fn( $l ) => str_replace( array( 'page-numbers', 'current' ), array( 'pg', '' ), $l ), $tv_links ) ) . '</nav>';
}
echo R::section( '<div class="grid g-3">' . R::cards( $tv_posts ) . '</div>' . $tv_pager, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
