<?php
/**
 * Latest stories grid.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( ! Options::section_enabled( 'latest-posts' ) ) {
	return;
}
$tv_exclude = ! empty( $attributes['excludeShown'] ) ? Query::shown() : array();
$tv_posts   = Query::latest( (int) $attributes['count'], $tv_exclude, (array) $attributes['categories'] );
if ( ! $tv_posts ) {
	return;
}
Query::mark_shown( $tv_posts );
$tv_variant = (string) $attributes['variant'];
$tv_cols    = max( 1, min( 4, (int) $attributes['columns'] ) );
$tv_head    = R::sec_head(
	array(
		'eyebrow'   => $attributes['eyebrow'],
		'heading'   => $attributes['heading'],
		'desc'      => $attributes['description'],
		'linkLabel' => $attributes['linkLabel'],
		'linkUrl'   => $attributes['linkUrl'],
	)
);
$tv_grid    = 'row' === $tv_variant
	? '<div class="grid g-' . min( 3, $tv_cols ) . '" style="gap:var(--s5)">' . R::cards( $tv_posts, 'row' ) . '</div>'
	: '<div class="grid g-' . $tv_cols . '">' . R::cards( $tv_posts, $tv_variant ) . '</div>';
echo R::section( $tv_head . $tv_grid, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
